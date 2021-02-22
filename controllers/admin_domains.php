<?php
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

        $package_group_id = $this->Companies->getSetting(
            Configure::get('Blesta.company_id'),
            'domain_manager_package_group'
        );
        $post_filters['package_group_id'] = $package_group_id ? $package_group_id->value : null;

        $status = (isset($this->get[1]) ? $this->get[1] : 'active');
        $page = (isset($this->get[2]) ? (int)$this->get[2] : 1);
        $sort = (isset($this->get['sort']) ? $this->get['sort'] : 'date_added');
        $order = (isset($this->get['order']) ? $this->get['order'] : 'desc');

        // Get only parent services
        $services = $this->Services->getList(null, $status, $page, [$sort => $order], false, $post_filters);
        $total_results = $this->Services->getListCount(null, $status, false, null, $post_filters);

        // Set the number of services of each type, not including children
        $status_count = [
            'active' => $this->Services->getStatusCount(null, 'active', false, $post_filters),
            'canceled' => $this->Services->getStatusCount(null, 'canceled', false, $post_filters),
            'pending' => $this->Services->getStatusCount(null, 'pending', false, $post_filters),
            'suspended' => $this->Services->getStatusCount(null, 'suspended', false, $post_filters),
            'in_review' => $this->Services->getStatusCount(null, 'in_review', false, $post_filters),
            'scheduled_cancellation' => $this->Services->getStatusCount(
                null,
                'scheduled_cancellation',
                false,
                $post_filters
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
            $service->expiration_date = $modules[$module_id]->
                getExpirationDate($service->name, 'Y-m-d H:i:s', $service->module_row_id);
            $service->registrar = $modules[$module_id]->getName();
        }

        // Set the input field filters for the widget
        $service_filters = new ServiceFilters();
        $this->set(
            'filters',
            $service_filters->getFilters(
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


        return $this->renderAjaxWidgetIfAsync(
            isset($this->get['sort']) ? true : (isset($this->get[1]) || isset($this->get[0]) ? false : null)
        );
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
            $this->flashMessage('error', $errors);
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.registrar_installed', true));
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
            $this->flashMessage('error', $errors);
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.registrar_uninstalled', true));
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
            $this->flashMessage('error', $errors);
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.registrar_upgraded', true));
        }
        $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
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
