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
    public function index($widget = false)
    {
        // Load required models
        $this->uses(['Domains.DomainsTlds', 'Domains.DomainsDomains', 'Companies', 'ModuleManager', 'Services', 'Packages']);

        // Force the action to index
        $this->action = 'index';

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
        $status = ($this->get[0] ?? 'active');
        $page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
        $sort = ($this->get['sort'] ?? 'date_added');
        $order = ($this->get['order'] ?? 'desc');

        $domains_filters = array_merge([
            'client_id' => $this->client->id,
            'status' => $status
        ], $post_filters);

        $services = $this->DomainsDomains->getList($domains_filters, $page, [$sort => $order]);
        $total_results = $this->DomainsDomains->getListCount($domains_filters);

        // Set the number of services of each type, not including children
        $status_count = [
            'active' => $this->DomainsDomains->getStatusCount('active', $domains_filters),
            'canceled' => $this->DomainsDomains->getStatusCount('canceled', $domains_filters),
            'pending' => $this->DomainsDomains->getStatusCount('pending', $domains_filters),
            'suspended' => $this->DomainsDomains->getStatusCount('suspended', $domains_filters),
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
        $this->set('action', ($widget ? 'widget' : $this->action));

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
     * Client widget
     */
    public function widget()
    {
        return $this->index(true);
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

        // Check if the service belongs to a parent service
        if (!empty($domain->parent_service_id)) {
            $domain->parent_service = $this->Services->get($domain->parent_service_id);
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
