<?php

use Blesta\Core\Util\Input\Fields\InputFields;
use Blesta\Core\Util\Input\Fields\Html;
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
        $this->uses(['Domains.DomainsTlds', 'Domains.DomainsDomains']);

        // Ensure a valid client was given
        $client_id = ($this->get['client_id'] ?? ($this->get[0] ?? null));
        if (empty($client_id) || !($client = $this->Clients->get($client_id))) {
            $this->redirect($this->base_uri . 'clients/');
        }

        // Get the wizard action/step
        $action = ($this->get['action'] ?? ($this->get[1] ?? null));
        if (empty($action)) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_main/add/' . $client_id . '/lookup/');
        }

        // Render action
        switch ($action) {
            case "lookup":
                $this->renderLookupStep($client);
                break;
            case "configuration":
                $this->renderConfigurationStep($client);
                break;
            case "confirmation":
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
                } else if (in_array($tld, $tlds)) {
                    $this->post['tlds'] = [$tld];
                }
            }

            // Remove TLD from domain
            preg_match("/^(.*?)\.(.*)/i", $this->post['domain'], $matches);
            $domain = $matches[1] ?? $this->post['domain'];
            $this->post['domain'] = $domain;

            // Process action
            switch ($action) {
                case "lookup":
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
                case "transfer":
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
            //$this->Session->clear('domain_configuration_fields');
        }

        // Set action
        $action = 'register';
        if (isset($this->post['transfer']) && $this->post['transfer'] == '1') {
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
        $modules = $this->Form->collapseObjectArray(
            $this->ModuleManager->getAll(Configure::get('Blesta.company_id'), 'name', 'asc', ['type' => 'registrar']),
            'name',
            'id'
        );

        // Get open invoices
        $invoices = $this->Form->collapseObjectArray(
            $this->Invoices->getAll($client->id),
            'id_value',
            'id'
        );

        // Get service statuses
        $status = $this->Services->getStatusTypes();
        unset($status['in_review']);

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
        if (isset($this->post['transfer']) && $this->post['transfer'] == '1') {
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
        if (is_numeric($term[0])) {
            $this->post['qty'] = $term[0];
        }

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
        $this->uses(['Domains.DomainsTlds', 'Domains.DomainsDomains']);

        // Ensure a valid client was given
        $client_id = ($this->get['client_id'] ?? ($this->get[0] ?? null));
        if (empty($client_id) || !($client = $this->Clients->get($client_id))) {
            $this->redirect($this->base_uri . 'clients/');
        }

        // Ensure a valid service was given
        $service_id = ($this->get['service_id'] ?? ($this->get[1] ?? null));
        if (empty($service_id) || !($service = $this->Services->get($service_id))) {
            $this->redirect($this->base_uri . 'clients/view/' . $client_id . '/');
        }

        $service->expiration_date = $this->DomainsDomains->getExpirationDate($service->id);
        $service->registration_date = $this->DomainsDomains->getRegistrationDate($service->id);
        $service->nameservers = $this->DomainsDomains->getNameservers($service->id);
        $vars = $service;

        // Get the domains package group
        $domains_package_group = $this->Companies->getSetting(
            Configure::get('Blesta.company_id'),
            'domains_package_group'
        );
        $domains_package_group = $domains_package_group->value ?? 0;

        // Get package from the service
        $package = $service->package ?? (object) [];
        $package->groups = $this->Form->collapseObjectArray(
            $package->groups ?? [],
            'name',
            'id'
        );

        // Validate the provided service is a domain
        if (!array_key_exists($domains_package_group, $package->groups)) {
            $this->redirect($this->base_uri . 'clients/view/' . $client_id . '/');
        }

        // Get list of registrar modules
        $modules = $this->Form->collapseObjectArray(
            $this->ModuleManager->getAll(Configure::get('Blesta.company_id'), 'name', 'asc', ['type' => 'registrar']),
            'name',
            'id'
        );

        // Get service statuses
        $statuses = $this->Services->getStatusTypes();

        // Get domain actions
        $actions = ['' => Language::_('AppController.select.please', true)];
        $actions = array_merge($actions, $this->getDomainActions());

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

        // Process edit
        if (!empty($this->post)) {
            // Set checkboxes
            $checkboxes = ['use_module'];
            foreach ($checkboxes as $checkbox) {
                if (!isset($this->post[$checkbox])) {
                    $this->post[$checkbox] = 'false';
                }
            }

            // Update package
            if (isset($this->post['module']) && $module->id !== $this->post['module'] && $service->status == 'pending') {
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
                $this->post['pricing_id'] = $pricing->id;
            }

            // Update service fields
            if (empty($this->post['action'])) {
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
                        if (str_contains($subfield->params['name'], '[')) {
                            $parts = explode('[', $subfield->params['name'], 2);
                            $subfield->params['name'] = $parts[0];
                        }

                        $allowed_fields[] = $field->params['name'];
                    }
                }

                $params = array_intersect_key($this->post, array_flip($allowed_fields));
                $this->Services->edit($service->id, $params);
                $errors = $this->Services->errors();
            }

            // Process domain actions
            if (!empty($this->post['action'])) {
                $this->post['service_ids'] = [$service->id];
                $errors = $this->updateDomains($this->post);
            }

            if (!empty($errors)) {
                $this->setMessage('error', $errors, false, null, false);
            } else {
                $this->flashMessage('message', Language::_('AdminMain.!success.service_edited', true));
                $this->redirect($this->base_uri . 'clients/view/' . $client->id);
            }

            $vars = (object) $this->post;
        }

        $this->set('client', $client);
        $this->set('service', $service);
        $this->set('package', $package);
        $this->set('modules', $modules);
        $this->set('statuses', $statuses);
        $this->set('actions', $actions);
        $this->set('module', $module);
        $this->set('fields', (new Html($fields))->generate());
        $this->set('vars', $vars);
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
        $this->outputAsJson($html);

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
        $this->outputAsJson(['pricing' => $years, 'package_id' => $package->id]);

        return false;
    }

    /**
     * Returns the widget listing the Domains for a given client
     */
    public function domains()
    {
        $this->uses(['Domains.DomainsDomains', 'ModuleManager']);

        // Process bulk domains options
        if (!empty($this->post) && isset($this->post['service_ids'])) {
            if (($errors = $this->updateDomains($this->post))) {
                $this->set('vars', (object)$this->post);
                $this->setMessage('error', $errors, false, null, false);
            } else {
                switch ($this->post['action']) {
                    case 'schedule_cancellation':
                        $term = 'AdminMain.!success.change_auto_renewal';
                        break;
                    case 'change_registrar':
                        $term = 'AdminMain.!success.domain_registrar_updated';
                        break;
                    case 'domain_renewal':
                        $term = 'AdminMain.!success.domain_renewal';
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
                }

                $this->flashMessage('message', Language::_($term, true), null, false);
                $this->redirect($this->base_uri . 'clients/view/'. ($this->get['client_id'] ?? ($this->get[0] ?? null)));
            }
        }

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

            foreach($post_filters as $filter => $value) {
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
        $modules = $this->Form->collapseObjectArray(
            $this->ModuleManager->getAll(Configure::get('Blesta.company_id'), 'name', 'asc', ['type' => 'registrar']),
            'name',
            'id'
        );

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
}
