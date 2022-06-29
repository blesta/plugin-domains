<?php

use Blesta\Core\Util\Common\Traits\Container;

/**
 * Domain Manager plugin handler
 *
 * @link https://www.blesta.com Blesta
 */
class DomainsPlugin extends Plugin
{
    // Load traits
    use Container;

    /**
     * @var Monolog\Logger An instance of the logger
     */
    protected $logger;

    public function __construct()
    {
        // Load components required by this plugin
        Loader::loadComponents($this, ['Input', 'Record']);

        Language::loadLang('domains_plugin', null, dirname(__FILE__) . DS . 'language' . DS);
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Initialize logger
        $logger = $this->getFromContainer('logger');
        $this->logger = $logger;
    }

    /**
     * Performs any necessary bootstraping actions
     *
     * @param int $plugin_id The ID of the plugin being installed
     */
    public function install($plugin_id)
    {
        Loader::loadModels(
            $this,
            ['Companies', 'Currencies', 'EmailGroups', 'Emails', 'Languages', 'PluginManager', 'DataFeeds']
        );

        Configure::load('domains', dirname(__FILE__) . DS . 'config' . DS);

        try {
            // domains_tlds
            $this->Record
                ->setField('id', ['type' => 'int', 'size' => 10, 'unsigned' => true, 'auto_increment' => true])
                ->setField('tld', ['type' => 'VARCHAR', 'size' => "64"])
                ->setField('company_id', ['type' => 'INT', 'size' => "10", 'unsigned' => true])
                ->setField('package_id', ['type' => 'INT', 'size' => "10", 'unsigned' => true, 'is_null' => true])
                ->setKey(['id'], 'primary')
                ->setKey(['tld', 'company_id'], 'unique')
                ->create('domains_tlds', true);

            // domains_packages
            $this->Record
                ->setField('id', ['type' => 'int', 'size' => 10, 'unsigned' => true, 'auto_increment' => true])
                ->setField('tld_id', ['type' => 'INT', 'size' => "10", 'unsigned' => true])
                ->setField('package_id', ['type' => 'INT', 'size' => "10", 'unsigned' => true])
                ->setKey(['id'], 'primary')
                ->setKey(['tld_id', 'package_id'], 'unique')
                ->create('domains_packages', true);

            $this->createDomainsDomainsTable();
        } catch (Exception $e) {
            // Error adding... no permission?
            $this->Input->setErrors(['db' => ['create' => $e->getMessage()]]);
            return;
        }
        $plugin = $this->PluginManager->get($plugin_id);

        // Add cron tasks for this plugin
        $this->addCronTasks($this->getCronTasks());

        $company_id = $plugin ? $plugin->company_id : Configure::get('Blesta.company_id');
        $languages = $this->Languages->getAll($company_id);
        $currencies = $this->Currencies->getAll($company_id);

        // Add the TLD package group
        $package_group_id = $this->addTldPackageGroup($company_id, $languages);

        // Add a package for each default TLD and add the TLD to the database
        $this->addTldPackages($company_id, $package_group_id);

        // Add a config option and option group for each TLD addon
        $this->addTldAddonConfigOptions($company_id, $currencies);

        // Set the default days to before renewal to send the first reminder
        if (!($setting = $this->Companies->getSetting($company_id, 'domains_first_reminder_days_before'))) {
            $this->Companies->setSetting($company_id, 'domains_first_reminder_days_before', 35);
        }
        // Set the default days to before renewal to send the second reminder
        if (!($setting = $this->Companies->getSetting($company_id, 'domains_second_reminder_days_before'))) {
            $this->Companies->setSetting($company_id, 'domains_second_reminder_days_before', 10);
        }
        // Set the default days to before renewal to send the expiration notice
        if (!($setting = $this->Companies->getSetting($company_id, 'domains_expiration_notice_days_after'))) {
            $this->Companies->setSetting($company_id, 'domains_expiration_notice_days_after', 1);
        }

        // Add all email templates
        $emails = Configure::get('Domains.install.emails');
        foreach ($emails as $email) {
            $group = $this->EmailGroups->getByAction($email['action']);
            if ($group) {
                $group_id = $group->id;
            } else {
                $group_id = $this->EmailGroups->add([
                    'action' => $email['action'],
                    'type' => $email['type'],
                    'plugin_dir' => $email['plugin_dir'],
                    'tags' => $email['tags']
                ]);
            }

            // Set from hostname to use that which is configured for the company
            if (isset(Configure::get('Blesta.company')->hostname)) {
                $email['from'] = str_replace(
                    '@mydomain.com',
                    '@' . Configure::get('Blesta.company')->hostname,
                    $email['from']
                );
            }

            // Add the email template for each language
            foreach ($languages as $language) {
                $this->Emails->add([
                    'email_group_id' => $group_id,
                    'company_id' => Configure::get('Blesta.company_id'),
                    'lang' => $language->code,
                    'from' => $email['from'],
                    'from_name' => $email['from_name'],
                    'subject' => $email['subject'],
                    'text' => $email['text'],
                    'html' => $email['html']
                ]);
            }
        }

        $this->upgrade1_5_0();

        // Set the default renewal days before expiration
        if (!($setting = $this->Companies->getSetting($company_id, 'domains_renewal_days_before_expiration'))) {
            $this->Companies->setSetting($company_id, 'domains_renewal_days_before_expiration', 30);
        }
    }

    /**
     * Performs migration of data from $current_version (the current installed version)
     * to the given file set version
     *
     * @param string $current_version The current installed version of this plugin
     * @param int $plugin_id The ID of the plugin being upgraded
     */
    public function upgrade($current_version, $plugin_id)
    {
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }

