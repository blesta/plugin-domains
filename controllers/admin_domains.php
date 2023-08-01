<?php

use Iodev\Whois\Factory;
use Blesta\Core\Util\Input\Fields\InputFields;

/**
 * Domain Manager admin_domains controller
 *
 * @link https://www.blesta.com Blesta
 */
class AdminDomains extends DomainsController
{
    /**
     * @var int The TLDs per page to show
     */
    private $tlds_per_page = 50;

    /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();

        $this->uses(['ModuleManager']);
    }

    /**
     * Returns the view for a list of extensions
     */
    public function index()
    {
        $this->redirect($this->base_uri . 'plugin/domains/admin_domains/browse/');
    }

    /**
     * Fetches the view for the domain list
     */
    public function browse()
    {
        $this->uses(['Domains.DomainsTlds', 'Domains.DomainsDomains', 'Companies', 'ModuleManager', 'Services']);

        if (!empty($this->post) && isset($this->post['service_ids'])) {
            if (($errors = $this->updateServices($this->post))) {
                $this->set('vars', (object)$this->post);
                $this->setMessage('error', $errors, false, null, false);
            } else {
                switch ($this->post['action']) {
                    case 'schedule_cancellation':
                        $term = 'AdminDomains.!success.change_auto_renewal';
                        break;
                    case 'domain_renewal':
                        $term = 'AdminDomains.!success.domain_renewal';
                        break;
                    case 'update_nameservers':
                        $term = 'AdminDomains.!success.update_nameservers';
                        break;
                    case 'push_to_client':
                        $term = 'AdminDomains.!success.domains_pushed';
                        break;
                }

                $this->setMessage('message', Language::_($term, true), false, null, false);
            }
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

        // Filter by domains type
        $domains_filters = $post_filters;
        $domains_filters['type'] = 'domains';

        $status = (isset($this->get[0]) ? $this->get[0] : 'active');
        $page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
        $sort = (isset($this->get['sort']) ? $this->get['sort'] : 'date_added');
        $order = (isset($this->get['order']) ? $this->get['order'] : 'desc');

        $alt_sort = false;
        if (in_array($sort, ['name', 'registrar', 'expiration_date', 'renewal_price'])) {
            $alt_sort = $sort;
            $sort = 'date_added';
        }

        // Get only parent services
        $domains_filters['status'] = $status;
        $services = $this->DomainsDomains->getList($domains_filters, $page, [$sort => $order]);
        $total_results = $this->DomainsDomains->getListCount($domains_filters);

        // Get TLD for each service
        foreach ($services as $service) {
            $service->tld = $this->DomainsTlds->getByPackage($service->package_id);
        }

        // Set the number of services of each type, not including children
        $status_count = [
            'active' => $this->DomainsDomains->getStatusCount('active', $domains_filters),
            'canceled' => $this->DomainsDomains->getStatusCount('canceled', $domains_filters),
            'pending' => $this->DomainsDomains->getStatusCount('pending', $domains_filters),
            'suspended' => $this->DomainsDomains->getStatusCount('suspended', $domains_filters),
            'in_review' => $this->DomainsDomains->getStatusCount('in_review', $domains_filters),
            'scheduled_cancellation' => $this->DomainsDomains->getStatusCount('scheduled_cancellation', $domains_filters)
        ];

        // Set the expected service renewal price
        $modules = [];
        foreach ($services as $service) {
            $module_id = $service->package->module_id;
            if (!isset($modules[$module_id])) {
                $modules[$module_id] = $this->ModuleManager->initModule($module_id);
            }

            $service->renewal_price = $this->Services->getRenewalPrice($service->id);
            $service->registrar = $modules[$module_id]->getName();
            $service->expiration_date = $this->DomainsDomains->getExpirationDate($service->id);
        }

        if ($alt_sort) {
            usort(
                $services,
                function ($service1, $service2) use ($alt_sort, $order) {
                    return $order == 'asc'
                        ? strcmp($service1->{$alt_sort}, $service2->{$alt_sort})
                        : strcmp($service2->{$alt_sort}, $service1->{$alt_sort});
                }
            );
            $sort = $alt_sort;
        }

        // Set the input field filters for the widget
        $this->set(
            'filters',
            $this->getFilters(
                [
                    'language' => Configure::get('Blesta.language'),
                    'company_id' => Configure::get('Blesta.company_id')
                ],
                $post_filters
            )
        );

        $this->set('filter_vars', $post_filters);
        $this->set('status', $status);
        $this->set('domains', $services);
        $this->set('status_count', $status_count);
        $this->set('actions', $this->getDomainActions());
        $this->set('widget_state', isset($this->widgets_state['services']) ? $this->widgets_state['services'] : null);
        $this->set('sort', $alt_sort ? $alt_sort : $sort);
        $this->set('order', $order);
        $this->set('negate_order', ($order == 'asc' ? 'desc' : 'asc'));

        // Overwrite default pagination settings
        $settings = array_merge(
            Configure::get('Blesta.pagination'),
            [
                'total_results' => $total_results,
                'uri' => $this->base_uri . 'plugin/domains/admin_domains/browse/' . $status . '/[p]/',
                'params' => ['sort' => $alt_sort ? $alt_sort : $sort, 'order' => $order]
            ]
        );
        $this->setPagination($this->get, $settings);

        // Render the request if ajax
        return $this->renderAjaxWidgetIfAsync(isset($this->get[1]) || isset($this->get['sort']));
    }

    /**
     * Gets a list of possible domain actions
     *
     * @return array A list of possible domain actions and their language
     */
    public function getDomainActions()
    {
        return [
            'change_auto_renewal' => Language::_('AdminDomains.browse.change_auto_renewal', true),
            'domain_renewal' => Language::_('AdminDomains.browse.domain_renewal', true),
            'update_nameservers' => Language::_('AdminDomains.browse.update_nameservers', true),
            'push_to_client' => Language::_('AdminDomains.browse.push_to_client', true)
        ];
    }

    /**
     * Updates the given services
     *
     * @param array $data An array of POST data including:
     *
     *  - service_ids An array of each service ID
     *  - action The action to perform, e.g. "schedule_cancelation"
     *  - action_type The type of action to perform, e.g. "term", "date"
     *  - date The cancel date if the action type is "date"
     * @return mixed An array of errors, or false otherwise
     */
    private function updateServices(array $data)
    {
        $this->uses(['Services', 'Domains.DomainsDomains']);

        // Require authorization to update a client's service
        if (!$this->authorized('admin_clients', 'editservice')) {
            $this->flashMessage('error', Language::_('AppController.!error.unauthorized_access', true), null, false);
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/browse/');
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
        $data['action'] = (isset($data['action']) ? $data['action'] : null);
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
            case 'domain_renewal':
                foreach ($data['service_ids'] as $service_id) {
                    $this->DomainsDomains->renewDomain($service_id, $data['years'] ?? 1);

                    if (($errors = $this->DomainsDomains->errors())) {
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
                    Loader::loadModels($this, ['Services', 'Invoices']);

                    // Get service
                    $service = $this->Services->get($service_id);
                    if (!$service) {
                        break;
                    }

                    // Move service
                    $this->Services->move($service->id, $this->post['client_id']);

                    if (($errors = $this->Services->errors())) {
                        return $errors;
                    }
                }
                break;
        }

        return $errors;
    }

    /**
     * Fetches the view for the registrar list
     */
    public function registrars()
    {
        // Get installed and available registrar modules
        $installed_registrars = $this->ModuleManager->getAll(
            Configure::get('Blesta.company_id'),
            'name',
            'asc',
            ['type' => 'registrar']
        );
        $available_registrars = $this->ModuleManager->getAvailable(Configure::get('Blesta.company_id'), 'registrar');

        // Get installed module details when available
        $registrars = [];
        foreach ($installed_registrars as $installed_registrar) {
            $registrars[$installed_registrar->class] = $installed_registrar;
        }

        // Add available registrars to the end of the list
        foreach ($available_registrars as $available_registrar) {
            if (!isset($registrars[$available_registrar->class])) {
                $registrars[$available_registrar->class] = $available_registrar;
            }
        }
        $this->set('registrars', array_values($registrars));
    }

    /**
     * Install a registrar for this company
     */
    public function installRegistrar()
    {
        if (!isset($this->post['id'])) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/registrars/');
        }

        $module_id = $this->ModuleManager->add(['class' => $this->post['id'], 'company_id' => $this->company_id]);

        if (($errors = $this->ModuleManager->errors())) {
            $this->flashMessage('error', $errors, null, false);
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/registrars/');
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.registrar_installed', true), null, false);
            $this->redirect($this->base_uri . 'settings/company/modules/manage/' . $module_id);
        }
    }

    /**
     * Uninstall a registrar for this company
     */
    public function uninstallRegistrar()
    {
        if (!isset($this->post['id']) || !($module = $this->ModuleManager->get($this->post['id']))) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/registrars/');
        }

        $this->ModuleManager->delete($this->post['id']);

        if (($errors = $this->ModuleManager->errors())) {
            $this->flashMessage('error', $errors, null, false);
        } else {
            $this->flashMessage(
                'message',
                Language::_('AdminDomains.!success.registrar_uninstalled', true),
                null,
                false
            );
        }
        $this->redirect($this->base_uri . 'plugin/domains/admin_domains/registrars/');
    }

    /**
     * Upgrade a registrar
     */
    public function upgradeRegistrar()
    {
        // Fetch the module to upgrade
        if (!isset($this->post['id']) || !($module = $this->ModuleManager->get($this->post['id']))) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/registrars/');
        }

        $this->ModuleManager->upgrade($this->post['id']);

        if (($errors = $this->ModuleManager->errors())) {
            $this->flashMessage('error', $errors, null, false);
        } else {
            $this->flashMessage(
                'message',
                Language::_('AdminDomains.!success.registrar_upgraded', true),
                null,
                false
            );
        }
        $this->redirect($this->base_uri . 'plugin/domains/admin_domains/registrars/');
    }

    /**
     * Import TLDs and their respective pricing from a registrar module
     */
    public function importTlds()
    {
        Loader::loadModels($this, ['Domains.DomainsTlds']);

        if (!empty($this->post) && $this->isAjax()) {
            set_time_limit(60*60*15); // 15 minutes

            $response = [];
            $tries = Configure::get('Blesta.transaction_deadlock_reattempts');
            do {
                $retry = false;

                try {
                    $this->DomainsTlds->import(
                        $this->post['tlds'] ?? [],
                        empty($this->post['module_id']) ? 0 : $this->post['module_id'],
                        Configure::get('Blesta.company_id'),
                        ['terms' => $this->post['terms'] ?? [1]]
                    );

                    if (($errors = $this->DomainsTlds->errors())) {
                        $response['error'] = $this->setMessage('error', $errors, true, null, false);
                    } else {
                        $response['message'] = $this->setMessage(
                            'message',
                            Language::_('AdminDomains.!success.tlds_imported', true),
                            true,
                            null,
                            false
                        );
                    }
                } catch (PDOException $e) {
                    // A deadlock occurred (PDO error 1213, SQLState 40001)
                    if ($tries > 0 && $e->getCode() == '40001' && str_contains($e->getMessage(), '1213')) {
                        $retry = true;
                    }
                } catch (Throwable $e) {
                    $response['error'] = $this->setMessage('error', $e->getMessage(), true, null, false);
                }

                $tries--;
            } while ($retry);

            $this->outputAsJson($response);

            return false;
        }
    }

    /**
     * Returns a JSON object containing the installed registrar modules
     */
    public function getImportModules()
    {
        // Check if the request was made through AJAX
        if (!$this->isAjax()) {
            header($this->server_protocol . ' 401 Unauthorized');
            exit();
        }

        // Get installed registrar modules
        $installed_registrars = $this->ModuleManager->getAll(
            Configure::get('Blesta.company_id'),
            'name',
            'asc',
            ['type' => 'registrar']
        );

        // Get installed module details when available
        $registrars = [];
        foreach ($installed_registrars as $installed_registrar) {
            $registrars[$installed_registrar->id] = $installed_registrar->name;
        }

        $this->outputAsJson($registrars);

        return false;
    }

    /**
     * Returns a JSON object containing the TLDs supported for a given module
     */
    public function getModuleTlds()
    {
        Loader::loadModels($this, ['Domains.DomainsTlds']);

        // Check if the provided module id exists
        if (!$this->isAjax() || !isset($this->get[0]) || !($module = $this->ModuleManager->get($this->get[0]))) {
            header($this->server_protocol . ' 401 Unauthorized');
            exit();
        }

        // Fetch TLD list from the module
        $response = [];
        $module_tlds = $this->ModuleManager->moduleRpc($module->id, 'getTlds');
        foreach ($module_tlds as $tld) {
            $tld = str_replace('_', '.', strtolower($tld));
            $disabled = false;

            // Check if the TLD already exists in the current company
            $local_tld = $this->DomainsTlds->get($tld, Configure::get('Blesta.company_id'));
            if ($local_tld) {
                $disabled = true;
            }

            $response['tlds'][$tld] = ['name' => $tld, 'disabled' => $disabled];
        }

        // Build TLD terms
        $response['terms'] = true;

        // Check if the module supports pricing sync
        try {
            $class_name = Loader::toCamelCase($module->class);
            $reflection = new ReflectionClass($class_name);
            if ($reflection->getMethod('getFilteredTldPricing')->class !== $class_name) {
                $response['message'] = $this->setMessage(
                    'notice',
                    Language::_('AdminDomains.!warning.price_sync_unsupported', true),
                    true,
                    null,
                    false
                );
                $response['terms'] = false;
            }
        } catch (Throwable $e) {
            $response['error'] = $this->setMessage('error', $e->getMessage(), true, null, false);
        }

        $this->outputAsJson($response);
        return false;
    }

    /**
     * Fetches the view for the whois page
     */
    public function whois()
    {
        $whois = Factory::get()->createWhois();
        if (!empty($this->post)) {
            try {
                $domain_info = [
                    'text' => $whois->lookupDomain($this->post['domain'])->text,
                    'available' => $whois->isDomainAvailable($this->post['domain'])
                ];
            } catch (Exception $e) {
                $domain_info = [
                    'text' => $e->getMessage(),
                    'available' => false
                ];
            }
        }
        $this->set('vars', $this->post);
        $this->set('domain_info', isset($domain_info) ? $domain_info : []);
    }

    /**
     * Fetches the view for the configuration page
     */
    public function configuration()
    {
        $this->uses([
            'Companies',
            'EmailGroups',
            'PackageGroups',
            'PackageOptionGroups',
            'CronTasks',
            'Domains.DomainsTlds'
        ]);

        $company_id = Configure::get('Blesta.company_id');
        $vars = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');
        $vars['domains_spotlight_tlds'] = isset($vars['domains_spotlight_tlds'])
            ? json_decode($vars['domains_spotlight_tlds'], true)
            : [];

        if (!empty($this->post)) {
            $accepted_settings = [
                'domains_spotlight_tlds',
                'domains_dns_management_option_group',
                'domains_email_forwarding_option_group',
                'domains_id_protection_option_group',
                'domains_first_reminder_days_before',
                'domains_second_reminder_days_before',
                'domains_expiration_notice_days_after',
                'domains_taxable',
                'domains_sync_price_markup',
                'domains_sync_renewal_markup',
                'domains_sync_transfer_markup',
                'domains_enable_rounding',
                'domains_markup_rounding',
                'domains_automatic_sync',
                'domains_sync_frequency',
                'domains_renewal_days_before_expiration',
                'domains_override_price'
            ];
            if (!isset($this->post['domains_spotlight_tlds'])) {
                $this->post['domains_spotlight_tlds'] = [];
            }
            if (!isset($this->post['domains_taxable'])) {
                $this->post['domains_taxable'] = '0';
            }
            if (!isset($this->post['domains_enable_rounding'])) {
                $this->post['domains_enable_rounding'] = '0';
            }
            if (!isset($this->post['domains_automatic_sync'])) {
                $this->post['domains_automatic_sync'] = '0';
            }
            if (!isset($this->post['domains_override_price'])) {
                $this->post['domains_override_price'] = '0';
            }
            $this->post['domains_spotlight_tlds'] = json_encode($this->post['domains_spotlight_tlds']);
            $this->DomainsTlds->updateDomainsCompanySettings($company_id, $this->post);

            // Update tax status if setting was changed
            if (isset($this->post['domains_taxable'])
                && $this->post['domains_taxable'] != ($vars['domains_taxable'] ?? null)
            ) {
                $this->DomainsTlds->updateTax($this->post['domains_taxable']);
            }

            // Update override price package setting if setting was changed
            if (isset($this->post['domains_override_price'])
                && $this->post['domains_override_price'] != ($vars['domains_override_price'] ?? null)
            ) {
                $this->DomainsTlds->updateOverridePriceSetting($this->post['domains_override_price']);
            }

            // Update cron task enabled
            $cron = $this->CronTasks->getTaskRunByKey('domain_tld_synchronization', 'domains');
            $this->CronTasks->editTaskRun(
                $cron->task_run_id,
                [
                    'interval' => $cron->interval,
                    'enabled' => $this->post['domains_automatic_sync']
                ]
            );

            // Set error/success messages
            if (($errors = $this->CronTasks->errors())) {
                $this->set('vars', (object) $this->post);
                $this->flashMessage('error', $errors);
            } else {
                $this->flashMessage(
                    'message',
                    Language::_('AdminDomains.!success.configuration_updated', true),
                    null,
                    false
                );
            }
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/configuration/');
        }

        $tab = $this->get['tab'] ?? 'general';
        $this->set('tabs', $this->configurationTabs($tab));
        $this->set('tab', $tab);
        $this->set('vars', $vars);
        $this->set('tlds', $this->DomainsTlds->getAll(['company_id' => $company_id]));
        $this->set(
            'package_groups',
            $this->Form->collapseObjectArray($this->PackageGroups->getAll($company_id, 'standard'), 'name', 'id')
        );
        $this->set(
            'package_option_groups',
            $this->Form->collapseObjectArray($this->PackageOptionGroups->getAll($company_id), 'name', 'id')
        );
        $this->set('renewal_days', $this->getDays(1, 90));
        $this->set('first_reminder_days', $this->getDays(26, 35));
        $this->set('second_reminder_days', $this->getDays(4, 10));
        $this->set('expiration_notice_days', $this->getDays(1, 5));
        $this->set('sync_days', $this->getDays(1, 30));
        $this->set('rounding_options', $this->getRoundingOptions());
        $this->set('first_reminder_template', $this->EmailGroups->getByAction('Domains.domain_renewal_1'));
        $this->set('second_reminder_template', $this->EmailGroups->getByAction('Domains.domain_renewal_2'));
        $this->set('expiration_notice_template', $this->EmailGroups->getByAction('Domains.domain_expiration'));
    }

    /**
     * Get a list of rounding options
     */
    private function getRoundingOptions()
    {
        return [
            '.00' => '.00', '.10' => '.10', '.20' => '.20', '.30' => '.30',
            '.40' => '.40', '.50' => '.50', '.60' => '.60', '.70' => '.70',
            '.80' => '.80', '.90' => '.90', '.95' => '.95', '.99' => '.99',
            '' => Language::_('AdminDomains.getroundingoptions.custom', true)
        ];
    }

    /**
     * Imports packages from outside the domain manager
     */
    public function importPackages()
    {
        $this->uses(['ModuleManager', 'Companies', 'Domains.DomainsTlds', 'Packages', 'PackageGroups', 'Services']);
        $company_settings = $this->DomainsTlds->getDomainsCompanySettings();

        if (!empty($this->post) || !empty($this->get)) {
            // Check if the request was made through AJAX
            if (!$this->isAjax()) {
                header($this->server_protocol . ' 401 Unauthorized');
                exit();
            }

            // Get company ID
            $company_id = Configure::get('Blesta.company_id');

            // Set whether to override current TLD packages with new cloned ones
            $overwrite_packages = ($this->post['overwrite_packages'] ?? '0') == '1';

            // Get the current TLDs
            $existing_tlds = $this->DomainsTlds->getAll(['company_id' => $company_id]);
            $existing_tld_packages = [];
            foreach ($existing_tlds as $existing_tld) {
                $existing_tld_packages[$existing_tld->tld] = $this->Form->collapseObjectArray(
                    $this->DomainsTlds->getTldPackages($existing_tld->tld),
                    'package_id',
                    'module_id'
                );
            }

            $temp = $this->SettingsCollection->fetchSetting($this->Companies, $company_id, 'uploads_dir');
            $log_file_path = $temp['value'] . $this->company_id . DS . 'domains_package_import.json';

            switch ($this->get[0] ?? '') {
                case 'list':
                    // Get TLD packages to import
                    $tlds_packages = $this->getPackageImportTlds(
                        $overwrite_packages,
                        $existing_tld_packages,
                        $company_settings
                    );

                    $tlds = array_keys($tlds_packages);
                    $this->outputAsJson($tlds);
                    return false;
                case 'import':
                    $this->Packages->begin();

                    // Remove time limit
                    set_time_limit(0);

                    file_put_contents(
                        $log_file_path,
                        json_encode(['errored' => [], 'package_created' => [], 'tld_created' => []])
                    );
                    $import_log = json_decode(file_get_contents($log_file_path), true);

                    // Set whether to migrate services from old packages to the new TLD packages
                    $migrate_services = ($this->post['migrate_services'] ?? '0') == '1';

                    // Get TLD packages to import
                    $tlds_packages = $this->getPackageImportTlds(
                        $overwrite_packages,
                        $existing_tld_packages,
                        $company_settings
                    );

                    // Keep track of which tlds have already been imported
                    $imported_tld_packages = [];

                    $errors = null;

                    // Attempt to import the TLDs from each package
                    foreach ($tlds_packages as $tld => $tld_packages) {
                        foreach ($tld_packages as $package) {
                            $this->importTld(
                                $package,
                                $tld,
                                $imported_tld_packages,
                                $existing_tld_packages,
                                $company_settings,
                                $overwrite_packages,
                                $migrate_services
                            );

                            if (($errors = $this->Packages->errors())) {
                                $import_log['errored'][$tld] = $tld;
                                file_put_contents($log_file_path, json_encode($import_log));
                                break 2;
                            }
                        }
                        $import_log['package_created'][$tld] = $tld;
                        file_put_contents($log_file_path, json_encode($import_log));
                    }

                    if ($errors) {
                        // Set error message
                        $this->Packages->rollback();
                        $this->outputAsJson([
                            'error' => $this->setMessage(
                                'error',
                                $errors,
                                true,
                                null,
                                false
                            )
                        ]);

                        return false;
                    } else {
                        // Create the TLDs
                        foreach ($imported_tld_packages as $tld => $module_packages) {
                            // Sort the imported packages by the number of migrated services
                            uasort(
                                $module_packages,
                                function ($a, $b) {
                                    $swap = $a['migrated_services'] < $b['migrated_services'];
                                    return $swap ? 1 : -1;
                                }
                            );

                            // The first package in the imported list should be marked as the primary for the
                            // TLD if the TLD is new or the setting to override package is enabled
                            $set_primary_package = $overwrite_packages
                                || !array_key_exists($tld, $existing_tld_packages);
                            foreach ($module_packages as $module_id => $package_info) {
                                $package_id = $package_info['package_id'];
                                // If the TLD for this package was deleted, create it again
                                if (!array_key_exists($tld, $existing_tld_packages)) {
                                    // Add the TLD
                                    $tld_vars = [
                                        'tld' => $tld,
                                        'package_id' => $package_id,
                                        'module_id' => $module_id
                                    ];
                                    $this->DomainsTlds->add($tld_vars);
                                    $existing_tld_packages[$tld] = [$module_id => $package_id];
                                } else {
                                    // The TLD already exists so just add this package as another
                                    // package on the TLD instead of the primary
                                    $this->DomainsTlds->addPackage(['tld' => $tld, 'package_id' => $package_id]);
                                }

                                // Report errors
                                if (($errors = $this->DomainsTlds->errors())) {
                                    break 2;
                                }

                                // Mark this package as the primary for this TLD or mark it as inactive
                                if ($set_primary_package) {
                                    $this->DomainsTlds->edit($tld, ['package_id' => $package_id]);
                                    $set_primary_package = false;
                                } else {
                                    $this->Packages->edit(
                                        $package_id,
                                        ['status' => 'inactive', 'meta' => (array)$package_info['meta']]
                                    );
                                }

                                // Report errors
                                if (($errors = $this->Packages->errors()) || ($errors = $this->DomainsTlds->errors())) {
                                    break 2;
                                }
                            }
                            unset($import_log['package_created'][$tld]);
                            $import_log['tld_created'][$tld] = $tld;
                            file_put_contents($log_file_path, json_encode($import_log));
                        }

                        if ($errors) {
                            // Set error message
                            $this->Packages->rollback();
                            $this->outputAsJson([
                                'error' => $this->setMessage(
                                    'error',
                                    $errors,
                                    true,
                                    null,
                                    false
                                )
                            ]);

                            return false;
                        } else {
                            // Set success message
                            $this->outputAsJson([
                                'message' => $this->setMessage(
                                    'message',
                                    Language::_('AdminDomains.!success.packages_imported', true),
                                    true,
                                    null,
                                    false
                                )
                            ]);
                            $this->Packages->commit();

                            return false;
                        }
                    }
                case 'check':
                    $import_log = json_decode(file_get_contents($log_file_path), true);

                    // Keep track of the status of the TLDs that are being imported
                    $tlds = [];
                    foreach ($import_log['errored'] as $tld) {
                        $tlds[$tld] = 'error';
                    }

                    foreach ($import_log['package_created'] as $tld) {
                        $tlds[$tld] = 'current';
                    }

                    foreach ($import_log['tld_created'] as $tld) {
                        $tlds[$tld] = 'success';
                    }

                    $this->outputAsJson($tlds);
                    return false;
            }
        }

        $package_group = $this->PackageGroups->get($company_settings['domains_package_group']);
        $this->setMessage(
            'notice',
            Language::_('AdminDomains.importpackages.order_form', true, $package_group->name),
            false,
            null,
            false
        );
        $this->set('tabs', $this->configurationTabs('importpackages', false));
        $this->set('vars', ($vars ?? []));
    }

    /**
     * Get all the TLDs/packages to import as new Domain Manager TLD packages
     *
     * @param bool $overwrite_packages True to delete existing TLD packages in favor of those being imported,
     * @param array $existing_tld_packages A list TLDs and modules that existed before the import began
     *  - [tld => [module_id => package_id]]
     * @param array $company_settings A list of company settings ([key => value])
     *  false to keep the existing TLD packages and prevent the new ones from being created
     *
     */
    function getPackageImportTlds($overwrite_packages, $existing_tld_packages, $company_settings)
    {
        // Get company ID
        $company_id = Configure::get('Blesta.company_id');

        // Keep track of the TLDs that will be imported
        $tlds = [];

        // Get all the registrar modules
        $installed_registrars = $this->ModuleManager->getAll($company_id, 'name', 'asc', ['type' => 'registrar']);
        foreach ($installed_registrars as $installed_registrar) {
            // Get all packages for the registrar module
            $packages = $this->Packages->getAll(
                $company_id,
                ['name' => 'ASC'],
                'active',
                null,
                ['module_id' => $installed_registrar->id]
            );

            // Get the tlds from the package
            foreach ($packages as $package_tld) {
                $package = $this->Packages->get($package_tld->id);

                // Check if the package is from the Domain Manager package group
                $from_domain_manager = false;
                foreach ($package->groups as $group) {
                    if (isset($company_settings['domains_package_group'])
                        && $group->id == $company_settings['domains_package_group']
                    ) {
                        $from_domain_manager = true;
                        break;
                    }
                }

                // Check if the package has a yearly pricing
                $has_yearly_pricing = false;
                foreach ($package->pricing as $pricing) {
                    if ($pricing->period == 'year') {
                        $has_yearly_pricing = true;
                    }
                }

                // Skip the package if it has no assigned TLDs, is from the Domain Manager,
                // or doesn't have any year(s) pricing terms
                if (empty($package->meta->tlds) || $from_domain_manager || !$has_yearly_pricing) {
                    continue;
                }

                $package_tlds = array_fill_keys((array) $package->meta->tlds, [$package]);

                foreach ($package_tlds as $tld => $packages) {
                    foreach($packages as $package) {
                        // Skip TLD/module pairs that are already in the domain manager
                        // unless the option was selected to overwite it
                        if (!$overwrite_packages
                            && array_key_exists($tld, $existing_tld_packages)
                            && array_key_exists($package->module_id, $existing_tld_packages[$tld])
                        ) {
                            unset($package_tlds[$tld]);
                        }
                    }
                }

                $tlds = array_merge($package_tlds, $tlds);
            }
        }

        return $tlds;
    }

    /**
     * Imports all the TLDs from a package as new Domain Manager TLD packages
     *
     * @param stdClass $package The package from which to import the TLD
     * @param string $tld The TLD to assign to the new package
     * @param array $imported_tld_packages A list keeping track of package details for TLDs and modules
     *  that have already been imported
     *  - [tld => [module_id => ['package_id' => x, 'migrated_services' => x, 'meta' => x]]]
     * @param array $existing_tld_packages A list TLDs and modules that existed before the import began
     *  - [tld => [module_id => package_id]]
     * @param array $company_settings A list of company settings ([key => value])
     * @param bool $overwrite_packages True to delete existing TLD packages in favor of those being imported,
     *  false to keep the existing TLD packages and prevent the new ones from being created
     * @param bool $migrate_services True to migrate services from the old packages to the newly created
     *  ones, false otherwise
     */
    private function importTld(
        $package,
        $tld,
        array &$imported_tld_packages,
        array &$existing_tld_packages,
        array $company_settings,
        $overwrite_packages,
        $migrate_services
    ) {
        // Skip this package/TLD if a package with the same module_id has already been imported
        if (array_key_exists($tld, $imported_tld_packages)
            && array_key_exists($package->module_id, $imported_tld_packages[$tld])
        ) {
            return;
        }

        if (array_key_exists($tld, $existing_tld_packages)
            && array_key_exists($package->module_id, $existing_tld_packages[$tld])
        ) {
            // A package exists for this TLD and module, so skip it unless the option was selected to overwite it
            if (!$overwrite_packages) {
                return;
            }

            // If set to override packages, delete the existing package for this TLD/module
            $this->Packages->delete($existing_tld_packages[$tld][$package->module_id]);
            if (($errors = $this->Packages->errors())) {
                return;
            }

            // Remove this package/module/tld from the list of existing ones
            unset($existing_tld_packages[$tld][$package->module_id]);
            if (empty($existing_tld_packages[$tld])) {
                unset($existing_tld_packages[$tld]);
            }
        }

        // Clone the package
        $package_id = $this->clonePackage($package, $tld, $company_settings);
        if ($package_id) {
            // Migrate the services from the cloned package to the new one if they match the TLD
            $migrated_services = 0;
            if ($migrate_services) {
                $migrated_services = $this->migrateServices($package->id, $package_id, $tld);
            }

            $imported_tld_packages[$tld][$package->module_id] = [
                'package_id' => $package_id,
                'migrated_services' => $migrated_services,
                'meta' => $package->meta
            ];

            // Deactivate cloned packages that no longer have services assigned
            $remaining_services = $this->Services->getAll(
                ['date_added' => 'DESC'],
                true,
                ['package_id' => $package->id, 'status' => 'all']
            );
            if (empty($remaining_services)) {
                $this->Packages->edit($package->id, ['status' => 'inactive', 'meta' => (array)$package->meta]);
            }
        }
    }

    /**
     * Formats details from a package to
     *
     * @param stdClass $package The package to clone
     * @param string The TLD that should be assigned to the new package
     * @param array $company_settings An array of company settings
     * @return int The ID of the new package
     */
    private function clonePackage(stdClass $package, $tld, array $company_settings)
    {
        $package_vars = [
            'pricing' => [], 'groups' => [], 'plugins' => [], 'option_groups' => [],
            'names' => [], 'descriptions' => [], 'email_content' => []
        ];

        // Clone the simple package fields
        $clone_fields = [
            'module_id', 'qty', 'client_qty', 'module_row', 'module_group',
            'taxable', 'single_term', 'status', 'company_id', 'prorata_day', 'prorata_cutoff',

        ];
        foreach ($clone_fields as $clone_field) {
            $package_vars[$clone_field] = $package->{$clone_field};
        }

        // Parse name details
        foreach ($package->names as $name) {
            $name->name = $tld;
            $package_vars['names'][] = (array)$name;
        }

        // Parse description details
        foreach ($package->descriptions as $description) {
            $package_vars['descriptions'][] = [
                'lang' => is_scalar($description->lang) ? $description->lang : 'en_us',
                'html' => is_scalar($description->html) ? $description->html : '',
                'text' => is_scalar($description->text) ? $description->text : '',
            ];
        }

        // Parse email content details
        if (empty($package->email_content)) {
            $package->email_content = [(object)[
                'lang' => 'en_us',
                'html' => '',
                'text' => '',
            ]];
        }

        foreach ($package->email_content as $email) {
            $package_vars['email_content'][] = [
                'lang' => is_scalar($email->lang) ? $email->lang : 'en_us',
                'html' => is_scalar($email->html) ? $email->html : '',
                'text' => is_scalar($email->text) ? $email->text : '',
            ];
        }

        // Parse pricing details
        foreach ($package->pricing as $pricing) {
            if ($pricing->period == 'year') {
                unset($pricing->id, $pricing->pricing_id, $pricing->package_id);
                $package_vars['pricing'][] = (array)$pricing;
            }
        }

        // Parse plugin details
        foreach ($package->plugins as $plugin) {
            $package_vars['plugins'][] = $plugin->plugin_id;
        }

        // Parse option group details
        foreach ($package->option_groups as $option_group) {
            $package_vars['option_groups'][] = $option_group->id;
        }

        // Assign the tld
        $package_vars['meta'] = (array)$package->meta;
        $package_vars['meta']['tlds'] = [$tld];
        // Assign the domain manager package group
        $package_vars['groups'] = isset($company_settings['domains_package_group'])
            ? [$company_settings['domains_package_group']]
            : [];
        // Mark the package group as hidden
        $package_vars['hidden'] = '1';

        // Add the package
        $package_id = $this->Packages->add($package_vars);

        return $package_id;
    }

    /**
     * Migrates services from one package to another based on the TLD
     *
     * @param int $from_package_id The package from which to migrate services
     * @param int $to_package_id The package to which services should be migrated
     * @param string $tld The TLD on which to base migrations
     * @return int The number of migrated services
     */
    private function migrateServices($from_package_id, $to_package_id, $tld)
    {
        $this->uses(['Services']);
        $services = $this->Services->getAll(
            ['date_added' => 'DESC'],
            true,
            ['package_id' => $from_package_id, 'status' => 'all']
        );

        $to_package = $this->Packages->get($to_package_id);
        $package_group_id = $to_package->groups[0]->id;
        $migrated_services = 0;
        foreach ($services as $service) {
            // Find the correct pricing to which to move
            $from_pricing = $this->Services->getPackagePricing($service->pricing_id);
            $pricing_id = null;
            foreach ($to_package->pricing as $pricing) {
                if ($pricing->term == $from_pricing->term
                    && $pricing->period == $from_pricing->period
                    && $pricing->currency == $from_pricing->currency
                ) {
                    $pricing_id = $pricing->id;
                    break;
                } elseif ($pricing->term == '1'
                    && $pricing->period == 'year'
                    && $pricing->currency == $from_pricing->currency
                ) {
                    $pricing_id = $pricing->id;
                }
            }

            // Don't migrate the service if there is no matching pricing
            if (!$pricing_id) {
                break;
            }

            if (str_ends_with(strtolower($service->name), $tld)) {
                $migrated_services++;
                $this->Services->edit(
                    $service->id,
                    ['pricing_id' => $pricing_id, 'package_group_id' => $package_group_id],
                    true
                );
            }
        }

        return $migrated_services;
    }

    /**
     * Manages the pricing of the TLDs configurable options
     */
    public function configurableOptions()
    {
        $this->uses(['Domains.DomainsTlds', 'PackageOptions', 'PackageOptionGroups']);

        // Get plugin company settings
        $plugin_settings = $this->DomainsTlds->getDomainsCompanySettings();

        // Get configurable options
        $tld_features = $this->DomainsTlds->getFeatures();
        $configurable_options = [];

        foreach ($tld_features as $feature) {
            if (isset($plugin_settings['domains_' . $feature . '_option_group'])) {
                $option_group_id = $plugin_settings['domains_' . $feature . '_option_group'];
                $option_group = $this->PackageOptionGroups->getAllOptions($option_group_id);

                foreach ($option_group as $option) {
                    $configurable_options[] = $this->PackageOptions->get($option->id);
                }
            }
        }

        $this->set('tabs', $this->configurationTabs('configurableoptions', false));
        $this->set('configurable_options', $configurable_options);
    }

    /**
     * Update configurable option pricing
     */
    public function configurableOptionsPricing()
    {
        $this->uses(['PackageOptions', 'Companies', 'Currencies']);

        // Fetch the configurable option
        if (
            !$this->isAjax()
            || !isset($this->get[0])
            || !($configurable_option = $this->PackageOptions->get($this->get[0]))
        ) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/configurableoptions/');
        }

        // Get company settings
        $company_id = Configure::get('Blesta.company_id');
        $company_settings = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');

        // Get company default currency
        $default_currency = isset($company_settings['default_currency']) ? $company_settings['default_currency'] : 'USD';

        // Get company currencies
        $currencies = $this->Currencies->getAll($company_id);

        foreach ($currencies as $key => $currency) {
            $currencies[$currency->code] = $currency;
            unset($currencies[$key]);
        }

        if (isset($currencies[$default_currency])) {
            $currencies = [$default_currency => $currencies[$default_currency]] + $currencies;
        }

        // Get configurable option pricing
        try {
            // Add a pricing for terms 1-10 years for each currency
            foreach ($currencies as $currency) {
                for ($i = 1; $i <= 10; $i++) {
                    foreach ($configurable_option->values as &$value) {
                        // Check if the term already exists
                        $exists_pricing = false;
                        foreach ($value->pricing as &$pricing) {
                            if ($pricing->term == $i && $pricing->period == 'year' && $pricing->currency == $currency->code) {
                                $exists_pricing = true;
                            }
                        }

                        // If the pricing not exists, add a placeholder for that pricing
                        if (!$exists_pricing) {
                            $value->pricing[] = (object)[
                                'term' => $i,
                                'period' => 'year',
                                'currency' => $currency->code
                            ];
                        }
                    }
                }
            }

            // Order pricing rows
            foreach ($configurable_option->values as &$value) {
                usort($value->pricing, function ($a, $b) {
                    if ($a->term == $b->term) {
                        return 0;
                    }

                    if ($a->term < $b->term) {
                        return -1;
                    }

                    return 1;
                });
            }
        } catch (Throwable $e) {
            echo $this->setMessage(
                'error',
                ['exception' => [$e->getMessage()]],
                true,
                ['show_close' => false],
                false
            );

            return false;
        }

        echo $this->partial(
            'admin_domains_configurableoptions_pricing',
            compact(
                'configurable_option',
                'currencies',
                'default_currency'
            )
        );

        return false;
    }

    /**
     * Updates the pricing of a configurable option
     */
    public function updateConfigurableOption()
    {
        $this->uses(['PackageOptions', 'Currencies']);
        $this->components(['Record']);

        // Fetch the configurable option
        if (!isset($this->get[0])
            || !($option = $this->PackageOptions->get($this->get[0]))
        ) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/configurableoptions/');
        }

        if (!empty($this->post['pricing'])) {
            // Build values array
            $value_vars = [];
            foreach ($this->post['pricing'] as $value_id => $pricing) {
                // Get value
                $value = $this->Record->select(['package_option_values.*', 'package_options.type' => 'option_type'])
                    ->from('package_option_values')
                    ->innerJoin('package_options', 'package_options.id', '=', 'package_option_values.option_id', false)
                    ->where('package_option_values.id', '=', $value_id)
                    ->fetch();

                if (empty($value)) {
                    $this->flashMessage(
                        'error',
                        Language::_('AdminDomains.!error.value_id_invalid', true),
                        null,
                        false
                    );

                    continue;
                }

                // Build value pricing array
                $value_pricing = [];
                foreach ($pricing as $term => $item) {
                    foreach ($item as $currency => $price) {
                        // Check if already exists a pricing for the same combination of term, period and currency
                        $pricing_row_match = $this->Record->select('package_option_pricing.*')
                            ->from('pricings')
                            ->innerJoin('package_option_pricing', 'package_option_pricing.pricing_id', '=', 'pricings.id', false)
                            ->innerJoin('package_option_values', 'package_option_values.id', '=', 'package_option_pricing.option_value_id', false)
                            ->where('package_option_values.id', '=', $value_id)
                            ->where('pricings.period', '=', 'year')
                            ->where('pricings.term', '=', $term)
                            ->where('pricings.currency', '=', $currency)
                            ->fetch();

                        // Build pricing row array
                        $pricing_row = [
                            'term' => $term,
                            'period' => 'year',
                            'currency' => $currency,
                            'price' => $price['price'],
                            'price_renews' => $price['price_renews']
                        ];

                        if (isset($pricing_row_match->id)) {
                            $pricing_row['id'] = $pricing_row_match->id;
                        }

                        $value_pricing[] = $pricing_row;
                    }
                }

                // Build value var
                $value_vars[] = [
                    'id' => $value->id,
                    'name' => $value->name,
                    'value' => $value->value,
                    'default' => $value->default,
                    'status' => $value->status,
                    'min' => $value->min,
                    'max' => $value->max,
                    'step' => $value->step,
                    'pricing' => $value_pricing
                ];
            }

            // Build option vars
            $option = $this->PackageOptions->get($this->get[0]);
            $option_vars = [
                'label' => $option->label,
                'name' => $option->name,
                'type' => $option->type,
                'addable' => $option->addable,
                'editable' => $option->editable,
                'values' => $value_vars,
                'groups' => [],
                'hidden' => 1
            ];

            foreach ($option->groups as $group) {
                $option_vars['groups'][] = $group->id;
            }

            // Get company currencies
            $company_id = Configure::get('Blesta.company_id');
            $currencies = $this->Currencies->getAll($company_id);

            // Remove disabled prices
            foreach ($currencies as $currency) {
                for ($i = 1; $i <= 10; $i++) {
                    foreach ($this->post['pricing'] as $value_id => $pricing) {
                        if (!isset($this->post['pricing'][$value_id][$i][$currency->code])) {
                            // The pricing has been marked as disabled, check if the pricing exists on
                            // the database and if exists, remove it
                            $pricing = $this->Record->select('package_option_pricing.*')
                                ->from('pricings')
                                ->innerJoin('package_option_pricing', 'package_option_pricing.pricing_id', '=', 'pricings.id', false)
                                ->innerJoin('package_option_values', 'package_option_values.id', '=', 'package_option_pricing.option_value_id', false)
                                ->where('package_option_values.id', '=', $value_id)
                                ->where('pricings.period', '=', 'year')
                                ->where('pricings.term', '=', $i)
                                ->where('pricings.currency', '=', $currency->code)
                                ->fetch();

                            if (!empty($pricing)) {
                                $this->Record->from('package_option_pricing')->
                                    leftJoin('pricings', 'pricings.id', '=', 'package_option_pricing.pricing_id', false)->
                                    where('package_option_pricing.id', '=', $pricing->id)->
                                    delete(['package_option_pricing.*', 'pricings.*']);
                            }
                        }
                    }
                }
            }

            // Update configurable option
            $this->PackageOptions->edit($this->get[0], $option_vars);

            if (($errors = $this->PackageOptions->errors())) {
                $this->flashMessage('error', $errors, null, false);
            } else {
                $this->flashMessage(
                    'message',
                    Language::_('AdminDomains.!success.configurable_option_updated', true),
                    null,
                    false
                );
            }
        }

        $this->redirect($this->base_uri . 'plugin/domains/admin_domains/configurableoptions/');
    }

    /**
     * Get a list of the tabs for the configuration view
     *
     * @param string $tab The URN of the current tab
     * @param bool $ajax True if the tabs are called from an AJAX-enabled view
     * @return array An array containing the tabs for the configuration view
     */
    private function configurationTabs($tab = 'general', $ajax = true)
    {
        return [
            [
                'name' => Language::_('AdminDomains.configuration.tab_general', true),
                'current' => (($tab ?? 'general') == 'general'),
                'attributes' => [
                    'class' => 'general',
                    'href' => $ajax ? '#' : $this->Html->safe($this->base_uri . 'plugin/domains/admin_domains/configuration/?tab=general'),
                    'id' => 'general_tab'
                ]
            ],
            [
                'name' => Language::_('AdminDomains.configuration.tab_notifications', true),
                'current' => (($tab ?? 'general') == 'notifications'),
                'attributes' => [
                    'class' => 'notifications',
                    'href' => $ajax ? '#' : $this->Html->safe($this->base_uri . 'plugin/domains/admin_domains/configuration/?tab=notifications'),
                    'id' => 'notifications_tab'
                ]
            ],
            [
                'name' => Language::_('AdminDomains.configuration.tab_advanced', true),
                'current' => (($tab ?? 'general') == 'advanced'),
                'attributes' => [
                    'class' => 'advanced',
                    'href' => $ajax ? '#' : $this->Html->safe($this->base_uri . 'plugin/domains/admin_domains/configuration/?tab=advanced'),
                    'id' => 'advanced_tab'
                ]
            ],
            [
                'name' => Language::_('AdminDomains.configuration.tab_tld_sync', true),
                'current' => (($tab ?? 'general') == 'tld_sync'),
                'attributes' => [
                    'class' => 'tld_sync',
                    'href' => $ajax ? '#' : $this->Html->safe($this->base_uri . 'plugin/domains/admin_domains/configuration/?tab=tld_sync'),
                    'id' => 'tld_sync_tab'
                ]
            ],
            [
                'name' => Language::_('AdminDomains.configuration.tab_importpackages', true),
                'current' => (($tab ?? 'general') == 'importpackages'),
                'attributes' => [
                    'class' => 'importpackages',
                    'href' => $this->Html->safe($this->base_uri . 'plugin/domains/admin_domains/importpackages/'),
                    'id' => 'importpackages_tab'
                ]
            ],
            [
                'name' => Language::_('AdminDomains.configuration.tab_configurableoptions', true),
                'current' => (($tab ?? 'general') == 'configurableoptions'),
                'attributes' => [
                    'class' => 'configurableoptions',
                    'href' => $this->Html->safe($this->base_uri . 'plugin/domains/admin_domains/configurableoptions/'),
                    'id' => 'configurableoptions_tab'
                ]
            ]
        ];
    }

    /**
     * Fetch a range of # of days and their language
     *
     * @param int $min_days The lower bound of the day range
     * @param int $max_days The upper bound of the day range
     * @return array A list of days and their language
     */
    private function getDays($min_days, $max_days)
    {
        $days = [
            '' => Language::_('AdminDomains.getDays.same_day', true)
        ];
        for ($i = $min_days; $i <= $max_days; $i++) {
            $days[$i] = Language::_(
                'AdminDomains.getDays.text_day'
                . (
                $i === 1
                    ? ''
                    : 's'
                ),
                true,
                $i
            );
        }
        return $days;
    }

    /**
     * Fetches the view for all TLDs and their pricing
     */
    public function tlds()
    {
        $this->uses(['ModuleManager', 'Packages', 'Domains.DomainsTlds']);
        $this->helpers(['Form', 'Widget']);

        $company_id = Configure::get('Blesta.company_id');
        $page = (isset($this->get[0]) ? (int) $this->get[0] : 1);
        $this->tlds_per_page = !empty($this->post['filters']['limit']) ? $this->post['filters']['limit'] : $this->tlds_per_page;

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

        // Add new TLD
        if (!empty($this->post['add_tld'])) {
            $vars = $this->post['add_tld'];

            // Set checkboxes
            if (empty($vars['dns_management'])) {
                $vars['dns_management'] = '0';
            }
            if (empty($vars['email_forwarding'])) {
                $vars['email_forwarding'] = '0';
            }
            if (empty($vars['id_protection'])) {
                $vars['id_protection'] = '0';
            }
            if (empty($vars['epp_code'])) {
                $vars['epp_code'] = '0';
            }

            $params = [
                'tld' => '.' . trim($vars['tld'], '.'),
                'company_id' => $company_id,
            ];
            $params = array_merge($vars, $params);

            if (!empty($vars['module'])) {
                $params['module_id'] = (int)$vars['module'];
            }

            $this->DomainsTlds->add($params);

            // Try to automatically update the package meta
            $update_meta = false;
            $query_string = '';
            if (!($errors = $this->DomainsTlds->errors())) {
                [$errors, $update_meta] = $this->autoUpdateTldMeta($params['tld']);
            }

            if (!empty($errors)) {
                $this->flashMessage('error', $errors);
            } else {
                $this->flashMessage('message', Language::_('AdminDomains.!success.tld_added', true));
                $query_string = $update_meta ? '?added_tld=' . $params['tld'] : '';
            }

            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/' . $query_string);
        }

        // Process TLD bulk actions
        if (!empty($this->post['tlds_bulk'])) {
            $bulk_data = $this->post['tlds_bulk'];
            $action = $bulk_data['action'] ?? null;

            if (!array_key_exists($action, $this->getTldActions())) {
                $this->flashMessage('error', Language::_('AdminDomains.!error.tlds_bulk[action].valid', true));
            } else if (!empty($bulk_data['tlds']) && is_array($bulk_data['tlds'])) {
                switch ($action) {
                    case 'change_status':
                        $status = $bulk_data['status'] ?? null;
                        foreach ($bulk_data['tlds'] as $tld) {
                            if ($status == 'enabled') {
                                $this->DomainsTlds->enable($tld);
                            } elseif ($status == 'disabled') {
                                $this->DomainsTlds->disable($tld);
                            }
                        }

                        $this->flashMessage('message', Language::_('AdminDomains.!success.change_status', true));
                        break;
                    case 'tld_sync':
                        Loader::load(dirname(__FILE__) . DS . '..' . DS . 'lib' . DS . 'tld_sync.php');
                        $sync_utility = new TldSync();
                        $sync_utility->synchronizePrices($bulk_data['tlds']);

                        $this->flashMessage('message', Language::_('AdminDomains.!success.tld_sync', true));
                        break;
                }
            }

            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
        }

        // Fetch all the TLDs and their pricing for this company
        $this->DomainsTlds->setPerPage($this->tlds_per_page);
        $tlds = $this->DomainsTlds->getList(
            array_merge($post_filters, ['company_id' => $company_id]),
            $page
        );
        $total_results = $this->DomainsTlds->getListCount(array_merge($post_filters, ['company_id' => $company_id]));

        foreach ($tlds as $key => $tld) {
            $package = $this->Packages->get($tld->package_id);
            $module = $this->ModuleManager->get($package->module_id);

            $tlds[$key]->package = $package;
            $tlds[$key]->module = $module;
        }

        // Fetch all modules for this company
        $modules = $this->ModuleManager->getAll(
            $company_id,
            'name',
            'asc',
            ['type' => 'registrar']
        );
        $select = ['' => Language::_('AppController.select.please', true)];
        $modules = $select + $this->Form->collapseObjectArray($modules, 'name', 'id');

        // Overwrite default pagination settings
        $settings = array_merge(
            Configure::get('Blesta.pagination'),
            [
                'total_results' => $total_results,
                'results_per_page' => $this->tlds_per_page,
                'uri' => $this->base_uri . 'plugin/domains/admin_domains/tlds/[p]/'
            ]
        );
        $this->setPagination($this->get, $settings);

        $this->set('page', $page ?? null);
        $this->set('added_tld', $this->get['added_tld'] ?? null);
        $this->set('tlds', $tlds);
        $this->set('modules', $modules);
        $this->set('tld_actions', $this->getTldActions());
        $this->set('tld_statuses', $this->getTldStatuses());

        // Set the input field filters for the widget
        $filters = $this->getTldFilters(
            [
                'language' => Configure::get('Blesta.language'),
                'company_id' => Configure::get('Blesta.company_id')
            ],
            $post_filters
        );
        $this->set('filters', $filters);
        $this->set('filter_vars', $post_filters);

        // Include WYSIWYG
        $this->Javascript->setFile('blesta/ckeditor/build/ckeditor.js', 'head', VENDORWEBDIR);

        return $this->renderAjaxWidgetIfAsync($this->isAjax());
    }

    /**
     * Gets a list of the available bulk actions for TLDs
     *
     * @return array An array containing the available bulk actions for TLDs
     */
    private function getTldActions()
    {
        return [
            'change_status' => Language::_('AdminDomains.getTldActions.option_change_status', true),
            'tld_sync' => Language::_('AdminDomains.getTldActions.option_tld_sync', true)
        ];
    }

    /**
     * Gets a list of the available bulk actions for TLDs
     *
     * @return array An array containing the available bulk actions for TLDs
     */
    private function getTldStatuses()
    {
        return [
            'enabled' => Language::_('AdminDomains.getTldStatuses.option_enabled', true),
            'disabled' => Language::_('AdminDomains.getTldStatuses.option_disabled', true)
        ];
    }

    /**
     * Disables a TLD for this company
     */
    public function disableTld()
    {
        $this->uses(['Packages', 'Domains.DomainsTlds']);

        // Fetch the package belonging to this TLD
        if (
            !isset($this->post['id'])
            || !($package = $this->Packages->get($this->post['id']))
            || !($tld = $this->DomainsTlds->getByPackage($this->post['id']))
        ) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
        }

        $this->DomainsTlds->disable($tld->tld);

        if (($errors = $this->DomainsTlds->errors())) {
            $this->flashMessage('error', $errors);
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.tld_disabled', true));
        }
        $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
    }

    /**
     * Enables a TLD for this company
     */
    public function enableTld()
    {
        $this->uses(['Packages', 'Domains.DomainsTlds']);

        // Fetch the package belonging to this TLD
        if (
            !isset($this->post['id'])
            || !($package = $this->Packages->get($this->post['id']))
            || !($tld = $this->DomainsTlds->getByPackage($this->post['id']))
        ) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
        }

        $this->DomainsTlds->enable($tld->tld);

        if (($errors = $this->DomainsTlds->errors())) {
            $this->flashMessage('error', $errors);
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.tld_enabled', true));
        }
        $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
    }

    /**
     * Sort TLDs
     */
    public function sortTlds()
    {
        $this->uses(['Domains.DomainsTlds']);

        if (!$this->isAjax()) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
        }

        $page = (isset($this->get[0]) ? (int)$this->get[0] : 1);

        if (!empty($this->post['tlds'])) {
            $tlds = [];
            foreach ($this->post['tlds'] as $order => $package_id) {
                $tlds[$order + (($page - 1) * $this->tlds_per_page)] = $package_id;
            }

            $this->DomainsTlds->sort($tlds, $this->company_id);
        }

        return false;
    }

    /**
     * Update TLD
     */
    public function updateTlds()
    {
        $this->uses(['Domains.DomainsTlds', 'Packages']);

        if (!$this->isAjax()) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
        }

        $updated_tld = str_replace('_', '.', $this->get[0] ?? null);

        if (!empty($this->post)) {
            $error = null;
            $update_meta = false;

            foreach ($this->post['tlds'] as $tld => $vars) {
                if ($updated_tld && $tld != $updated_tld) {
                    continue;
                }

                // Set checkboxes
                if (empty($vars['dns_management'])) {
                    $vars['dns_management'] = '0';
                }
                if (empty($vars['email_forwarding'])) {
                    $vars['email_forwarding'] = '0';
                }
                if (empty($vars['id_protection'])) {
                    $vars['id_protection'] = '0';
                }
                if (empty($vars['epp_code'])) {
                    $vars['epp_code'] = '0';
                }

                // Check if the module has been updated and is required to update the package meta
                if (!is_null($updated_tld)) {
                    $tld = str_replace('_', '.', strtolower($tld));
                    $tld_obj = $this->DomainsTlds->get($tld);
                    $package = $this->Packages->get($tld_obj->package_id);

                    if (isset($vars['module']) && $vars['module'] !== $package->module_id) {
                        $update_meta = true;
                    }
                }

                // Update TLD
                $vars = array_merge($vars, [
                    'module_id' => $vars['module'],
                ]);
                $this->DomainsTlds->edit($tld, $vars);

                if (($errors = $this->DomainsTlds->errors())) {
                    $error = $errors;
                }

                // Try to automatically update the package meta
                if ($update_meta && empty($errors)) {
                    [$error, $update_meta] = $this->autoUpdateTldMeta($tld);
                }
            }
        }

        if (!empty($error)) {
            echo json_encode([
                'message' => $this->setMessage(
                    'error',
                    $error,
                    true,
                    null,
                    false
                ),
                'update_meta' => false
            ]);
        } else {
            echo json_encode([
                'message' => $this->setMessage(
                    'message',
                    Language::_('AdminDomains.!success.tld_updated', true),
                    true,
                    null,
                    false
                ),
                'update_meta' => $update_meta
            ]);
        }

        return false;
    }

    /**
     * Attempt to automatically update the TLD package meta
     *
     * @param string $tld The TLD for which to try setting meta data
     * @return array|null A list of errors from the update attempt
     */
    private function autoUpdateTldMeta($tld)
    {
        $error = null;
        $update_meta = true;
        $tld = str_replace('_', '.', strtolower($tld));
        $tld_obj = $this->DomainsTlds->get($tld);
        $package_fields = $this->DomainsTlds->getTldFields($tld_obj->package_id);

        $vars = [];
        // Automatically select the first available module row group
        if (isset($package_fields['groups'])) {
            if (empty($package_fields['groups'])) {
                $vars['module_group'] = '';
            } else {
                $vars['module_group'] = array_key_first($package_fields['groups']);
            }
        }

        // Automatically select the first available module row
        if (isset($package_fields['rows'])) {
            $vars['module_row'] = array_key_first($package_fields['rows']);
        }

        // Automatically select the first item from select fields with only one option
        if (is_array($package_fields['fields'])) {
            foreach ($package_fields['fields'] as $key => $field) {
                if ($field->type == 'fieldSelect') {
                    if (isset($field->params['options']) && count($field->params['options']) == 1) {
                        if (substr($field->params['name'], 0, 5) == 'meta[') {
                            $vars['meta'][substr($field->params['name'], 5, -1)]
                                = array_key_first($field->params['options']);
                        } else {
                            $vars[$field->params['name']] = array_key_first($field->params['options']);
                        }
                    }
                }

                // Automatically select the first item from select sub-fields with only one option
                if (!empty($field->fields)) {
                    foreach ($field->fields as $sub_key => $sub_field) {
                        if ($sub_field->type == 'fieldSelect') {
                            if (isset($sub_field->params['options']) && count($sub_field->params['options']) == 1) {
                                if (substr($sub_field->params['name'], 0, 5) == 'meta[') {
                                    $vars['meta'][substr($sub_field->params['name'], 5, -1)]
                                        = array_key_first($sub_field->params['options']);
                                } else {
                                    $vars[$sub_field->params['name']] = array_key_first($sub_field->params['options']);
                                }

                                unset($package_fields['fields'][$key]->fields[$sub_key]);
                            }
                        }
                    }
                }

                if (empty($package_fields['fields'][$key]->fields)) {
                    unset($package_fields['fields'][$key]);
                }
            }
        }

        // Don't show the update meta modal box if all fields were automatically updated
        if (empty($package_fields['fields'])) {
            $this->DomainsTlds->edit($tld_obj->tld, $vars);

            $update_meta = false;
            if (($errors = $this->DomainsTlds->errors())) {
                $update_meta = true;
                $error = $errors;
            }
        }

        return [$error, $update_meta];
    }

    /**
     * Update TLD pricing
     */
    public function pricing()
    {
        $this->uses(['Packages', 'Currencies', 'Languages', 'Domains.DomainsTlds']);
        $this->helpers(['Form', 'CurrencyFormat']);

        // Fetch the package belonging to this TLD
        if (
            !$this->isAjax()
            || !isset($this->get[0])
            || !($package = $this->Packages->get($this->get[0]))
            || !($tld = $this->DomainsTlds->getByPackage($this->get[0]))
        ) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
        }

        // Get company settings
        $company_settings = $this->Form->collapseObjectArray(
            $this->Companies->getSettings(Configure::get('Blesta.company_id')),
            'value',
            'key'
        );

        // Get company default currency
        $default_currency = isset($company_settings['default_currency']) ? $company_settings['default_currency'] : 'USD';

        // Get company currencies
        $currencies = $this->Currencies->getAll(Configure::get('Blesta.company_id'));

        foreach ($currencies as $key => $currency) {
            $currencies[$currency->code] = $currency;
            unset($currencies[$key]);
        }

        if (isset($currencies[$default_currency])) {
            $currencies = [$default_currency => $currencies[$default_currency]] + $currencies;
        }

        // Get company languages
        $languages = $this->Languages->getAll(Configure::get('Blesta.company_id'));

        // Updates pricing
        if (!empty($this->post)) {
            $tld = $this->DomainsTlds->getByPackage($this->get[0]);

            // Update TLD package
            $this->DomainsTlds->edit($tld->tld, $this->post);

            // Set empty checkboxes
            for ($i = 1; $i <= 10; $i++) {
                foreach ($currencies as $code => $currency) {
                    if (!isset($this->post['pricing'][$i][$code]['enabled'])) {
                        $this->post['pricing'][$i][$code]['enabled'] = 0;
                    }
                }
            }

            // Update pricing
            if (!isset($this->post['pricing'])) {
                $this->post['pricing'] = [];
            }
            $this->DomainsTlds->updatePricings($tld->tld, $this->post['pricing']);

            if (($errors = $this->DomainsTlds->errors())) {
                echo json_encode([
                    'message' => $this->setMessage(
                        'error',
                        $errors,
                        true,
                        null,
                        false
                    )
                ]);
            } else {
                $tld->message = $this->setMessage(
                    'message',
                    Language::_('AdminDomains.!success.tld_updated', true),
                    true,
                    null,
                    false
                );
                echo json_encode($tld);
            }

            return false;
        }

        // Get TLD package
        $package = $this->Packages->get($this->get[0], true);
        $tld = $this->DomainsTlds->getByPackage($this->get[0]);

        try {
            // Get TLD package fields
            $package_fields = $this->DomainsTlds->getTldFields($this->get[0]);
            $package_fields_view = 'admin' . DS . 'default';
            $tld_pricings = [];

            // Add a pricing for terms 1-10 years for each currency
            foreach ($currencies as $currency) {
                for ($i = 1; $i <= 10; $i++) {
                    // Check if the term already exists
                    $exists_pricing = false;
                    foreach ($package->pricing as $pricing) {
                        if ($pricing->term == $i && $pricing->period == 'year' && $pricing->currency == $currency->code) {
                            $exists_pricing = true;
                            $pricing->enabled = true;
                            $tld_pricings[] = $pricing;
                            break;
                        }
                    }

                    // If the term not exists, add a placeholder for that term
                    if (!$exists_pricing) {
                        $tld_pricings[] = (object)[
                            'term' => $i,
                            'period' => 'year',
                            'currency' => $currency->code,
                            'enabled' => false
                        ];
                    }
                }
            }

            $package->pricing = $tld_pricings;
        } catch (Throwable $e) {
            echo $this->setMessage(
                'error',
                ['exception' => [$e->getMessage()]],
                true,
                ['show_close' => false],
                false
            );

            return false;
        }

        // Check what non-default currencies doesn't have any price set
        foreach ($currencies as $code => $currency) {
            foreach ($package->pricing as $pricing) {
                if ($pricing->currency == $code) {
                    if (!isset($currencies[$code]->automatic_currency_conversion)) {
                        $currencies[$code]->automatic_currency_conversion = ($code !== $default_currency);
                    }

                    if (
                        (
                            isset($pricing->price)
                            && isset($pricing->price_renews)
                            && $pricing->price > 0
                            && $pricing->price_renews > 0
                            && $currencies[$code]->automatic_currency_conversion
                        ) || $currency->exchange_rate == 0
                    ) {
                        $currencies[$code]->automatic_currency_conversion = false;
                    }
                }
            }
        }

        echo $this->partial(
            'admin_domains_pricing',
            compact(
                'package',
                'package_fields',
                'package_fields_view',
                'tld',
                'currencies',
                'default_currency',
                'languages'
            )
        );

        return false;
    }

    /**
     * Update TLD package meta
     */
    public function meta()
    {
        $this->uses(['Domains.DomainsTlds']);
        $this->helpers(['Form', 'CurrencyFormat']);

        // Fetch the package belonging to this TLD
        $meta_tld = str_replace('_', '.', strtolower($this->get[0] ?? null));
        if (
            !$this->isAjax()
            || !isset($this->get[0])
            || !($tld = $this->DomainsTlds->get($meta_tld))
        ) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
        }

        if (!empty($this->post)) {
            // Update TLD package
            $this->DomainsTlds->edit($tld->tld, $this->post);

            if (($errors = $this->DomainsTlds->errors())) {
                echo json_encode([
                    'message' => $this->setMessage(
                        'error',
                        $errors,
                        true,
                        ['show_close' => false],
                        false
                    )
                ]);
            } else {
                $tld->message = $this->setMessage(
                    'message',
                    Language::_('AdminDomains.!success.tld_updated', true),
                    true,
                    ['show_close' => false],
                    false
                );
                echo json_encode($tld);
            }

            return false;
        }

        // Get TLD package fields
        $package_fields = $this->DomainsTlds->getTldFields($tld->package_id);
        $package_fields_view = 'admin' . DS . 'default';

        // Return partial view
        echo $this->partial(
            'admin_domains_meta',
            compact(
                'package_fields',
                'package_fields_view',
                'tld'
            )
        );

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
        Loader::loadComponents($this, ['Record']);
        Loader::loadModels($this, ['ModuleManager']);
        Loader::loadHelpers($this, ['Form']);

        $fields = new InputFields();

        // Set module ID filter
        $modules = $this->Form->collapseObjectArray(
            $this->ModuleManager->getAll($options['company_id'], 'name', 'asc', ['type' => 'registrar']),
            'name',
            'id'
        );

        $module = $fields->label(
            Language::_('AdminDomains.getfilters.field_module_id', true),
            'module_id'
        );
        $module->attach(
            $fields->fieldSelect(
                'filters[module_id]',
                ['' => Language::_('AdminDomains.getfilters.any', true)] + $modules,
                isset($vars['module_id']) ? $vars['module_id'] : null,
                ['id' => 'module_id', 'class' => 'form-control']
            )
        );
        $fields->setField($module);

        // Set the package name filter
        $package_name = $fields->label(
            Language::_('AdminDomains.getfilters.field_package_name', true),
            'package_name'
        );
        $package_name->attach(
            $fields->fieldText(
                'filters[package_name]',
                isset($vars['package_name']) ? $vars['package_name'] : null,
                [
                    'id' => 'package_name',
                    'class' => 'form-control stretch',
                    'placeholder' => Language::_('AdminDomains.getfilters.field_package_name', true)
                ]
            )
        );
        $fields->setField($package_name);

        // Set the service meta filter
        $service_meta = $fields->label(
            Language::_('AdminDomains.getfilters.field_service_meta', true),
            'service_meta'
        );
        $service_meta->attach(
            $fields->fieldText(
                'filters[service_meta]',
                isset($vars['service_meta']) ? $vars['service_meta'] : null,
                [
                    'id' => 'service_meta',
                    'class' => 'form-control stretch',
                    'placeholder' => Language::_('AdminDomains.getfilters.field_service_meta', true)
                ]
            )
        );
        $fields->setField($service_meta);

        return $fields;
    }

    /**
     * Gets a list of input fields for filtering TLDs
     *
     * @param array $options A list of options for building the filters including:
     *  - language The language for filter labels and tooltips
     *  - company_id The company ID to filter modules on
     * @param array $vars A list of submitted inputs that act as defaults for filter fields including:
     *  - module_id The module ID on which to filter packages
     *  - tld The (partial) name of the TLD to filter
     * @return InputFields An object representing the list of filter input field
     */
    private function getTldFilters(array $options, array $vars = [])
    {
        //Loader::loadComponents($this, ['Record']);
        Loader::loadModels($this, ['ModuleManager']);
        Loader::loadHelpers($this, ['Form']);

        $fields = new InputFields();

        // Set the TLD filter
        $tld = $fields->label(
            Language::_('AdminDomains.gettldfilters.field_search_tld', true),
            'search_tld'
        );
        $tld->attach(
            $fields->fieldText(
                'filters[search_tld]',
                $vars['search_tld'] ?? null,
                [
                    'id' => 'search_tld',
                    'class' => 'form-control stretch',
                    'placeholder' => Language::_('AdminDomains.gettldfilters.field_search_tld', true)
                ]
            )
        );
        $fields->setField($tld);

        // Set module ID filter
        $modules = $this->Form->collapseObjectArray(
            $this->ModuleManager->getAll($options['company_id'], 'name', 'asc', ['type' => 'registrar']),
            'name',
            'id'
        );

        $module = $fields->label(
            Language::_('AdminDomains.gettldfilters.field_module_id', true),
            'module_id'
        );
        $module->attach(
            $fields->fieldSelect(
                'filters[module_id]',
                ['' => Language::_('AdminDomains.gettldfilters.any', true)] + $modules,
                $vars['module_id'] ?? null,
                ['id' => 'module_id', 'class' => 'form-control stretch']
            )
        );
        $fields->setField($module);

        // Set the Limit filter
        $limit = $fields->label(
            Language::_('AdminDomains.gettldfilters.field_limit', true),
            'limit'
        );
        $limit->attach(
            $fields->fieldNumber(
                'filters[limit]',
                $vars['limit'] ?? $this->tlds_per_page,
                1,
                null,
                null,
                [
                    'id' => 'limit',
                    'class' => 'form-control stretch',
                    'placeholder' => Language::_('AdminDomains.gettldfilters.field_limit', true)
                ]
            )
        );
        $fields->setField($limit);

        return $fields;
    }
}
