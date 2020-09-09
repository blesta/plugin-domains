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
        try {
            // domain_manager_tlds
            $this->Record
                ->setField('tld', ['type' => 'VARCHAR', 'size' => "64"])
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

        // Add cron tasks for this plugin
        $this->addCronTasks($this->getCronTasks());

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
                // Perform necessary actions
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
            // Domains
            [
                'group_alias' => 'domain_manager.admin_domains',
                'name' => Language::_('DomainManagerPlugin.permission.admin_domains', true),
                'alias' => 'domain_manager.admin_domains',
                'action' => '*',
            ],
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
