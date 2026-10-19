<?php

use Blesta\Core\Pricing\Presenter\Type\PresenterInterface;

/**
 * Domain Manager parent controller
 *
 * @link https://www.blesta.com Blesta
 */
class DomainsController extends AppController
{
    /**
     * Require admin to be login and setup the view
     */
    public function preAction()
    {
        $this->structure->setDefaultView(APPDIR);
        parent::preAction();

        $this->requireLogin();

        // Auto load language for the controller
        Language::loadLang(
            [Loader::fromCamelCase(get_class($this))],
            null,
            dirname(__FILE__) . DS . 'language' . DS
        );
        Language::loadLang(
            'domains_controller',
            null,
            dirname(__FILE__) . DS . 'language' . DS
        );

        // If this is an admin controller, set the portal type to admin
        $this->portal = 'client';

        if (substr($this->controller, 0, 5) == 'admin') {
            $this->portal = 'admin';
        }

        // Override default view directory
        $this->view->view = 'default';
        $this->orig_structure_view = $this->structure->view;
        $this->structure->view = 'default';

        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);

        // Set the sidebar for all settings pages
        if ($this->portal == 'admin') {
            // Determine if sidebar should be shown
            $show_sidebar = false;
            $sidebar_partial = null;

            // AdminDomains controller: settings sidebar (excluding browse and index)
            if ($this->controller === 'admin_domains' && !in_array($this->action, ['browse', 'index'])) {
                $show_sidebar = true;
                $sidebar_partial = 'partials/admin_domains_sidebar';
            }

            // AdminMain controller: client sidebar (for add and edit actions)
            if ($this->controller === 'admin_main' && in_array($this->action, ['add', 'edit', 'tab'])) {
                $show_sidebar = true;
                $sidebar_partial = 'partials/admin_main_sidebar';
            }

            if ($show_sidebar && $sidebar_partial) {
                Language::loadLang('admin_domains', null, PLUGINDIR . 'domains' . DS . 'language' . DS);
                $this->structure->set('side_bar', [$sidebar_partial, $this->view]);
            }

            // Set the page title language term
            $page_title = Loader::toCamelCase($this->controller) . '.'
                . Loader::fromCamelCase($this->action ?? 'index') . '.page_title';
            $this->structure->set('page_title', Language::_($page_title, true));
        }
    }


    /**
     * Gets a list of possible domain actions
     *
     * @return array A list of possible domain actions and their language
     */
    protected function getDomainActions()
    {
        return [
            'change_auto_renewal' => Language::_('DomainsController.getDomainActions.change_auto_renewal', true),
            'change_expiration_date' => Language::_('DomainsController.getDomainActions.change_expiration_date', true),
            'change_registration_date' => Language::_('DomainsController.getDomainActions.change_registration_date', true),
            'change_registrar' => Language::_('DomainsController.getDomainActions.change_registrar', true),
            'domain_renewal' => Language::_('DomainsController.getDomainActions.domain_renewal', true),
            'set_price_override' => Language::_('DomainsController.getDomainActions.set_price_override', true),
            'remove_price_override' => Language::_('DomainsController.getDomainActions.remove_price_override', true),
            'update_nameservers' => Language::_('DomainsController.getDomainActions.update_nameservers', true),
            'push_to_client' => Language::_('DomainsController.getDomainActions.push_to_client', true),
            'unparent' => Language::_('DomainsController.getDomainActions.unparent', true),
            'queue_sync' => Language::_('DomainsController.getDomainActions.queue_sync', true)
        ];
    }

    /**
     * Updates the given domains
     *
     * @param array $data An array of POST data including:
     *
     *  - service_ids An array of each service ID
     *  - action The action to perform, e.g. "change_auto_renewal"
     * @return mixed An array of errors, or false otherwise
     */
    protected function updateDomains(array $data)
    {
        $this->uses(['Services', 'Domains.DomainsDomains']);

        // Require authorization to update a client's service
        if (!$this->authorized('admin_clients', 'editservice')) {
            $this->flashMessage('error', Language::_('AppController.!error.unauthorized_access', true), null, false);
            $this->redirect($this->base_uri . 'clients/');
        }

        // Only include service IDs in the list
        $service_ids = [];
        if (isset($data['service_ids'])) {
            foreach ((array)$data['service_ids'] as $service_id) {
                if (is_numeric($service_id)) {
                    $service_ids[] = $service_id;
                }
            }
        }

        $data['service_ids'] = $service_ids;
        $data['action'] = ($data['action'] ?? null);
        $errors = false;

        switch ($data['action']) {
            case 'change_auto_renewal':
                // Schedule cancellation or remove scheduled cancellations for each service
                foreach ($data['service_ids'] as $service_id) {
                    if (isset($data['auto_renewal']) && $data['auto_renewal'] == 'off') {
                        $this->Services->cancel($service_id, ['date_canceled' => 'end_of_term']);
                    } else {
                        $this->Services->unCancel($service_id);
                    }

                    if (($errors = $this->Services->errors())) {
                        break;
                    }
                }
                break;
            case 'change_registrar':
                foreach ($data['service_ids'] as $service_id) {
                    $this->DomainsDomains->updateRegistrar($service_id, $data['module_id'] ?? null);

                    if (($errors = $this->DomainsDomains->errors())) {
                        break;
                    }
                }
                break;
            case 'change_expiration_date':
                foreach ($data['service_ids'] as $service_id) {
                    $this->DomainsDomains->setExpirationDate($service_id, $this->DomainsDomains->dateToUtc($data['expiration_date'] ?? null));

                    if (($errors = $this->DomainsDomains->errors())) {
                        break;
                    }
                }
                break;
            case 'change_registration_date':
                foreach ($data['service_ids'] as $service_id) {
                    $this->DomainsDomains->setRegistrationDate($service_id, $this->DomainsDomains->dateToUtc($data['registration_date'] ?? null));

                    if (($errors = $this->DomainsDomains->errors())) {
                        break;
                    }
                }
                break;
            case 'domain_renewal':
                foreach ($data['service_ids'] as $service_id) {
                    $this->DomainsDomains->renewDomain($service_id, $data['years'] ?? 1);

                    if (($errors = $this->DomainsDomains->errors())) {
                        break;
                    }
                }
                break;
            case 'set_price_override':
                foreach ($data['service_ids'] as $service_id) {
                    $service = $this->Services->get($service_id);
                    if ($service && isset($service->package_pricing)) {
                        $this->Services->edit(
                            $service_id,
                            [
                                'override_price' => $service->package_pricing->price_renews,
                                'override_currency' => $service->package_pricing->currency
                            ],
                            true
                        );

                        if (($errors = $this->Services->errors())) {
                            break;
                        }
                    }
                }
                break;
            case 'remove_price_override':
                foreach ($data['service_ids'] as $service_id) {
                    $this->Services->edit(
                        $service_id,
                        ['override_price' => null, 'override_currency' => null],
                        true
                    );

                    if (($errors = $this->Services->errors())) {
                        break;
                    }
                }
                break;
            case 'update_nameservers':
                foreach ($data['service_ids'] as $service_id) {
                    $this->DomainsDomains->updateNameservers($service_id, $data['nameservers'] ?? []);

                    if (($errors = $this->DomainsDomains->errors())) {
                        break;
                    }
                }
                break;
            case 'push_to_client':
                foreach ($data['service_ids'] as $service_id) {
                    // Get service
                    $service = $this->Services->get($service_id);
                    if (!$service) {
                        break;
                    }

                    // Move service
                    $service_id = $this->Services->move($service->id, $this->post['client_id'] ?? $data['client_id']);
                    if (($errors = $this->Services->errors())) {
                        return $errors;
                    }
                    if (empty($service_id)) {
                        $errors = ['move' => ['error' => Language::_('DomainsController.!error.move_error', true)]];
                    }
                }
                break;
            case 'unparent':
                foreach ($data['service_ids'] as $service_id) {
                    Loader::loadModels($this, ['Services']);

                    // Get service
                    $service = $this->Services->get($service_id);
                    if (!$service) {
                        break;
                    }

                    // Skip if the service is not a child
                    if (empty($service->parent_service_id)) {
                        continue;
                    }

                    // Remove override price
                    $pricing = ['override_price' => null, 'override_currency' => null];
                    $this->Services->edit($service_id, $pricing, true);

                    // Remove parent service
                    $parent_service = ['parent_service_id' => null];
                    $this->Services->edit($service_id, $parent_service, true);

                    if (($errors = $this->Services->errors())) {
                        return $errors;
                    }
                }
                break;
            case 'queue_sync':
                foreach ($data['service_ids'] as $service_id) {
                    $this->DomainsDomains->queueSync($service_id);

                    if (($errors = $this->DomainsDomains->errors())) {
                        break;
                    }
                }
                break;
        }

        return $errors;
    }

    /**
     * Fetches all pending service changes queued for the given service
     *
     * @param int $service_id The ID of the service whose queued changes to fetch
     * @return array An array of stdClass objects representing each queued service change
     */
    protected function getQueuedServiceChanges($service_id)
    {
        $this->uses(['ServiceChanges']);

        return $this->ServiceChanges->getAll('pending', $service_id);
    }

    /**
     * Cancels any pending queued service changes
     *
     * @param int $service_id The ID of the service whose pending service changes to cancel
     */
    protected function cancelServiceChanges($service_id)
    {
        $this->uses(['Invoices', 'ServiceChanges', 'Transactions']);

        foreach ($this->getQueuedServiceChanges($service_id) as $change) {
            // Unapply any payments from the invoice
            $transactions = $this->Transactions->getApplied(null, $change->invoice_id);
            foreach ($transactions as $transaction) {
                $this->Transactions->unapply($transaction->id, [$change->invoice_id]);
            }

            // Void the invoice
            $this->Invoices->edit($change->invoice_id, ['status' => 'void']);

            // Cancel the service change
            $this->ServiceChanges->edit($change->id, ['status' => 'canceled']);
        }
    }

    /**
     * Queues a service change for later processing
     *
     * @param int $service_id The ID of the service being queued
     * @param int $invoice_id The ID of the invoice associated with the service change
     * @param array $vars An array of all data to queue to successfully update a service
     * @return array An array of queue info, including:
     *
     *  - service_change_id The ID of the service change, if created
     *  - errors An array of errors
     */
    protected function queueServiceChange($service_id, $invoice_id, array $vars)
    {
        $this->uses(['ServiceChanges']);

        unset($vars['prorate']);
        $change_id = $this->ServiceChanges->add($service_id, $invoice_id, ['data' => $vars]);

        return [
            'service_change_id' => $change_id,
            'errors' => $this->ServiceChanges->errors()
        ];
    }

    /**
     * Creates an invoice from the given line items
     *
     * @param stdClass $client An stdClass object representing the client
     * @param PresenterInterface $presenter An instance of the PresenterInterface
     * @param string $currency The ISO 4217 currency code
     * @param bool $deliver True to set the invoice for delivery to the client's invoice method
     * @param int $service_id The ID of the service the items are for (optional)
     * @return array An key/value array containing:
     *
     *  - invoice_id The ID of the invoice, if created
     *  - errors An array of errors if the invoice could not be created
     */
    protected function makeInvoice(
        stdClass $client,
        PresenterInterface $presenter,
        $currency,
        $deliver = true,
        $service_id = null
    ) {
        $this->uses(['Invoices']);

        $invoice_vars = [
            'client_id' => $client->id,
            'date_billed' => date('c'),
            'date_due' => date('c'),
            'currency' => $currency,
            'lines' => $this->makeLineItems($presenter, $service_id)
        ];

        // Set this invoice for delivery
        if ($deliver && isset($client->settings['inv_method'])) {
            $invoice_vars['delivery'] = [$client->settings['inv_method']];
        }

        $invoice_id = $this->Invoices->add($invoice_vars);

        return [
            'invoice_id' => $invoice_id,
            'errors' => $this->Invoices->errors()
        ];
    }

    /**
     * Creates a set of line items from the given presenter
     *
     * @see DomainsController::makeInvoice
     *
     * @param PresenterInterface $presenter An instance of the PresenterInterface
     * @param int $service_id The ID of the service the items are for (optional)
     * @return array An array of line items
     */
    protected function makeLineItems(PresenterInterface $presenter, $service_id = null)
    {
        $items = [];

        // Setup line items from each of the presenter's items
        foreach ($presenter->items() as $item) {
            // Tax has to be deconstructed since the presenter's tax amounts cannot be passed along
            $items[] = [
                'qty' => $item->qty,
                'amount' => $item->price,
                'description' => $item->description,
                'tax' => !empty($item->taxes),
                'service_id' => ($service_id ? $service_id : null)
            ];
        }

        // Add a line item for each discount amount
        foreach ($presenter->discounts() as $discount) {
            // The total discount is the negated total
            $items[] = [
                'qty' => 1,
                'amount' => (-1 * $discount->total),
                'description' => $discount->description,
                'tax' => false,
                'service_id' => ($service_id ? $service_id : null)
            ];
        }

        return $items;
    }

    /**
     * Creates an in-house credit for the client
     *
     * @param int $client_id The ID of the client to credit
     * @param float $amount The amount to credit
     * @param string $currency The ISO 4217 currency code for the credit
     * @return int The ID of the transaction for this credit
     */
    protected function createCredit($client_id, $amount, $currency)
    {
        $this->uses(['Transactions']);

        $vars = [
            'client_id' => $client_id,
            'amount' => $amount,
            'currency' => $currency,
            'type' => 'other'
        ];

        // Find and set the transaction type to In House Credit, if available
        foreach ($this->Transactions->getTypes() as $type) {
            if ($type->name == 'in_house_credit') {
                $vars['transaction_type_id'] = $type->id;
                break;
            }
        }

        return $this->Transactions->add($vars);
    }

    /**
     * Fetches the ID of the coupon matching the given code
     *
     * @param string $coupon_code The coupon code
     * @return int The ID of the coupon, 0 if no such coupon exists, or null if no code was given
     */
    protected function getCouponId($coupon_code)
    {
        $this->uses(['Coupons']);

        $coupon_id = null;
        $coupon_code = trim($coupon_code);

        if ($coupon_code !== '') {
            $coupon_id = 0;
            if (($coupon = $this->Coupons->getByCode($coupon_code))) {
                $coupon_id = $coupon->id;
            }
        }

        return $coupon_id;
    }

    /**
     * Determines whether paid service changes must be queued rather than applied immediately
     *
     * @return bool True if service changes should be queued, false otherwise
     */
    protected function queueServiceChanges()
    {
        $this->uses(['Clients']);
        $this->components(['SettingsCollection']);

        $setting = $this->SettingsCollection->fetchClientSetting(
            $this->client->id,
            $this->Clients,
            'process_paid_service_changes'
        );

        return (($setting['value'] ?? null) == 'true');
    }

    /**
     * Determines whether the given registrar module implements the given method itself, rather
     * than inheriting the RegistrarModule stub. The stubs set an "unsupported" error whenever they
     * are called, so support must be determined without calling them
     *
     * @param Module $module An instance of the registrar module
     * @param string $method The name of the method to check
     * @return bool True if the module implements the method, false otherwise
     */
    protected function registrarSupports($module, $method)
    {
        if (!($module instanceof RegistrarModule) || !method_exists($module, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($module, $method);

        return ($reflection->getDeclaringClass()->getName() !== 'RegistrarModule');
    }
}
