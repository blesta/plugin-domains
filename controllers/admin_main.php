<?php

use Blesta\Core\Util\Input\Fields\InputFields;
use Blesta\Core\Util\Input\Fields\Html;
use Blesta\Core\Util\PackageOptions\Logic as OptionLogic;
use Blesta\Core\Util\Validate\Server;

/**
 * Domain Manager admin_main controller
 *
 * @link https://www.blesta.com Blesta
 */
class AdminMain extends DomainsController
{
    /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();

        // Load required models
        $this->uses(['Clients', 'Companies', 'ModuleManager', 'Packages', 'Invoices', 'Services']);
        $this->helpers(['Form', 'CurrencyFormat']);
        $this->components(['Session']);

        // Load required language
        Language::loadLang('admin_clients', null, ROOTWEBDIR . 'language' . DS);

        // Set the client info, if the request is not going through AJAX
        if (!$this->isAjax()) {
            $client_id = ($this->get['client_id'] ?? ($this->get[0] ?? null));
            $this->client = $this->Clients->get($client_id);

            $this->structure->set(
                'page_title',
                Language::_('AdminMain.index.page_title', true, $this->client->id_code)
            );
        }
    }

    /**
     * Redirects to the Domains Browse view
     */
    public function index()
    {
        $this->redirect($this->base_uri . 'plugin/domains/admin_domains/browse/');
    }

    /**
     * Adds a new domain
     */
    public function add()
    {
        $this->uses(['Domains.DomainsTlds', 'Domains.DomainsDomains', 'Contacts', 'ClientGroups', 'EmailVerifications', 'Invoices']);

        // Ensure a valid client was given
        $client_id = ($this->get['client_id'] ?? ($this->get[0] ?? null));
        if (empty($client_id) || !($client = $this->Clients->get($client_id))) {
            $this->redirect($this->base_uri . 'clients/');
        }

        // Load additional client data for sidebar
        $client->contacts = array_merge(
            $this->Contacts->getAll($client->id, 'billing'),
            $this->Contacts->getAll($client->id, 'other')
        );
        $client->numbers = $this->Contacts->getNumbers($client->contact_id);
        $client->note_count = $this->Clients->getNoteListCount($client->id);
        $client->group = $this->ClientGroups->get($client->client_group_id);

        // Include an international-formatted version of each number
        foreach ($client->numbers as $number) {
            $number->international = $this->Contacts->intlNumber($number->number, $client->country, ' ');
        }

        // Fetch email verification status
        $email_verification = $this->EmailVerifications->getByContactId($client->contact_id);
        $client->settings['email_verification_status'] = 'unsent';
        if (isset($email_verification->verified)) {
            $client->settings['email_verification_status'] = ($email_verification->verified == 1 ? 'verified' : 'unverified');
        }

        // Load additional data for sidebar
        $status = [
            'active' => Language::_('AdminClients.view.status_active', true),
            'inactive' => Language::_('AdminClients.view.status_inactive', true),
            'fraud' => Language::_('AdminClients.view.status_fraud', true)
        ];

        $number_types = $this->Contacts->getNumberTypes();
        $number_locations = $this->Contacts->getNumberLocations();
        $delivery_methods = $this->Invoices->getDeliveryMethods($client->id);

        // Set sidebar variables
        $this->set('status', $status);
        $this->set('number_types', $number_types);
        $this->set('number_locations', $number_locations);
        $this->set('delivery_methods', $delivery_methods);

        // Get the wizard action/step
        $action = ($this->get['action'] ?? ($this->get[1] ?? null));
        if (empty($action)) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_main/add/' . $client_id . '/lookup/');
        }

        // Render action
        switch ($action) {
            case 'lookup':
                $this->renderLookupStep($client);
                break;
            case 'configuration':
                $this->renderConfigurationStep($client);
                break;
            case 'confirmation':
                $this->renderConfirmationStep($client);
                break;
            default:
                $this->redirect($this->base_uri . 'clients/');
                break;
        }

        $this->set('client', $client);
        $this->set('vars', (object) $this->post);
    }

    /**
     * Renders the domain lookup step
     */
    private function renderLookupStep($client)
    {
        // Get all the available TLDs
        $tlds = $this->Form->collapseObjectArray(
            $this->DomainsTlds->getAll(['company_id' => Configure::get('Blesta.company_id')]),
            'tld',
            'tld'
        );

        // Get spotlight TLDs
        $spotlight_tlds = $this->Companies->getSetting(
            Configure::get('Blesta.company_id'),
            'domains_spotlight_tlds'
        );
        $spotlight_tlds = json_decode($spotlight_tlds->value ?? '', true);

        if (empty($spotlight_tlds)) {
            $spotlight_tlds = array_slice($tlds, 0, 4);
        }

        // Process form
        $lookup = [];
        if (!empty($this->post)) {
            // Set action
            $action = null;
            if (isset($this->post['lookup'])) {
                $action = 'lookup';
            }
            if (isset($this->post['transfer'])) {
                $action = 'transfer';
            }

            $this->post['domain'] = preg_replace('/^www\./i', '', $this->post['domain'] ?? '');

            // Get TLD from domain, if no TLD checkboxes were checked
            if (empty($this->post['tlds'])) {
                $tld = strstr($this->post['domain'] ?? '', '.');

                // If the domain does not contain a TLD, search by the first 4 TLDs on the spotlight
                if (empty($tld)) {
                    $this->post['tlds'] = array_slice($spotlight_tlds, 0, 4);
                } elseif (in_array($tld, $tlds)) {
                    $this->post['tlds'] = [$tld];
                }
            }

            // Remove TLD from domain
            preg_match('/^(.*?)\.(.*)/i', $this->post['domain'], $matches);
            $domain = $matches[1] ?? $this->post['domain'];
            $this->post['domain'] = $domain;

            // Process action
            switch ($action) {
                case 'lookup':
                    foreach ($this->post['tlds'] ?? [] as $tld) {
                        $availability = $this->DomainsDomains->checkAvailability($domain . $tld);
                        $lookup[] = [
                            'domain' => $domain . $tld,
                            'tld' => $tld,
                            'available' => $availability,
                            'transfer' => false
                        ];
                    }
                    break;
                case 'transfer':
                    foreach ($this->post['tlds'] ?? [] as $tld) {
                        $availability = $this->DomainsDomains->checkTransferAvailability($domain . $tld);
                        $lookup[] = [
                            'domain' => $domain . $tld,
                            'tld' => $tld,
                            'available' => $availability,
                            'transfer' => true
                        ];
                    }
                    break;
                default:
                    $this->redirect($this->base_uri . 'plugin/domains/admin_main/add/' . $client->id . '/');
                    break;
            }

            if (empty($lookup)) {
                $this->setMessage('error', Language::_('AdminMain.!error.unsupported_domain', true), false, null, false);
            }
        }

        // Set vars
        $vars = (object) $this->post ?? [];
        $this->set(
            'form',
            $this->partial(
                'admin_main_add_lookup',
                compact(
                    'client',
                    'tlds',
                    'spotlight_tlds',
                    'lookup',
                    'vars'
                )
            )
        );
    }

    /**
     * Renders the domain configuration step
     */
    private function renderConfigurationStep($client)
    {
        // Get fields from session, if available
        $configuration_fields = $this->Session->read('domain_configuration_fields');
        if (!empty($configuration_fields)) {
            $this->post = (array) $configuration_fields;
            $this->Session->clear('domain_configuration_fields');
        }

        // Set action
        $action = 'register';
        if (isset($this->post['transfer'])) {
            $action = 'transfer';
        }

        // Redirect to lookup if no post data has been passed
        if (empty($this->post) || is_null($action)) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_main/add/' . $client->id . '/');
        }

        // Redirect to lookup if no valid domain has been provided
        $validator = new Server();
        if (!$validator->isDomain($this->post['domain'] ?? '')) {
            $this->flashMessage('error', Language::_('AdminMain.!error.unsupported_domain', true), null, false);
            $this->redirect($this->base_uri . 'plugin/domains/admin_main/add/' . $client->id . '/lookup/');
        }

        $vars = (object) $this->post ?? [];

        // Get TLD from domain
        $domain = $this->post['domain'];
        $tld = strstr($this->post['domain'] ?? '', '.');

        // Get package from TLD
        $package = $this->DomainsTlds->getTldPackage($tld, Configure::get('Blesta.company_id'));
        $package = $this->Packages->get($package->id);

        if (!isset($vars->module)) {
            $vars->module = $package->module_id;
        }

        // Build years array
        $years = $this->formatPricingOptions($package, $action);

        // Get list of registrar modules
        $modules = $this->getConfiguredRegistrarModules();

        // Get open invoices
        $invoices = $this->Form->collapseObjectArray(
            $this->Invoices->getAll($client->id),
            'id_value',
            'id'
        );

        // Get service statuses
        $status = $this->Services->getStatusTypes();
        unset($status['in_review']);

        // Set the pricing IDs so the configurable options of the selected term can be fetched
        $pricing_ids = $this->formatPricingIds($package);

        // Set vars
        $this->set('domain', $domain);
        $this->set(
            'form',
            $this->partial(
                'admin_main_add_configuration',
                compact(
                    'client',
                    'action',
                    'domain',
                    'package',
                    'years',
                    'pricing_ids',
                    'modules',
                    'invoices',
                    'status',
                    'vars'
                )
            )
        );
    }

    /**
     * Fetch a list of pricing options
     *
     * @param stdClass $package An object representing the package
     * @param string $action The domain action to fetch the pricing
     * @return array A list of available years for the given package
     */
    private function formatPricingOptions($package, $action = 'register')
    {
        $years = [];
        foreach ($package->pricing as $pricing) {
            if ($pricing->period !== 'year') {
                continue;
            }

            $price = $this->CurrencyFormat->format(
                ($action == 'register' ? $pricing->price : $pricing->price_transfer) + $pricing->setup_fee,
                $pricing->currency
            );
            $term = Language::_('AdminMain.add.term_' . $pricing->period . ($pricing->term > 1 ? 's' : ''), true, $pricing->term);

            if ($pricing->price == $pricing->price_renews) {
                $years[$pricing->term . '-' . $pricing->currency] = Language::_('AdminMain.add.term', true, $term, $price);
            } else {
                $renewal_price = $this->CurrencyFormat->format($pricing->price_renews, $pricing->currency);
                $years[$pricing->term . '-' . $pricing->currency] = Language::_('AdminMain.add.term_recurring', true, $term, $price, $renewal_price);
            }
        }

        return $years;
    }

    /**
     * Confirmation step
     */
    private function renderConfirmationStep($client)
    {
        // Set action
        $action = 'register';
        if (isset($this->post['transfer'])) {
            $action = 'transfer';
        }

        // Redirect to lookup if no post data has been passed
        if (empty($this->post) || is_null($action)) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_main/add/' . $client->id . '/');
        }

        // Redirect to lookup if no valid domain has been provided
        $validator = new Server();
        if (!$validator->isDomain($this->post['domain'] ?? '')) {
            $this->flashMessage('error', Language::_('AdminMain.!error.unsupported_domain', true), null, false);
            $this->redirect($this->base_uri . 'plugin/domains/admin_main/add/' . $client->id . '/lookup/');
        }

        // Set checkboxes
        $checkboxes = ['use_module', 'notify_order'];
        foreach ($checkboxes as $checkbox) {
            if (!isset($this->post[$checkbox])) {
                $this->post[$checkbox] = 'false';
            }
        }

        // Get TLD from domain
        $domain = $this->post['domain'];
        $tld = strstr($this->post['domain'] ?? '', '.');

        // Check if a package exists for the given module
        $package = $this->DomainsTlds->getTldPackageByModuleId(
            $tld,
            $this->post['module'],
            Configure::get('Blesta.company_id')
        );

        // If a package doesn't exist for this TLD and the provided module, clone the default one
        if (empty($package)) {
            $package_id = $this->DomainsTlds->duplicate($tld, $this->post['module']);
            $package = $this->Packages->get($package_id);
        }

        // If a package exists, but it's not active, make it restricted
        if (isset($package->status) && $package->status == 'inactive') {
            $this->Packages->edit($package->id, ['status' => 'restricted']);
        }

        // Get service statuses
        $status = $this->Services->getStatusTypes();

        // Get invoice to append the domain
        $invoice = $this->Invoices->get($this->post['invoice_id'] ?? null);

        // Get the module used to register the domain
        $module = $this->ModuleManager->get($this->post['module'] ?? null);

        // Set client and staff
        $this->post['client_id'] = $client->id;
        $this->post['staff_id'] = $this->Session->read('blesta_staff_id');

        // Initialize line totals
        $line_totals = [
            'subtotal' => 0,
            'total' => 0,
            'total_without_exclusive_tax' => 0,
            'tax' => []
        ];

        // Get the pricing from the amount of years
        $term = explode('-', $this->post['years'] ?? '', 2);
        $pricing = $this->DomainsTlds->getPricing($package->id, $term[0], $term[1]);

        if (empty($pricing)) {
            $this->flashMessage('error', Language::_('AdminMain.!error.unsupported_domain', true), null, false);
            $this->redirect($this->base_uri . 'plugin/domains/admin_main/add/' . $client->id . '/lookup/');
        }

        $this->post['pricing_id'] = $pricing->pricing_id;
        $this->post['qty'] = 1;

        // Process coupon
        if (isset($this->post['submit']) && !empty($this->post['coupon_code'])) {
            $this->uses(['Coupons']);
            $coupon = $this->Coupons->getByCode($this->post['coupon_code']);

            if (isset($coupon->id)) {
                $this->post['coupon_id'] = $coupon->id;
            }
        }

        // Get the service items and start summing line totals
        $options = [
            'includeSetupFees' => true,
            'prorateStartDate' => date('c'),
            'recur' => false
        ];
        if ($action == 'transfer') {
            $options['transfer'] = true;
        }
        $items = $this->Services->getServiceItems($this->post, $options, $line_totals, $pricing->currency);

        // Format line totals
        $line_totals['subtotal'] = $this->CurrencyFormat->format($line_totals['subtotal'], $pricing->currency);
        $line_totals['total'] = $this->CurrencyFormat->format($line_totals['total'], $pricing->currency);
        $line_totals['total_without_exclusive_tax'] = $this->CurrencyFormat->format(
            $line_totals['total_without_exclusive_tax'],
            $pricing->currency
        );

        if (isset($line_totals['discount'])) {
            $line_totals['discount'] = $this->CurrencyFormat->format($line_totals['discount'], $pricing->currency);
        }

        foreach ($line_totals['tax'] as &$tax) {
            $tax = $this->CurrencyFormat->format($tax, $pricing->currency);
        }

        // Process edit
        if (isset($this->post['edit'])) {
            unset($this->post['edit']);

            $this->Session->write('domain_configuration_fields', $this->post);
            $this->redirect($this->base_uri . 'plugin/domains/admin_main/add/' . $client->id . '/configuration/');
        }

        // Process domain
        unset($this->post['submit']);
        if (!empty($this->post) && isset($this->post['save'])) {
            // If the package is restricted, add the client to the package
            if ($package->status == 'restricted') {
                $this->DomainsTlds->assignClientPackage($client->id, $package->id);
            }

            $params = $this->post;
            unset($params['save']);
            unset($params['years']);

            // Set module row
            if (!empty($params['module_row'])) {
                $params['module_row_id'] = $params['module_row'];
            }
            unset($params['module_row']);

            // Set notification
            $notify = false;
            if ($params['notify_order'] == 'true') {
                $notify = true;
            }
            unset($params['notify_order']);

            // Add service
            try {
                $package_group_id = $this->Companies->getSetting(Configure::get('Blesta.company_id'), 'domains_package_group');
                $params['package_group_id'] = $package_group_id->value;
                $service_id = $this->Services->add($params, null, $notify);

                $transfers = [];
                if ($action == 'transfer') {
                    $transfers = [$service_id];
                }

                if (($errors = $this->Services->errors())) {
                    $this->setMessage('error', $errors, false, null, false);
                } else {
                    // Create the invoice
                    if ($params['invoice_method'] == 'create') {
                        $invoice_id = $this->Invoices->createFromServices(
                            $params['client_id'],
                            [$service_id],
                            $pricing->currency,
                            date('c'),
                            true,
                            false,
                            $transfers
                        );
                    } elseif ($params['invoice_method'] == 'append') {
                        $invoice_id = $this->Invoices->appendServices($params['invoice_id'], [$service_id]);
                    }

                    // Redirect the client to pay the invoice
                    $this->flashMessage(
                        'message',
                        Language::_('AdminMain.!success.domain_' . $action, true),
                        null,
                        false
                    );
                    $this->redirect($this->base_uri . 'clients/view/' . $client->id . '/');
                }
            } catch (Throwable $e) {
                $this->setMessage('error', $e->getMessage(), false, null, false);
            }
        }

        // Get package name
        $package = $this->Packages->get($package->id);

        // Set vars
        $vars = (object) $this->post;
        $this->set('domain', $domain);
        $this->set(
            'form',
            $this->partial(
                'admin_main_add_confirmation',
                compact(
                    'client',
                    'action',
                    'domain',
                    'package',
                    'status',
                    'invoice',
                    'module',
                    'pricing',
                    'items',
                    'line_totals',
                    'vars'
                )
            )
        );
    }

    /**
     * Edits an existing domain
     */
    public function edit()
    {
        $this->uses([
            'Domains.DomainsTlds', 'Domains.DomainsDomains', 'Contacts', 'ClientGroups',
            'EmailVerifications', 'Invoices', 'Currencies', 'Coupons', 'Logs', 'PackageOptions',
            'PackageOptionConditionSets', 'ServiceChanges'
        ]);

        // Ensure a valid client was given
        $client_id = ($this->get['client_id'] ?? ($this->get[0] ?? null));
        if (empty($client_id) || !($client = $this->Clients->get($client_id))) {
            $this->redirect($this->base_uri . 'clients/');
        }

        // Load additional client data for sidebar
        $client->contacts = array_merge(
            $this->Contacts->getAll($client->id, 'billing'),
            $this->Contacts->getAll($client->id, 'other')
        );
        $client->numbers = $this->Contacts->getNumbers($client->contact_id);
        $client->note_count = $this->Clients->getNoteListCount($client->id);
        $client->group = $this->ClientGroups->get($client->client_group_id);

        // Include an international-formatted version of each number
        foreach ($client->numbers as $number) {
            $number->international = $this->Contacts->intlNumber($number->number, $client->country, ' ');
        }

        // Fetch email verification status
        $email_verification = $this->EmailVerifications->getByContactId($client->contact_id);
        $client->settings['email_verification_status'] = 'unsent';
        if (isset($email_verification->verified)) {
            $client->settings['email_verification_status'] = ($email_verification->verified == 1 ? 'verified' : 'unverified');
        }

        // Load additional data for sidebar
        $status = [
            'active' => Language::_('AdminClients.view.status_active', true),
            'inactive' => Language::_('AdminClients.view.status_inactive', true),
            'fraud' => Language::_('AdminClients.view.status_fraud', true)
        ];

        $number_types = $this->Contacts->getNumberTypes();
        $number_locations = $this->Contacts->getNumberLocations();
        $delivery_methods = $this->Invoices->getDeliveryMethods($client->id);

        // Set sidebar variables
        $this->set('status', $status);
        $this->set('number_types', $number_types);
        $this->set('number_locations', $number_locations);
        $this->set('delivery_methods', $delivery_methods);

        // Ensure a valid service was given
        $service_id = ($this->get['service_id'] ?? ($this->get[1] ?? null));
        if (empty($service_id) || !($service = $this->Services->get($service_id))) {
            $this->redirect($this->base_uri . 'clients/view/' . $client_id . '/');
        }

        // Get the company settings
        $company_settings = $this->SettingsCollection->fetchSettings(null, $this->company_id);

        $service->expiration_date = $this->DomainsDomains->getExpirationDate($service->id);
        $service->registration_date = $this->DomainsDomains->getRegistrationDate($service->id);
        $service->nameservers = $this->DomainsDomains->getNameservers($service->id);
        $vars = $service;

        // Get package from the service
        $package = $service->package ?? (object) [];
        $package->groups = $this->Form->collapseObjectArray(
            $package->groups ?? [],
            'name',
            'id'
        );

        // Validate the provided service is a domain
        if (!($this->ModuleManager->initModule($package->module_id) instanceof RegistrarModule)) {
            $this->redirect($this->base_uri . 'clients/editservice/' . $client_id . '/' . $service_id . '/');
        }

        // Determine which tab of the page is being viewed
        $tab = ($this->get[2] ?? 'domain');
        if (!in_array($tab, $this->getEditTabs($service))) {
            $tab = 'domain';
        }

        // Get list of registrar modules
        $modules = $this->getConfiguredRegistrarModules();

        // Get service statuses
        $statuses = $this->Services->getStatusTypes();

        // Get the actions available for this domain
        $actions = $this->getServiceActions($service);

        // Set action vars
        $vars->auto_renewal = empty($service->date_canceled) ? 'on' : 'off';

        // If the domain is not pending, fetch the module fields
        $service_fields = (array) $this->Form->collapseObjectArray($service->fields, 'value', 'key');
        $vars = (object) array_merge((array) $vars, $service_fields);
        $module_vars = (object) array_merge((array) $service, $service_fields);

        if (!empty($this->post)) {
            $module_vars = (object) $this->post;
        }

        $module_row = $this->ModuleManager->getRow($service->module_row_id);
        $module = $this->ModuleManager->get($module_row->module_id);

        $fields_type = 'getAdminEditFields';
        if ($service->status == 'pending') {
            $fields_type = 'getAdminAddFields';
        }
        $fields = $this->ModuleManager->moduleRpc($module_row->module_id, $fields_type, [$package, $module_vars]);

        $vars->module = $module_row->module_id;
        $vars->module_row = $service->module_row_id;
        $vars->module_row_id = $service->module_row_id;

        // Set the configurable options currently applied to the service
        $vars = (object) array_merge(
            (array) $vars,
            $this->PackageOptions->formatServiceOptions($service->options)
        );

        // Set the price override fields
        $vars->price_override = ($service->override_price !== null && $service->override_currency !== null
            ? 'true'
            : 'false'
        );
        $vars->override_price = $service->override_price;
        $vars->override_currency = ($service->override_currency ?? ($service->package_pricing->currency ?? null));

        // Set the dates used by the service actions
        $vars->date_renews = (!empty($service->date_renews)
            ? $this->Date->cast($service->date_renews, 'Y-m-d')
            : null
        );
        $vars->date_canceled = (!empty($service->date_canceled)
            ? $this->Date->cast($service->date_canceled, 'Y-m-d')
            : $this->Date->modify(date('c'), '+1 day', 'Y-m-d', Configure::get('Blesta.company_timezone'))
        );

        // Set the coupon currently applied to the service
        if ($service->coupon_id && ($coupon = $this->Coupons->get($service->coupon_id))) {
            $service->coupon_code = $coupon->code;
        }

        // Set the terms, currencies and module rows available to the domain
        $terms = $this->formatTermOptions($package);
        $currencies = $this->Form->collapseObjectArray(
            $this->Currencies->getAll(Configure::get('Blesta.company_id')),
            'code',
            'code'
        );
        [$module_row_fields, $module_row_name] = $this->getModuleRowOptions($module);

        // Set the expected renewal price
        $service->renewal_price = $this->Services->getRenewalPrice($service->id);

        // Set the open invoices a reactivated domain may be appended to
        $invoices = $this->Form->collapseObjectArray(
            $this->Invoices->getAll($client->id, 'open', ['id_code' => 'desc']),
            'id_code',
            'id'
        );

        // Fetch module management tabs
        $tabs = $this->getServiceTabs($service, $package, $module, null, null, $tab);

        // Process the submitted section of the page
        if (!empty($this->post)) {
            // Require authorization to update a client's service
            if (!$this->authorized('admin_clients', 'editservice')) {
                $this->flashMessage(
                    'error',
                    Language::_('AppController.!error.unauthorized_access', true),
                    null,
                    false
                );
                $this->redirect($this->base_uri . 'clients/');
            }

            $section = ($this->post['section'] ?? (!empty($this->post['action']) ? 'domain_action' : 'module'));

            switch ($section) {
                case 'domain_action':
                    $errors = $this->processDomainAction($client, $service);
                    break;
                case 'information':
                    $errors = $this->processServiceInformation($service, $module_row_fields);
                    break;
                case 'package':
                    $errors = $this->processServicePackage($client, $service);
                    break;
                default:
                    $errors = $this->processModuleFields($service, $package, $module, $fields);
                    break;
            }

            if (!empty($errors)) {
                $this->setMessage('error', $errors, false, null, false);
            } else {
                $this->flashMessage('message', Language::_('AdminMain.!success.service_edited', true));
                $this->redirect($this->base_uri . 'clients/view/' . $client->id);
            }

            $vars = (object) array_merge((array) $vars, $this->post);
        }

        $fields = (new Html($fields))->generate();

        return $this->clientProfileView(
            'admin_main_edit',
            compact(
                'client',
                'service',
                'package',
                'modules',
                'statuses',
                'actions',
                'module',
                'fields',
                'tabs',
                'vars',
                'terms',
                'currencies',
                'invoices',
                'module_row_fields',
                'module_row_name',
                'tab'
            )
        );
    }

    /**
     * Fetches the tabs of the domain management page available to the given service
     *
     * @param stdClass $service An object representing the domain service
     * @return array A numerically indexed array of tab identifiers
     */
    private function getEditTabs(stdClass $service)
    {
        $tabs = ['domain'];

        // The term and configurable options cannot be changed until the domain is active
        if ($service->status !== 'pending' && $service->status !== 'canceled') {
            $tabs[] = 'options';
        }

        $tabs[] = 'basic';

        return $tabs;
    }

    /**
     * Fetches the actions available for the given domain service, combining the domain specific
     * actions with the service actions offered by the core
     *
     * @param stdClass $service An object representing the domain service
     * @return array A key/value list of available actions and their language
     */
    private function getServiceActions(stdClass $service)
    {
        if ($service->status == 'pending') {
            return [];
        }

        // Coupons are updated from the package section of the page
        $service_actions = $this->Services->getActions($service->status);
        unset($service_actions['update_coupon']);

        // The renew date of a one-time service cannot be changed
        if (($service->package_pricing->period ?? null) == 'onetime') {
            unset($service_actions['change_renew']);
        }

        // Only the service actions apply to a canceled domain
        if ($service->status == 'canceled') {
            return $service_actions;
        }

        return array_merge($this->getDomainActions(), $service_actions);
    }

    /**
     * Fetches a list of the terms available to the given package
     *
     * @param stdClass $package An object representing the package
     * @return array A key/value list of pricing IDs and their term and renewal price
     */
    private function formatTermOptions(stdClass $package)
    {
        $terms = [];
        foreach (($package->pricing ?? []) as $pricing) {
            if ($pricing->period !== 'year') {
                continue;
            }

            $term = Language::_(
                'AdminMain.edit.term_' . $pricing->period . ($pricing->term > 1 ? 's' : ''),
                true,
                $pricing->term
            );
            $terms[$pricing->id] = Language::_(
                'AdminMain.add.term',
                true,
                $term,
                $this->CurrencyFormat->format($pricing->price_renews, $pricing->currency)
            );
        }

        return $terms;
    }

    /**
     * Fetches the module rows available to a domain of the given module
     *
     * @param stdClass $module An object representing the registrar module
     * @return array An array containing a key/value list of module rows and the module row name
     */
    private function getModuleRowOptions(stdClass $module)
    {
        $meta_key = $this->ModuleManager->moduleRpc($module->id, 'moduleRowMetaKey');

        $module_row_fields = [];
        foreach (($module->rows ?? []) as $row) {
            $module_row_fields[$row->id] = ($row->meta->{$meta_key} ?? $module->name);
        }

        return [$module_row_fields, $this->ModuleManager->moduleRpc($module->id, 'moduleRowName')];
    }

    /**
     * Processes an action submitted from the domain actions section
     *
     * @param stdClass $client An object representing the client
     * @param stdClass $service An object representing the domain service
     * @return mixed An array of errors, or false otherwise
     */
    private function processDomainAction(stdClass $client, stdClass $service)
    {
        $action = ($this->post['action'] ?? null);
        $service_actions = ['suspend', 'unsuspend', 'cancel', 'uncancel', 'schedule_cancel', 'change_renew'];

        // Domain specific actions are shared with the domain browse page
        if (!in_array($action, $service_actions)) {
            $this->post['service_ids'] = [$service->id];

            return $this->updateDomains($this->post);
        }

        switch ($action) {
            case 'suspend':
                $this->Services->suspend($service->id, $this->post);
                break;
            case 'unsuspend':
                $this->Services->unsuspend($service->id, $this->post);
                break;
            case 'cancel':
                // Cancel right now
                $this->post['date_canceled'] = date('c');
                $this->post['notify_cancel'] = ($this->post['notify_cancel'] ?? 'false');

                $this->Services->cancel($service->id, $this->post);
                break;
            case 'uncancel':
                $this->Services->unCancel($service->id, $this->post);
                break;
            case 'schedule_cancel':
                // Remove any scheduled cancellation
                if (($this->post['cancel'] ?? null) == 'none') {
                    $this->Services->unCancel($service->id);
                    break;
                }

                if (($this->post['cancel'] ?? null) == 'term') {
                    $this->post['date_canceled'] = 'end_of_term';
                }
                $this->post['notify_cancel'] = ($this->post['notify_cancel'] ?? 'false');

                $this->Services->cancel($service->id, $this->post);
                break;
            case 'change_renew':
                return $this->changeRenewDate($client, $service);
        }

        return $this->Services->errors();
    }

    /**
     * Changes the renew date of the given service, prorating the difference
     *
     * @param stdClass $client An object representing the client
     * @param stdClass $service An object representing the domain service
     * @return mixed An array of errors, or false otherwise
     */
    private function changeRenewDate(stdClass $client, stdClass $service)
    {
        $data = array_merge(
            [
                'date_renews' => (isset($this->post['date_renews'])
                    ? $this->Services->dateToUtc($this->post['date_renews']) . 'Z'
                    : $service->date_renews . 'Z'
                ),
                'date_paid_through' => null,
                'pricing_id' => $service->pricing_id,
                'qty' => $service->qty,
                'use_module' => 'false'
            ],
            $this->PackageOptions->formatServiceOptions($service->options)
        );

        $prorate = (($this->post['prorate'] ?? null) == 'true');
        $pricing = $service->package_pricing;

        // Determine the items/totals
        $service_change = $this->ServiceChanges->getPresenter($service->id, $data);
        $total = ($service_change ? $service_change->totals()->total : 0);

        $this->Services->validateServiceEdit($service->id, $data, true);
        $errors = $this->Services->errors();

        // Create the invoice for the service change
        if (empty($errors) && $service_change && $prorate && $total > 0) {
            $invoice_data = $this->makeInvoice($client, $service_change, $pricing->currency, true, $service->id);
            $errors = $invoice_data['errors'];
        }

        if (empty($errors)) {
            $this->Services->edit($service->id, $data, true);
            $errors = $this->Services->errors();
        }

        // Issue a credit for the service change
        $allow_credit = (($client->settings['client_prorate_credits'] ?? null) == 'true');
        if (empty($errors) && $prorate && $total < 0 && $allow_credit) {
            $this->createCredit($client->id, abs($total), $pricing->currency);
        }

        return $errors;
    }

    /**
     * Processes the service information section, assigning the domain to a different module row
     *
     * @param stdClass $service An object representing the domain service
     * @param array $module_row_fields A key/value list of the module rows available to the service
     * @return mixed An array of errors, or false otherwise
     */
    private function processServiceInformation(stdClass $service, array $module_row_fields)
    {
        // The module row to change to must be a valid row of the service's module
        if (!isset($this->post['module_row_id'])
            || !array_key_exists($this->post['module_row_id'], $module_row_fields)
        ) {
            return false;
        }

        $this->Services->edit(
            $service->id,
            ['module_row_id' => $this->post['module_row_id'], 'use_module' => 'false'],
            true
        );

        return $this->Services->errors();
    }

    /**
     * Processes the package section, updating the term, price override, coupon and configurable
     * options of the domain and prorating the difference
     *
     * @param stdClass $client An object representing the client
     * @param stdClass $service An object representing the domain service
     * @return mixed An array of errors, or false otherwise
     */
    private function processServicePackage(stdClass $client, stdClass $service)
    {
        // Set unchecked checkboxes so they are not carried over when re-rendering the page
        foreach (['price_override', 'prorate', 'disable_option_logic', 'use_module'] as $checkbox) {
            $this->post[$checkbox] = ($this->post[$checkbox] ?? 'false');
        }

        $data = $this->post;

        // Set any price overrides for this service
        if (($this->post['price_override'] ?? null) == 'true') {
            $data['override_price'] = (is_numeric($this->post['override_price'] ?? null)
                ? $this->post['override_price']
                : null
            );
            $data['override_currency'] = (is_numeric($this->post['override_price'] ?? null)
                && !empty($this->post['override_currency'])
                    ? $this->post['override_currency']
                    : null
            );

            // Cannot change package/term
            unset($data['pricing_id']);
        } else {
            // Reset price overrides
            $data['override_price'] = null;
            $data['override_currency'] = null;
        }

        if (isset($this->post['coupon_code'])) {
            $data['coupon_id'] = $this->getCouponId($this->post['coupon_code']);
        }

        // Always set config options so that they can be removed if no longer valid
        $data['configoptions'] = ($data['configoptions'] ?? []);

        // Determine the pricing currency
        $pricing = $service->package_pricing;
        if (isset($data['pricing_id']) && ($package = $this->Packages->getByPricingId($data['pricing_id']))) {
            foreach ($package->pricing as $price) {
                if ($price->id == $data['pricing_id']) {
                    $pricing = $price;
                    break;
                }
            }
        }

        // Cancel any pending service change
        $this->cancelServiceChanges($service->id);

        // Determine the items/totals
        $data = array_merge($data, ['qty' => (!empty($data['qty']) ? $data['qty'] : 1)]);
        $service_change = $this->ServiceChanges->getPresenter($service->id, $data);
        $total = ($service_change ? $service_change->totals()->total : 0);

        // Determine whether credits are allowed
        $allow_credit = (($client->settings['client_prorate_credits'] ?? null) == 'true');
        $queue_service_changes = (($client->settings['process_paid_service_changes'] ?? null) == 'true');
        $prorate = (($data['prorate'] ?? null) == 'true');

        // Don't allow proration on the service to create an invoice. We'll handle this ourselves
        unset($data['prorate']);

        $this->Services->validateServiceEdit($service->id, $data);
        $errors = $this->Services->errors();

        // Validate that the submitted config options are valid given the Option Logic
        $option_logic = new OptionLogic();
        if (($this->post['disable_option_logic'] ?? 'false') == 'false') {
            $option_logic->setService($service);
            $option_logic->setPackageOptionConditionSets(
                $this->PackageOptionConditionSets->getAll(
                    [
                        'package_id' => $pricing->package_id,
                        'opition_ids' => $this->Form->collapseObjectArray(
                            $this->PackageOptions->getAllByPackageId(
                                $pricing->package_id,
                                $pricing->term,
                                $pricing->period,
                                $pricing->currency
                            ),
                            'id',
                            'id'
                        )
                    ],
                    ['option_id']
                )
            );
        }

        // Create the invoice for the service change
        $invoice_id = '';
        if (empty($errors)
            && !($errors = $option_logic->validate($data['configoptions']))
            && $service_change
            && $prorate
            && $total > 0
        ) {
            $invoice_data = $this->makeInvoice($client, $service_change, $pricing->currency, true, $service->id);
            $invoice_id = $invoice_data['invoice_id'];
            $errors = $invoice_data['errors'];
        }

        if (empty($errors)) {
            if ($queue_service_changes && $prorate && $total > 0) {
                $result = $this->queueServiceChange($service->id, $invoice_id, $data);
                $errors = $result['errors'];
            } else {
                // Update the service immediately when not being queued, prorated, or charging any amount
                $this->Services->edit($service->id, $data);
                $errors = $this->Services->errors();
            }
        }

        // Issue a credit for the service change
        $transaction_id = null;
        if (empty($errors) && $prorate && $total < 0 && $allow_credit) {
            $transaction_id = $this->createCredit($client->id, abs($total), $pricing->currency);
        }

        // Log the service change
        if (empty($errors)) {
            $this->Logs->addServiceChange([
                'service_id' => $service->id,
                'transactions' => ($transaction_id ? [$transaction_id] : []),
                'old_service' => (array) $service,
                'new_service' => (array) $this->Services->get($service->id)
            ]);
        }

        return $errors;
    }

    /**
     * Processes the basic options section, updating the registrar module fields of the domain
     *
     * @param stdClass $service An object representing the domain service
     * @param stdClass $package An object representing the package of the domain service
     * @param stdClass $module An object representing the registrar module
     * @param InputFields $fields The module fields of the domain service
     * @return mixed An array of errors, or false otherwise
     */
    private function processModuleFields(stdClass $service, stdClass $package, stdClass $module, $fields)
    {
        // Set checkboxes
        $checkboxes = ['use_module'];
        foreach ($checkboxes as $checkbox) {
            if (!isset($this->post[$checkbox])) {
                $this->post[$checkbox] = 'false';
            }
        }

        // Update package
        if (isset($this->post['module']) && $module->id != $this->post['module'] && $service->status == 'pending') {
            $tld = strstr($service->name ?? $this->post['domain'] ?? '', '.');
            $package = $this->DomainsTlds->getTldPackageByModuleId(
                $tld,
                $this->post['module'],
                Configure::get('Blesta.company_id')
            );

            // If a package doesn't exist for this TLD and the provided module, clone the default one
            if (empty($package)) {
                $this->DomainsTlds->duplicate($tld, $this->post['module']);
                $package = $this->DomainsTlds->getTldPackageByModuleId(
                    $tld,
                    $this->post['module'],
                    Configure::get('Blesta.company_id')
                );
            }

            // Get the pricing from the amount of years
            $pricing = $this->DomainsTlds->getPricing(
                $package->id,
                $service->package_pricing->term,
                $service->package_pricing->currency
            );
            $this->post['pricing_id'] = $pricing->pricing_id;
        }

        // Update service fields
        $allowed_fields = ['status', 'pricing_id', 'use_module'];
        foreach ($fields->getFields() as $field) {
            foreach ($field->fields as $subfield) {
                if (str_contains($subfield->params['name'], '[')) {
                    $parts = explode('[', $subfield->params['name'], 2);
                    $subfield->params['name'] = $parts[0];
                }

                $allowed_fields[] = $subfield->params['name'];
            }

            if ($field->type !== 'label') {
                $allowed_fields[] = $field->params['name'];
            }
        }

        $params = array_intersect_key($this->post, array_flip($allowed_fields));
        $this->Services->edit($service->id, $params);

        if (($errors = $this->Services->errors())) {
            return $errors;
        }

        // Services::edit only stores the service fields returned by the module, and registrar
        // modules commonly return nothing from editService, so the submitted values have to be
        // stored directly when the module is bypassed
        if (($params['use_module'] ?? 'true') == 'false') {
            $this->setServiceFields($service->id, $params);
        }

        return $this->Services->errors();
    }

    /**
     * Stores the submitted values of the service fields the given service already has
     *
     * @param int $service_id The ID of the service whose fields to set
     * @param array $vars The submitted service field values
     */
    private function setServiceFields($service_id, array $vars)
    {
        // Refetch the service so that any field the module may have set is not reverted
        if (!($service = $this->Services->get($service_id))) {
            return;
        }

        $fields = [];
        foreach (($service->fields ?? []) as $field) {
            if (isset($vars[$field->key])) {
                $field->value = $vars[$field->key];
            }

            $fields[] = (array) $field;
        }

        if (!empty($fields)) {
            $this->Services->setFields($service_id, $fields);
        }
    }

    /**
     * Service tab request
     */
    public function tab()
    {
        $this->uses(['Services', 'Packages', 'ModuleManager']);

        // Ensure we have a client to load
        if (!isset($this->get[0]) || !($client = $this->Clients->get((int)$this->get[0]))) {
            $this->redirect($this->base_uri . 'clients/');
        }

        // Ensure we have a service
        if (
            !isset($this->get[1])
            || !($service = $this->Services->get((int)$this->get[1]))
            || $service->client_id != $client->id
            || !isset($this->get[2])
        ) {
            $this->redirect($this->base_uri . 'clients/view/' . $client->id . '/');
        }

        $package = $this->Packages->get($service->package->id);
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;
        $method = null;
        $plugin_id = null;

        // If the second GET argument is a number, we must infer this to mean a plugin, not a module
        $tab_view = '';
        if (is_numeric($this->get[2])) {
            // No plugin method was given to call, or the plugin is not supported by the service
            $valid_plugins = $this->Form->collapseObjectArray($package->plugins, 'plugin_id', 'plugin_id');
            if (!isset($this->get[3]) || !array_key_exists($this->get[2], $valid_plugins)) {
                $this->redirect($this->base_uri . 'plugin/domains/admin_main/edit/' . $client->id . '/' . $service->id . '/');
            }

            // Determine the plugin/method called
            $plugin_id = $this->get[2];
            $method = $this->get[3];

            // Process and retrieve the plugin tab content
            $tab_view = $this->processPluginTab($plugin_id, $method, $service);
        } else {
            // Determine the method called
            $method = $this->get[2];

            // Process and retrieve the module tab content
            $tab_view = $this->processModuleTab($module, $method, $package, $service);
        }

        // Fetch service tabs
        $module_row = $this->ModuleManager->getRow($service->module_row_id);
        $module = $this->ModuleManager->get($module_row->module_id);
        $tabs = $this->getServiceTabs($service, $package, $module, $method, $plugin_id);

        return $this->clientProfileView(
            'admin_main_tab',
            compact(
                'client',
                'tab_view',
                'service',
                'package',
                'tabs'
            )
        );
    }

    /**
     * Fetches the add service fields from a specific registrar module
     */
    public function getModuleFields()
    {
        // Check if the request was made through AJAX
        if (!$this->isAjax()) {
            header($this->server_protocol . ' 401 Unauthorized');
            exit();
        }

        // Ensure a valid module was given
        $module_id = ($this->get['module_id'] ?? ($this->get[0] ?? null));
        if (empty($module_id) || !($module = $this->ModuleManager->get($module_id))) {
            return false;
        }

        // Ensure a valid package was given
        $package_id = ($this->get['package_id'] ?? ($this->get[1] ?? null));
        if (empty($package_id) || !($package = $this->Packages->get($package_id))) {
            return false;
        }

        // Get the type of fields to obtain
        $fields_type = 'getClientAddFields';
        if (isset($this->get[2]) && $this->get[2] == 'edit') {
            $fields_type = 'getAdminEditFields';
        }

        // Set package type to domain
        if (!isset($package->meta->type)) {
            $package->meta->type = 'domain';
        }

        // Get service fields, if a service id has been provided
        $vars = (object) $_POST ?? $this->post;

        $service_fields = [];
        if (($service = $this->Services->get($vars->service_id ?? null))) {
            $service_fields = $this->Form->collapseObjectArray(
                $service->fields ?? [],
                'value',
                'key'
            );

            $vars = (object) array_merge((array) $vars, (array) $service_fields);
        }

        // Get module fields
        $fields = $this->ModuleManager->moduleRpc($module->id, $fields_type, [$package, $vars]);

        // Add module row dropdown
        $meta_key = $this->ModuleManager->moduleRpc($module->id, 'moduleRowMetaKey');
        $module_rows = ['' => Language::_('AdminMain.getmodulefields.auto_choose', true)];
        foreach ($module->rows as $row) {
            $module_rows[$row->id] = $row->meta->{$meta_key} ?? $module->name;
        }

        $row_name = $this->ModuleManager->moduleRpc($module->id, 'moduleRowName');
        if (method_exists($fields, 'label')) {
            $rows_label = $fields->label($row_name, 'module_row');
            $rows_label->attach(
                $fields->fieldSelect(
                    'module_row',
                    $module_rows,
                    $vars->module_row ?? null,
                    ['id' => 'module_row']
                )
            );
            $fields->setField($rows_label);
        }

        // Render HTML
        $html = (new Html($fields))->generate();
        $this->outputAsJson(['content' => $html]);

        return false;
    }

    /**
     * Fetches the add service pricing options from a specific registrar module
     */
    public function getPricing()
    {
        // Check if the request was made through AJAX
        if (!$this->isAjax()) {
            header($this->server_protocol . ' 401 Unauthorized');
            exit();
        }

        $this->uses(['Domains.DomainsTlds']);

        // Ensure a valid module was given
        $module_id = ($this->get['module_id'] ?? ($this->get[0] ?? null));
        if (empty($module_id) || !($module = $this->ModuleManager->get($module_id))) {
            return false;
        }

        // Ensure a valid package was given
        $package_id = ($this->get['package_id'] ?? ($this->get[1] ?? null));
        if (empty($package_id) || !($package = $this->Packages->get($package_id)) || !isset($package->meta->tlds[0])) {
            return false;
        }

        $tld = $package->meta->tlds[0];
        // Check if a package exists for the given module
        $package = $this->DomainsTlds->getTldPackageByModuleId(
            $tld,
            $module_id,
            Configure::get('Blesta.company_id')
        );

        // If a package doesn't exist for this TLD and the provided module, clone the default one
        if (empty($package)) {
            $package_id = $this->DomainsTlds->duplicate($tld, $module_id, Configure::get('Blesta.company_id'));
            $package = $this->Packages->get($package_id);
        } else {
            $package = $this->Packages->get($package->id);
        }

        $years = $this->formatPricingOptions($package);
        $this->outputAsJson([
            'pricing' => $years,
            'pricing_ids' => $this->formatPricingIds($package),
            'package_id' => $package->id
        ]);

        return false;
    }

    /**
     * Fetches a list of pricing IDs keyed the same way as the pricing options, so the configurable
     * options of the selected term can be fetched
     *
     * @param stdClass $package An object representing the package
     * @return array A key/value list of "{term}-{currency}" keys and their pricing ID
     */
    private function formatPricingIds(stdClass $package)
    {
        $pricing_ids = [];
        foreach (($package->pricing ?? []) as $pricing) {
            if ($pricing->period !== 'year') {
                continue;
            }

            $pricing_ids[$pricing->term . '-' . $pricing->currency] = $pricing->id;
        }

        return $pricing_ids;
    }

    /**
     * Returns the widget listing the Domains for a given client
     */
    public function domains()
    {
        $this->uses(['Domains.DomainsDomains']);

        // Process bulk domains options
        if (!empty($this->post) && isset($this->post['service_ids'])) {
            if (($errors = $this->updateDomains($this->post))) {
                $this->set('vars', (object)$this->post);
                $this->setMessage('error', $errors, false, null, false);
            } else {
                switch ($this->post['action']) {
                    case 'change_auto_renewal':
                        $term = 'AdminMain.!success.change_auto_renewal';
                        break;
                    case 'change_expiration_date':
                        $term = 'AdminMain.!success.change_expiration_date';
                        break;
                    case 'change_registration_date':
                        $term = 'AdminMain.!success.change_registration_date';
                        break;
                    case 'change_registrar':
                        $term = 'AdminMain.!success.domain_registrar_updated';
                        break;
                    case 'domain_renewal':
                        $term = 'AdminMain.!success.domain_renewal';
                        break;
                    case 'set_price_override':
                        $term = 'AdminMain.!success.set_price_override';
                        break;
                    case 'remove_price_override':
                        $term = 'AdminMain.!success.remove_price_override';
                        break;
                    case 'update_nameservers':
                        $term = 'AdminMain.!success.update_nameservers';
                        break;
                    case 'push_to_client':
                        $term = 'AdminMain.!success.domains_pushed';
                        break;
                    case 'unparent':
                        $term = 'AdminMain.!success.domains_unparented';
                        break;
                    case 'queue_sync':
                        $term = 'AdminMain.!success.queue_sync';
                        break;
                }

                $this->flashMessage('message', Language::_($term, true), null, false);
                $this->redirect($this->base_uri . 'clients/view/' . ($this->get['client_id'] ?? ($this->get[0] ?? null)));
            }
        }

        // Get the company settings
        $company_settings = $this->SettingsCollection->fetchSettings(null, $this->company_id);

        // Ensure a valid client was given
        $client_id = ($this->get['client_id'] ?? ($this->get[0] ?? null));

        if (empty($client_id) || !($client = $this->Clients->get($client_id))) {
            $this->redirect($this->base_uri . 'clients/');
        }

        // Set filters from post input
        $post_filters = [];
        if (isset($this->post['filters'])) {
            $post_filters = $this->post['filters'];
            unset($this->post['filters']);

            foreach ($post_filters as $filter => $value) {
                if (empty($value)) {
                    unset($post_filters[$filter]);
                }
            }
        }

        // Get domains
        $status = ($this->get[1] ?? 'active');
        $page = (isset($this->get[2]) ? (int)$this->get[2] : 1);
        $sort = ($this->get['sort'] ?? 'date_added');
        $order = ($this->get['order'] ?? 'desc');

        $domains_filters = array_merge([
            'client_id' => $client->id,
            'status' => $status
        ], $post_filters);

        $domains = $this->DomainsDomains->getList($domains_filters, $page, [$sort => $order]);
        $total_results = $this->DomainsDomains->getListCount($domains_filters);

        // Set the number of domains of each type, not including children
        $status_count = [
            'active' => $this->DomainsDomains->getStatusCount('active', $domains_filters),
            'canceled' => $this->DomainsDomains->getStatusCount('canceled', $domains_filters),
            'pending' => $this->DomainsDomains->getStatusCount('pending', $domains_filters),
            'suspended' => $this->DomainsDomains->getStatusCount('suspended', $domains_filters)
        ];

        // Get list of registrar modules
        $modules = $this->getConfiguredRegistrarModules();

        // Set the input field filters for the widget
        $filters = $this->getFilters($post_filters);
        $this->set('filters', $filters);
        $this->set('filter_vars', $post_filters);

        // Set view fields
        $this->set('client', $client);
        $this->set('status', $status);
        $this->set('domains', $domains);
        $this->set('status_count', $status_count);
        $this->set('actions', $this->getDomainActions());
        $this->set('modules', $modules);
        $this->set('widget_state', $this->widgets_state['main_domains'] ?? null);
        $this->set('sort', $sort);
        $this->set('order', $order);
        $this->set('negate_order', ($order == 'asc' ? 'desc' : 'asc'));

        $this->structure->set('page_title', Language::_('AdminMain.index.page_title', true, $client->id_code));

        $this->Javascript->setFile('date.min.js');
        $this->Javascript->setFile('jquery.datePicker.min.js');
        $this->Javascript->setInline(
            'Date.firstDayOfWeek=' . ($company_settings['calendar_begins'] == 'sunday' ? 0 : 1) . ';'
        );

        // Set language for periods
        $periods = $this->Packages->getPricingPeriods();
        foreach ($this->Packages->getPricingPeriods(true) as $period => $lang) {
            $periods[$period . '_plural'] = $lang;
        }
        $this->set('periods', $periods);

        // Overwrite default pagination settings
        $settings = array_merge(
            Configure::get('Blesta.pagination'),
            [
                'total_results' => $total_results,
                'uri' => $this->base_uri . 'plugin/domains/admin_main/domains/' . $client->id . '/' . $status . '/[p]/',
                'params' => ['sort' => $sort, 'order' => $order]
            ]
        );
        $this->setPagination($this->get, $settings);

        if ($this->isAjax()) {
            return $this->renderAjaxWidgetIfAsync(
                isset($this->get['client_id']) ? null : (isset($this->get[2]) || isset($this->get['sort']))
            );
        }
    }

    /**
     * Client Domains count
     */
    public function clientDomainsCount()
    {
        $client_id = $this->get[0] ?? null;
        $status = $this->get[1] ?? 'active';

        echo $this->Services->getStatusCount($client_id, $status, false, ['type' => 'domains']);

        return false;
    }

    /**
     * Gets a list of input fields for filtering domains
     *
     * @param array $options A list of options for building the filters including:
     *  - language The language for filter labels and tooltips
     *  - company_id The company ID to filter modules on
     * @param array $vars A list of submitted inputs that act as defaults for filter fields including:
     *  - module_id The module ID on which to filter packages
     *  - package_name The (partial) name of the packages for which to fetch services
     *  - service_meta The (partial) value of meta data on which to filter services
     * @return InputFields An object representing the list of filter input field
     */
    private function getFilters(array $options, array $vars = [])
    {
        $fields = new InputFields();

        // Set the package name filter
        $package_name = $fields->label(
            Language::_('AdminMain.getfilters.field_package_name', true),
            'package_name'
        );
        $package_name->attach(
            $fields->fieldText(
                'filters[package_name]',
                $vars['package_name'] ?? null,
                [
                    'id' => 'package_name',
                    'class' => 'form-control stretch',
                    'placeholder' => Language::_('AdminMain.getfilters.field_package_name', true)
                ]
            )
        );
        $fields->setField($package_name);

        // Set the service meta filter
        $service_meta = $fields->label(
            Language::_('AdminMain.getfilters.field_service_meta', true),
            'service_meta'
        );
        $service_meta->attach(
            $fields->fieldText(
                'filters[service_meta]',
                $vars['service_meta'] ?? null,
                [
                    'id' => 'service_meta',
                    'class' => 'form-control stretch',
                    'placeholder' => Language::_('AdminMain.getfilters.field_service_meta', true)
                ]
            )
        );
        $fields->setField($service_meta);

        return $fields;
    }

    /**
     * Get list of configured registrar modules
     *
     * @return array Array of module names keyed by module ID
     */
    private function getConfiguredRegistrarModules()
    {
        $all_modules = $this->ModuleManager->getAll(
            Configure::get('Blesta.company_id'),
            'name',
            'asc',
            ['type' => 'registrar']
        );

        // Filter out modules without configured module rows
        $configured_modules = [];
        foreach ($all_modules as $module) {
            $module_rows = $this->ModuleManager->getRows($module->id);
            if (!empty($module_rows)) {
                $configured_modules[] = $module;
            }
        }

        return $this->Form->collapseObjectArray($configured_modules, 'name', 'id');
    }

    /**
     * Renders a view as a client profile page
     *
     * @param string $view The view file name to render
     * @param array $vars An array of variables to pass to the view
     */
    private function clientProfileView($view, array $vars = [])
    {
        $this->uses([
            'Contacts',
            'ClientGroups',
            'Actions',
            'Logs'
        ]);
        $this->helpers(['Form', 'Color']);

        // Fetch client
        $client = $vars['client'] ?? null;
        if (is_null($client)) {
            $client = $this->Clients->get($vars['client_id'] ?? null);
        }

        // Get all contacts, excluding the primary
        $client->contacts = array_merge(
            $this->Contacts->getAll($client->id, 'billing'),
            $this->Contacts->getAll($client->id, 'other')
        );
        $client->numbers = $this->Contacts->getNumbers($client->contact_id);
        $client->note_count = $this->Clients->getNoteListCount($client->id);
        $client->group = $this->ClientGroups->get($client->client_group_id);

        // Get user logs
        $user_log = $this->Logs->getUserLog($client->user_id, 'success');

        // Set all contact types besides 'primary' and 'other'
        $contact_types = $this->Contacts->getContactTypes();
        $contact_type_ids = $this->Form->collapseObjectArray(
            $this->Contacts->getTypes($this->company_id),
            'real_name',
            'id'
        );
        unset($contact_types['primary'], $contact_types['other']);

        // Render content
        $content = $this->partial($view, $vars);

        // Set view variables
        $this->set('contact_types', $contact_types + $contact_type_ids);
        $this->set('user_log', $user_log);
        $this->set('client', $client);
        $this->set('content', $content);
        $this->set('number_types', $this->Contacts->getNumberTypes());
        $this->set('number_locations', $this->Contacts->getNumberLocations());
        $this->set('status', $this->Clients->getStatusTypes());
        $this->set('default_currency', $client->settings['default_currency']);
        $this->set('multiple_groups', $this->ClientGroups->getListCount($this->company_id) > 0);
        $this->set('delivery_methods', $this->Invoices->getDeliveryMethods($client->id));
        $this->set(
            'plugin_actions',
            $this->Actions->getAll(
                ['company_id' => $this->company_id, 'location' => 'action_staff_client', 'enabled' => 1],
                true
            )
        );
        $this->set('client_account', $this->Clients->getDebitAccount($client->id));
        $this->set('is_ajax', $this->isAjax());

        // Set override variables
        foreach ($vars as $key => $value) {
            $this->set($key, $value);
        }

        // Fetch default admin layout
        $layout = 'default';
        $admin_view_dir = $this->Companies->getSetting(Configure::get('Blesta.company_id'), 'admin_view_dir');
        if (!empty($admin_view_dir->value)) {
            $layout = $admin_view_dir->value;
        }

        // Render view
        if ($this->isAjax()) {
            $this->controller = $view;
            $this->action = null;

            return $this->renderAjaxWidgetIfAsync();
        }
    }

    /**
     * Retrieves the service management tabs when editing a service
     *
     * @param stdClass $service An stdClass object representing the service
     * @param stdClass $package An stdClass object representing the service's package
     * @param Module $module An instance of the module used by the service
     * @param string|null $method The method being called (i.e. the tab action, optional)
     * @param int|null $plugin_id The ID of the plugin being called (optional)
     */
    private function getServiceTabs(
        stdClass $service,
        stdClass $package,
        $module,
        $method = null,
        $plugin_id = null,
        $tab = null
    ) {
        // Each tab of the domain management page and its language
        $page_tabs = [
            'domain' => Language::_('AdminMain.edit.tab_domain', true),
            'options' => Language::_('AdminMain.edit.tab_options', true),
            'basic' => Language::_('AdminClients.editservice.tab_basic', true)
        ];

        $tabs = [];
        foreach ($this->getEditTabs($service) as $page_tab) {
            $tabs[] = [
                'name' => $page_tabs[$page_tab],
                'attributes' => [
                    // These tabs are not loaded over AJAX because the widget injects the response
                    // with innerHTML, which would not run the inline scripts the page relies on
                    'href' => $this->base_uri . 'plugin/domains/admin_main/edit/' . $service->client_id . '/'
                        . $service->id . '/' . ($page_tab === 'domain' ? '' : $page_tab . '/')
                ],
                // Default to the domain tab as being the current one
                'current' => ($method === null && $plugin_id === null && $page_tab === ($tab ?? 'domain'))
            ];
        }

        // Set tabs only if the service has not been canceled
        if ($service->status != 'canceled') {
            $module_tabs = $this->ModuleManager->moduleRpc(
                $module->id,
                'getAdminServiceTabs',
                [$service]
            );

            // Set each of the module tabs
            foreach ($module_tabs ?? [] as $action => $name) {
                $tabs[] = [
                    'name' => $name,
                    'attributes' => [
                        'href' => $this->base_uri . 'plugin/domains/admin_main/tab/' . $service->client_id . '/'
                            . $service->id . '/' . $action . '/',
                        'class' => 'ajax'
                    ],
                    'current' => ($plugin_id === null && strtolower($action) === strtolower($method ?? ''))
                ];
            }

            // Retrieve the plugin tabs
            foreach ($package->plugins as $plug) {
                // Skip the plugin if it is not available
                if (!($plugin = $this->getPlugin($plug->plugin_id))) {
                    continue;
                }

                foreach ($plugin->getAdminServiceTabs($service) as $action => $tab) {
                    $attributes = [
                        'href' => (!empty($tab['href'])
                            ? $tab['href']
                            : $this->base_uri . 'plugin/domains/admin_main/tab/' . $service->client_id . '/'
                            . $service->id . '/' . $plug->plugin_id . '/' . $action . '/'
                        ),
                        'class' => 'ajax'
                    ];

                    $tabs[] = [
                        'name' => $tab['name'],
                        'attributes' => $attributes,
                        'current' => ($plug->plugin_id == $plugin_id && strtolower($action) === strtolower($method ?? ''))
                    ];
                }
            }
        }

        return $tabs;
    }

    /**
     * Processes and retrieves the module tab content for the given method
     *
     * @param Module $module The module instance
     * @param string $method The method on the module to call to retrieve the tab content
     * @param stdClass $package An stdClass object representing the package
     * @param stdClass $service An stdClass object representing the service being managed
     * @return string The tab content
     */
    private function processModuleTab($module, $method, stdClass $package, stdClass $service)
    {
        $content = '';

        // Get tabs
        $admin_tabs = $module->getAdminServiceTabs($service);
        $valid_method = array_key_exists(strtolower($method), array_change_key_case($admin_tabs, CASE_LOWER));

        // Load/process the tab request
        if ($valid_method && is_callable([$module, $method])) {
            // Set the module row used for this service
            $module->setModuleRow($module->getModuleRow($service->module_row_id));

            // Call the module method and set any messages to the view
            $content = $module->{$method}($package, $service, $this->get, $this->post, $this->files);
            $this->setServiceTabMessages($module->errors(), $module->getMessages());
        } else {
            // Invalid method called, redirect
            $this->redirect($this->base_uri . 'plugin/domains/admin_main/edit/' . $service->client_id . '/' . $service->id . '/');
        }

        return $content;
    }

    /**
     * Processes and retrieves the plugin tab content for the given method
     *
     * @param int $plugin_id The ID of the plugin
     * @param string $method The method on the plugin to call to retrieve the tab content
     * @param stdClass $service An stdClass object representing the service being managed
     * @return string The tab content
     */
    private function processPluginTab($plugin_id, $method, stdClass $service)
    {
        $content = '';

        if (($plugin = $this->getPlugin($plugin_id))) {
            $plugin->base_uri = $this->base_uri;

            // Get tabs
            $admin_tabs = $plugin->getAdminServiceTabs($service);
            $valid_method = array_key_exists(strtolower($method), array_change_key_case($admin_tabs, CASE_LOWER));

            // Retrieve the plugin tab content
            if ($valid_method && is_callable([$plugin, $method])) {
                // Call the plugin method and set any messages to the view
                $content = $plugin->{$method}($service, $this->get, $this->post, $this->files);
                $this->setServiceTabMessages($plugin->errors(), $plugin->getMessages());
            } else {
                // Invalid method called, redirect
                $this->redirect(
                    $this->base_uri . 'plugin/domains/admin_main/edit/' . $service->client_id . '/' . $service->id . '/'
                );
            }
        }

        return $content;
    }

    /**
     * Retrieves an instance of the given plugin if it is enabled
     *
     * @param int $plugin_id The ID of the plugin
     * @return Plugin|null An instance of the plugin
     */
    private function getPlugin($plugin_id)
    {
        $this->uses(['PluginManager']);
        $this->components(['Plugins']);

        if (($plugin = $this->PluginManager->get($plugin_id)) && $plugin->enabled == '1') {
            try {
                return $this->Plugins->create($plugin->dir);
            } catch (Throwable $e) {
                // Do nothing
            }
        }

        return null;
    }

    /**
     * Sets messages to the view based on the given errors and messages provided
     *
     * @param array|bool|null $errors An array of error messages (optional)
     * @param array $messages An array of any other messages keyed by type (optional)
     */
    private function setServiceTabMessages($errors = null, array $messages = null)
    {
        // Prioritize error messages over any other messages
        if (!empty($errors)) {
            $this->setMessage('error', $errors, false, null, false);
        } elseif (!empty($messages)) {
            // Display messages if any
            foreach ($messages as $type => $message) {
                $this->setMessage($type, $message, false, null, false);
            }
        } elseif (!empty($this->post)) {
            // Default to display a message after POST
            $this->setMessage('success', Language::_('AdminClients.!success.service_tab', true), false, null, false);
        }
    }
}
