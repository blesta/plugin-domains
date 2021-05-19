<?php
use Blesta\Core\Util\Input\Fields\InputFields;

/**
 * Domain Manager client_main controller
 *
 * @link https://www.blesta.com Blesta
 */
class ClientMain extends DomainsController
{
    /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();

        // Get client
        $this->uses(['Clients']);
        $this->client = $this->Clients->get($this->Session->read('blesta_client_id'), true);

        $this->structure->set('page_title', Language::_('ClientMain.index.page_title', true, $this->client->id_code));
    }

    /**
     * Returns the view for a list of extensions
     */
    public function index()
    {
        // Load required models
        $this->uses(['Companies', 'ModuleManager', 'Services', 'Packages']);

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

        // Filter by domains package group
        $package_group_id = $this->Companies->getSetting(
            Configure::get('Blesta.company_id'),
            'domains_package_group'
        );
        $services_filters = $post_filters;
        $services_filters['package_group_id'] = $package_group_id ? $package_group_id->value : null;

        $status = (isset($this->get[0]) ? $this->get[0] : 'active');
        $page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
        $sort = (isset($this->get['sort']) ? $this->get['sort'] : 'date_added');
        $order = (isset($this->get['order']) ? $this->get['order'] : 'desc');

        // Get services
        $services = $this->Services->getList(
            $this->client->id,
            $status,
            $page,
            [$sort => $order],
            false,
            $services_filters
        );
        $total_results = $this->Services->getListCount($this->client->id, $status, false, null, $services_filters);

        // Set the number of services of each type, not including children
        $status_count = [
            'active' => $this->Services->getStatusCount($this->client->id, 'active', false, $services_filters),
            'canceled' => $this->Services->getStatusCount($this->client->id, 'canceled', false, $services_filters),
            'pending' => $this->Services->getStatusCount($this->client->id, 'pending', false, $services_filters),
            'suspended' => $this->Services->getStatusCount($this->client->id, 'suspended', false, $services_filters),
        ];

        // Set language for periods
        $periods = $this->Packages->getPricingPeriods();
        foreach ($this->Packages->getPricingPeriods(true) as $period => $lang) {
            $periods[$period . '_plural'] = $lang;
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
        $this->set('periods', $periods);
        $this->set('status', $status);
        $this->set('domains', $services);
        $this->set('status_count', $status_count);
        $this->set('sort', $sort);
        $this->set('order', $order);
        $this->set('negate_order', ($order == 'asc' ? 'desc' : 'asc'));

        // Overwrite default pagination settings
        $settings = array_merge(
            Configure::get('Blesta.pagination_client'),
            [
                'total_results' => $total_results,
                'uri' => $this->Html->safe($this->base_uri . 'plugin/domains/client_main/index/' . $status . '/[p]/'),
                'params' => ['sort' => $sort, 'order' => $order]
            ]
        );
        $this->setPagination($this->get, $settings);

        if ($this->isAjax()) {
            return $this->renderAjaxWidgetIfAsync(
                isset($this->get['sort']) ? true : (isset($this->get[1]) || isset($this->get[0]) ? false : null)
            );
        }
    }

    /**
     * Service Info
     */
    public function serviceInfo()
    {
        $this->uses(['ModuleManager', 'Services', 'Packages']);

        // Ensure we have a service
        if (!($domain = $this->Services->get((int)$this->get[0])) || $domain->client_id != $this->client->id) {
            $this->redirect($this->base_uri);
        }
        $this->set('domain', $domain);

        $package = $this->Packages->get($domain->package->id);
        $module = $this->ModuleManager->initModule($domain->package->module_id);

        if ($module) {
            $module->base_uri = $this->base_uri;
            $module->setModuleRow($module->getModuleRow($domain->module_row_id));
            $this->set('content', $module->getClientServiceInfo($domain, $package));
        }

        // Set any addon services
        $services = $this->Services->getAllChildren($domain->id);
        // Set the expected service renewal price
        foreach ($services as $service) {
            $service->renewal_price = $this->Services->getRenewalPrice($service->id);
        }
        $this->set('services', $services);

        // Set language for periods
        $periods = $this->Packages->getPricingPeriods();
        foreach ($this->Packages->getPricingPeriods(true) as $period => $lang) {
            $periods[$period . '_plural'] = $lang;
        }

        $this->set('periods', $periods);
        $this->set('statuses', $this->Services->getStatusTypes());

        echo $this->outputAsJson($this->view->fetch('client_main_serviceinfo'));

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
        $this->components(['Record']);
        $this->uses(['ModuleManager']);
        $this->helpers(['Form']);

        $fields = new InputFields();

        // Set the package name filter
        $package_name = $fields->label(
            Language::_('ClientMain.getfilters.field_package_name', true),
            'package_name'
        );
        $package_name->attach(
            $fields->fieldText(
                'filters[package_name]',
                isset($vars['package_name']) ? $vars['package_name'] : null,
                [
                    'id' => 'package_name',
                    'class' => 'form-control stretch',
                    'placeholder' => Language::_('ClientMain.getfilters.field_package_name', true)
                ]
            )
        );
        $fields->setField($package_name);

        // Set the service meta filter
        $service_meta = $fields->label(
            Language::_('ClientMain.getfilters.field_service_meta', true),
            'service_meta'
        );
        $service_meta->attach(
            $fields->fieldText(
                'filters[service_meta]',
                isset($vars['service_meta']) ? $vars['service_meta'] : null,
                [
                    'id' => 'service_meta',
                    'class' => 'form-control stretch',
                    'placeholder' => Language::_('ClientMain.getfilters.field_service_meta', true)
                ]
            )
        );
        $fields->setField($service_meta);

        return $fields;
    }
}
