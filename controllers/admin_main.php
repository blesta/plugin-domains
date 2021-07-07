<?php

use Blesta\Core\Util\Input\Fields\InputFields;

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

        $this->structure->set('page_title', Language::_('AdminMain.index.page_title', true));
    }

    /**
     * Redirects to the Domains Browse view
     */
    public function index()
    {
        $this->redirect($this->base_uri . 'plugin/domains/admin_domains/browse/');
    }

    /**
     * Returns the widget listing the Domains for a given client
     */
    public function domains()
    {
        $this->uses(['Clients', 'Services', 'Companies', 'Packages', 'ModuleManager']);

        // Process bulk domains options
        if (!empty($this->post) && isset($this->post['service_ids'])) {
            if (($errors = $this->updateDomains($this->post))) {
                $this->set('vars', (object)$this->post);
                $this->setMessage('error', $errors, false, null, false);
            } else {
                $term = 'AdminMain.!success.';
                $term .= isset($this->post['action']) ? $this->post['action'] : '';

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

        // Filter by domains type
        $domains_filters = $post_filters;
        $domains_filters['type'] = 'domains';

        $status = (isset($this->get[1]) ? $this->get[1] : 'active');
        $page = (isset($this->get[2]) ? (int)$this->get[2] : 1);
        $sort = (isset($this->get['sort']) ? $this->get['sort'] : 'date_added');
        $order = (isset($this->get['order']) ? $this->get['order'] : 'desc');

        // Get only parent domains
        $domains = $this->Services->getList($client->id, $status, $page, [$sort => $order], false, $domains_filters);
        $total_results = $this->Services->getListCount($client->id, $status, false, null, $domains_filters);

        // Set the number of domains of each type, not including children
        $status_count = [
            'active' => $this->Services->getStatusCount($client->id, 'active', false, $domains_filters),
            'canceled' => $this->Services->getStatusCount($client->id, 'canceled', false, $domains_filters),
            'pending' => $this->Services->getStatusCount($client->id, 'pending', false, $domains_filters),
            'suspended' => $this->Services->getStatusCount($client->id, 'suspended', false, $domains_filters)
        ];

        // Set the expected service renewal price
        $modules = [];
        foreach ($domains as $domain) {
            $module_id = $domain->package->module_id;
            if (!isset($modules[$module_id])) {
                $modules[$module_id] = $this->ModuleManager->initModule($module_id);
            }

            $domain->renewal_price = $this->Services->getRenewalPrice($domain->id);
            $domain->registrar = $modules[$module_id]->getName();
        }

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
                'uri' => $this->base_uri . 'plugin/domains/admin_main/index/' . $client->id . '/' . $status . '/[p]/',
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
        $this->uses(['Services']);

        $client_id = isset($this->get[0]) ? $this->get[0] : null;
        $status = isset($this->get[1]) ? $this->get[1] : 'active';

        echo $this->Services->getStatusCount($client_id, $status, false, ['type' => 'domains']);

        return false;
    }

    /**
     * Gets a list of possible domain actions
     *
     * @return array A list of possible domain actions and their language
     */
    public function getDomainActions()
    {
        return ['change_auto_renewal' => Language::_('AdminMain.index.change_auto_renewal', true)];
    }

    /**
     * Updates the given domains
     *
     * @param array $data An array of POST data including:
     *
     *  - service_ids An array of each service ID
     *  - action The action to perform, e.g. "change_auto_renewal"
     * @return mixed An array of errors, or false otherwise
     */
    private function updateDomains(array $data)
    {
        $this->uses(['Services']);

        // Require authorization to update a client's service
        if (!$this->authorized('admin_clients', 'editservice')) {
            $this->flashMessage('error', Language::_('AppController.!error.unauthorized_access', true), null, false);
            $this->redirect($this->base_uri . 'clients/');
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
                isset($vars['package_name']) ? $vars['package_name'] : null,
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
                isset($vars['service_meta']) ? $vars['service_meta'] : null,
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
