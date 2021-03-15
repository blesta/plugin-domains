<?php
use Iodev\Whois\Factory;
use Blesta\Core\Util\Filters\ServiceFilters;
/**
 * Domain Manager admin_domains controller
 *
 * @link https://www.blesta.com Blesta
 */
class AdminDomains extends DomainManagerController
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
        $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/browse/');
    }

    /**
     * Fetches the view for the domain list
     */
    public function browse()
    {
        $this->uses(['Companies', 'ModuleManager', 'Services']);

        if (!empty($this->post) && isset($this->post['service_ids'])) {
            if (($errors = $this->updateServices($this->post))) {
                $this->set('vars', (object) $this->post);
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

            foreach($post_filters as $filter => $value) {
                if (empty($value)) {
                    unset($post_filters[$filter]);
                }
            }
        }
        $service_filters = $post_filters;

        $package_group_id = $this->Companies->getSetting(
            Configure::get('Blesta.company_id'),
            'domain_manager_package_group'
        );
        $service_filters['package_group_id'] = $package_group_id ? $package_group_id->value : null;


        $status = (isset($this->get[0]) ? $this->get[0] : 'active');
        $page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
        $sort = (isset($this->get['sort']) ? $this->get['sort'] : 'date_added');
        $order = (isset($this->get['order']) ? $this->get['order'] : 'desc');

        $alt_sort = false;
        if (in_array($sort, ['registrar', 'expiration_date', 'renewal_price'])) {
            $alt_sort = $sort;
            $sort = 'date_added';
        }

        // Get only parent services
        $services = $this->Services->getList(null, $status, $page, [$sort => $order], false, $service_filters);
        $total_results = $this->Services->getListCount(null, $status, false, null, $service_filters);

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
        $service_filter_generator = new ServiceFilters();
        $this->set(
            'filters',
            $service_filter_generator->getFilters(
                [
                    'language' => Configure::get('Blesta.language'),
                    'company_id' => Configure::get('Blesta.company_id'),
                    'module_type' => 'registrar'
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
        $this->set('sort', $sort);
        $this->set('order', $order);
        $this->set('negate_order', ($order == 'asc' ? 'desc' : 'asc'));
        // Overwrite default pagination settings
        $settings = array_merge(
            Configure::get('Blesta.pagination'),
            [
                'total_results' => $total_results,
                'uri' => $this->base_uri . 'plugin/domain_manager/admin_domains/browse/',
                'params' => ['sort' => $sort, 'order' => $order]
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
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/browse/');
        }

        // Only include service IDs in the list
        $service_ids = [];
        if (isset($data['service_ids'])) {
            foreach ((array) $data['service_ids'] as $service_id) {
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

        // Add avilable registrars to the end of the list
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
    public function installregistrar()
    {
        if (!isset($this->post['id'])) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
        }

        $module_id = $this->ModuleManager->add(['class' => $this->post['id'], 'company_id' => $this->company_id]);

        if (($errors = $this->ModuleManager->errors())) {
            $this->flashMessage('error', $errors, null, false);
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.registrar_installed', true), null, false);
            $this->redirect($this->base_uri . 'settings/company/modules/manage/' . $module_id);
        }
    }

    /**
     * Uninstall a registrar for this company
     */
    public function uninstallregistrar()
    {
        if (!isset($this->post['id']) || !($module = $this->ModuleManager->get($this->post['id']))) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
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
        $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
    }

    /**
     * Upgrade a registrar
     */
    public function upgraderegistrar()
    {
        // Fetch the module to upgrade
        if (!isset($this->post['id']) || !($module = $this->ModuleManager->get($this->post['id']))) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
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
        $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
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
        $this->uses(['Companies', 'PackageGroups', 'PackageOptionGroups', 'DomainManager.DomainManagerTlds']);
        $company_id = Configure::get('Blesta.company_id');
        $vars = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');
        $vars['domain_manager_spotlight_tlds'] = isset($vars['domain_manager_spotlight_tlds'])
            ? json_decode($vars['domain_manager_spotlight_tlds'], true)
            : [];
        if (!empty($this->post)) {
            // Leave the spotlight tlds out for now as we don't intend to include them in the initial release
            $accepted_settings = [
//                'domain_manager_spotlight_tlds',
                'domain_manager_package_group',
                'domain_manager_dns_management_option_group',
                'domain_manager_email_forwarding_option_group',
                'domain_manager_id_protection_option_group',
            ];
            if (!isset($this->post['domain_manager_spotlight_tlds'])) {
                $this->post['domain_manager_spotlight_tlds'] = [];
            }
            $this->post['domain_manager_spotlight_tlds'] = json_encode($this->post['domain_manager_spotlight_tlds']);
            $this->Companies->setSettings(
                $company_id,
                array_intersect_key($this->post, array_flip($accepted_settings))
            );

            $this->flashMessage(
                'message',
                Language::_('AdminDomains.!success.configuration_updated', true),
                null,
                false
            );
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/configuration/');
        }

        $this->set('vars', $vars);
        $this->set('tlds', $this->DomainManagerTlds->getAll(['company_id' => $company_id]));
        $this->set(
            'package_groups',
            $this->Form->collapseObjectArray($this->PackageGroups->getAll($company_id, 'standard'), 'name', 'id')
        );
        $this->set(
            'package_option_groups',
            $this->Form->collapseObjectArray($this->PackageOptionGroups->getAll($company_id), 'name', 'id')
        );
    }
}
