<?php

use Blesta\Core\Pricing\Presenter\Type\PresenterInterface;
use Blesta\Core\Util\Input\Fields\Html as FieldsHtml;
use Blesta\Core\Util\Input\Fields\InputFields;
use Blesta\Core\Util\PackageOptions\Logic as OptionLogic;

/**
 * Domain Manager client_main controller
 *
 * @link https://www.blesta.com Blesta
 */
class ClientMain extends DomainsController
{
    /**
     * @var string The base URI of this controller
     */
    private $plugin_uri;

    /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();

        // Get client
        $this->uses(['Clients']);
        $this->client = $this->Clients->get($this->Session->read('blesta_client_id'), true);

        // Only active clients may manage their domains, the same as in ClientController
        if (!$this->client || $this->client->status != 'active') {
            $this->Session->clear();

            if ($this->isAjax()) {
                header($this->server_protocol . ' 403 Forbidden');
                exit();
            }

            $this->flashMessage(
                'error',
                Language::_('AppController.!error.client_unauthorized_access', true),
                null,
                false
            );
            $this->redirect($this->base_uri);
        }

        // The domain management views reuse the core service language and partials
        Language::loadLang('client_services', null, ROOTWEBDIR . 'language' . DS);

        $this->plugin_uri = $this->base_uri . 'plugin/domains/client_main/';
        $this->set('plugin_uri', $this->plugin_uri);

        $this->structure->set('page_title', Language::_('ClientMain.index.page_title', true, $this->client->id_code));

        // The views that manage a single domain promote one heading of their own. The list is
        // left alone, it promotes its widget title instead
        $managing = [
            'manage', 'tab', 'changeterm', 'upgrade', 'manageoptions', 'review', 'addons', 'addaddon'
        ];
        if (in_array(strtolower($this->action ?? ''), $managing)) {
            $this->structure->set('title', Language::_('ClientMain.manage.page_heading', true));
        }
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

            foreach ($post_filters as $filter => $value) {
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
                'uri' => $this->Html->safe($this->plugin_uri . 'index/' . $status . '/[p]/'),
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

        // Returned as raw html: both the widget's expandable row and the legacy table row
        // assign the response straight to innerHTML without decoding it. The view is fetched
        // directly rather than rendered, so resolve it against the active template here
        echo $this->view->fetch('client_main_serviceinfo', $this->getPluginViewDir('client_main_serviceinfo'));

        return false;
    }

    /**
     * Manage a domain
     */
    public function manage()
    {
        $this->uses([
            'Domains.DomainsDomains', 'Coupons', 'Invoices', 'ModuleManager', 'Packages', 'Services'
        ]);

        // Ensure we have a domain service belonging to this client
        if (!isset($this->get[0])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
        ) {
            $this->redirect($this->base_uri);
        }

        // Services that are not managed domains belong to the core service view
        if (!$this->DomainsDomains->isManagedDomain($service->id)) {
            $this->redirect($this->base_uri . 'services/manage/' . $service->id . '/');
        }

        $package = $this->Packages->get($service->package->id);
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;
        $module->setModuleRow($module->getModuleRow($service->module_row_id));

        // Set parent service
        $service->parent = null;
        if (!empty($service->parent_service_id)) {
            $service->parent = $this->Services->get($service->parent_service_id);
        }

        // Determine which domain features the registrar makes available
        $capabilities = $this->getDomainCapabilities($module, $package);

        // Fetch the content the module renders on the default tab
        $partial_tab_view = $this->processModuleTab(
            $module,
            'getClientManagementContent',
            $package,
            $service,
            true
        );
        if (!empty($partial_tab_view)) {
            $this->set('partial_tab_view', $partial_tab_view);
        }

        // Set sidebar tabs
        $this->buildTabs($service, $package, $module);
        $this->structure->set('page_title', Language::_('ClientMain.manage.page_title', true, $service->name));

        $this->setDomainDetails($service, $package, $module, $capabilities);

        if ($this->isAjax()) {
            return $this->renderAjaxWidgetIfAsync(false);
        }
    }

    /**
     * Fetches the domain specific data and sets the view variables and partials shared by the
     * domain management and tab pages
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @param stdClass $package An stdClass object representing the TLD package
     * @param Module $module An instance of the registrar module used by the service
     * @param array $capabilities A key/value list of the features the registrar makes available
     */
    private function setDomainDetails(stdClass $service, stdClass $package, $module, array $capabilities)
    {
        // Set the domain specific data
        $service->registration_date = $this->DomainsDomains->getRegistrationDate($service->id);
        $service->expiration_date = $this->DomainsDomains->getExpirationDate($service->id);
        $service->nameservers = $this->DomainsDomains->getNameservers($service->id);
        $service->auto_renewal = (empty($service->date_canceled) ? 'on' : 'off');
        $service->renewal_price = $this->Services->getRenewalPrice($service->id);

        // The registrar lock and the WHOIS summary are shown on the page, so they are fetched
        // here and cached like the name servers. Any error the registrar reports is left unhandled
        // on purpose, so that an unreachable registrar does not stop the rest of the page from rendering
        $registrar_lock = null;
        $domain_contacts = [];
        $module_id = $module->getModule()->id;
        if ($capabilities['lock']) {
            $registrar_lock = $this->getCachedRegistrarValue(
                'registrar_lock',
                $service->id,
                function () use ($service, $module, $module_id) {
                    return (bool)$this->ModuleManager->moduleRpc(
                        $module_id,
                        'getDomainIsLocked',
                        [$this->getDomainName(compact('service', 'module')), $service->module_row_id],
                        $service->module_row_id
                    );
                }
            );
        }

        if ($capabilities['contacts']) {
            $domain_contacts = $this->getCachedRegistrarValue(
                'contacts',
                $service->id,
                function () use ($service, $module, $module_id) {
                    return $this->formatDomainContacts(
                        $this->ModuleManager->moduleRpc(
                            $module_id,
                            'getDomainContacts',
                            [$this->getDomainName(compact('service', 'module')), $service->module_row_id],
                            $service->module_row_id
                        )
                    );
                }
            );
        }

        $this->setDomainView($service, $package, $module, $capabilities, [
            'registrar_lock' => $registrar_lock,
            'registrant' => $this->getRegistrantContact($domain_contacts),
            'id_protection' => $this->serviceOptionEnabled($service, 'id_protection')
        ]);
    }

