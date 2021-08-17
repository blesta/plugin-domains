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
     * Setup
     */
    public function preAction()
    {
        parent::preAction();

        $this->uses(['ModuleManager']);
        $this->structure->set('page_title', Language::_('AdminDomains.index.page_title', true));
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
        $this->uses(['Domains.DomainsTlds', 'Companies', 'ModuleManager', 'Services']);

        if (!empty($this->post) && isset($this->post['service_ids'])) {
            if (($errors = $this->updateServices($this->post))) {
                $this->set('vars', (object)$this->post);
                $this->setMessage('error', $errors, false, null, false);
            } else {
                $term = 'AdminDomains.!success.';
                $term .= isset($this->post['action']) ? $this->post['action'] : '';
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
        $service_filters = $post_filters;

        $package_group_id = $this->Companies->getSetting(
            Configure::get('Blesta.company_id'),
            'domains_package_group'
        );
        $service_filters['package_group_id'] = $package_group_id ? $package_group_id->value : null;

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
        $services = $this->Services->getList(null, $status, $page, [$sort => $order], false, $service_filters);
        $total_results = $this->Services->getListCount(null, $status, false, null, $service_filters);

        // Get TLD for each service
        foreach ($services as $service) {
            $service->tld = $this->DomainsTlds->getByPackage($service->package_id);
        }

        // Set the number of services of each type, not including children
        $status_count = [
            'active' => $this->Services->getStatusCount(null, 'active', false, $service_filters),
            'canceled' => $this->Services->getStatusCount(null, 'canceled', false, $service_filters),
            'pending' => $this->Services->getStatusCount(null, 'pending', false, $service_filters),
            'suspended' => $this->Services->getStatusCount(null, 'suspended', false, $service_filters),
            'in_review' => $this->Services->getStatusCount(null, 'in_review', false, $service_filters),
            'scheduled_cancellation' => $this->Services->getStatusCount(
                null,
                'scheduled_cancellation',
                false,
                $service_filters
            ),
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
        return ['change_auto_renewal' => Language::_('AdminDomains.getdomainactions.change_auto_renewal', true)];
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
        $this->uses(
            ['Companies', 'EmailGroups', 'PackageGroups', 'PackageOptionGroups', 'Domains.DomainsTlds']
        );
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
                'domains_epp_code_option_group',
                'domains_first_reminder_days_before',
                'domains_second_reminder_days_before',
                'domains_expiration_notice_days_after',
                'domains_taxable'
            ];
            if (!isset($this->post['domains_spotlight_tlds'])) {
                $this->post['domains_spotlight_tlds'] = [];
            }
            if (!isset($this->post['domains_taxable'])) {
                $this->post['domains_taxable'] = '0';
            }
            $this->post['domains_spotlight_tlds'] = json_encode($this->post['domains_spotlight_tlds']);
            $this->Companies->setSettings(
                $company_id,
                array_intersect_key($this->post, array_flip($accepted_settings))
            );

            // Update tax status
            if (isset($this->post['domains_taxable'])) {
                $this->DomainsTlds->updateTax($this->post['domains_taxable']);
            }

            $this->flashMessage(
                'message',
                Language::_('AdminDomains.!success.configuration_updated', true),
                null,
                false
            );
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/configuration/');
        }

        $this->set('tab', isset($this->get['tab']) ? $this->get['tab'] : 'general');
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
        $this->set('first_reminder_days', $this->getDays(26, 35));
        $this->set('second_reminder_days', $this->getDays(4, 10));
        $this->set('expiration_notice_days', $this->getDays(1, 5));
        $this->set('first_reminder_template', $this->EmailGroups->getByAction('Domains.domain_renewal_1'));
        $this->set('second_reminder_template', $this->EmailGroups->getByAction('Domains.domain_renewal_2'));
        $this->set('expiration_notice_template', $this->EmailGroups->getByAction('Domains.domain_expiration'));
    }

    /**
     * Imports packages from outside the domain manager
     */
    public function importPackages()
    {
        $this->uses(['ModuleManager', 'Companies', 'Domains.DomainsTlds', 'Packages', 'PackageGroups', 'Services']);

        // Get company settings
        $company_id = Configure::get('Blesta.company_id');
        $company_settings = $this->Form->collapseObjectArray(
            $this->Companies->getSettings($company_id),
            'value',
            'key'
        );
        if (!empty($this->post)) {
            $this->Packages->begin();

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

            // Keep track of which tlds have already been imported
            $imported_tld_packages = [];

            // Set whether to override current TLD packages with new cloned ones
            $overwrite_packages = isset($this->post['overwrite_packages']);
            // Set whether to migrate services from old packages to the new TLD packages
            $migrate_services = isset($this->post['migrate_services']);

            // Get all the registrar modules
            $installed_registrars = $this->ModuleManager->getAll(
                Configure::get('Blesta.company_id'),
                'name',
                'asc',
                ['type' => 'registrar']
            );
            $errors = null;
            foreach ($installed_registrars as $installed_registrar) {
                // Get all packages for the registrar module
                $packages = $this->Packages->getAll(
                    Configure::get('Blesta.company_id'),
                    ['name' => 'ASC'],
                    'active',
                    null,
                    ['module_id' => $installed_registrar->id]
                );

                // Attempt to import the TLDs from each package
                foreach ($packages as $package) {
                    $this->importPackage(
                        $package->id,
                        $imported_tld_packages,
                        $existing_tld_packages,
                        $company_settings,
                        $overwrite_packages,
                        $migrate_services
                    );

                    if (($errors = $this->Packages->errors())) {
                        break 2;
                    }
                }
            }

            if ($errors) {
                $this->Packages->rollback();
                $this->setMessage(
                    'error',
                    $errors,
                    false,
                    null,
                    false
                );
            } else {
                // Create the TLDs
                foreach ($imported_tld_packages as $tld => $module_packages) {
                    foreach ($module_packages as $module_id => $package_id) {
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
                            // The TLD already exists so just add this package as another package on the TLD instead of
                            // the primary
                            $this->DomainsTlds->addPackage(['tld' => $tld, 'package_id' => $package_id]);
                        }
                    }
                }

                // Set success message
                $this->setMessage(
                    'message',
                    Language::_('AdminDomains.!success.packages_imported', true),
                    false,
                    null,
                    false
                );
                $this->Packages->commit();
            }
            $vars = $this->post;
        }

        $package_group = $this->PackageGroups->get($company_settings['domains_package_group']);
        $this->setMessage(
            'notice',
            Language::_('AdminDomains.importpackages.order_form', true, $package_group->name ?? ''),
            false,
            null,
            false
        );
        $this->set('vars', ($vars ?? []));
    }

    /**
     * Imports all the TLDs from a package as new Domain Manager TLD packages
     *
     * @param int $package_id The ID of the package from which to import TLDs
     * @param array $imported_tld_packages A list keeping tract of which TLDs and modules have already been imported
     *  ([tld => [module_id => package_id]])
     * @param array $existing_tld_packages A list TLDs and modules that existed before the import began
     *  ([tld => [module_id => package_id]])
     * @param array $company_settings A list of company settings ([key => value])
     * @param bool $overwrite_packages True to delete existing TLD packages in favor of those being imported,
     *  false to keep the existing TLD packages and prevent the new ones from being created
     * @param bool $migrate_services True to migrate services from the old packages to the newly created
     *  ones, false otherwise
     */
    private function importPackage(
        $package_id,
        array &$imported_tld_packages,
        array &$existing_tld_packages,
        array $company_settings,
        $overwrite_packages,
        $migrate_services
    ) {
        $package = $this->Packages->get($package_id);

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
            return;
        }

        // Clone the package once for each assigned TLD
        foreach ($package->meta->tlds as $tld) {
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
                return;
            }
        }
    }

    /**
     * Imports all the TLDs from a package as new Domain Manager TLD packages
     *
     * @param stdClass $package The package from which to import the TLD
     * @param string $tld The TLD to assign to the new package
     * @param array $imported_tld_packages A list keeping tract of which TLDs and modules have already been imported
     *  ([tld => [module_id => package_id]])
     * @param array $existing_tld_packages A list TLDs and modules that existed before the import began
     *  ([tld => [module_id => package_id]])
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


        // If set to override packages, delete the existing package for this TLD/module
        if (array_key_exists($tld, $existing_tld_packages)
            && array_key_exists($package->module_id, $existing_tld_packages[$tld])
            && $overwrite_packages
        ) {
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
            $imported_tld_packages[$tld][$package->module_id] = $package_id;

            // Migrate the services from the cloned package to the new one if they match the TLD
            if ($migrate_services) {
                $this->migrateServices($package->id, $package_id, $tld);
            }

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
            $package_vars['descriptions'][] = (array)$description;
        }

        // Parse description details
        foreach ($package->email_content as $email) {
            $package_vars['email_content'][] = (array)$email;
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
                $this->Services->edit(
                    $service->id,
                    ['pricing_id' => $pricing_id, 'package_group_id' => $package_group_id],
                    true
                );
            }
        }
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
            '' => Language::_('AdminDomains.getDays.never', true)
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
        $this->helpers(['Form']);

        $company_id = Configure::get('Blesta.company_id');

        // Add new TLD
        if (!empty($this->post)) {
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

            $query_string = '';
            if (($errors = $this->DomainsTlds->errors())) {
                $this->flashMessage('error', $errors);
            } else {
                $this->flashMessage('message', Language::_('AdminDomains.!success.tld_added', true));
                $query_string = '?added_tld=' . $params['tld'];
            }

            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/' . $query_string);
        }

        // Fetch all the TLDs and their pricing for this company
        $tlds = $this->DomainsTlds->getAll(['company_id' => $company_id]);

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

        $this->set('added_tld', $this->get['added_tld'] ?? null);
        $this->set('tlds', $tlds);
        $this->set('modules', $modules);

        // Include WYSIWYG
        $this->Javascript->setFile('blesta/ckeditor/build/ckeditor.js', 'head', VENDORWEBDIR);

        return $this->renderAjaxWidgetIfAsync($this->isAjax());
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

        if (!empty($this->post)) {
            $this->DomainsTlds->sort($this->post['tlds']);
        }

        return false;
    }

    /**
     * Update TLD
     */
    public function updateTlds()
    {
        $this->uses(['Domains.DomainsTlds']);

        if (!$this->isAjax()) {
            $this->redirect($this->base_uri . 'plugin/domains/admin_domains/tlds/');
        }

        $updated_tld = $this->get[0] ?? null;

        if (!empty($this->post)) {
            $error = null;
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

                // Update TLD
                $vars = array_merge($vars, [
                    'module_id' => $vars['module'],
                ]);
                $this->DomainsTlds->edit($tld, $vars);

                if (($errors = $this->DomainsTlds->errors())) {
                    $error = $errors;
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
                )
            ]);
        } else {
            echo json_encode([
                'message' => $this->setMessage(
                    'message',
                    Language::_('AdminDomains.!success.tld_updated', true),
                    true,
                    null,
                    false
                )
            ]);
        }

        return false;
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

        if (!empty($this->post)) {
            $tld = $this->DomainsTlds->getByPackage($this->get[0]);

            // Update TLD package
            $this->DomainsTlds->edit($tld->tld, $this->post);

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

        // Get company languages
        $languages = $this->Languages->getAll($company_id);

        // Get TLD package
        $package = $this->Packages->get($this->get[0], true);
        $tld = $this->DomainsTlds->getByPackage($this->get[0]);

        try {
            // Get TLD package fields
            $package_fields = $this->DomainsTlds->getTldFields($this->get[0]);
            $package_fields_view = 'admin' . DS . 'default';

            // Add a pricing for terms 1-10 years for each currency
            foreach ($currencies as $currency) {
                for ($i = 1; $i <= 10; $i++) {
                    // Check if the term already exists
                    $exists_pricing = false;
                    foreach ($package->pricing as &$pricing) {
                        if ($pricing->term == $i && $pricing->period == 'year' && $pricing->currency == $currency->code) {
                            $exists_pricing = true;
                            $pricing->enabled = true;
                        }
                    }

                    // If the term not exists, add a placeholder for that term
                    if (!$exists_pricing) {
                        $package->pricing[] = (object)[
                            'term' => $i,
                            'period' => 'year',
                            'currency' => $currency->code,
                            'enabled' => false
                        ];
                    }
                }
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
        if (
            !$this->isAjax()
            || !isset($this->get[0])
            || !($tld = $this->DomainsTlds->get($this->get[0]))
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
}