        // Upgrade if possible
        if (version_compare($this->getVersion(), $current_version, '>')) {
            // Handle the upgrade, set errors using $this->Input->setErrors() if any errors encountered

            // Upgrade to 1.1.0
            if (version_compare($current_version, '1.1.0', '<')) {
                $this->upgrade1_1_0();
            }

            // Upgrade to 1.3.0
            if (version_compare($current_version, '1.3.0', '<')) {
                $this->upgrade1_3_0();
            }

            // Upgrade to 1.4.0
            if (version_compare($current_version, '1.4.0', '<')) {
                $this->upgrade1_4_0();
            }

            // Upgrade to 1.5.0
            if (version_compare($current_version, '1.5.0', '<')) {
                $this->upgrade1_5_0();
            }

            // Upgrade to 1.6.0
            if (version_compare($current_version, '1.6.0', '<')) {
                $this->upgrade1_6_0();
            }

            // Upgrade to 1.6.1
            if (version_compare($current_version, '1.6.1', '<')) {
                $this->upgrade1_6_1();
            }
        }
    }

    /**
     * Update to v1.1.0
     */
    private function upgrade1_1_0()
    {
        if (!isset($this->Form)) {
            Loader::loadHelpers($this, ['Form']);
        }

        Loader::loadModels($this, ['Companies', 'Packages']);

        // Update domains tlds table
        $this->Record->query(
            'ALTER TABLE domains_tlds DROP PRIMARY KEY, ADD id INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,'
                . ' ADD UNIQUE `tld`(`tld`, `company_id`)'
        );

        $companies = $this->Companies->getAll();

        // Shift the TLD order from the domains_tlds table to the package_group table
        foreach ($companies as $company) {
            if (($setting = $this->Companies->getSetting($company->id, 'domains_package_group'))) {
                $tlds = $this->Record->select()->
                    from('domains_tlds')->
                    where('company_id', '=', $company->id)->
                    fetchAll();

                usort(
                    $tlds,
                    function ($tld1, $tld2) {
                        if ($tld1->order == $tld2->order) {
                            return 0;
                        }
                        return ($tld1->order < $tld2->order) ? -1 : 1;
                    }
                );

                $package_ids = array_values($this->Form->collapseObjectArray($tlds, 'package_id', 'id'));

                $this->Packages->orderPackages($setting->value, $package_ids);
            }

            // Put the Domain Manager nav items in the appropriate spot
            $this->reorderNavigationItems($company->id);
        }
    }

    /**
     * Update to v1.3.0
     */
    private function upgrade1_3_0()
    {
        Loader::loadModels($this, ['CronTasks', 'Companies', 'PackageOptions', 'PackageOptionGroups']);

        // Add new cron task to automatically synchronize TLDs
        $cron_tasks = $this->getCronTasks();
        $task = null;
        foreach ($cron_tasks as $task) {
            if ($task['key'] == 'domain_tld_synchronization') {
                break;
            }
        }

        if ($task) {
            $this->addCronTasks([$task]);
        }

        // Remove the epp_code config option groups
        $companies = $this->Companies->getAll();
        foreach ($companies as $company) {
            $setting = $this->Companies->getSetting($company->id, 'domains_epp_code_option_group');
            if ($setting) {
                // Delete options
                $package_options = $this->PackageOptionGroups->getAllOptions($setting->value);
                foreach ($package_options ?? [] as $package_option) {
                    $this->PackageOptions->delete($package_option->id);
                }

                // Delete option group
                $this->PackageOptionGroups->delete($setting->value);
                $this->Companies->unsetSetting($company->id, $setting->key);
            }
        }
    }

    /**
     * Update to v1.4.0
     */
    private function upgrade1_4_0()
    {
        Loader::loadModels($this, ['Companies', 'PackageOptionGroups']);
        Loader::loadComponents($this, ['Record']);

        $companies = $this->Companies->getAll();
        foreach ($companies as $company) {
            if (($setting = $this->Companies->getSetting($company->id, 'domains_dns_management_option_group'))) {
                // Hide package option group
                $this->Record->where('id', '=', $setting->value)
                    ->update('package_option_groups', ['hidden' => true]);

                // Hide package options belonging to the group
                $package_options = $this->PackageOptionGroups->getAllOptions($setting->value, ['hidden' => true]);
                foreach ($package_options as $package_option) {
                    $this->Record->where('id', '=', $package_option->id)
                        ->update('package_options', ['hidden' => true]);
                }
            }

            if (($setting = $this->Companies->getSetting($company->id, 'domains_email_forwarding_option_group'))) {
                // Hide package option group
                $this->Record->where('id', '=', $setting->value)
                    ->update('package_option_groups', ['hidden' => true]);

                // Hide package options belonging to the group
                $package_options = $this->PackageOptionGroups->getAllOptions($setting->value, ['hidden' => true]);
                foreach ($package_options as $package_option) {
                    $this->Record->where('id', '=', $package_option->id)
                        ->update('package_options', ['hidden' => true]);
                }
            }

            if (($setting = $this->Companies->getSetting($company->id, 'domains_id_protection_option_group'))) {
                // Hide package option group
                $this->Record->where('id', '=', $setting->value)
                    ->update('package_option_groups', ['hidden' => true]);

                // Hide package options belonging to the group
                $package_options = $this->PackageOptionGroups->getAllOptions($setting->value, ['hidden' => true]);
                foreach ($package_options as $package_option) {
                    $this->Record->where('id', '=', $package_option->id)
                        ->update('package_options', ['hidden' => true]);
                }
            }
        }
    }

    /**
     * Update to v1.5.0
     */
    private function upgrade1_5_0()
    {
        Loader::loadModels($this, ['Companies', 'DataFeeds']);

        // Add data feed
        try {
            $this->DataFeeds->add([
                'feed' => 'domain',
                'dir' => 'domains',
                'class' => '\\DomainsFeed'
            ]);

            // Add data feed endpoint to all companies
            $companies = $this->Companies->getAll();
            foreach ($companies as $company) {
                $this->DataFeeds->addEndpoint([
                    'company_id' => $company->id,
                    'feed' => 'domain',
                    'endpoint' => 'pricing',
                    'enabled' => 0
                ]);
            }
        } catch (Throwable $e) {
            // Nothing to do
        }
    }

    /**
     * Update to v1.6.0
     */
    private function upgrade1_6_0()
    {
        Loader::loadModels($this, ['Companies', 'PluginManager', 'Domains.DomainsDomains']);

        try {
            $this->createDomainsDomainsTable();
        } catch (Exception $e) {
            // Error adding... no permission?
            $this->Input->setErrors(['db' => ['create' => $e->getMessage()]]);
            return;
        }

        // Add all the existing domains for all companies with the domain manager installed
        $plugins = $this->PluginManager->getByDir('domains');
        foreach ($plugins as $plugin) {
            // Set the default renewal days before expiration
            if (!($setting = $this->Companies->getSetting($plugin->company_id, 'domains_renewal_days_before_expiration'))) {
                $this->Companies->setSetting($plugin->company_id, 'domains_renewal_days_before_expiration', 0);
            }

            $domains = $this->DomainsDomains->getAll([
                'company_id' => $plugin->company_id
            ]);

            // Record expiration date in domains_domains for each domain service in the system
            foreach ($domains as $domain) {
                $vars = ['service_id' => $domain->id, 'expiration_date' => $domain->date_renews];
                $fields = ['service_id', 'expiration_date'];

                $this->Record->duplicate('service_id', '=', $domain->id)->insert('domains_domains', $vars, $fields);
            }

        }
    }

    /**
     * Update to v1.6.1
     */
    private function upgrade1_6_1()
    {
        Loader::loadModels($this, ['Companies', 'PackageOptionGroups', 'PackageOptions', 'SettingsCollection']);

        $companies = $this->Companies->getAll();
        foreach ($companies as $company) {
            // Get default currency
            $default_currency = $this->SettingsCollection->fetchSetting(null, $company->id, 'default_currency');
            $currency = ($default_currency['value'] ?? 'USD');

            if (($setting = $this->Companies->getSetting($company->id, 'domains_dns_management_option_group'))) {
                $package_options = $this->PackageOptionGroups->getAllOptions($setting->value, ['hidden' => true]);
                foreach ($package_options as $package_option) {
                    $values = $this->PackageOptions->getValues($package_option->id);
                    foreach ($values as &$value) {
                        if (empty($value->pricing)) {
                            $value->pricing = [];
                            for ($i = 1; $i <= 10; $i++) {
                                $value->pricing[] = ['term' => $i, 'period' => 'year', 'currency' => $currency, 'price' => 0];
                            }
                        }

                        $value = (array) $value;
                    }

                    // Update package option
                    $option = array_merge((array) $package_option, ['values' => $values]);
                    $this->PackageOptions->edit($package_option->id, $option);
                }
            }

            if (($setting = $this->Companies->getSetting($company->id, 'domains_email_forwarding_option_group'))) {
                $package_options = $this->PackageOptionGroups->getAllOptions($setting->value, ['hidden' => true]);
                foreach ($package_options as $package_option) {
                    $values = $this->PackageOptions->getValues($package_option->id);
                    foreach ($values as &$value) {
                        if (empty($value->pricing)) {
                            $value->pricing = [];
                            for ($i = 1; $i <= 10; $i++) {
                                $value->pricing[] = ['term' => $i, 'period' => 'year', 'currency' => $currency, 'price' => 0];
                            }
                        }

                        $value = (array) $value;
                    }

                    // Update package option
                    $option = array_merge((array) $package_option, ['values' => $values]);
                    $this->PackageOptions->edit($package_option->id, $option);
                }
            }

            if (($setting = $this->Companies->getSetting($company->id, 'domains_id_protection_option_group'))) {
                $package_options = $this->PackageOptionGroups->getAllOptions($setting->value, ['hidden' => true]);
                foreach ($package_options as $package_option) {
                    $values = $this->PackageOptions->getValues($package_option->id);
                    foreach ($values as &$value) {
                        if (empty($value->pricing)) {
                            $value->pricing = [];
                            for ($i = 1; $i <= 10; $i++) {
                                $value->pricing[] = ['term' => $i, 'period' => 'year', 'currency' => $currency, 'price' => 0];
                            }
                        }

                        $value = (array) $value;
                    }

                    // Update package option
                    $option = array_merge((array) $package_option, ['values' => $values]);
                    $this->PackageOptions->edit($package_option->id, $option);
                }
            }
        }
    }

    /**
     * Creates the domains_domains database table
     */
    private function createDomainsDomainsTable()
    {
        $this->Record
            ->setField('id', ['type' => 'int', 'size' => 10, 'unsigned' => true, 'auto_increment' => true])
            ->setField('service_id', ['type' => 'INT', 'size' => "10", 'unsigned' => true])
            ->setField('expiration_date', ['type' => 'datetime'])
            ->setKey(['id'], 'primary')
            ->setKey(['service_id'], 'unique')
            ->create('domains_domains', true);
    }

    /**
     * Move the Domain Manager navigation items to the appropriate spot in the nav
     *
     * @param int $company_id The company for which to move navigation items
     */
    private function reorderNavigationItems($company_id)
    {
        Loader::loadModels($this, ['Actions', 'Navigation']);
        // Get the current navigation items
        $navigation_items = $this->Navigation->getAll(
            ['location' => 'nav_staff', 'company_id' => $company_id]
        );

        // Delete existing staff navigation items for this company
        $this->Navigation->delete(['company_id' => $company_id, 'location' => 'nav_staff']);

        // Re-add the navigation items, with the Domain Manager items in the proper place
        $order = 0;
        foreach ($navigation_items as $navigation_item) {
            // Don't re-add existing Domain Manager nav items
            if ($navigation_item->url == 'plugin/domains/admin_domains/browse/'
                || $navigation_item->url == 'plugin/domains/admin_domains/tlds/'
            ) {
                continue;
            }

            $params = [
                'action_id' => $navigation_item->action_id,
                'order' => $order++,
                'parent_url' => $navigation_item->parent_url
            ];
            $this->Navigation->add($params);

            // Add the Domain Manager nav items
            if ($navigation_item->url == 'billing/services/') {
                // Get the current Browse Domains action
                $action = $this->Actions->getByUrl(
                    'plugin/domains/admin_domains/browse/',
                    'nav_staff',
                    $company_id
                );

                if ($action) {
                    $params = [
                        'action_id' => $action->id,
                        'order' => $order++,
                        'parent_url' => $navigation_item->parent_url
                    ];
                    $this->Navigation->add($params);
                }
            } elseif ($navigation_item->url == 'package_options/') {
                // Get the current TLD Pricing action
                $action = $this->Actions->getByUrl(
                    'plugin/domains/admin_domains/tlds/',
                    'nav_staff',
                    $company_id
                );

                if ($action) {
                    $params = [
                        'action_id' => $action->id,
                        'order' => $order++,
                        'parent_url' => $navigation_item->parent_url
                    ];
                    $this->Navigation->add($params);
                }
            }
        }

        try {
            // Create domains packages table
            $this->Record
                ->setField('id', ['type' => 'int', 'size' => 10, 'unsigned' => true, 'auto_increment' => true])
                ->setField('tld_id', ['type' => 'INT', 'size' => "10", 'unsigned' => true])
                ->setField('package_id', ['type' => 'INT', 'size' => "10", 'unsigned' => true])
                ->setKey(['id'], 'primary')
                ->setKey(['tld_id', 'package_id'], 'unique')
                ->create('domains_packages', true);

            // Remove the order column
            $this->Record->query(
                'ALTER TABLE domains_tlds DROP COLUMN `order`;'
            );

            // Remove the extra feature columns
            $this->Record->query(
                'ALTER TABLE domains_tlds
                    DROP COLUMN `dns_management`,
                    DROP COLUMN `email_forwarding`,
                    DROP COLUMN `id_protection`,
                    DROP COLUMN `epp_code`;'
            );
        } catch (Exception $e) {
            // Error adding... no permission?
            $this->Input->setErrors(['db' => ['create' => $e->getMessage()]]);
            return;
        }
    }

    /**
     * Add a package group for hiding and managing TLDs
     *
     * @param int $company_id The ID of the company for which to add the package group
     * @param array $languages A list of objects, each representing a language for which to add a name and description
     * @return int The ID of the package group
     */
    private function addTldPackageGroup($company_id, array $languages)
    {
        Loader::loadModels($this, ['PackageGroups', 'Companies']);

        // Check if there is a package group collision between all system companies
        $domains_package_group = $this->Companies->getSetting($company_id, 'domains_package_group');
        $companies = $this->Companies->getAll();

        foreach ($companies as $company) {
            $company_domains_package_group = $this->Companies->getSetting($company->id, 'domains_package_group');

            if ($domains_package_group
                && $company_domains_package_group
                && $company_domains_package_group->value == $domains_package_group->value
                && $company->id != $company_id
            ) {
                // A collision was found, unset the domains_package_group setting for the current company
                $this->Companies->unsetSetting($company_id, 'domains_package_group');
                break;
            }
        }

        // Don't create a new TLD package group if it is already set
        if (!($package_group_setting = $this->Companies->getSetting($company_id, 'domains_package_group'))
            || !($package_group = $this->PackageGroups->get($package_group_setting->value))
        ) {
            // Assemble the parameters for adding the TLD package group
            $params = [
                'company_id' => $company_id,
                'type' => 'standard',
                'hidden' => '1',
                'names' => [],
                'descriptions' => [],
                'allow_upgrades' => 0
            ];

            // Add name and description for each language
            foreach ($languages as $language) {
                $params['names'][] = [
                    'lang' => $language->code,
                    'name' => Language::_('DomainsPlugin.tld_package_group.name', true)
                ];
                $params['descriptions'][] = [
                    'lang' => $language->code,
                    'description' => Language::_('DomainsPlugin.tld_package_group.description', true)
                ];
            }

            // Add the TLD package group
            $package_group_id = $this->PackageGroups->add($params);

            $this->Companies->setSetting($company_id, 'domains_package_group', $package_group_id);
        } else {
            $package_group_id = $package_group->id;
        }

        return $package_group_id;
    }

    /**
     * Adds a package and database TLD for each of the default TLDs
     *
     * @param int $company_id The ID of the company for which to add TLD packages
     * @param int $package_group_id The ID of the TLD package group
     */
    private function addTldPackages($company_id, $package_group_id)
    {
        Loader::loadModels($this, ['ModuleManager', 'Packages', 'Companies', 'Domains.DomainsTlds']);

        // Get generic domain module
        if (!$this->ModuleManager->isInstalled('generic_domains', $company_id)) {
            $this->ModuleManager->add(['class' => 'generic_domains', 'company_id' => $company_id]);
        }
        $module = $this->ModuleManager->getByClass('generic_domains', $company_id);
        $module = isset($module[0]) ? $module[0] : null;

        if (!isset($module->id)) {
            $this->Input->setErrors([
                'module_id' => [
                    'invalid' => Language::_('DomainsPlugin.!error.module_id.exists', true)
                ]
            ]);
            return;
        }

        // Create a package for each tld and add it to the database
        $default_tlds = $this->DomainsTlds->getDefaultTlds();
        $tld_packages_setting = $this->Companies->getSetting($company_id, 'domains_tld_packages');
        $tld_packages = (array)($tld_packages_setting ? unserialize($tld_packages_setting->value) : []);

        // Check if there is a package collision between all system companies
        $companies = $this->Companies->getAll();

        foreach ($companies as $company) {
            $company_tld_packages_setting = $this->Companies->getSetting($company->id, 'domains_tld_packages');
            $company_tld_packages = (array)($company_tld_packages_setting ? unserialize($company_tld_packages_setting->value) : []);

            if ($company_tld_packages == $tld_packages && $company->id != $company_id) {
                // A collision was found, set the domains_tld_packages setting as an empty array for the current company
                $this->Companies->setSetting($company_id, 'domains_tld_packages', serialize([]));
                $tld_packages = [];
                break;
            }
        }

        foreach ($default_tlds as $default_tld) {
            // Skip package creation for this TLD if there is already a package assigned to it
            if (array_key_exists($default_tld, $tld_packages)
                && ($package = $this->Packages->get($tld_packages[$default_tld]))
            ) {
                $package_id = $package->id;
            }

            // Create new package
            $tld_params = [
                'tld' => $default_tld,
                'company_id' => $company_id,
                'package_group_id' => $package_group_id,
                'module_id' => $module->id
            ];

            if (isset($package_id)) {
                $tld_params['package_id'] = $package_id;
                unset($tld_params['package_group_id']);
            }
            $tld = $this->DomainsTlds->add($tld_params);
            $package_id = $tld['package_id'] ?? $package_id;

            // Set errors
            $errors = $this->DomainsTlds->errors();
            if (!empty($errors)) {
                $this->logger->error(json_encode($errors));
                $this->Input->setErrors($errors);
            }

            $tld_packages[$default_tld] = $package_id;

            unset($package_id);
        }

        // Save the TLD packages for this company
        $this->Companies->setSetting($company_id, 'domains_tld_packages', serialize($tld_packages));
    }

    /**
     * Adds a config option group and setting for each TLD addon
     *
     * @param int $company_id The ID of the company for which to add TLD config options
     * @param array $currencies A list of objects, each representing a currency for which to add a pricing
     */
    private function addTldAddonConfigOptions($company_id, array $currencies)
    {
        Loader::loadModels($this, ['PackageOptions', 'PackageOptionGroups']);

        $tld_addons = ['email_forwarding', 'dns_management', 'id_protection'];
        foreach ($tld_addons as $tld_addon) {
            $setting = $this->Companies->getSetting($company_id, 'domains_' . $tld_addon . '_option_group');
            // Skip option group creation for this tld and if there is already a group assigned to it
            if ($setting
                && ($option_group = $this->PackageOptionGroups->get($setting->value))
                && $option_group->company_id === $company_id
            ) {
                continue;
            }

            // Create the params for the config option group
            $option_group_params = [
                'company_id' => $company_id,
                'name' => Language::_('DomainsPlugin.' . $tld_addon . '.name', true),
                'description' => Language::_('DomainsPlugin.' . $tld_addon . '.description', true),
                'hidden' => 1
            ];
            // Add the config option group
            $option_group_id = $this->PackageOptionGroups->add($option_group_params);

            // Set the company setting
            $this->Companies->setSetting(
                $company_id,
                'domains_' . $tld_addon . '_option_group',
                $option_group_id
            );

            // Create the params for the config option
            $option_params = [
                'company_id' => $company_id,
                'label' => Language::_('DomainsPlugin.' . $tld_addon . '.name', true),
                'name' => $tld_addon,
                'type' => 'checkbox',
                'addable' => 1,
                'editable' => 1,
                'values' => [
                    [
                        'name' => Language::_('DomainsPlugin.enabled', true),
                        'value' => 1,
                    ]
                ],
                'pricing' => [],
                'groups' => [$option_group_id],
                'hidden' => 1
            ];

            // Add a pricing for terms 1-10 years for each currency
            foreach ($currencies as $currency) {
                for ($i = 1; $i <= 10; $i++) {
                    $option_params['pricing'][] = ['term' => $i, 'period' => 'year', 'currency' => $currency->code, 'price' => 0];
                }
            }

            // Add the config option for the TLD addon
            $this->PackageOptions->add($option_params);
        }
    }

    /**
     * Performs any necessary cleanup actions
     *
     * @param int $plugin_id The ID of the plugin being uninstalled
     * @param bool $last_instance True if $plugin_id is the last instance across
     *  all companies for this plugin, false otherwise
     */
    public function uninstall($plugin_id, $last_instance)
    {
        Loader::loadModels($this, ['CronTasks', 'Companies', 'Emails', 'EmailGroups', 'DataFeeds']);

        Configure::load('domains', dirname(__FILE__) . DS . 'config' . DS);
        $emails = Configure::get('Domains.install.emails');

        // Fetch the cron tasks for this plugin
        $cron_tasks = $this->getCronTasks();

        if ($last_instance) {
            try {
                // Remove database tables
                $this->Record->drop('domains_tlds');
                $this->Record->drop('domains_packages');
                $this->Record->drop('domains_domains');
            } catch (Exception $e) {
                // Error dropping... no permission?
                $this->Input->setErrors(['db' => ['create' => $e->getMessage()]]);
                return;
            }

            // Remove the cron tasks
            foreach ($cron_tasks as $task) {
                $cron_task = $this->CronTasks->getByKey($task['key'], $task['dir'], $task['task_type']);
                if ($cron_task) {
                    $this->CronTasks->deleteTask($cron_task->id, $task['task_type'], $task['dir']);
                }
            }

            // Remove data feed
            $this->DataFeeds->delete('domain');
        } else {
            // Save the company TLD packages, so we can restore them in the future
            $tld_packages_setting = $this->Companies->getSetting(
                Configure::get('Blesta.company_id'),
                'domains_tld_packages'
            );
            $tld_packages = ($tld_packages_setting ? unserialize($tld_packages_setting->value) : []);
            $tlds = $this->Record->select()->
                from('domains_tlds')->
                where('domains_tlds.company_id', '=', Configure::get('Blesta.company_id'))->
                fetchAll();

            foreach ($tlds as $tld) {
                $tld_packages[$tld->tld] = $tld->package_id;
            }

            $this->Companies->setSetting(
                Configure::get('Blesta.company_id'),
                'domains_tld_packages',
                serialize($tld_packages)
            );

            // Remove company TLDs
            $this->Record->from('domains_tlds')->
                leftJoin('domains_packages', 'domains_packages.tld_id', '=', 'domains_tlds.id', false)->
                where('domains_tlds.company_id', '=', Configure::get('Blesta.company_id'))->
                delete(['domains_tlds.*', 'domains_packages.*']);

            // Remove data feed endpoints
            $endpoints = $this->DataFeeds->getAllEndpoints(['company_id' => Configure::get('Blesta.company_id')]);
            foreach ($endpoints as $endpoint) {
                $this->DataFeeds->deleteEndpoint($endpoint->id);
            }
        }

        // Remove individual cron task runs
        foreach ($cron_tasks as $task) {
            $cron_task_run = $this->CronTasks->getTaskRunByKey($task['key'], $task['dir'], false, $task['task_type']);
            if ($cron_task_run) {
                $this->CronTasks->deleteTaskRun($cron_task_run->task_run_id);
            }
        }

        // Remove emails and email groups as necessary
        foreach ($emails as $email) {
            // Fetch the email template created by this plugin
            $group = $this->EmailGroups->getByAction($email['action']);

            // Delete all emails templates belonging to this plugin's email group and company
            if ($group) {
                $this->Emails->deleteAll($group->id, Configure::get('Blesta.company_id'));

                if ($last_instance) {
                    $this->EmailGroups->delete($group->id);
                }
            }
        }

        // We intentionally do not remove packages, config options, etc. as that would cause a whole host of problems.
        // Instead we leave all these things in place including the company settings so that the domain can be easily
        // uninstalled and re-installed
    }

    /**
     * Attempts to add new cron tasks for this plugin
     *
     * @param array $tasks A list of cron tasks to add
     */
    private function addCronTasks(array $tasks)
    {
        Loader::loadModels($this, ['CronTasks']);
        foreach ($tasks as $task) {
            $task_id = $this->CronTasks->add($task);

            if (!$task_id) {
                $cron_task = $this->CronTasks->getByKey($task['key'], $task['dir'], $task['task_type']);
                if ($cron_task) {
                    $task_id = $cron_task->id;
                }
            }

            if ($task_id) {
                $task_vars = ['enabled' => $task['enabled']];
                if ($task['type'] === 'time') {
                    $task_vars['time'] = $task['type_value'];
                } else {
                    $task_vars['interval'] = $task['type_value'];
                }

                $this->CronTasks->addTaskRun($task_id, $task_vars);
            }
        }
    }

    /**
     * Retrieves cron tasks available to this plugin along with their default values
     *
     * @return array A list of cron tasks
     */
    private function getCronTasks()
    {
        return [
            [
                'key' => 'domain_synchronization',
                'task_type' => 'plugin',
                'dir' => 'domains',
                'name' => Language::_('DomainsPlugin.getCronTasks.domain_synchronization', true),
                'description' => Language::_(
                    'DomainsPlugin.getCronTasks.domain_synchronization_description',
                    true
                ),
                'type' => 'time',
                'type_value' => '08:00:00',
                'enabled' => 1
            ],
            [
                'key' => 'domain_tld_synchronization',
                'task_type' => 'plugin',
                'dir' => 'domains',
                'name' => Language::_('DomainsPlugin.getCronTasks.domain_tld_synchronization', true),
                'description' => Language::_(
                    'DomainsPlugin.getCronTasks.domain_tld_synchronization_description',
                    true
                ),
                'type' => 'interval',
                'type_value' => '1440', // 60*24 1 day
                'enabled' => 1
            ],
            [
                'key' => 'domain_term_change',
                'task_type' => 'plugin',
                'dir' => 'domains',
                'name' => Language::_('DomainsPlugin.getCronTasks.domain_term_change', true),
                'description' => Language::_('DomainsPlugin.getCronTasks.domain_term_change_description', true),
                'type' => 'time',
                'type_value' => '08:00:00',
                'enabled' => 1
            ],
            [
                'key' => 'domain_renewal_reminders',
                'task_type' => 'plugin',
                'dir' => 'domains',
                'name' => Language::_('DomainsPlugin.getCronTasks.domain_renewal_reminders', true),
                'description' => Language::_(
                    'DomainsPlugin.getCronTasks.domain_renewal_reminders_description',
                    true
                ),
                'type' => 'time',
                'type_value' => '08:00:00',
                'enabled' => 1
            ]
        ];
    }

    /**
     * Runs the cron task identified by the key used to create the cron task
     *
     * @param string $key The key used to create the cron task
     * @see CronTasks::add()
     */
    public function cron($key)
    {
        switch ($key) {
            case 'domain_synchronization':
                $this->synchronizeDomains();
                break;
            case 'domain_tld_synchronization':
                $this->synchronizeTldDomains();
                break;
            case 'domain_term_change':
                $this->cronDomainTermChange();
                break;
            case 'domain_renewal_reminders':
                $this->cronDomainRenewalReminders();
                break;
        }
    }

    /**
     * Performs the domain synchronization cron task
     */
    private function synchronizeDomains()
    {
        Loader::loadModels($this, ['Companies', 'ModuleManager', 'Services']);
        Loader::loadHelpers($this, ['Form']);

        $company_id = Configure::get('Blesta.company_id');
        $settings = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');
        if (!isset($settings['domains_package_group'])) {
            return;
        }

        // Find all domain services
        $services = $this->Services->getAll(
            ['date_added' => 'DESC'],
            true,
            ['package_group_id' => $settings['domains_package_group']]
        );

        // Set the service renew date based on the expiration date retrieved from the module
        $modules = [];
        foreach ($services as $service) {
            $module_id = $service->package->module_id;
            if (!isset($modules[$module_id])) {
                $modules[$module_id] = $this->ModuleManager->initModule($module_id);
            }

            // Get the domain name from the module
            $domain = $service->name;
            if (method_exists($modules[$module_id], 'getServiceDomain')) {
                $domain = $modules[$module_id]->getServiceDomain($service);
            }

            // Get the expiration date of this service from the registrar
            $renew_date = $this->Services->Date->modify(
                $service->date_renews,
                '-' . ($settings['domains_renewal_days_before_expiration'] ?? 0) . ' days',
                'Y-m-d 00:00:00',
                Configure::get('Blesta.company_timezone')
            );
            if (method_exists($modules[$module_id], 'getExpirationDate')) {
                $renew_date = $this->Services->Date->modify(
                    $modules[$module_id]->getExpirationDate($service, 'c'),
                    '-' . ($settings['domains_renewal_days_before_expiration'] ?? 0) . ' days',
                    'Y-m-d 00:00:00',
                    Configure::get('Blesta.company_timezone')
                );
            }

            // Update the renew date if the expiration date is greater than the renew date
            if (strtotime($renew_date) > strtotime($service->date_renews)) {
                $this->Services->edit($service->id, ['date_renews' => $renew_date]);
            }
        }
    }

    /**
     * Synchronizes all TLDs with the pricing from their registrar module
     */
    private function synchronizeTldDomains()
    {
        if (!isset($this->Form)) {
            Loader::loadHelpers($this, ['Form']);
        }
        if (!isset($this->Date)) {
            Loader::loadHelpers($this, ['Date']);
        }

        Loader::loadModels($this, ['Companies', 'Domains.DomainsTlds']);

        // Get domains company settings
        $company_id = Configure::get('Blesta.company_id');
        $settings = $this->DomainsTlds->getDomainsCompanySettings($company_id);

        // Validate if the task can run
        $last_execution = $settings['domains_sync_last_execution'] ?? null;

        if (
            (
                is_null($last_execution)
                || strtotime($this->Date->modify(
                    date($last_execution),
                    '+' . ((int) $settings['domains_sync_frequency'] ?? 1) . ' days',
                    'Y-m-d',
                    Configure::get('Blesta.company_timezone')
                )) <= strtotime($this->Date->format('Y-m-d', date('c')))
            )
            && !empty($settings['domains_sync_frequency'])
        ) {
            // Get all TLDs for the current company
            $tlds = $this->DomainsTlds->getAll(['company_id' => $company_id]);

            // Build a list of the TLDs to be synchronized
            $tld_list = [];
            foreach ($tlds as $tld) {
                $tld_list[] = $tld->tld;
            }

            // Load sync tool
            Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'tld_sync.php');
            $this->TldSync = new TldSync();

            $this->TldSync->synchronizePrices($tld_list, $company_id);

            // Save last execution
            $this->Companies->setSetting(
                $company_id,
                'domains_sync_last_execution',
                $this->Companies->dateToUtc(date('c'))
            );
        }
    }

    /**
     * Performs the domain term change cron task
     */
    private function cronDomainTermChange()
    {
        Loader::loadModels($this, ['Domains.DomainsTlds', 'Companies', 'Services']);
        Loader::loadHelpers($this, ['Form']);

        $company_id = Configure::get('Blesta.company_id');
        $settings = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');
        if (!isset($settings['domains_package_group'])) {
            return;
        }

        // Find all domain services that do not have a 1 year pricing term
        $services = $this->Services->getAll(
            ['date_added' => 'DESC'],
            true,
            [
                'package_group_id' => $settings['domains_package_group'],
                'excluded_pricing_term' => 1,
                'pricing_period' => 'year'
            ]
        );

        // Update the term for each service that is not on a 1 year pricing term
        foreach ($services as $service) {
            foreach ($service->package->pricing as $pricing) {
                // Find the 1 year pricing for the current package and update the service to use it
                if ($pricing->term == 1
                    && $pricing->period == 'year'
                    && $service->package_pricing->currency == $pricing->currency
                ) {
                    $this->Services->edit($service->id, ['pricing_id' => $pricing->id]);
                    break;
                }
            }
        }
    }

    /**
     * Performs the domain renewal reminder cron task
     */
    private function cronDomainRenewalReminders()
    {
        Loader::loadModels(
            $this,
            ['Domains.DomainsTlds', 'Clients', 'Companies', 'Contacts', 'Emails', 'Services']
        );
        Loader::loadHelpers($this, ['Form']);

        $company_id = Configure::get('Blesta.company_id');
        $settings = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');
        if (!isset($settings['domains_package_group'])) {
            return;
        }

        // Get the current day relative to the configured timezone
        $date = clone $this->Services->Date;
        $date->setTimezone(Configure::get('Blesta.company_timezone'), 'UTC');
        $today = $date->format('c', date('c'));

        // Get reminder date ranges
        $first_reminder_days = '+' . $settings['domains_first_reminder_days_before'] . ' days';
        $second_reminder_days = '+' . $settings['domains_second_reminder_days_before'] . ' days';
        $expiration_notice_days = '-' . $settings['domains_expiration_notice_days_after'] . ' days';
        $reminders = [
            'domain_renewal_1' => [
                'start_date' => $date->modify($today, $first_reminder_days, 'Y-m-d 00:00:00'),
                'end_date' => $date->modify($today, $first_reminder_days, 'Y-m-d 23:59:59'),
            ],
            'domain_renewal_2' => [
                'start_date' => $date->modify($today, $second_reminder_days, 'Y-m-d 00:00:00'),
                'end_date' => $date->modify($today, $second_reminder_days, 'Y-m-d 23:59:59'),
            ],
            'domain_expiration' => [
                'start_date' => $date->modify($today, $expiration_notice_days, 'Y-m-d 00:00:00'),
                'end_date' => $date->modify($today, $expiration_notice_days, 'Y-m-d 23:59:59'),
            ]
        ];

        // Send reminders for each service renewing on the appropriate day
        foreach ($reminders as $reminder => $dates) {
            $start_date = $date->format('Y-m-d H:i:s', $dates['start_date']);
            $end_date = $date->format('Y-m-d H:i:s', $dates['end_date']);

            // Fetch all qualifying services
            $services = $this->Services->getAll(
                ['date_added' => 'DESC'],
                true,
                [],
                [
                    'services' => [
                        'package_group_id' => $settings['domains_package_group'],
                        ['column' => 'date_renews', 'operator' => '>=', 'value' => $start_date],
                        ['column' => 'date_renews', 'operator' => '<=', 'value' => $end_date]
                    ]
                ]
            );

            // Send the email for each service
            foreach ($services as $service) {
                $lang = null;
                $client = $this->Clients->get($service->client_id);
                $contact = $this->Contacts->get($client->contact_id);
                if ($client && $client->settings['language']) {
                    $lang = $client->settings['language'];
                }

                $tags = ['service' => $service, 'client' => $client, 'contact' => $contact, 'domain' => $service->name];
                $options = ['to_client_id' => $service->client_id];

                // Format the renew date
                $service->date_renews = $date->format('Y-m-d', $service->date_renews);
                $this->Emails->send(
                    'Domains.' . $reminder,
                    Configure::get('Blesta.company_id'),
                    $lang,
                    $contact->email,
                    $tags,
                    null,
                    null,
                    null,
                    $options
                );
            }
        }
    }

    /**
     * Returns all actions to be configured for this widget
     * (invoked after install() or upgrade(), overwrites all existing actions)
     *
     * @return array A numerically indexed array containing:
     *  - action The action to register for
     *  - uri The URI to be invoked for the given action
     *  - name The name to represent the action (can be language definition)
     *  - options An array of key/value pair options for the given action
     */
    public function getActions()
    {
        return [
            // Domains Nav
            [
                'location' => 'nav_staff',
                'uri' => 'plugin/domains/admin_domains/browse/',
                'name' => 'DomainsPlugin.nav_secondary_staff.domains',
                'options' => ['parent' => 'billing/']
            ],
            // Domains Client Nav
            [
                'location' => 'nav_client',
                'uri' => 'plugin/domains/client_main/index/',
                'name' => 'DomainsPlugin.nav_client.domains',
                'options' => ['parent' => 'services/index/active/']
            ],
            // Domain Configuration Nav
            [
                'location' => 'nav_staff',
                'uri' => 'plugin/domains/admin_domains/tlds/',
                'name' => 'DomainsPlugin.nav_secondary_staff.domain_options',
                'options' => ['parent' => 'packages/']
            ],
            // Staff Widget
            [
                'action' => 'widget_staff_client',
                'uri' => 'plugin/domains/admin_main/domains/',
                'name' => 'DomainsPlugin.widget_staff_home.main'
            ],
            // Client Widget
            [
                'action' => 'widget_client_home',
                'uri' => 'plugin/domains/client_main/widget/',
                'name' => 'DomainsPlugin.widget_client_home.main'
            ]
        ];
    }

    /**
     * Returns all cards to be configured for this plugin (invoked after install() or upgrade(),
     * overwrites all existing cards)
     *
     * @return array A numerically indexed array containing:
     *
     *  - level The level this card should be displayed on (client or staff) (optional, default client)
     *  - callback A method defined by the plugin class for calculating the value of the card or fetching a custom html
     *  - callback_type The callback type, 'value' to fetch the card value or
     *      'html' to fetch the custom html code (optional, default value)
     *  - background The background color in hexadecimal or path to the background image for this card (optional)
     *  - background_type The background type, 'color' to set a hexadecimal background or
     *      'image' to set an image background (optional, default color)
     *  - label A string or language key appearing under the value as a label
     *  - link The link to which the card will be pointed (optional)
     *  - enabled Whether this card appears on client profiles by default
     *      (1 to enable, 0 to disable) (optional, default 1)
     */
    public function getCards()
    {
        return [
            [
                'level' => 'client',
                'callback' => ['this', 'getDomainCount'],
                'callback_type' => 'value',
                'background' => '#fff',
                'background_type' => 'color',
                'label' => 'DomainsPlugin.card_client.getDomainCount',
                'link' => 'plugin/domains/client_main/',
                'enabled' => 1
            ]
        ];
    }

    /**
     * Returns all permissions to be configured for this plugin (invoked after install(), upgrade(),
     *  and uninstall(), overwrites all existing permissions)
     *
     * @return array A numerically indexed array containing:
     *
     *  - group_alias The alias of the permission group this permission belongs to
     *  - name The name of this permission
     *  - alias The ACO alias for this permission (i.e. the Class name to apply to)
     *  - action The action this ACO may control (i.e. the Method name of the alias to control access for)
     */
    public function getPermissions()
    {
        return [
            // Browse Domains
            [
                'group_alias' => 'domains.admin_domains',
                'name' => Language::_('DomainsPlugin.permission.admin_domains.browse', true),
                'alias' => 'domains.admin_domains',
                'action' => 'browse',
            ],
            // TLD Pricing
            [
                'group_alias' => 'domains.admin_domains',
                'name' => Language::_('DomainsPlugin.permission.admin_domains.tlds', true),
                'alias' => 'domains.admin_domains',
                'action' => 'tlds',
            ],
            // Registrars
            [
                'group_alias' => 'domains.admin_domains',
                'name' => Language::_('DomainsPlugin.permission.admin_domains.registrars', true),
                'alias' => 'domains.admin_domains',
                'action' => 'registrars',
            ],
            // Whois
            [
                'group_alias' => 'domains.admin_domains',
                'name' => Language::_('DomainsPlugin.permission.admin_domains.whois', true),
                'alias' => 'domains.admin_domains',
                'action' => 'whois',
            ],
            // Configuration
            [
                'group_alias' => 'domains.admin_domains',
                'name' => Language::_('DomainsPlugin.permission.admin_domains.configuration', true),
                'alias' => 'domains.admin_domains',
                'action' => 'configuration',
            ]
        ];
    }

    /**
     * Returns all permission groups to be configured for this plugin (invoked after install(), upgrade(),
     *  and uninstall(), overwrites all existing permission groups)
     *
     * @return array A numerically indexed array containing:
     *
     *  - name The name of this permission group
     *  - level The level this permission group resides on (staff or client)
     *  - alias The ACO alias for this permission group (i.e. the Class name to apply to)
     */
    public function getPermissionGroups()
    {
        return [
            // Domains
            [
                'name' => Language::_('DomainsPlugin.permission.admin_domains', true),
                'level' => 'staff',
                'alias' => 'domains.admin_domains'
            ]
        ];
    }

    /**
     * Returns all events to be registered for this plugin
     * (invoked after install() or upgrade(), overwrites all existing events)
     *
     * @return array A numerically indexed array containing:
     *  - event The event to register for
     *  - callback A string or array representing a callback function or class/method.
     *      If a user (e.g. non-native PHP) function or class/method, the plugin must
     *      automatically define it when the plugin is loaded. To invoke an instance
     *      methods pass "this" instead of the class name as the 1st callback element.
     */
    public function getEvents()
    {
        return [
            [
                'event' => 'Packages.deleteAfter',
                'callback' => ['this', 'deletePackageTld']
            ],
            [
                'event' => 'Services.addAfter',
                'callback' => ['this', 'updateRenewalDate']
            ],
            [
                'event' => 'Services.editAfter',
                'callback' => ['this', 'updateRenewalDate']
            ]
        ];
    }

    /**
     * Deletes TLD associated with a deleted package
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event The event to process
     */
    public function deletePackageTld($event)
    {
        Loader::loadModels($this, ['Domains.DomainsTlds']);
        $params = $event->getParams();

        if (isset($params['package_id'])) {
            $tlds = $this->DomainsTlds->getAll(['package_id' => $params['package_id']]);

            foreach ($tlds as $tld) {
                $this->DomainsTlds->delete($tld->tld);
            }
        }
    }

    /**
     * Updates the renewal date of a recently added domain
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event The event to process
     */
    public function updateRenewalDate($event)
    {
        Loader::loadModels($this, ['Domains.DomainsDomains', 'Companies', 'ModuleManager', 'Services']);
        $params = $event->getParams();

        if (isset($params['old_service'])) {
            $params['vars'] = (array) $params['old_service'];
        }

        // Validate if the service is being handled by the Domain Manager and the module type is registrar
        $package_group_id = $this->Companies->getSetting(Configure::get('Blesta.company_id'), 'domains_package_group');
        $module_row = $this->ModuleManager->getRow($params['vars']['module_row_id']);
        $module = $this->ModuleManager->get($module_row->module_id ?? null, false, false);

        if ($package_group_id->value == ($params['vars']['package_group_id'] ?? null) && $module->type == 'registrar') {
            $service = $this->Services->get($params['service_id'] ?? null);

            // Get the domain expiration date for this service
            $expiration_date = $this->DomainsDomains->getExpirationDate($service->id);

            // Save the expiration date locally
            $this->DomainsDomains->setExpirationDate($service->id, $expiration_date);

            // Calculate the renew date based on the domains_renewal_days_before_expiration setting
            $renewal_days = $this->Companies->getSetting(
                Configure::get('Blesta.company_id'),
                'domains_renewal_days_before_expiration'
            );
            $renewal_date = $this->Services->Date->modify(
                $expiration_date,
                '-' . ($renewal_days->value ?? 0) . ' days',
                'Y-m-d 00:00:00',
                Configure::get('Blesta.company_timezone')
            );

            $this->Record->where('id', '=', $service->id)
                ->update('services', ['date_renews' => $renewal_date]);
        }
    }

    /**
     * Retrieves the value for a client card
     *
     * @param int $client_id The ID of the client for which to fetch the card value
     * @return mixed The value for the client card
     */
    public function getDomainCount($client_id)
    {
        Loader::loadModels($this, ['Services']);

        return $this->Services->getListCount(
            $client_id,
            'all',
            true,
            null,
            ['type' => 'domains']
        ) - $this->Services->getListCount(
            $client_id,
            'canceled',
            true,
            null,
            ['type' => 'domains']
        );
    }
}