    /**
     * Renders a module or plugin service tab
     */
    public function tab()
    {
        $this->uses(['Domains.DomainsDomains', 'Coupons', 'Invoices', 'ModuleManager', 'Packages', 'Services']);

        // Ensure we have a domain service belonging to this client
        if (!isset($this->get[0])
            || !isset($this->get[1])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
        ) {
            $this->redirect($this->base_uri);
        }

        // Disallow clients from viewing module/plugin tabs if the service is not active
        if ($service->status != 'active') {
            $statuses = $this->Services->getStatusTypes();
            $this->flashMessage(
                'error',
                Language::_('ClientServices.!error.tab_unavailable', true, $statuses[$service->status])
            );
            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        $package = $this->Packages->get($service->package->id);
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;

        // A numeric second argument identifies a plugin tab rather than a module tab
        $plugin_id = null;
        if (is_numeric($this->get[1])) {
            $valid_plugins = $this->Form->collapseObjectArray($package->plugins, 'plugin_id', 'plugin_id');
            if (!isset($this->get[2]) || !array_key_exists($this->get[1], $valid_plugins)) {
                $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
            }

            $plugin_id = $this->get[1];
            $method = $this->get[2];
            $tab_view = $this->processPluginTab($plugin_id, $method, $service);
        } else {
            $method = $this->get[1];
            $tab_view = $this->processModuleTab($module, $method, $package, $service);
        }

        // Set sidebar tabs
        $this->buildTabs($service, $package, $module, $method, $plugin_id);

        // Meridian keeps the hero and quick actions of the management page around the tab
        if ($this->layout == 'meridian') {
            $module->setModuleRow($module->getModuleRow($service->module_row_id));

            $service->parent = null;
            if (!empty($service->parent_service_id)) {
                $service->parent = $this->Services->get($service->parent_service_id);
            }

            $this->setDomainDetails($service, $package, $module, $this->getDomainCapabilities($module, $package));
        }

        $this->set('tab_view', $tab_view);
        $this->set('service', $service);
        $this->set('package', $package);

        if ($this->isAjax()) {
            return $this->renderAjaxWidgetIfAsync(false);
        }
    }

    /**
     * Enable or disable auto-renewal for a domain
     */
    public function autoRenewal()
    {
        $domain = $this->getManagedDomain();

        // Auto-renewal schedules or removes a cancellation, so it follows the client cancellation rules
        if (!$this->clientCanToggleAutoRenewal()) {
            if ($this->isAjax()) {
                exit();
            }
            $this->redirect($this->plugin_uri . 'manage/' . $domain['service']->id . '/');
        }

        if (!empty($this->post)) {
            $this->saveDomainSection('auto_renewal', $domain);
        }

        $this->uses(['Invoices']);

        $this->set('service', $domain['service']);
        $this->set('past_due_invoices', $this->Invoices->getAllWithService($domain['service']->id, null, 'past_due'));
        $this->set('vars', (object)['auto_renewal' => (empty($domain['service']->date_canceled) ? 'on' : 'off')]);

        return $this->renderModal('client_main_autorenewal');
    }

    /**
     * Update the name servers assigned to a domain
     */
    public function nameservers()
    {
        $domain = $this->getManagedDomain('nameservers');

        if (!empty($this->post)) {
            $this->saveDomainSection('nameservers', $domain);
        }

        $this->set('service', $domain['service']);
        $this->set('vars', (object)['nameservers' => $this->DomainsDomains->getNameservers($domain['service']->id)]);

        return $this->renderModal('client_main_nameservers');
    }

    /**
     * Lock or unlock a domain at the registrar
     */
    public function registrarLock()
    {
        $domain = $this->getManagedDomain('lock');

        if (!empty($this->post)) {
            $this->saveDomainSection('registrar_lock', $domain);
        }

        $locked = $this->ModuleManager->moduleRpc(
            $domain['module']->getModule()->id,
            'getDomainIsLocked',
            [$this->getDomainName($domain), $domain['service']->module_row_id],
            $domain['service']->module_row_id
        );

        $this->set('service', $domain['service']);
        $this->set('vars', (object)['registrar_lock' => ($locked ? 'on' : 'off')]);

        return $this->renderModal('client_main_registrarlock');
    }

    /**
     * Update the contacts assigned to a domain at the registrar
     */
    public function contacts()
    {
        $domain = $this->getManagedDomain('contacts');

        if (!empty($this->post)) {
            $this->saveDomainSection('contacts', $domain);
        }

        $contacts = $this->ModuleManager->moduleRpc(
            $domain['module']->getModule()->id,
            'getDomainContacts',
            [$this->getDomainName($domain), $domain['service']->module_row_id],
            $domain['service']->module_row_id
        );

        $this->set('service', $domain['service']);
        $this->set('domain_contacts', $this->formatDomainContacts($contacts));

        return $this->renderModal('client_main_contacts');
    }

    /**
     * Fetches the registrant from a set of normalized domain contacts, which is the contact the
     * WHOIS summary is built from
     *
     * @param array $contacts A key/value list of contact types and their field values
     * @see ClientMain::formatDomainContacts()
     * @return array The registrant contact, or an empty array when there is none
     */
    private function getRegistrantContact(array $contacts)
    {
        // Registrars name the registrant differently, so fall back to the first contact given
        foreach (['registrant', 'owner', 'registrant_contact'] as $type) {
            foreach ($contacts as $key => $contact) {
                if (strtolower((string)$key) === $type) {
                    return $contact;
                }
            }
        }

        return (empty($contacts) ? [] : reset($contacts));
    }

    /**
     * Determines whether the given configurable option feature is enabled for a domain
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @param string $option_name The name of the configurable option, e.g. "id_protection"
     * @return bool True if the option is set on the service, false otherwise
     */
    private function serviceOptionEnabled(stdClass $service, $option_name)
    {
        foreach (($service->options ?? []) as $option) {
            if ($option->option_name == $option_name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalizes the contacts returned by a registrar. Each contact may be given as an array or
     * as an object, the list may be keyed by contact type or numerically with the type held in
     * the external ID, and some registrars mix scalar values into the same response
     *
     * @param mixed $contacts The contacts as the registrar returned them
     * @return array A key/value list of contact types and their field values
     */
    private function formatDomainContacts($contacts)
    {
        $formatted = [];

        foreach ((array)$contacts as $key => $contact) {
            // Skip anything that does not describe a contact
            if (!is_array($contact) && !is_object($contact)) {
                continue;
            }

            $contact = (array)$contact;
            $type = (is_numeric($key) ? ($contact['external_id'] ?? $key) : $key);
            $formatted[$type] = $contact;
        }

        return $formatted;
    }

    /**
     * Request the EPP code of a domain
     */
    public function eppCode()
    {
        $domain = $this->getManagedDomain('epp');

        if (!empty($this->post)) {
            $this->saveDomainSection('epp', $domain);
        }

        $this->set('service', $domain['service']);

        return $this->renderModal('client_main_eppcode');
    }

    /**
     * Fetches the domain being managed, along with everything needed to act on it. Redirects
     * when the service is not an active domain belonging to this client, or when the registrar
     * does not make the given feature available
     *
     * @param string $capability The feature the request requires, if any (optional)
     * @return array An array containing the service, package, module and capabilities
     */
    private function getManagedDomain($capability = null)
    {
        $this->uses(['Domains.DomainsDomains', 'ModuleManager', 'Packages', 'Services']);

        // Domain settings may only be changed while the domain is active
        if (!isset($this->get[0])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
            || $service->status != 'active'
        ) {
            if ($this->isAjax()) {
                exit();
            }
            $this->redirect($this->base_uri);
        }

        $package = $this->Packages->get($service->package->id);
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;
        $module->setModuleRow($module->getModuleRow($service->module_row_id));

        $capabilities = $this->getDomainCapabilities($module, $package);

        if ($capability !== null && !$capabilities[$capability]) {
            if ($this->isAjax()) {
                exit();
            }
            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        return compact('service', 'package', 'module', 'capabilities');
    }

    /**
     * Determines whether the client may change a domain's auto-renewal, which schedules or removes an
     * end of term cancellation
     *
     * @return bool True if the client may change auto-renewal, false otherwise
     */
    private function clientCanToggleAutoRenewal()
    {
        return ($this->client->settings['clients_cancel_services'] ?? null) == 'true'
            && in_array(($this->client->settings['clients_cancel_options'] ?? 'both'), ['end_of_term', 'both']);
    }

    /**
     * Fetches a registrar value for the given domain from the cache, or from the registrar when it is not
     * cached. The value is only cached when the registrar reports no errors
     *
     * @param string $key The name of the cached value
     * @param int $service_id The ID of the domain service
     * @param callable $fetch A function that fetches the value from the registrar
     * @return mixed The value
     */
    private function getCachedRegistrarValue($key, $service_id, callable $fetch)
    {
        $cache_path = Configure::get('Blesta.company_id') . DS . 'plugins' . DS . 'domains' . DS;

        if (($cache = Cache::fetchCache($key . '_' . $service_id, $cache_path))) {
            return safe_unserialize(base64_decode($cache));
        }

        $value = $fetch();

        if (!$this->ModuleManager->errors() && Configure::get('Caching.on') && is_writable(CACHEDIR)) {
            try {
                Cache::writeCache(
                    $key . '_' . $service_id,
                    base64_encode(serialize($value)),
                    strtotime(Configure::get('Blesta.cache_length')) - time(),
                    $cache_path
                );
            } catch (\Throwable $e) {
                // Write to cache failed, so disable caching
                Configure::set('Caching.on', false);
            }
        }

        return $value;
    }

    /**
     * Discards a cached registrar value for the given domain
     *
     * @param string $key The name of the cached value
     * @param int $service_id The ID of the domain service
     */
    private function clearRegistrarCache($key, $service_id)
    {
        Cache::clearCache(
            $key . '_' . $service_id,
            Configure::get('Blesta.company_id') . DS . 'plugins' . DS . 'domains' . DS
        );
    }

    /**
     * Fetches the domain name of the given domain from its registrar
     *
     * @param array $domain An array of domain data
     * @see ClientMain::getManagedDomain()
     * @return string The domain name
     */
    private function getDomainName(array $domain)
    {
        return $this->ModuleManager->moduleRpc(
            $domain['module']->getModule()->id,
            'getServiceDomain',
            [$domain['service']],
            $domain['service']->module_row_id
        );
    }

    /**
     * Saves the given domain section and returns to the management page. The modals report
     * their outcome through a flash message, so this never returns
     *
     * @param string $section The domain section being saved
     * @param array $domain An array of domain data
     * @see ClientMain::getManagedDomain()
     */
    private function saveDomainSection($section, array $domain)
    {
        $this->post['section'] = $section;
        $errors = $this->processDomainSection($domain['service'], $domain['module'], $domain['capabilities']);

        if (!empty($errors)) {
            $this->flashMessage('error', $errors);
        } else {
            $this->flashMessage('message', Language::_('ClientMain.!success.' . $section, true));
        }

        $this->redirect($this->plugin_uri . 'manage/' . $domain['service']->id . '/');
    }

    /**
     * Outputs one of the domain option modals
     *
     * @param string $view The name of the view to render
     * @return bool False, to prevent the structure from being rendered around the modal
     */
    private function renderModal($view)
    {
        echo $this->modalStyle() . $this->view->fetch($view, $this->getPluginViewDir($view));

        return false;
    }

    /**
     * Fetches the style that widens a fetched modal. The template clones its global dialog at a
     * fixed width and injects the fragment with innerHTML, which does not run scripts, so the
     * width is set with a style element the fragment carries. Templates that do not provide the
     * view keep their own dialog width
     *
     * @return string The style to prepend to a modal, or an empty string
     */
    private function modalStyle()
    {
        $view = 'client_main_modal_style';

        if (($dir = $this->getPluginViewDir($view)) === null) {
            return '';
        }

        $style = clone $this->view;

        return $style->fetch($view, $dir);
    }

    /**
     * Determines which domain features the given registrar module makes available
     *
     * @param Module $module An instance of the registrar module used by the service
     * @param stdClass $package An stdClass object representing the TLD package
     * @return array A key/value list of features and whether they are available
     */
    private function getDomainCapabilities($module, stdClass $package)
    {
        return [
            'lock' => (
                $this->registrarSupports($module, 'getDomainIsLocked')
                && $this->registrarSupports($module, 'lockDomain')
                && $this->registrarSupports($module, 'unlockDomain')
            ),
            'contacts' => (
                $this->registrarSupports($module, 'getDomainContacts')
                && $this->registrarSupports($module, 'setDomainContacts')
            ),
            'epp' => (
                ($package->meta->epp_code ?? '0') == '1'
                && $module instanceof RegistrarModule
                && $module->supportsFeature('epp_code')
                && $this->registrarSupports($module, 'sendEppEmail')
            ),
            'nameservers' => $this->registrarSupports($module, 'setDomainNameservers')
        ];
    }

    /**
     * Processes a domain section submitted from the management page
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @param Module $module An instance of the registrar module used by the service
     * @param array $capabilities A key/value list of the features the registrar makes available
     * @return mixed An array of errors, or false otherwise
     */
    private function processDomainSection(stdClass $service, $module, array $capabilities)
    {
        $section = ($this->post['section'] ?? null);
        $module_id = $module->getModule()->id;

        switch ($section) {
            case 'auto_renewal':
                $this->uses(['Invoices', 'Users']);

                // Verify that the client's password is correct
                $user = $this->Users->get($this->Session->read('blesta_id'));
                $username = ($user ? $user->username : '');

                if (!$this->Users->auth($username, ['password' => ($this->post['password'] ?? null)])) {
                    return ['password' => ['mismatch' => Language::_('ClientServices.!error.password_mismatch', true)]];
                }

                if (($this->post['auto_renewal'] ?? null) == 'off') {
                    if (!empty($this->Invoices->getAllWithService($service->id, null, 'past_due'))) {
                        return [
                            'auto_renewal' => [
                                'past_due' => Language::_('ClientMain.!error.auto_renewal_past_due', true)
                            ]
                        ];
                    }

                    $this->Services->cancel($service->id, ['date_canceled' => 'end_of_term']);
                } else {
                    $this->Services->unCancel($service->id);
                }

                return $this->Services->errors();
            case 'nameservers':
                if (!$capabilities['nameservers']) {
                    return ['section' => ['unsupported' => Language::_('ClientMain.!error.unsupported', true)]];
                }

                // Discard any blank nameserver fields
                $nameservers = array_values(array_filter(
                    array_map('trim', (array)($this->post['nameservers'] ?? [])),
                    function ($nameserver) {
                        return ($nameserver !== '');
                    }
                ));

                $this->DomainsDomains->updateNameservers($service->id, $nameservers);

                if (($errors = $this->DomainsDomains->errors())) {
                    return $errors;
                }

                // The nameservers are cached by the model, so the stale entry must be discarded
                Cache::clearCache(
                    'nameservers_' . $service->id,
                    Configure::get('Blesta.company_id') . DS . 'plugins' . DS . 'domains' . DS
                );

                return false;
            case 'registrar_lock':
                if (!$capabilities['lock']) {
                    return ['section' => ['unsupported' => Language::_('ClientMain.!error.unsupported', true)]];
                }

                $domain_name = $this->ModuleManager->moduleRpc(
                    $module_id,
                    'getServiceDomain',
                    [$service],
                    $service->module_row_id
                );
                $this->ModuleManager->moduleRpc(
                    $module_id,
                    (($this->post['registrar_lock'] ?? null) == 'on' ? 'lockDomain' : 'unlockDomain'),
                    [$domain_name, $service->module_row_id],
                    $service->module_row_id
                );

                if (($errors = $this->ModuleManager->errors())) {
                    return $errors;
                }

                $this->clearRegistrarCache('registrar_lock', $service->id);

                return false;
            case 'contacts':
                if (!$capabilities['contacts']) {
                    return ['section' => ['unsupported' => Language::_('ClientMain.!error.unsupported', true)]];
                }

                $domain_name = $this->ModuleManager->moduleRpc(
                    $module_id,
                    'getServiceDomain',
                    [$service],
                    $service->module_row_id
                );
                $this->ModuleManager->moduleRpc(
                    $module_id,
                    'setDomainContacts',
                    [$domain_name, (array)($this->post['contacts'] ?? []), $service->module_row_id],
                    $service->module_row_id
                );

                if (($errors = $this->ModuleManager->errors())) {
                    return $errors;
                }

                $this->clearRegistrarCache('contacts', $service->id);

                return false;
            case 'epp':
                if (!$capabilities['epp']) {
                    return ['section' => ['unsupported' => Language::_('ClientMain.!error.unsupported', true)]];
                }

                $domain_name = $this->ModuleManager->moduleRpc(
                    $module_id,
                    'getServiceDomain',
                    [$service],
                    $service->module_row_id
                );
                $this->ModuleManager->moduleRpc(
                    $module_id,
                    'sendEppEmail',
                    [$domain_name, $service->module_row_id],
                    $service->module_row_id
                );

                return $this->ModuleManager->errors();
        }

        return ['section' => ['invalid' => Language::_('ClientMain.!error.invalid_section', true)]];
    }

    /**
     * Sets all of the view variables and partials used by the domain management page
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @param stdClass $package An stdClass object representing the TLD package
     * @param Module $module An instance of the registrar module used by the service
     * @param array $capabilities A key/value list of the features the registrar makes available
     * @param array $domain_vars A key/value list of additional domain data for the view
     */
    private function setDomainView(
        stdClass $service,
        stdClass $package,
        $module,
        array $capabilities,
        array $domain_vars = []
    ) {
        // Set language for periods
        $periods = $this->Packages->getPricingPeriods();
        foreach ($this->Packages->getPricingPeriods(true) as $period => $lang) {
            $periods[$period . '_plural'] = $lang;
        }

        // Set whether the client can cancel a service
        if (!$service->date_canceled
            || (
                $service->date_canceled
                && strtotime($this->Date->cast($service->date_canceled, 'date_time')) > strtotime(date('c'))
            )
        ) {
            $client_cancel_service = ($this->client->settings['clients_cancel_services'] ?? null) == 'true';
            $clients_cancel_options = ($this->client->settings['clients_cancel_options'] ?? 'both');
        } else {
            // Service is already canceled, can't cancel it again
            $client_cancel_service = false;
            $clients_cancel_options = 'both';
        }

        // Set whether the client can renew a domain
        $client_renew_service = ($this->client->settings['clients_renew_services'] ?? null) == 'true';

        // Set whether the client can change the service term
        $client_change_service_term = ($this->client->settings['client_change_service_term'] ?? null) == 'true';
        $alternate_service_terms = [];
        if ($client_change_service_term && isset($service->package_pricing->id)
            && isset($service->package_pricing->period) && $service->package_pricing->period != 'onetime') {
            $alternate_service_terms = $this->getPackageTerms($package, [$service->package_pricing->id]);
        }

        // Set whether the client can change to another package in the same group
        $client_change_service_package = ($this->client->settings['client_change_service_package'] ?? null) == 'true';
        if ($client_change_service_package) {
            $client_change_service_package = !empty($this->getUpgradablePackages(
                $package,
                ($service->parent_service_id ? 'addon' : 'standard')
            ));
        }

        // Set whether any config options are available to be added/updated
        $available_options = $this->getAvailableOptions($service);

        // Determine whether a recurring coupon applies to this service
        $recurring_coupon = false;
        if ($service->coupon_id && $service->date_renews) {
            $recurring_coupon = $this->Coupons->getRecurring(
                $service->coupon_id,
                $service->package_pricing->currency,
                $service->date_renews . 'Z'
            );
        }

        // Display a notice regarding this service having queued service changes
        $queued_changes = $this->getQueuedServiceChanges($service->id);
        if (!empty($queued_changes) && $this->queueServiceChanges()) {
            $this->setMessage(
                'notice',
                Language::_('ClientServices.!notice.queued_service_change', true),
                false,
                null,
                false
            );
        }

        $service_params = array_merge($domain_vars, [
            'periods' => $periods,
            'service' => $service,
            'package' => $package,
            'plugin_uri' => $this->plugin_uri,
            'next_invoice_date' => $this->Services->getNextInvoiceDate($service->id),
            'client_cancel_service' => $client_cancel_service,
            'clients_cancel_options' => $clients_cancel_options,
            'client_renew_service' => $client_renew_service,
            'client_change_service_term' => $client_change_service_term,
            'client_change_service_package' => $client_change_service_package,
            'alternate_service_terms' => $alternate_service_terms,
            'available_config_options' => (!empty($available_options)),
            'recurring_coupon' => $recurring_coupon,
            'queued_service_change' => !empty($queued_changes),
            'capabilities' => $capabilities,
            'overdue_invoice_count' => count(
                $this->Invoices->getAllWithService($service->id, $this->client->id, 'past_due')
            )
        ]);

        $this->set('service_hero', $this->partial('client_main_hero', $service_params));
        $this->set('service_infobox', $this->partial('client_main_infobox', $service_params));
        $this->set('quick_actions', $this->partial('client_main_quickactions', $service_params));

        // Meridian edits the configurable options in a modal rendered with the page, so that the
        // Option Logic script it carries still runs. The default template uses a full page
        if ($this->layout == 'meridian' && !empty($available_options)) {
            $this->set('config_options_modal', $this->partial('client_main_configoptions_modal', array_merge(
                $service_params,
                [
                    'package_options' => $this->getPackageOptionsPartial(
                        $service,
                        $package,
                        '#config_options_modal .modal-body'
                    ),
                    'unpaid_invoices' => $this->Invoices->getAllWithService(
                        $service->id,
                        $this->client->id,
                        'open'
                    )
                ]
            )));
        }
        $this->set('nameservers_card', $this->partial('client_main_nameservers_card', $service_params));
        $this->set('whois_card', $this->partial('client_main_whois', $service_params));

        $this->set('service', $service);
        $this->set('package', $package);
        $this->set('periods', $periods);

        // Display a notice regarding the service being suspended/canceled
        $error_notice = [];
        if (!empty($service->date_suspended)) {
            $error_notice[] = Language::_(
                'ClientServices.manage.text_date_suspended',
                true,
                $this->Date->cast($service->date_suspended)
            );
        }
        if (!empty($service->date_canceled)) {
            $scheduled = ($this->Date->toTime($this->Date->cast($service->date_canceled))
                > $this->Date->toTime($this->Date->cast(date('c'))));

            $error_notice[] = Language::_(
                'ClientServices.manage.text_date_' . ($scheduled ? 'to_cancel' : 'canceled'),
                true,
                $this->Date->cast($service->date_canceled)
            );
        }
        if (!empty($error_notice)) {
            $this->setMessage('error', ['notice' => $error_notice], false, null, false);
        }
    }

    /**
     * Processes and retrieves the module tab content for the given method
     *
     * @param Module $module The module instance
     * @param string $method The method on the module to call to retrieve the tab content
     * @param stdClass $package An stdClass object representing the package
     * @param stdClass $service An stdClass object representing the service being managed
     * @param bool $partial True to return the module tab as a partial view
     * @return string The tab content
     */
    private function processModuleTab($module, $method, stdClass $package, stdClass $service, $partial = false)
    {
        $content = '';

        // Get tabs
        $client_tabs = $module->getClientServiceTabs($service);
        if ($partial) {
            $client_tabs[$method] = $method;
        }
        $valid_method = array_key_exists(strtolower($method), array_change_key_case($client_tabs, CASE_LOWER));

        // Load/process the tab request
        if ($valid_method && is_callable([$module, $method])) {
            // Set the module row used for this service
            $module->setModuleRow($module->getModuleRow($service->module_row_id));

            // Call the module method and set any messages to the view
            $content = $module->{$method}($package, $service, $this->get, $this->post, $this->files);

            if (!$partial) {
                $this->setServiceTabMessages($module->errors(), $module->getMessages());
            }
        } elseif (!$partial) {
            // Invalid method called, redirect
            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        return $content;
    }

    /**
     * Processes and retrieves the plugin tab content for the given method
     *
     * @param int $plugin_id The ID of the plugin
     * @param string $method The method on the plugin to call to retrieve the tab content
     * @param stdClass $service An stdClass object representing the service being managed
     * @return string The tab content
     */
    private function processPluginTab($plugin_id, $method, stdClass $service)
    {
        $content = '';

        if (($plugin = $this->getPlugin($plugin_id))) {
            $plugin->base_uri = $this->base_uri;

            // Get tabs
            $client_tabs = $plugin->getClientServiceTabs($service);
            $valid_method = array_key_exists(strtolower($method), array_change_key_case($client_tabs, CASE_LOWER));

            // Retrieve the plugin tab content
            if ($valid_method && is_callable([$plugin, $method])) {
                $content = $plugin->{$method}($service, $this->get, $this->post, $this->files);
                $this->setServiceTabMessages($plugin->errors(), $plugin->getMessages());
            } else {
                $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
            }
        }

        return $content;
    }

    /**
     * Sets messages to the view based on the given errors and messages provided
     *
     * @param array|bool|null $errors An array of error messages (optional)
     * @param array $messages An array of any other messages keyed by type (optional)
     */
    private function setServiceTabMessages($errors = null, ?array $messages = null)
    {
        // Prioritize error messages over any other messages
        if (!empty($errors)) {
            $this->setMessage('error', $errors, false, null, false);
        } elseif (!empty($messages)) {
            foreach ($messages as $type => $message) {
                $this->setMessage($type, $message, false, null, false);
            }
        } elseif (!empty($this->post)) {
            $this->setMessage(
                'success',
                Language::_('ClientServices.!success.manage.tab_updated', true),
                false,
                null,
                false
            );
        }
    }

    /**
     * Retrieves an instance of the given plugin if it is enabled
     *
     * @param int $plugin_id The ID of the plugin
     * @return Plugin|null An instance of the plugin
     */
    private function getPlugin($plugin_id)
    {
        $this->uses(['PluginManager']);
        $this->components(['Plugins']);

        if (($plugin = $this->PluginManager->get($plugin_id)) && $plugin->enabled == '1') {
            try {
                return $this->Plugins->create($plugin->dir);
            } catch (Throwable $e) {
                // Do nothing
            }
        }

        return null;
    }

    /**
     * Builds and sets the sidebar tabs for the domain management views
     *
     * @param stdClass $service An stdClass object representing the service
     * @param stdClass $package An stdClass object representing the package used by the service
     * @param Module $module An instance of the module used by the service
     * @param string|null $method The method being called (i.e. the tab action, optional)
     * @param int|null $plugin_id The ID of the plugin being called (optional)
     */
    private function buildTabs(stdClass $service, stdClass $package, $module, $method = null, $plugin_id = null)
    {
        // Domain information tab
        $tabs = [
            [
                // Not loaded over AJAX: the management page spans several cards, while the
                // widget only swaps the content section of the one it is told about
                'name' => Language::_('ClientMain.manage.tab_domain_info', true),
                'attributes' => ['href' => $this->plugin_uri . 'manage/' . $service->id . '/'],
                'current' => ($plugin_id === null && $method === null),
                'icon' => 'fas fa-info-circle'
            ]
        ];

        // Determine whether addons are accessible
        $has_addons = $this->Services->hasChildren($service->id);
        if (!$has_addons) {
            $has_addons = !empty($this->getAddonPackages($service->package_group_id));
        }

        if ($has_addons) {
            $tabs[] = [
                'name' => Language::_('ClientServices.manage.tab_addons', true),
                'attributes' => ['href' => $this->plugin_uri . 'addons/' . $service->id . '/', 'class' => 'ajax'],
                'current' => ($plugin_id === null && $method == 'addons'),
                'icon' => 'fas fa-plus-circle'
            ];
        }

        $tabs = array_merge($tabs, $this->formatExtensionTabs($service, $package, $module, $method, $plugin_id));

        // The management page returns to the domain list, every page below it returns to the
        // domain being managed
        $on_manage_page = (strtolower($this->action ?? '') === 'manage');
        $tabs[] = [
            'name' => ($on_manage_page
                ? Language::_('ClientMain.manage.tab_domain_return', true)
                : Language::_('ClientMain.manage.tab_service_return', true, $service->name)
            ),
            'attributes' => [
                'href' => ($on_manage_page
                    ? $this->plugin_uri . 'index/'
                    : $this->plugin_uri . 'manage/' . $service->id . '/'
                )
            ],
            'current' => false,
            'icon' => 'fas fa-arrow-left'
        ];

        $this->set('tabs', $this->partial('client_main_tabs', ['tabs' => $tabs]));
    }

    /**
     * Formats module and plugin tabs into an array of tabs
     *
     * @param stdClass $service An stdClass object representing the service
     * @param stdClass $package An stdClass object representing the package used by the service
     * @param Module $module An instance of the module used by the service
     * @param string|null $method The method being called (i.e. the tab action, optional)
     * @param int|null $plugin_id The ID of the plugin being called (optional)
     * @return array An array of tabs
     */
    private function formatExtensionTabs(
        stdClass $service,
        stdClass $package,
        $module,
        $method = null,
        $plugin_id = null
    ) {
        $extension_tabs = [];

        // Module and plugin tabs are only available while the domain is active
        if ($service->status != 'active') {
            return $extension_tabs;
        }

        // Set the module tabs
        foreach ($module->getClientServiceTabs($service) as $action => $link) {
            if (!is_array($link)) {
                $link = ['name' => $link];
            }

            if (!isset($link['href'])) {
                $link['href'] = $this->plugin_uri . 'tab/' . $service->id . '/' . $action . '/';
                $link['class'] = 'ajax';
            }

            $extension_tabs[] = [
                'name' => $link['name'],
                'attributes' => ['href' => $link['href'], 'class' => ($link['class'] ?? '')],
                'current' => ($plugin_id === null && strtolower($action) == strtolower($method ?? '')),
                'icon' => ($link['icon'] ?? 'fas fa-cog')
            ];
        }

        // Set the plugin tabs
        foreach ($package->plugins as $plug) {
            if (!($plugin = $this->getPlugin($plug->plugin_id))) {
                continue;
            }

            foreach ($plugin->getClientServiceTabs($service) as $action => $tab) {
                $extension_tabs[] = [
                    'name' => $tab['name'],
                    'attributes' => [
                        'href' => (!empty($tab['href'])
                            ? $tab['href']
                            : $this->plugin_uri . 'tab/' . $service->id . '/' . $plug->plugin_id . '/' . $action . '/'
                        ),
                        'class' => 'ajax'
                    ],
                    'current' => ($plug->plugin_id == $plugin_id && strtolower($action) === strtolower($method ?? '')),
                    'icon' => ($tab['icon'] ?? 'fas fa-cog')
                ];
            }
        }

        return $extension_tabs;
    }

    /**
     * Renders a view belonging to the core client template. The domain management views reuse the
     * core partials that carry no service URIs of their own, so they stay visually identical to
     * the service views without being duplicated here
     *
     * @param string $view The name of the core view to render
     * @param array $params An array of parameters to set in the view
     * @return string The rendered view
     */
    private function corePartial($view, array $params = [])
    {
        $partial = clone $this->view;
        $partial->setDefaultView(APPDIR);
        $partial->set($params);

        return $partial->fetch($view, $this->portal . DS . $this->layout);
    }

    /**
     * Renew a domain for a number of years
     */
    public function renew()
    {
        $this->uses(['Domains.DomainsDomains', 'Invoices', 'Packages', 'Services', 'Users']);

        $client_can_renew_service = ($this->client->settings['clients_renew_services'] ?? null) == 'true';
        $client_can_change_term = ($this->client->settings['client_change_service_term'] ?? null) == 'true';

        // Ensure we have a domain that belongs to the client and is not currently canceled or suspended
        if (!$client_can_renew_service
            || !isset($this->get[0])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
            || in_array($service->status, ['canceled', 'suspended'])
        ) {
            if ($this->isAjax()) {
                exit();
            }
            $this->redirect($this->base_uri);
        }

        $package = $this->Packages->get($service->package->id);

        // Only yearly terms may be used to renew a domain
        [$terms, $years] = $this->getYearTerms($package, $service);

        if (empty($terms)) {
            if ($this->isAjax()) {
                exit();
            }
            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        // Set the confirmation message for renewing the domain for each term
        $renew_messages = [];
        foreach ($years as $pricing_id => $term) {
            $renew_messages[$pricing_id] = Language::_(
                'ClientMain.renew.confirm_renew',
                true,
                $terms[$pricing_id],
                $this->Date->modify(
                    date('c', strtotime($service->date_renews ?? 'now')),
                    '+' . $term . ' years',
                    'date',
                    Configure::get('Blesta.company_timezone')
                )
            );
        }

        if (!empty($this->post)) {
            // Verify that the client's password is correct, set $errors otherwise
            $user = $this->Users->get($this->Session->read('blesta_id'));
            $username = ($user ? $user->username : '');

            if ($this->Users->auth($username, ['password' => ($this->post['password'] ?? null)])) {
                // If clients can't change the term, only the current one may be used
                if (!$client_can_change_term) {
                    $this->post['pricing_id'] = $service->package_pricing->id;
                }

                // Redirect if no valid term pricing ID was given
                if (empty($this->post['pricing_id']) || !array_key_exists($this->post['pricing_id'], $years)) {
                    $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
                }

                $this->DomainsDomains->renewDomain($service->id, $years[$this->post['pricing_id']]);
                $errors = $this->DomainsDomains->errors();
            } else {
                $errors = ['password' => ['mismatch' => Language::_('ClientServices.!error.password_mismatch', true)]];
            }

            if (!empty($errors)) {
                $this->flashMessage('error', $errors);
                $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
            }

            $this->flashMessage('message', Language::_('ClientMain.!success.domain_renewed', true));
            $this->redirect($this->base_uri . 'pay/');
        }

        echo $this->modalStyle() . $this->corePartial('client_services_renew', [
            'service' => $service,
            'package' => $package,
            'vars' => (object)[],
            'renew_messages' => $renew_messages,
            'client_can_change_term' => $client_can_change_term,
            'terms' => ['' => Language::_('AppController.select.please', true)] + $terms
        ]);

        return false;
    }

    /**
     * Cancel a domain
     */
    public function cancel()
    {
        $this->uses(['Currencies', 'Domains.DomainsDomains', 'Invoices', 'Packages', 'Services', 'Users']);
        $this->components(['SettingsCollection']);

        $client_can_cancel_service = ($this->client->settings['clients_cancel_services'] ?? null) == 'true';

        // Ensure we have a domain that belongs to the client and is not currently canceled or suspended
        if (!$client_can_cancel_service
            || !isset($this->get[0])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
            || in_array($service->status, ['canceled', 'suspended'])
        ) {
            if ($this->isAjax()) {
                exit();
            }
            $this->redirect($this->base_uri);
        }

        $clients_cancel_options = ($this->client->settings['clients_cancel_options'] ?? 'both');

        if (!empty($this->post)) {
            $data = [
                'date_canceled' => ($this->post['date_canceled'] ?? null),
                'cancellation_reason' => ($this->post['cancellation_reason'] ?? null)
            ];

            // Verify that the client's password is correct, set $errors otherwise
            $user = $this->Users->get($this->Session->read('blesta_id'));
            $username = ($user ? $user->username : '');

            if ($this->Users->auth($username, ['password' => ($this->post['password'] ?? null)])) {
                $past_due_invoices = $this->Invoices->getAllWithService($service->id, null, 'past_due');

                switch ($this->post['cancel']) {
                    case 'now':
                        if (empty($past_due_invoices)
                            && in_array($clients_cancel_options, ['now', 'both'])
                        ) {
                            $this->Services->cancel($service->id, $data);
                        }
                        break;
                    case 'term':
                        if (empty($past_due_invoices)
                            && in_array($clients_cancel_options, ['end_of_term', 'both'])
                        ) {
                            $data['date_canceled'] = 'end_of_term';
                            $this->Services->cancel($service->id, $data);
                        }
                        break;
                    default:
                        // Do not cancel
                        $this->Services->unCancel($service->id);
                        break;
                }
            } else {
                $errors = ['password' => ['mismatch' => Language::_('ClientServices.!error.password_mismatch', true)]];
            }

            if (!empty($errors) || ($errors = $this->Services->errors())) {
                $this->flashMessage('error', $errors);
            } else {
                $this->flashMessage(
                    'message',
                    Language::_(
                        'ClientServices.!success.service_'
                        . ($this->post['cancel'] == 'term' ? 'schedule_' : ($this->post['cancel'] == '' ? 'not_' : ''))
                        . 'canceled',
                        true
                    )
                );
            }

            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        // Set the confirmation message for canceling the domain
        $cancel_messages = [
            'now' => Language::_('ClientServices.cancel.confirm_cancel_now', true),
            'term' => Language::_('ClientServices.cancel.confirm_cancel', true)
        ];
        if (($service->package_pricing->cancel_fee ?? 0) > 0) {
            $client_settings = $this->SettingsCollection->fetchClientSettings($service->client_id);

            $pricing_info = ($client_settings['default_currency'] != $service->package_pricing->currency
                ? $this->Services->getPricingInfo($service->id, $client_settings['default_currency'])
                : $this->Services->getPricingInfo($service->id)
            );

            if ($pricing_info) {
                $cancellation_fee = $this->Currencies->toCurrency(
                    $pricing_info->cancel_fee,
                    $pricing_info->currency,
                    $this->company_id
                );

                $cancel_messages['now'] = Language::_('ClientServices.cancel.confirm_cancel_now', true) . ' '
                    . Language::_(
                        'ClientServices.cancel.confirm_cancel_now_fee' . ($pricing_info->tax ? '_tax' : ''),
                        true,
                        $cancellation_fee
                    );
            }
        }

        foreach ($cancel_messages as $key => $message) {
            $cancel_messages[$key] = $this->setMessage('notice', $message, true, null, false);
        }

        echo $this->modalStyle() . $this->corePartial('client_services_cancel', [
            'service' => $service,
            'package' => $this->Packages->get($service->package->id),
            'past_due_invoices' => $this->Invoices->getAllWithService($service->id, null, 'past_due'),
            'vars' => (object)['cancel' => ''],
            'clients_cancel_options' => $clients_cancel_options,
            'confirm_cancel_messages' => $cancel_messages
        ]);

        return false;
    }

    /**
     * Fetches the yearly terms available to the given package, priced as the domain would be renewed
     *
     * @param stdClass $package An stdClass object representing the TLD package
     * @param stdClass $service An stdClass object representing the domain service
     * @return array An array containing a key/value list of pricing IDs and their term language,
     *  and a key/value list of pricing IDs and the number of years they represent
     */
    private function getYearTerms(stdClass $package, stdClass $service)
    {
        $terms = [];
        $years = [];

        foreach (($package->pricing ?? []) as $pricing) {
            if ($pricing->period !== 'year') {
                continue;
            }

            // The service's override price is billed only when renewing for its current term
            $amount = ($pricing->price_renews ?? 0);
            $currency = $pricing->currency;
            if ($service->pricing_id == $pricing->id
                && !empty($service->override_price)
                && !empty($service->override_currency)
            ) {
                $amount = $service->override_price;
                $currency = $service->override_currency;
            }

            $terms[$pricing->id] = Language::_(
                'ClientMain.renew.term' . ($pricing->term == 1 ? '' : 's'),
                true,
                $pricing->term,
                $this->CurrencyFormat->format($amount, $currency)
            );
            $years[$pricing->id] = $pricing->term;
        }

        return [$terms, $years];
    }

    /**
     * Change the domain term
     */
    public function changeTerm()
    {
        $this->uses(['Domains.DomainsDomains', 'Invoices', 'ModuleManager', 'Packages', 'Services']);

        $client_can_change_service_term = ($this->client->settings['client_change_service_term'] ?? null) == 'true';

        // Ensure we have a domain with alternate package terms available to change to
        if (!$client_can_change_service_term
            || !isset($this->get[0])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
            || $service->status != 'active'
            || !empty($service->date_canceled)
            || ($service->package_pricing->period == 'onetime')
            || !($package = $this->Packages->get($service->package->id))
            || !($terms = $this->getPackageTerms($package, [], $service))
        ) {
            $this->redirect($this->base_uri);
        }

        // Changes may not be made to the domain while a pending change currently exists
        if (!empty($this->getQueuedServiceChanges($service->id)) && $this->queueServiceChanges()) {
            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        $this->Session->clear('client_update_domain');

        // Determine whether invoices for this domain remain unpaid
        $unpaid_invoices = $this->Invoices->getAllWithService($service->id, $this->client->id, 'open');

        // Remove current term
        $current_term = ($terms[$service->package_pricing->id] ?? '');
        unset($terms[$service->package_pricing->id]);

        if (!empty($this->post)) {
            // Disallow term change if the current domain has not been paid
            if (!empty($unpaid_invoices)) {
                $this->flashMessage('error', Language::_('ClientServices.!error.invoices_change_term', true));
                $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
            }

            // Redirect if no valid term pricing ID was given
            if (empty($this->post['pricing_id']) || !array_key_exists($this->post['pricing_id'], $terms)) {
                $this->redirect($this->plugin_uri . 'changeterm/' . $service->id . '/');
            }

            // Remove override prices
            $vars = $this->post;
            $vars['override_price'] = null;
            $vars['override_currency'] = null;

            $this->Session->write(
                'client_update_domain',
                ['service_id' => $service->id, 'vars' => $vars, 'type' => 'service_term']
            );
            $this->redirect($this->plugin_uri . 'review/' . $service->id . '/');
        }

        $this->set('package', $package);
        $this->set('service', $service);
        $this->set('terms', ['' => Language::_('AppController.select.please', true)] + $terms);
        $this->set('current_term', $current_term);
        $this->set('unpaid_invoices', $unpaid_invoices);

        // Set sidebar tabs
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;
        $this->buildTabs($service, $package, $module, 'changeterm');
    }

    /**
     * Change the domain package
     */
    public function upgrade()
    {
        $this->uses(['Domains.DomainsDomains', 'Invoices', 'ModuleManager', 'Packages', 'Services']);

        $client_change_service_package = ($this->client->settings['client_change_service_package'] ?? null) == 'true';
        $service = null;
        $upgradable_packages = [];

        if ($client_change_service_package && isset($this->get[0])) {
            $service = $this->Services->get((int)$this->get[0]);
        }
        if ($service) {
            $upgradable_packages = $this->getUpgradablePackages(
                $service->package,
                ($service->parent_service_id ? 'addon' : 'standard')
            );
        }

        // Ensure we have a valid domain with packages that can be changed
        if (!$client_change_service_package
            || !$service
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
            || $service->status != 'active'
            || !empty($service->date_canceled)
            || empty($upgradable_packages)
        ) {
            $this->redirect($this->base_uri);
        }

        // Changes may not be made to the domain while a pending change currently exists
        if (!empty($this->getQueuedServiceChanges($service->id)) && $this->queueServiceChanges()) {
            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        $this->Session->clear('client_update_domain');

        $unpaid_invoices = $this->Invoices->getAllWithService($service->id, $this->client->id, 'open');

        // Build the list of package terms available
        $terms = [];
        foreach ($upgradable_packages as $pack) {
            $group_terms = $this->getPackageTerms($pack, [], $service, false, true, true);
            if (!empty($group_terms)) {
                $terms['package_' . $pack->id] = ['name' => $pack->name, 'value' => 'optgroup'];
                $terms += $group_terms;
            }
        }

        if (!empty($this->post)) {
            // Disallow the change if the current domain has not been paid
            if (!empty($unpaid_invoices)) {
                $this->flashMessage('error', Language::_('ClientServices.!error.invoices_upgrade_package', true));
                $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
            }

            // Redirect if no valid term pricing ID was given
            if (empty($this->post['pricing_id']) || !array_key_exists($this->post['pricing_id'], $terms)) {
                $this->redirect($this->plugin_uri . 'upgrade/' . $service->id . '/');
            }

            // Set client limit error if it has been reached
            if (($package = $this->Packages->getByPricingId($this->post['pricing_id']))
                && $package->client_qty !== null
                && $package->client_qty <= $this->Services->getListCount($this->client->id, 'all', true, $package->id)
            ) {
                $this->flashMessage('error', Language::_('ClientServices.!notice.client_limit', true));
                $this->redirect($this->plugin_uri . 'upgrade/' . $service->id . '/');
            }

            // Remove override prices
            $vars = $this->post;
            $vars['override_price'] = null;
            $vars['override_currency'] = null;

            $this->Session->write(
                'client_update_domain',
                ['service_id' => $service->id, 'vars' => $vars, 'type' => 'service_package']
            );
            $this->redirect($this->plugin_uri . 'review/' . $service->id . '/');
        }

        // Set the current package and term
        $singular_periods = $this->Packages->getPricingPeriods();
        $plural_periods = $this->Packages->getPricingPeriods(true);
        $amount = ($service->package_pricing->price_renews ?? $service->package_pricing->price);
        $currency = $service->package_pricing->currency;
        if (!empty($service->override_price) && !empty($service->override_currency)) {
            $amount = $service->override_price;
            $currency = $service->override_currency;
        }

        $period = ($service->package_pricing->term != 1
            ? $plural_periods[$service->package_pricing->period]
            : $singular_periods[$service->package_pricing->period]
        );
        $current_term = ($service->package_pricing->period == 'onetime'
            ? Language::_(
                'ClientServices.upgrade.current_package_onetime',
                true,
                $service->package->name,
                $period,
                $this->CurrencyFormat->format($amount, $currency)
            )
            : Language::_(
                'ClientServices.upgrade.current_package',
                true,
                $service->package->name,
                $service->package_pricing->term,
                $period,
                $this->CurrencyFormat->format($amount, $currency)
            )
        );

        $this->set('service', $service);
        $this->set('terms', ['' => Language::_('AppController.select.please', true)] + $terms);
        $this->set('current_term', $current_term);
        $this->set('unpaid_invoices', $unpaid_invoices);

        // Set sidebar tabs
        $package = $this->Packages->get($service->package->id);
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;
        $this->buildTabs($service, $package, $module, 'changeterm');
    }

    /**
     * Manage the configurable options of a domain
     */
    public function manageOptions()
    {
        $this->uses([
            'Domains.DomainsDomains', 'Invoices', 'ModuleManager', 'PackageOptionConditionSets',
            'PackageOptions', 'Packages', 'Services'
        ]);

        // Determine whether a valid domain is given and whether available options exist to be managed
        if (!isset($this->get[0])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
            || $service->status != 'active'
            || !$this->getAvailableOptions($service)
        ) {
            $this->redirect($this->base_uri);
        }

        // Changes may not be made to the domain while a pending change currently exists
        if (!empty($this->getQueuedServiceChanges($service->id)) && $this->queueServiceChanges()) {
            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        $this->Session->clear('client_update_domain');

        $unpaid_invoices = $this->Invoices->getAllWithService($service->id, $this->client->id, 'open');

        // Save the selected config options for the review step
        if (!empty($this->post)) {
            if (!empty($unpaid_invoices)) {
                $this->flashMessage('error', Language::_('ClientServices.!error.invoices_manage_options', true));
                $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
            }

            $this->Session->write(
                'client_update_domain',
                [
                    'service_id' => $service->id,
                    'vars' => ['configoptions' => (array)($this->post['configoptions'] ?? [])],
                    'type' => 'config_options'
                ]
            );
            $this->redirect($this->plugin_uri . 'review/' . $service->id . '/');
        }

        // Set sidebar tabs
        $package = $this->Packages->get($service->package->id);
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;
        $this->buildTabs($service, $package, $module);

        $package_options = $this->getPackageOptionsPartial($service, $package, '.card-body');

        $this->set('service', $service);
        $this->set('package', $package);
        $this->set('module', $module);
        $this->set('available_options', !empty($package_options));
        $this->set('unpaid_invoices', $unpaid_invoices);
        $this->set('package_options', $package_options);
    }

    /**
     * Builds the partial holding the configurable options a client may add or update, together
     * with the Option Logic that drives them
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @param stdClass $package An stdClass object representing the TLD package
     * @param string $selector The CSS selector of the container holding the options
     * @return string The rendered partial
     */
    private function getPackageOptionsPartial(stdClass $service, stdClass $package, $selector)
    {
        $this->uses(['PackageOptions']);

        $pricing = $service->package_pricing;
        $options = $this->PackageOptions->formatServiceOptions($service->options);
        $options['service_id'] = $service->id;
        $option_ids = array_keys($options['configoptions'] ?? []);

        // Fetch only editable package options that are already set
        $edit_fields = $this->getPackageOptionFields(
            $package->id,
            $pricing->term,
            $pricing->period,
            $pricing->currency,
            (object)$options,
            null,
            array_merge(['new' => 0, 'editable' => 1, 'allow' => $option_ids], $options)
        );

        // Fetch only addable package options that are not already set
        $add_fields = $this->getPackageOptionFields(
            $package->id,
            $pricing->term,
            $pricing->period,
            $pricing->currency,
            (object)$options,
            null,
            array_merge(['new' => 1, 'addable' => 1, 'disallow' => $option_ids], $options)
        );

        if (empty($edit_fields) && empty($add_fields)) {
            return '';
        }

        return $this->corePartial(
            'client_services_manage_package_options',
            [
                'add_fields' => $add_fields,
                'edit_fields' => $edit_fields,
                'show_no_options_message' => true,
                'option_logic_js' => $this->getOptionLogic($service, $package, $pricing, $selector)->getJavascript()
            ]
        );
    }

    /**
     * AJAX Fetch all package options for the given pricing ID and domain
     */
    public function packageOptions()
    {
        $this->uses(['PackageOptionConditionSets', 'PackageOptions', 'Packages', 'Services']);

        // Ensure we have a valid pricing ID and service ID
        if (!isset($this->get[0])
            || !isset($this->get[1])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !($package = $this->Packages->getByPricingId((int)$this->get[1]))
        ) {
            if ($this->isAjax()) {
                header($this->server_protocol . ' 401 Unauthorized');
                exit();
            }
            $this->redirect($this->base_uri);
        }

        $pricing = $this->getPricing($package->pricing, (int)$this->get[1]);

        $options = $this->PackageOptions->formatServiceOptions($service->options);
        $options['service_id'] = $service->id;
        $option_ids = array_keys($options['configoptions'] ?? []);
        $upgrade = ($service->package->id != $package->id);

        $add_fields = false;
        $edit_fields = false;
        if ($pricing) {
            // Fetch only editable package options that are already set
            $edit_fields = $this->getPackageOptionFields(
                $pricing->package_id,
                $pricing->term,
                $pricing->period,
                $pricing->currency,
                (object)$options,
                null,
                array_merge(
                    ['new' => 0, 'editable' => 1, 'allow' => $option_ids, 'upgrade' => $upgrade],
                    $options
                )
            );

            // Fetch only addable package options that are not already set
            $add_fields = $this->getPackageOptionFields(
                $pricing->package_id,
                $pricing->term,
                $pricing->period,
                $pricing->currency,
                (object)$options,
                null,
                array_merge(
                    ['new' => 1, 'addable' => 1, 'disallow' => $option_ids, 'upgrade' => $upgrade],
                    $options
                )
            );
        }

        $output = ['html' => '', 'limit_reached' => false];
        if ($package->client_qty !== null
            && $package->client_qty <= $this->Services->getListCount($this->client->id, 'all', true, $package->id)
        ) {
            $output['limit_reached'] = true;
            $output['html'] .= $this->setMessage(
                'error',
                Language::_('ClientServices.!notice.client_limit', true),
                true,
                null,
                false
            );
        }

        $option_logic = $this->getOptionLogic($service, $package, $service->package_pricing, '#package_options');

        $output['html'] .= $this->corePartial(
            'client_services_manage_package_options',
            [
                'add_fields' => $add_fields,
                'edit_fields' => $edit_fields,
                'option_logic_js' => $option_logic->getJavascript()
            ]
        );

        echo $this->outputAsJson($output);

        return false;
    }

    /**
     * Review page for updating the domain package, term and configurable options
     */
    public function review()
    {
        $this->uses([
            'Domains.DomainsDomains', 'Invoices', 'Logs', 'ModuleManager', 'PackageOptionConditionSets',
            'PackageOptions', 'Packages', 'ServiceChanges', 'Services'
        ]);

        // Determine whether a valid domain is given
        if (!isset($this->get[0])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
            || !($data = $this->Session->read('client_update_domain'))
        ) {
            $this->redirect($this->base_uri);
        }

        // Redirect if the domain doesn't match the session info
        if (!isset($data['service_id']) || $data['service_id'] != $service->id) {
            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        // Fetch all of the input data
        $vars = ($data['vars'] ?? []);
        $selected_options = (array)($vars['configoptions'] ?? []);

        // Fetch the pricing to use for all settable options
        $pricing = null;
        $pricing_id = ($vars['pricing_id'] ?? null);
        if ($pricing_id && ($new_package = $this->Packages->getByPricingId($pricing_id))) {
            $pricing = $this->getPricing($new_package->pricing, $pricing_id);

            // Set error about client limit if it has been reached
            if ($new_package->id != $service->package->id
                && $new_package->client_qty !== null
                && $new_package->client_qty <= $this->Services->getListCount(
                    $this->client->id,
                    'all',
                    true,
                    $new_package->id
                )
            ) {
                $this->flashMessage('error', Language::_('ClientServices.!notice.client_limit', true));
                $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
            }
        }

        // Fetch options that the client can set
        $new_package_id = (isset($new_package) && $new_package ? $new_package->id : $service->package->id);
        $pricing = ($pricing ?: $service->package_pricing);
        $settable_options = $this->getSettableOptions(
            $new_package_id,
            $pricing->term,
            $pricing->period,
            $pricing->currency,
            $service->options,
            $selected_options
        );

        $options = [];
        $settable_option_ids = [];
        foreach ($settable_options as $option) {
            $settable_option_ids[$option->id] = $option->id;
            if (array_key_exists($option->id, $selected_options)) {
                $options[$option->id] = $selected_options[$option->id];
            }
        }

        // Maintain the existing options that are not currently being modified
        $options += $this->getCurrentMatchingOptions(
            $new_package_id,
            $pricing->term,
            $pricing->period,
            $pricing->currency,
            $service->options,
            $settable_option_ids
        );

        // Include any price overrides, if given
        $overrides = [];
        foreach (['override_price', 'override_currency'] as $override) {
            if ($pricing->id !== $service->pricing_id) {
                // Remove any price overrides if the term/package has changed
                $overrides[$override] = null;
            } elseif (isset($service->{$override})) {
                $overrides[$override] = $service->{$override};
            }
        }

        // Include the current service module fields (to pass any module error checking)
        $vars = ['qty' => $service->qty];
        foreach ($service->fields as $field) {
            $vars[$field->key] = $field->value;
        }
        $vars = array_merge(
            $vars,
            $overrides,
            ['pricing_id' => $pricing->id, 'configoptions' => $options, 'use_module' => 'true']
        );

        // Determine the items/totals
        $service_change = $this->ServiceChanges->getPresenter($service->id, $vars);
        $total = ($service_change ? $service_change->totals()->total : 0);

        if (!empty($this->post)) {
            $errors = false;
            $invoice_id = '';
            $queue_service = $this->queueServiceChanges();
            $allow_credit = ($this->client->settings['client_prorate_credits'] ?? null) == 'true';

            // Validate that the submitted config options are valid given the Option Logic
            $option_logic = new OptionLogic();
            $option_logic->setService($service);
            $option_logic->setPackageOptionConditionSets(
                $this->PackageOptionConditionSets->getAll(
                    [
                        'package_id' => $pricing->package_id,
                        'opition_ids' => $this->Form->collapseObjectArray(
                            $this->PackageOptions->getAllByPackageId(
                                $pricing->package_id,
                                $pricing->term,
                                $pricing->period,
                                $pricing->currency,
                                null,
                                $selected_options
                            ),
                            'id',
                            'id'
                        )
                    ],
                    ['option_id']
                )
            );

            if (!($errors = $option_logic->validate($options))) {
                $this->Services->validateServiceEdit($service->id, $vars);
                $errors = $this->Services->errors();
            }

            // Create the invoice for the service change
            if (empty($errors) && $service_change && $total > 0) {
                $invoice_data = $this->makeInvoice(
                    $this->client,
                    $service_change,
                    $pricing->currency,
                    false,
                    $service->id
                );
                $invoice_id = $invoice_data['invoice_id'];
                $errors = $invoice_data['errors'];
            }

            if (empty($errors)) {
                if ($queue_service && $total > 0) {
                    $result = $this->queueServiceChange($service->id, $invoice_id, $vars);
                    $errors = $result['errors'];
                } else {
                    $this->Services->edit($service->id, $vars);
                    $errors = $this->Services->errors();
                }
            }

            // Issue a credit for the service change
            $transaction_id = null;
            if (empty($errors) && $total < 0 && $allow_credit) {
                $transaction_id = $this->createCredit($this->client->id, abs($total), $pricing->currency);
            }

            // Log service change
            if (empty($errors) && $total < 0) {
                $this->Logs->addServiceChange([
                    'service_id' => $service->id,
                    'transactions' => ($transaction_id ? [$transaction_id] : []),
                    'old_service' => (array)$service,
                    'new_service' => (array)$this->Services->get($service->id)
                ]);
            }

            if (empty($errors)) {
                $this->Session->clear('client_update_domain');

                $message = ($queue_service
                    ? 'ClientServices.!success.service_queue'
                    : 'ClientServices.!success.' . ($data['type'] ?? '') . '_updated'
                );
                $redirect_uri = $this->plugin_uri . 'manage/' . $service->id . '/';

                // Redirect to pay the invoice if we have one
                if (($invoice = $this->Invoices->get($invoice_id)) && $invoice->due > 0) {
                    $message = ($queue_service ? 'ClientServices.!success.service_queue_pay' : $message);
                    $redirect_uri = $this->base_uri . 'pay/method/' . $invoice_id . '/';
                }

                $this->flashMessage('message', Language::_($message, true));
                $this->redirect($redirect_uri);
            }

            $this->setMessage('error', $errors, false, null, false);
        }

        // Set sidebar tabs
        $package = $this->Packages->get($service->package->id);
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;
        $this->buildTabs($service, $package, $module);

        // Set language for periods
        $periods = $this->Packages->getPricingPeriods();
        foreach ($this->Packages->getPricingPeriods(true) as $period => $lang) {
            $periods[$period . '_plural'] = $lang;
        }

        // Determine what the new totals would be once changed
        $recur_service_data = null;
        if ($pricing->period != 'onetime') {
            // Flag from_service so the pricing system applies limit_recurring semantics
            if (!empty($service->coupon_id)) {
                $vars['coupon_id'] = $service->coupon_id;
                $vars['from_service'] = '1';
            }

            $recur_service_data = $this->Services->getDataPresenter(
                $this->client->id,
                $vars,
                [
                    'includeSetupFees' => false,
                    'recur' => true,
                    'upgrade' => false,
                    'config_options' => ($vars['configoptions'] ?? [])
                ]
            );
        }

        $this->set('periods', $periods);
        $this->set('service', $service);
        $this->set('package', $package);
        $this->set('module', $module);
        $this->set('review', $this->formatServiceReview($service, $options, $pricing_id));
        $this->set('totals', $this->totals($service_change, $pricing->currency, ($recur_service_data ?: null)));
    }

    /**
     * AJAX updates totals for input data changed for a domain
     */
    public function updateTotals()
    {
        if (!$this->isAjax()) {
            header($this->server_protocol . ' 401 Unauthorized');
            exit();
        }

        $this->uses(['Packages', 'Services']);

        // Only allow the pricing ID and config options to be provided
        $vars = array_intersect_key($this->post, array_flip(['pricing_id', 'configoptions', 'parent_service_id']));
        $vars['qty'] = 1;

        // Determine the currency to be used
        $currency = null;
        $pricing = null;
        $pricing_id = ($vars['pricing_id'] ?? null);
        if ($pricing_id && ($package = $this->Packages->getByPricingId((int)$pricing_id))) {
            $pricing = $this->getPricing($package->pricing, (int)$pricing_id);
            $currency = ($pricing->currency ?? null);
        }

        // Default to the client's currency
        if (empty($currency)) {
            $currency = $this->Clients->getSetting($this->client->id, 'default_currency');
            $currency = $currency->value;
        }

        $now = date('c');
        $service_data = $this->Services->getDataPresenter(
            $this->client->id,
            $vars,
            [
                'includeSetupFees' => true,
                // Line items show they are billed from this date
                'startDate' => $now,
                'prorateStartDate' => $now,
                'recur' => false,
                'upgrade' => false
            ]
        );

        if ($service_data) {
            $recur_service_data = null;
            if ($pricing && $pricing->period != 'onetime') {
                $recur_service_data = $this->Services->getDataPresenter(
                    $this->client->id,
                    $vars,
                    [
                        'includeSetupFees' => false,
                        'recur' => true,
                        'upgrade' => false,
                        'config_options' => ($vars['configoptions'] ?? [])
                    ]
                );
            }

            echo $this->outputAsJson(
                $this->totals($service_data, $currency, ($recur_service_data ?: null))
            );
        }

        return false;
    }

    /**
     * List the addons of a domain
     */
    public function addons()
    {
        $this->uses(['Domains.DomainsDomains', 'ModuleManager', 'Packages', 'Services']);

        // Determine whether a domain is given and may have addons
        if (!isset($this->get[0])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
        ) {
            $this->redirect($this->base_uri);
        }

        $addon_services = $this->Services->getAllChildren($service->id);
        $available_addons = $this->getAddonPackages($service->package_group_id);

        // Must have addons available to view this page
        if (empty($addon_services) && empty($available_addons)) {
            $this->redirect($this->plugin_uri . 'manage/' . $service->id . '/');
        }

        $package = $this->Packages->get($service->package->id);
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;

        // Set sidebar tabs
        $this->buildTabs($service, $package, $module, 'addons');

        // Set language for periods
        $periods = $this->Packages->getPricingPeriods();
        foreach ($this->Packages->getPricingPeriods(true) as $period => $lang) {
            $periods[$period . '_plural'] = $lang;
        }

        $this->set('periods', $periods);
        $this->set('statuses', $this->Services->getStatusTypes());
        $this->set('services', $addon_services);
        $this->set('service', $service);
        $this->set('package', $package);
        $this->set(
            'client_can_create_addons',
            (
                !empty($available_addons)
                && ($this->client->settings['client_create_addons'] ?? null) == 'true'
            )
        );
    }

    /**
     * Create an addon for a domain
     */
    public function addAddon()
    {
        $this->uses([
            'Domains.DomainsDomains', 'Invoices', 'ModuleManager', 'PackageOptionConditionSets',
            'PackageOptions', 'Packages', 'Services'
        ]);

        $client_can_create_addon = ($this->client->settings['client_create_addons'] ?? null) == 'true';

        // Ensure a valid domain was given
        if (!$client_can_create_addon
            || !isset($this->get[0])
            || !($service = $this->Services->get((int)$this->get[0]))
            || $service->client_id != $this->client->id
            || !$this->DomainsDomains->isManagedDomain($service->id)
            || !($available_addons = $this->getAddonPackages($service->package_group_id))
        ) {
            $this->redirect($this->base_uri);
        }

        $package = $this->Packages->get($service->package->id);
        $module = $this->ModuleManager->initModule($service->package->module_id);
        $module->base_uri = $this->base_uri;

        $package_group_id = ($this->get['package_group_id'] ?? '');
        $addon_pricing_id = ($this->get['pricing_id'] ?? '');

        // Detect module refresh fields
        $refresh_fields = (($this->post['refresh_fields'] ?? null) == 'true');

        if (!empty($package_group_id) && !empty($addon_pricing_id)) {
            // Determine whether the addon is valid
            $fields = $this->validateAddon($service->id, $package_group_id, $addon_pricing_id);

            if (!$fields['valid']) {
                $this->setMessage(
                    'error',
                    Language::_('ClientServices.!error.addon_invalid', true),
                    false,
                    null,
                    false
                );
            } elseif ($fields['package']->client_qty !== null
                && $fields['package']->client_qty <= $this->Services->getListCount(
                    $this->client->id,
                    'all',
                    true,
                    $fields['package']->id
                )
            ) {
                $this->set('limit_reached', true);
                $this->setMessage(
                    'error',
                    Language::_('ClientServices.!notice.client_limit', true),
                    false,
                    null,
                    false
                );
            } elseif (!$refresh_fields && !empty($this->post)) {
                $data = $this->post;

                // Set pricing term selected
                $data['package_id'] = $fields['package']->id;
                $data['pricing_id'] = $fields['pricing']->id;
                $data['parent_service_id'] = $fields['parent_service']->id;
                $data['package_group_id'] = $fields['package_group']->id;
                $data['client_id'] = $this->client->id;
                $data['status'] = 'pending';
                $data['use_module'] = 'true';

                // Unset any fields that may adversely affect the Services::add() call
                unset(
                    $data['override_price'],
                    $data['override_currency'],
                    $data['date_added'],
                    $data['date_renews'],
                    $data['date_last_renewed'],
                    $data['date_suspended'],
                    $data['date_canceled'],
                    $data['notify_order'],
                    $data['invoice_id'],
                    $data['invoice_method'],
                    $data['coupon_id']
                );

                if (isset($data['qty'])) {
                    $data['qty'] = (int)$data['qty'];
                }

                // Validate that the submitted config options are valid given the Option Logic
                $option_logic = new OptionLogic();
                $option_logic->setPackageOptionConditionSets(
                    $this->PackageOptionConditionSets->getAll(
                        [
                            'package_id' => $fields['package']->id,
                            'opition_ids' => $this->Form->collapseObjectArray(
                                $this->PackageOptions->getAllByPackageId(
                                    $fields['pricing']->package_id,
                                    $fields['pricing']->term,
                                    $fields['pricing']->period,
                                    $fields['pricing']->currency
                                ),
                                'id',
                                'id'
                            )
                        ],
                        ['option_id']
                    )
                );

                if (!($errors = $option_logic->validate((array)($data['configoptions'] ?? [])))) {
                    $this->Services->validateService($fields['package'], $data);
                    $errors = $this->Services->errors();
                }

                if (!empty($errors)) {
                    $this->setMessage('error', $errors, false, null, false);
                } else {
                    $service_id = $this->Services->add($data, ['package_id' => $fields['package']->id]);

                    if (($errors = $this->Services->errors())) {
                        $this->setMessage('error', $errors, false, null, false);
                    } else {
                        $invoice_id = $this->Invoices->createFromServices(
                            $this->client->id,
                            [$service_id],
                            $fields['currency'],
                            date('c')
                        );

                        if ($invoice_id) {
                            $this->flashMessage(
                                'message',
                                Language::_('ClientServices.!success.addon_service_created', true)
                            );
                            $this->redirect($this->base_uri . 'pay/method/' . $invoice_id . '/');
                        }
                    }
                }
            }

            // Set the configurable options partial for the selected addon
            $data = array_merge($this->post, ['addon' => $package_group_id . '_' . $addon_pricing_id]);
            if (!empty($fields['pricing']) && ($addon_options = $this->getAddonOptions($fields, true, $data))) {
                $this->set('addon_options', $addon_options);
                $vars = (object)$data;
            } else {
                $vars = (object)['addon' => ''];
            }
        }

        // Set sidebar tabs
        $this->buildTabs($service, $package, $module, 'addons');

        $this->set('module', $module->getModule());
        $this->set('package', $package);
        $this->set('service', $service);
        $this->set('addons', $this->getAddonPackageList($available_addons));
        $this->set('vars', ($vars ?? new stdClass()));
    }

    /**
     * AJAX - Retrieves the configurable options for a given addon
     *
     * @param array $fields A list of addon fields (optional)
     * @param bool $return True to return this partial view, false to output as json (optional, default false)
     * @param array $vars A list of input vars (optional)
     * @return mixed False if addon fields are invalid; a partial view if $return is true; otherwise null
     */
    public function getAddonOptions($fields = [], $return = false, $vars = [])
    {
        if (!$this->isAjax() && !$return) {
            header($this->server_protocol . ' 401 Unauthorized');
            exit();
        }

        $this->uses(['ModuleManager', 'PackageOptionConditionSets', 'PackageOptions', 'Packages', 'Services']);

        // Validate the addon
        if (!empty($this->get['package_group_id']) && !empty($this->get['pricing_id']) && !empty($this->get[0])) {
            $fields = $this->validateAddon(
                (int)$this->get[0],
                (int)$this->get['package_group_id'],
                (int)$this->get['pricing_id']
            );

            if (!$fields['valid']) {
                return false;
            }
        }

        if (empty($fields) || !$fields['valid'] || !$fields['module']) {
            return false;
        }

        $vars = (object)$vars;

        // Set the 'new' option to 1 to indicate these config options are for a new package being
        // added, or 0 to indicate they are for changes to form fields
        $options = ['new' => (isset($vars->configoptions) ? 0 : 1), 'addable' => 1];

        $service_fields = $fields['module']->getClientAddFields($fields['package'], $vars);

        // If the client can select the module group, show a dropdown with the available options
        $package = $this->Packages->get($fields['pricing']->package_id ?? null);
        $module = $this->ModuleManager->initModule($package->module_id, $this->company_id);
        if ($package->module_group_client == '1') {
            $module_group_id = $service_fields->label(
                $module->moduleGroupName()
                    ?? Language::_('ClientServices.getaddonoptions.field_module_group_id', true),
                'module_group_id'
            );
            $module_group_id->attach(
                $service_fields->fieldSelect(
                    'module_group_id',
                    $this->Form->collapseObjectArray($package->module_groups, 'name', 'id'),
                    ($this->post['module_group_id'] ?? $vars->module_group_id ?? null),
                    ['id' => 'module_group_id']
                )
            );
            $service_fields->setField($module_group_id);
        }

        $package_options = $this->PackageOptions->getFields(
            $fields['pricing']->package_id,
            $fields['pricing']->term,
            $fields['pricing']->period,
            $fields['pricing']->currency,
            $vars,
            null,
            $options
        );

        $fields_html = new FieldsHtml($service_fields);
        $option_logic = new OptionLogic();
        $option_logic->setPackageOptionConditionSets(
            $this->PackageOptionConditionSets->getAll(
                [
                    'package_id' => $fields['pricing']->package_id,
                    'opition_ids' => $this->Form->collapseObjectArray(
                        $this->PackageOptions->getAllByPackageId(
                            $fields['pricing']->package_id,
                            $fields['pricing']->term,
                            $fields['pricing']->period,
                            $fields['pricing']->currency,
                            null,
                            $options
                        ),
                        'id',
                        'id'
                    )
                ],
                ['option_id']
            )
        );
        $option_logic->setOptionContainerSelector($fields_html->getContainerSelector());

        $partial = $this->corePartial(
            'client_services_configure_addon',
            [
                'module' => $fields['module']->getModule(),
                'input_html' => $fields_html,
                'package_options' => $this->corePartial(
                    'client_services_package_options',
                    [
                        'input_html' => (new FieldsHtml($package_options)),
                        'option_logic_js' => $option_logic->getJavascript()
                    ]
                )
            ]
        );

        if ($return) {
            return $partial;
        }

        $this->outputAsJson($partial);

        return false;
    }

    /**
     * Builds the Option Logic used to show and validate configurable options
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @param stdClass $package An stdClass object representing the package whose options to use
     * @param stdClass $pricing An stdClass object representing the pricing term to use
     * @param string $selector The CSS selector of the container holding the options
     * @return OptionLogic An instance of the Option Logic
     */
    private function getOptionLogic(stdClass $service, stdClass $package, stdClass $pricing, $selector)
    {
        $this->uses(['PackageOptionConditionSets', 'PackageOptions']);

        $option_logic = new OptionLogic();
        $option_logic->setService($service);
        $option_logic->setPackageOptionConditionSets(
            $this->PackageOptionConditionSets->getAll(
                [
                    'package_id' => $package->id,
                    'opition_ids' => $this->Form->collapseObjectArray(
                        $this->PackageOptions->getAllByPackageId(
                            $package->id,
                            $pricing->term,
                            $pricing->period,
                            $pricing->currency,
                            null
                        ),
                        'id',
                        'id'
                    )
                ],
                ['option_id']
            )
        );
        $option_logic->setOptionContainerSelector($selector);

        return $option_logic;
    }

    /**
     * Fetches a list of package options that are addable or editable for this domain
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @return array An array of all addable and editable package options for the service
     */
    private function getAvailableOptions($service)
    {
        return $this->getSettableOptions(
            $service->package->id,
            $service->package_pricing->term,
            $service->package_pricing->period,
            $service->package_pricing->currency,
            $service->options,
            $this->getSelectedOptionIds($service)
        );
    }

    /**
     * Fetches a set of available package option IDs for the given domain that the user can add or update
     * @see ClientMain::getAvailableOptions
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @return array A key/value array of available options where each key is the option ID
     */
    private function getSelectedOptionIds($service)
    {
        $this->uses(['PackageOptions']);

        // Create a list of option IDs currently set
        $option_ids = [];
        foreach ($service->options as $option) {
            $option_ids[] = $option->option_id;
        }

        // Fetch addable package options that don't currently exist
        $options = $this->PackageOptions->getAllByPackageId(
            $service->package->id,
            $service->package_pricing->term,
            $service->package_pricing->period,
            $service->package_pricing->currency,
            null,
            array_merge(
                ['addable' => 1, 'disallow' => $option_ids],
                $this->PackageOptions->formatServiceOptions($service->options)
            )
        );

        $available_option_ids = [];
        foreach ($options as $option) {
            $available_option_ids[$option->id] = '';
        }

        return $available_option_ids;
    }

    /**
     * Returns an array of all pricing terms for the given package that optionally
     * recur and do not match the given pricing IDs
     *
     * @param stdClass $package An stdClass object representing the package to fetch the terms for
     * @param array $pricing_ids An array of pricing IDs to exclude (optional)
     * @param mixed $service An stdClass object representing the service (optional)
     * @param bool $remove_non_recurring_terms True to include only package terms that recur (optional, default true)
     * @param bool $match_periods True to only set terms that match the period set for the $service
     *  (optional, default false)
     * @param bool $upgrade Whether these terms are being fetched for a package change (optional, default false)
     * @return array An array of key/value pairs where the key is the package pricing ID and the
     *  value is a string representing the price, term, and period
     */
    private function getPackageTerms(
        stdClass $package,
        array $pricing_ids = [],
        $service = null,
        $remove_non_recurring_terms = true,
        $match_periods = false,
        $upgrade = false
    ) {
        $singular_periods = $this->Packages->getPricingPeriods();
        $plural_periods = $this->Packages->getPricingPeriods(true);
        $terms = [];

        foreach (($package->pricing ?? []) as $price) {
            // Ignore non-recurring terms, and exclude the given pricing IDs
            if (($remove_non_recurring_terms && $price->period == 'onetime')
                || in_array($price->id, $pricing_ids)
            ) {
                continue;
            }

            // Check that the service period matches this term's period
            if ($match_periods && $service
                && !(
                    $service->package_pricing->period == $price->period
                    || ($price->period != 'onetime' && $service->package_pricing->period != 'onetime')
                )
            ) {
                continue;
            }

            // Set the package pricing to the service override values
            $amount = $price->price;
            $renew_amount = ($price->price_renews ?? 0);
            $currency = $price->currency;
            $term = 'ClientServices.get_package_terms.term';

            if ($service && $service->pricing_id == $price->id
                && !empty($service->override_price) && !empty($service->override_currency)
            ) {
                $amount = $service->override_price;
                $currency = $service->override_currency;
                $renew_amount = $amount;
            } elseif ($service && isset($price->price_renews) && (!$upgrade || $package->upgrades_use_renewal)) {
                $amount = $renew_amount;
            } elseif (isset($price->price_renews) && $price->price != $price->price_renews) {
                $term = 'ClientServices.get_package_terms.term_recurring';
            }

            $period = ($price->term != 1 ? $plural_periods[$price->period] : $singular_periods[$price->period]);

            $terms[$price->id] = ($price->period == 'onetime'
                ? Language::_(
                    'ClientServices.get_package_terms.term_onetime',
                    true,
                    $period,
                    $this->CurrencyFormat->format($amount, $currency)
                )
                : Language::_(
                    $term,
                    true,
                    $price->term,
                    $period,
                    $this->CurrencyFormat->format($amount, $currency),
                    $this->CurrencyFormat->format($renew_amount, $currency)
                )
            );
        }

        return $terms;
    }

    /**
     * Returns an array of packages that can be changed to from the same package group
     *
     * @param stdClass $package The package from which to fetch other available packages
     * @param string $type The type of package group ("standard" or "addon")
     * @return array An array of stdClass objects representing packages in the same group
     */
    private function getUpgradablePackages($package, $type)
    {
        if (!$package || empty($package->module_id)) {
            return [];
        }

        $packages = $this->Packages->getCompatiblePackages($package->id, $package->module_id, $type);

        $restricted_package_ids = [];
        foreach ($this->Clients->getRestrictedPackages($this->client->id) as $restricted) {
            $restricted_package_ids[] = $restricted->package_id;
        }

        foreach ($packages as $index => $temp_package) {
            // Remove unavailable restricted packages
            if ($temp_package->status == 'inactive'
                || ($temp_package->status == 'restricted' && !in_array($temp_package->id, $restricted_package_ids))
            ) {
                unset($packages[$index]);
                continue;
            }

            // Remove the given package since you cannot change to the identical package
            if ($package->id == $temp_package->id) {
                unset($packages[$index]);
            }
        }

        return array_values($packages);
    }

    /**
     * Builds a partial template for the package options
     *
     * @param int $package_id The ID of the package whose package options to fetch
     * @param int $term The package option pricing term
     * @param string $period The package option pricing period
     * @param string $currency The ISO 4217 currency code for this pricing
     * @param stdClass $vars An stdClass object containing input fields
     * @param string $convert_currency The ISO 4217 currency code to convert the pricing to
     * @param array $options An array of filtering options (optional)
     * @return mixed The partial template, or boolean false if no fields are available
     */
    private function getPackageOptionFields(
        $package_id,
        $term,
        $period,
        $currency,
        $vars,
        $convert_currency = null,
        ?array $options = null
    ) {
        $this->uses(['PackageOptions']);

        $package_options = $this->PackageOptions->getFields(
            $package_id,
            $term,
            $period,
            $currency,
            $vars,
            $convert_currency,
            $options
        );
        $option_fields = $package_options->getFields();

        return (!empty($option_fields)
            ? $this->corePartial(
                'client_services_package_options',
                ['fields' => $option_fields, 'input_html' => new FieldsHtml($package_options)]
            )
            : false
        );
    }

    /**
     * Retrieves a list of service options that can be set by the client for the given package and term
     *
     * @param int $package_id The ID of the package whose options to use
     * @param int $term The pricing term
     * @param string $period The pricing period
     * @param string $currency The ISO 4217 pricing currency code
     * @param array $current_options An array of current service options
     * @param array $selected_options A key/value list of option IDs and their selected values
     * @return array An array of stdClass objects representing each settable package option field
     */
    private function getSettableOptions(
        $package_id,
        $term,
        $period,
        $currency,
        array $current_options,
        array $selected_options = []
    ) {
        $this->uses(['PackageOptions']);

        $options = $this->PackageOptions->getAllByPackageId(
            $package_id,
            $term,
            $period,
            $currency,
            null,
            $this->PackageOptions->formatServiceOptions($current_options)
        );

        // Re-key each current option by ID
        $edit_options = [];
        foreach ($current_options as $current_option) {
            $edit_options[$current_option->option_id] = $current_option;
        }

        $available_options = [];
        foreach ($options as $option) {
            // Set editable options
            if (array_key_exists($option->id, $edit_options) && $option->editable == '1') {
                $available_options[] = $option;
            }

            // Set addable options
            if (!array_key_exists($option->id, $edit_options) && $option->addable == '1'
                && array_key_exists($option->id, $selected_options)
            ) {
                $available_options[] = $option;
            }
        }

        return $available_options;
    }

    /**
     * Retrieves a list of current options that match those available from the given package and term
     *
     * @param int $package_id The ID of the package whose options to use
     * @param int $term The pricing term
     * @param string $period The pricing period
     * @param string $currency The ISO 4217 pricing currency code
     * @param array $current_options An array of current service options
     * @param array $settable_options An array of options that could be selected for modification
     * @return array A key/value array where the key is the option ID and the value is the selected value
     */
    private function getCurrentMatchingOptions(
        $package_id,
        $term,
        $period,
        $currency,
        array $current_options,
        array $settable_options = []
    ) {
        $this->uses(['PackageOptions']);

        $options = $this->PackageOptions->getAllByPackageId(
            $package_id,
            $term,
            $period,
            $currency,
            null,
            $this->PackageOptions->formatServiceOptions($current_options)
        );

        // Re-key each option
        $package_options = [];
        foreach ($options as $option) {
            $package_options[$option->id] = [];
            foreach ($option->values as $value) {
                $package_options[$option->id][$value->id] = $value->value;
            }
        }

        // Re-key each current option by ID
        $edit_options = [];
        foreach ($current_options as $current_option) {
            $edit_options[$current_option->option_id] = $current_option;
        }

        // Check whether each current option is an available package option
        $available_options = [];
        foreach ($edit_options as $option_id => $option) {
            if (array_key_exists($option_id, $package_options) && !array_key_exists($option_id, $settable_options)) {
                $available_options[$option_id] = ($option->option_type == 'quantity' ? $option->qty : $option->value);
            }
        }

        return $available_options;
    }

    /**
     * Formats package/term and package options into sections representing their current and new values
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @param array $option_values A key/value array of the new service option IDs and their values
     * @param int $pricing_id The new pricing ID (optional)
     * @return stdClass A formatted object of all packages and options
     */
    private function formatServiceReview($service, array $option_values = [], $pricing_id = null)
    {
        $formatted_package = (object)['current' => null, 'new' => null];

        // Fetch the current package and its pricing info
        $package = $this->Packages->get($service->package->id);
        $package->pricing = $this->getPricing($package->pricing, $service->pricing_id);
        $formatted_package->current = $package;

        // Fetch the new package and its pricing info
        if ($pricing_id) {
            $package = $this->Packages->getByPricingId($pricing_id);
            $package->pricing = $this->getPricing($package->pricing, $pricing_id);
            $formatted_package->new = $package;
        }

        return (object)[
            'packages' => $formatted_package,
            'config_options' => $this->formatReviewOptions($service, $option_values, $pricing_id)
        ];
    }

    /**
     * Formats package options and their values into categories for current and new
     * @see ClientMain::formatServiceReview
     *
     * @param stdClass $service An stdClass object representing the domain service
     * @param array $option_values A key/value array of the new service option IDs and their values
     * @param int $pricing_id The new pricing ID (optional)
     * @return array A formatted array of all options
     */
    private function formatReviewOptions($service, array $option_values = [], $pricing_id = null)
    {
        $this->uses(['PackageOptions']);

        $formatted_options = [];

        // Fetch the current package options
        $current_values = $this->PackageOptions->formatServiceOptions($service->options);
        $current_values = ($current_values['configoptions'] ?? []);

        // Fetch all of the possible options
        $all_options = $this->PackageOptions->getByPackageId($service->package->id);

        $pricing = null;
        if ($pricing_id && ($new_package = $this->Packages->getByPricingId($pricing_id))) {
            // Key all options by ID
            $option_ids = [];
            foreach ($all_options as $option) {
                $option_ids[$option->id] = null;
            }

            // Combine all options with the new package options
            foreach ($this->PackageOptions->getByPackageId($new_package->id) as $option) {
                if (!array_key_exists($option->id, $option_ids)) {
                    $all_options[] = $option;
                }
            }

            $pricing = $this->getPricing($new_package->pricing, $pricing_id);
        }

        // Set the new pricing as the current service pricing if not set
        $pricing = ($pricing ?: $service->package_pricing);

        // Match the available package options with the given options
        $i = 0;
        foreach ($all_options as $package_option) {
            if (!array_key_exists($package_option->id, $option_values)
                && !array_key_exists($package_option->id, $current_values)
            ) {
                continue;
            }

            $formatted_options[$i] = $package_option;
            $formatted_options[$i]->new_value = false;
            $formatted_options[$i]->current_value = false;

            // Fetch the new option value
            if (array_key_exists($package_option->id, $option_values)) {
                $formatted_options[$i]->new_value = $this->PackageOptions->getValue(
                    $package_option->id,
                    $option_values[$package_option->id]
                );

                if ($formatted_options[$i]->new_value) {
                    $formatted_options[$i]->new_value->selected_value = $option_values[$package_option->id];
                    $formatted_options[$i]->new_value->pricing = $this->PackageOptions->getValuePrice(
                        $formatted_options[$i]->new_value->id,
                        $pricing->term,
                        $pricing->period,
                        $pricing->currency
                    );
                }
            }

            // Fetch the current option value
            if (array_key_exists($package_option->id, $current_values)) {
                $formatted_options[$i]->current_value = $this->PackageOptions->getValue(
                    $package_option->id,
                    $current_values[$package_option->id]
                );

                if ($formatted_options[$i]->current_value) {
                    $formatted_options[$i]->current_value->selected_value = $current_values[$package_option->id];
                    $formatted_options[$i]->current_value->pricing = $this->PackageOptions->getValuePrice(
                        $formatted_options[$i]->current_value->id,
                        $service->package_pricing->term,
                        $service->package_pricing->period,
                        $service->package_pricing->currency
                    );
                }
            }

            $i++;
        }

        // Remove any options/values that should not be shown
        foreach ($formatted_options as $i => &$option) {
            // If there is no new or current value, remove the option entirely
            if (!$option->new_value && !$option->current_value) {
                unset($formatted_options[$i]);
                continue;
            }

            // Remove any quantity options that are being set to a quantity of 0
            if ($option->type == 'quantity' && $option->new_value && $option->new_value->selected_value == '0') {
                if (!$option->current_value) {
                    unset($formatted_options[$i]);
                } else {
                    $option->new_value = false;
                }
            }
        }

        return array_values($formatted_options);
    }

    /**
     * Builds and returns the totals partial
     *
     * @param PresenterInterface $presenter An instance of the PresenterInterface
     * @param string $currency The ISO 4217 currency code
     * @param PresenterInterface $recur_presenter An instance of the PresenterInterface representing a renewing service
     * @return string The totals partial template
     */
    private function totals(PresenterInterface $presenter, $currency, ?PresenterInterface $recur_presenter = null)
    {
        $array_merge = $this->getFromContainer('pricing')->arrayMerge();

        return $this->corePartial(
            'client_services_totals',
            [
                'totals' => $presenter->totals(),
                'totals_recurring' => ($recur_presenter ? $recur_presenter->totals() : null),
                'discounts' => $array_merge->combineSum($presenter->discounts(), 'id', 'total'),
                'taxes' => $array_merge->combineSum($presenter->taxes(), 'id', 'total'),
                'currency' => $currency,
                'settings' => $this->client->settings
            ]
        );
    }

    /**
     * Validates that the given addon data is valid for this client
     * @see ClientMain::addAddon(), ClientMain::getAddonOptions()
     *
     * @param int $service_id The ID of the parent service to which the addon is to be assigned
     * @param int $package_group_id The ID of the package group
     * @param int $price_id The ID of the addon's package pricing
     * @return array An array of addon fields
     */
    private function validateAddon($service_id, $package_group_id, $price_id)
    {
        $this->uses(['ModuleManager', 'PackageGroups', 'Packages', 'Services']);

        // Ensure a valid addon was given
        if (!($parent_service = $this->Services->get((int)$service_id))
            || $parent_service->client_id != $this->client->id
            || !($available_addons = $this->getAddonPackages($parent_service->package_group_id))
            || !($package = $this->Packages->getByPricingId((int)$price_id))
            || !($package_group = $this->PackageGroups->get((int)$package_group_id))
            || $package_group->company_id != $this->company_id
        ) {
            return ['valid' => false];
        }

        // Confirm that the given package is an available addon
        $valid = false;
        $addon_groups = ($available_addons[$package_group->id] ?? null);
        $currency = $this->Clients->getSetting($this->client->id, 'default_currency');
        $currency = $currency->value;
        $pricing = null;

        foreach (($addon_groups->addons ?? []) as $addon) {
            if ($addon->id == $package->id) {
                $valid = true;
                $pricing = $this->getPricing($package->pricing, (int)$price_id);
                $currency = ($pricing ? $pricing->currency : $currency);
                break;
            }
        }

        // Ensure a valid module exists
        $module = $this->ModuleManager->initModule($package->module_id, $this->company_id);
        if (!$module) {
            $valid = false;
        } else {
            $module->base_uri = $this->base_uri;
        }

        return compact('valid', 'parent_service', 'package', 'pricing', 'package_group', 'module', 'currency');
    }

    /**
     * Retrieves a list of name/value pairs for addon packages
     *
     * @param array $addons A list of package groups containing addon packages available to the client
     * @return array A list of name/value pairs
     */
    private function getAddonPackageList(array $addons)
    {
        // Set language for periods
        $periods = $this->Packages->getPricingPeriods();
        foreach ($this->Packages->getPricingPeriods(true) as $period => $lang) {
            $periods[$period . '_plural'] = $lang;
        }

        $addon_packages = ['' => Language::_('AppController.select.please', true)];
        foreach ($addons as $package_group) {
            foreach ($package_group->addons as $addon) {
                $addon_packages[] = ['name' => $addon->name, 'value' => 'optgroup'];

                foreach ($addon->pricing as $price) {
                    $period_singular = ($periods[$price->period] ?? '');
                    $period_plural = ($periods[$price->period . '_plural'] ?? '');

                    if (($price->period ?? null) == 'onetime') {
                        $term = $period_singular;
                    } else {
                        $term = ($price->term ?? '');
                        $term = Language::_(
                            'ClientServices.addaddon.term',
                            true,
                            $term,
                            ($term == 1 ? $period_singular : $period_plural)
                        );
                    }

                    $cost = $this->CurrencyFormat->format(
                        ($price->price ?? null),
                        ($price->currency ?? null),
                        ['code' => false]
                    );
                    $renew_cost = $this->CurrencyFormat->format(
                        ($price->price_renews ?? 0),
                        ($price->currency ?? null),
                        ['code' => false]
                    );
                    $option_term = 'ClientServices.addaddon.term_price';
                    $display_renewal_price = (($price->price_renews ?? null)
                        && ($price->price_renews ?? null) != ($price->price ?? null)
                    );

                    $name = Language::_(
                        $option_term . ($display_renewal_price ? '_recurring' : ''),
                        true,
                        $term,
                        $cost,
                        $renew_cost
                    );

                    if ($price->setup_fee > 0) {
                        $name = Language::_(
                            $option_term . '_setupfee' . ($display_renewal_price ? '_recurring' : ''),
                            true,
                            $term,
                            $cost,
                            $this->CurrencyFormat->format($price->setup_fee, $price->currency, ['code' => false]),
                            $renew_cost
                        );
                    }

                    $addon_packages[] = ['name' => $name, 'value' => $package_group->id . '_' . $price->id];
                }
            }
        }

        return $addon_packages;
    }

    /**
     * Retrieves a list of all addon packages available to the client in the given package group
     *
     * @param int $parent_group_id The ID of the parent group to list packages for
     * @return array An array of addon package groups containing an array of addon packages
     */
    private function getAddonPackages($parent_group_id)
    {
        $this->uses(['Packages']);

        $packages = [];
        $restricted_packages = $this->Clients->getRestrictedPackages($this->client->id);

        foreach ($this->Packages->getAllAddonGroups($parent_group_id) as $package_group) {
            foreach ($this->Packages->getAllPackagesByGroup($package_group->id) as $package) {
                // Check whether the client has access to this package
                if ($package->status == 'inactive') {
                    continue;
                } elseif ($package->status == 'restricted') {
                    $available = false;
                    foreach ($restricted_packages as $restricted) {
                        if ($restricted->package_id == $package->id) {
                            $available = true;
                            break;
                        }
                    }

                    if (!$available) {
                        continue;
                    }
                }

                if (!isset($packages[$package_group->id])) {
                    $packages[$package_group->id] = $package_group;
                    $packages[$package_group->id]->addons = [];
                }
                $packages[$package_group->id]->addons[] = $package;
            }
        }

        return $packages;
    }

    /**
     * Retrieves the matching pricing information from the given pricings
     *
     * @param array $pricings An array of stdClass objects representing each pricing
     * @param int $pricing_id The ID of the pricing to retrieve from the list
     * @return mixed An stdClass object representing the pricing information, or null if not found
     */
    private function getPricing(array $pricings, $pricing_id)
    {
        foreach ($pricings as $price) {
            if ($price->id == $pricing_id) {
                return $price;
            }
        }

        return null;
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
                $vars['package_name'] ?? null,
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
                $vars['service_meta'] ?? null,
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
