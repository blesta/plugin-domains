<?php
/**
 * Domain Manager plugin handler
 *
 * @link https://www.blesta.com Blesta
 */
class DomainManagerPlugin extends Plugin
{
    public function __construct()
    {
        // Load components required by this plugin
        Loader::loadComponents($this, ['Input', 'Record']);

        Language::loadLang('domain_manager_plugin', null, dirname(__FILE__) . DS . 'language' . DS);
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');
    }

    /**
     * Performs any necessary bootstraping actions
     *
     * @param int $plugin_id The ID of the plugin being installed
     */
    public function install($plugin_id)
    {
        Loader::loadModels($this, ['Companies', 'Currencies', 'Languages', 'PluginManager']);

        try {
            // domain_manager_tlds
            $this->Record
                ->setField('tld', ['type' => 'VARCHAR', 'size' => "64"])
                ->setField('company_id', ['type' => 'INT', 'size' => "10", 'unsigned' => true])
                ->setField('package_id', ['type' => 'INT', 'size' => "10", 'unsigned' => true, 'is_null' => true])
                ->setField('dns_management', ['type' => 'TINYINT', 'size' => "1", 'default' => 0])
                ->setField('email_forwarding', ['type' => 'TINYINT', 'size' => "1", 'default' => 0])
                ->setField('id_protection', ['type' => 'TINYINT', 'size' => "1", 'default' => 0])
                ->setKey(['tld'], 'primary')
                ->create('domain_manager_tlds', true);

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
        $this->addTldPackages($company_id, $package_group_id, $languages, $currencies);

        // Add a config option and option group for each TLD addon
        $this->addTldAddonConfigOptions($company_id, $currencies);

        // Set an empty array for the list of spotlight TLDs
        if (!($setting = $this->Companies->getSetting($company_id, 'domain_manager_spotlight_tlds'))) {
            $this->Companies->setSetting($company_id, 'domain_manager_spotlight_tlds', json_encode([]));
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
        Loader::loadModels($this, ['PackageGroups']);
        // Don't create a new TLD package group if it is already set
        if (!($package_group_setting = $this->Companies->getSetting($company_id, 'domain_manager_package_group'))
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
                    'name' => Language::_('DomainManagerPlugin.tld_package_group.name', true)
                ];
                $params['descriptions'][] = [
                    'lang' => $language->code,
                    'description' => Language::_('DomainManagerPlugin.tld_package_group.description', true)
                ];
            }

            // Add the TLD package group
            $package_group_id = $this->PackageGroups->add($params);

            $this->Companies->setSetting($company_id, 'domain_manager_package_group', $package_group_id);
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
     * @param array $languages A list of objects, each representing a language for which to add a name and description
     * @param array $currencies A list of objects, each representing a currency for which to add a pricing
     */
    private function addTldPackages($company_id, $package_group_id, array $languages, array $currencies)
    {
        Loader::loadModels($this, ['ModuleManager', 'Packages', 'DomainManager.DomainManagerTlds']);

        // Get the none module for this company
        $none_modules = $this->ModuleManager->getByClass('none', $company_id);

        // Create a package for each tld and add it to the database
        $default_tlds = $this->DomainManagerTlds->getDefaultTlds();
        $tld_packages_setting = $this->Companies->getSetting($company_id, 'domain_manager_tld_packages');
        $tld_packages = ($tld_packages_setting ? json_decode($tld_packages_setting->value, true) : []);
        foreach ($default_tlds as $default_tld) {
            // Skip package creation for this TLD if there is already a package assigned to it
            if (array_key_exists($default_tld, $tld_packages)
                && ($package = $this->Packages->get($tld_packages[$default_tld]))
            ) {
                $tld_params = ['tld' => $default_tld, 'company_id' => $company_id, 'package_id' => $package->id];
                $this->DomainManagerTlds->add($tld_params);
                continue;
            }

            $package_params = [
                'module_id' => (!empty($none_modules) && is_array($none_modules) ? $none_modules[0]->id : null),
                'names' => [],
                'descriptions' => [],
                'hidden' => '1',
                'company_id' => $company_id,
                'pricing' => [],
                'groups' => [$package_group_id]
            ];

            // Add a pricing for terms 1-10 years for each currency
            foreach ($currencies as $currency) {
                for ($i = 1; $i <= 10; $i++) {
                    $package_params['pricing'][] = ['term' => $i, 'period' => 'year', 'currency' => $currency->code];
                }
            }

            // Add a package name and email content for each language
            foreach ($languages as $language) {
                $package_params['names'][] = [
                    'lang' => $language->code,
                    'name' => $language->code
                ];
                $package_params['email_content'][] = [
                    'lang' => $language->code,
                    'html' => '',
                    'text' => ''
                ];
            }

            // Add the package for this TLD
            $package_id = $this->Packages->add($package_params);

            // Add this TLD to the database
            $tld_params = ['tld' => $default_tld, 'company_id' => $company_id, 'package_id' => $package_id];
            $this->DomainManagerTlds->add($tld_params);

            $tld_packages[$default_tld] = $package_id;
        }
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
            $setting = $this->Companies->getSetting($company_id, 'domain_manager_' . $tld_addon . '_option_group');
            // Skip option group creation for this tld and if there is already a group assigned to it
            if ($setting && ($option_group = $this->PackageOptionGroups->get($setting->value))) {
                continue;
            }

            // Create the params for the config option group
            $option_group_params = [
                'company_id' => $company_id,
                'name' => Language::_('DomainManagerPlugin.' . $tld_addon . '.name', true),
                'description' => Language::_('DomainManagerPlugin.' . $tld_addon . '.description', true),
            ];
            // Add the config option group
            $option_group_id = $this->PackageOptionGroups->add($option_group_params);

            // Set the company setting
            $this->Companies->setSetting(
                $company_id,
                'domain_manager_' . $tld_addon . '_option_group',
                $option_group_id
            );

            // Create the params for the config option
            $option_params = [
                'company_id' => $company_id,
                'label' => Language::_('DomainManagerPlugin.' . $tld_addon . '.name', true),
                'name' => $tld_addon,
                'type' => 'checkbox',
                'addable' => 1,
                'editable' => 1,
                'values' => [
                    [
                        'name' => Language::_('DomainManagerPlugin.enabled', true),
                        'value' => 1,
                    ]
                ],
                'pricing' => [],
                'groups' => [$option_group_id]
            ];

            // Add a pricing for terms 1-10 years for each currency
            foreach ($currencies as $currency) {
                for ($i = 0; $i < 10; $i++) {
                    $option_params['pricing'][] = ['term' => $i, 'period' => 'year', 'currency' => $currency->code];
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
        Loader::loadModels($this, ['CronTasks']);

        // Fetch the cron tasks for this plugin
        $cron_tasks = $this->getCronTasks();

        if ($last_instance) {
            try {
                // Remove database tables
                $this->Record->drop('domain_manager_tlds');
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
        }

        // Remove individual cron task runs
        foreach ($cron_tasks as $task) {
            $cron_task_run = $this->CronTasks->getTaskRunByKey($task['key'], $task['dir'], false, $task['task_type']);
            if ($cron_task_run) {
                $this->CronTasks->deleteTaskRun($cron_task_run->task_run_id);
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
                'dir' => 'domain_manager',
                'name' => Language::_('DomainManagerPlugin.getCronTasks.domain_synchronization', true),
                'description' => Language::_(
                    'DomainManagerPlugin.getCronTasks.domain_synchronization_description',
                    true
                ),
                'type' => 'time',
                'type_value' => '08:00:00',
                'enabled' => 1
            ],
            [
                'key' => 'domain_term_change',
                'task_type' => 'plugin',
                'dir' => 'domain_manager',
                'name' => Language::_('DomainManagerPlugin.getCronTasks.domain_term_change', true),
                'description' => Language::_('DomainManagerPlugin.getCronTasks.domain_term_change_description', true),
                'type' => 'time',
                'type_value' => '08:00:00',
                'enabled' => 1
            ],
            [
                'key' => 'domain_renewal_reminders',
                'task_type' => 'plugin',
                'dir' => 'domain_manager',
                'name' => Language::_('DomainManagerPlugin.getCronTasks.domain_renewal_reminders', true),
                'description' => Language::_(
                    'DomainManagerPlugin.getCronTasks.domain_renewal_reminders_description',
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
            case 'domain_term_change':
                // Perform necessary actions
                break;
            case 'domain_renewal_reminders':
                // Perform necessary actions
                break;
        }
    }

    /**
     * Performs the domain synchronization cron task
     */
    private function synchronizeDomains()
    {
        Loader::loadModels($this, ['Companies', 'Services']);
        Loader::loadHelpers($this, ['Form']);

        $company_id = Configure::get('Blesta.company_id');
        $settings = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');
        if (!isset($settings['domain_manager_package_group'])) {
            return;
        }

        // Find all domain services
        $services = $this->Services->getAll(
            ['date_added' => 'DESC'],
            true,
            ['package_group_id' => $settings['domain_manager_package_group']]
        );

        // Set the service renew date based on the expiration date retreived from the module
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
            $expiration_date = $modules[$module_id]->getExpirationDate($domain, 'c', $service->module_row_id);

//            TODO
//            Get tld renewal buffer in some way
//            if (!empty(renwal buffer)) {
//                $renew_date = $this->Services->Date->modify($renew_date, renwal buffer . ' days', 'c');
//            }

            // If the expiration date is different than the date renews
            if (strtotime($renew_date) != strtotime($service->date_renews)) {
                $this->Services->edit($service->id, ['date_renews' => $renew_date]);
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
                'action' => 'nav_primary_staff',
                'uri' => 'plugin/domain_manager/admin_domains/index/',
                'name' => 'DomainManagerPlugin.nav_primary_staff.main',
                'options' => [
                    'sub' => [
                        [
                            'uri' => 'plugin/domain_manager/admin_domains/browse',
                            'name' => 'DomainManagerPlugin.nav_primary_staff.browse'
                        ],
                        [
                            'uri' => 'plugin/domain_manager/admin_domains/tlds',
                            'name' => 'DomainManagerPlugin.nav_primary_staff.tlds'
                        ],
                        [
                            'uri' => 'plugin/domain_manager/admin_domains/registrars',
                            'name' => 'DomainManagerPlugin.nav_primary_staff.registrars'
                        ],
                        [
                            'uri' => 'plugin/domain_manager/admin_domains/whois',
                            'name' => 'DomainManagerPlugin.nav_primary_staff.whois'
                        ],
                        [
                            'uri' => 'plugin/domain_manager/admin_domains/configuration',
                            'name' => 'DomainManagerPlugin.nav_primary_staff.configuration'
                        ]
                    ]
                ]
            ],
            // Widget
            [
                'action' => 'widget_staff_home',
                'uri' => 'plugin/domain_manager/admin_main/index/',
                'name' => 'DomainManagerPlugin.widget_staff_home.main',
            ],
            // Client Widget
            [
                'action' => 'widget_client_home',
                'uri' => 'plugin/domain_manager/client_main/index/',
                'name' => 'DomainManagerPlugin.widget_client_home.main',
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
                'label' => 'DomainManagerPlugin.card_client.getDomainCount',
                'link' => 'plugin/domain_manager/client_main/',
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
                'group_alias' => 'domain_manager.admin_domains',
                'name' => Language::_('DomainManagerPlugin.permission.admin_domains.browse', true),
                'alias' => 'domain_manager.admin_domains',
                'action' => 'browse',
            ],
            // TLD Pricing
            [
                'group_alias' => 'domain_manager.admin_domains',
                'name' => Language::_('DomainManagerPlugin.permission.admin_domains.tlds', true),
                'alias' => 'domain_manager.admin_domains',
                'action' => 'tlds',
            ],
            // Registrars
            [
                'group_alias' => 'domain_manager.admin_domains',
                'name' => Language::_('DomainManagerPlugin.permission.admin_domains.registrars', true),
                'alias' => 'domain_manager.admin_domains',
                'action' => 'registrars',
            ],
            // Whois
            [
                'group_alias' => 'domain_manager.admin_domains',
                'name' => Language::_('DomainManagerPlugin.permission.admin_domains.whois', true),
                'alias' => 'domain_manager.admin_domains',
                'action' => 'whois',
            ],
            // Configuration
            [
                'group_alias' => 'domain_manager.admin_domains',
                'name' => Language::_('DomainManagerPlugin.permission.admin_domains.configuration', true),
                'alias' => 'domain_manager.admin_domains',
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
                'name' => Language::_('DomainManagerPlugin.permission.admin_domains', true),
                'level' => 'staff',
                'alias' => 'domain_manager.admin_domains'
            ]
        ];
    }

    /**
     * Retrieves the value for a client card
     *
     * @param int $client_id The ID of the client for which to fetch the card value
     * @return mixed The value for the client card
     */
    public function getDomainCount($client_id)
    {
        return '0';
    }
}
