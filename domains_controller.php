<?php
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
        $this->view->view = "default";
        $this->orig_structure_view = $this->structure->view;
        $this->structure->view = "default";

        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);

        // Set the sidebar for all settings pages
        if ($this->portal == 'admin') {
            Language::loadLang('admin_domains', null, PLUGINDIR . 'domains' . DS . 'language' . DS);
            $this->structure->set(
                'side_bar',
                ['partials/admin_domains_sidebar', $this->view]
            );

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
            'unparent' => Language::_('DomainsController.getDomainActions.unparent', true)
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
        }

        return $errors;
    }
}
