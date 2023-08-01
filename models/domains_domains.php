<?php

/**
 * Domain Manager TLDs Management Model
 *
 * @link https://www.blesta.com Blesta
 */
class DomainsDomains extends DomainsModel
{
    /**
     * Returns a list with all the Domains for the given company
     *
     * @param array $filters A list of filters for the query
     *
     *  - client_id The client ID (optional)
     *  - company_id The ID of the company for which this domain is available (optional)
     *  - excluded_pricing_term The pricing term by which to exclude results (optional)
     *  - module_id The module ID on which to filter packages (optional)
     *  - pricing_period The pricing period for which to fetch services (optional)
     *  - package_id The package ID (optional)
     *  - package_name The (partial) name of the packages for which to fetch services (optional)
     *  - service_meta The (partial) value of meta data on which to filter services (optional)
     *  - status The status type of the services to fetch (optional, default 'active'):
     *    - active All active services
     *    - canceled All canceled services
     *    - pending All pending services
     *    - suspended All suspended services
     *    - in_review All services that require manual review before they may become pending
     *    - scheduled_cancellation All services scheduled to be canceled
     *    - all All active/canceled/pending/suspended/in_review
     * @param array $order A key/value pair array of fields to order the results by
     * @return array An array of stdClass objects
     */
    public function getAll(array $filters = [], array $order = ['id' => 'asc'])
    {
        Loader::loadModels($this, ['Services', 'Companies', 'ModuleManager']);

        $filters['company_id'] = $filters['company_id'] ?? Configure::get('Blesta.company_id');

        // Filter by package group
        $package_group_id = $this->Companies->getSetting($filters['company_id'], 'domains_package_group');
        $filters['package_group_id'] = $package_group_id ? $package_group_id->value : null;

        $services = $this->Services->getAll($order, true, $filters);

        // Add domain fields
        foreach ($services as &$service) {
            $module = $this->ModuleManager->initModule($service->package->module_id);
            $service->registrar = $module->getName();
            $service->renewal_price = $this->Services->getRenewalPrice($service->id);
            $service->expiration_date = $this->getExpirationDate($service->id);
        }

        return $services;
    }

    /**
     * Returns a list of Domains for the given company
     *
     * @param array $filters A list of filters for the query
     *
     *  - client_id The client ID (optional)
     *  - company_id The ID of the company for which this domain is available (optional)
     *  - excluded_pricing_term The pricing term by which to exclude results (optional)
     *  - module_id The module ID on which to filter packages (optional)
     *  - pricing_period The pricing period for which to fetch services (optional)
     *  - package_id The package ID (optional)
     *  - package_name The (partial) name of the packages for which to fetch services (optional)
     *  - service_meta The (partial) value of meta data on which to filter services (optional)
     *  - status The status type of the services to fetch (optional, default 'active'):
     *    - active All active services
     *    - canceled All canceled services
     *    - pending All pending services
     *    - suspended All suspended services
     *    - in_review All services that require manual review before they may become pending
     *    - scheduled_cancellation All services scheduled to be canceled
     *    - all All active/canceled/pending/suspended/in_review
     * @param int $page The page number of results to fetch
     * @param array $order A key/value pair array of fields to order the results by
     * @return array An array of stdClass objects
     */
    public function getList(array $filters = [], $page = 1, array $order = ['order' => 'asc'])
    {
        Loader::loadModels($this, ['Services', 'Companies', 'ModuleManager']);

        // Filter by type
        $filters['type'] = 'domains';

        // Set service status
        $status = $filters['status'] ?? 'active';
        $client_id = $filters['client_id'] ?? null;

        $services = $this->Services->getList($client_id, $status, $page, $order, true, $filters);

        // Add domain fields
        foreach ($services as &$service) {
            $module = $this->ModuleManager->initModule($service->package->module_id);
            $service->registrar = $module->getName();
            $service->renewal_price = $this->Services->getRenewalPrice($service->id);
            $service->expiration_date = $this->getExpirationDate($service->id);
        }

        return $services;
    }

    /**
     * Returns the total number of Domains for the given filters
     *
     * @param array $filters A list of filters for the query
     *
     *  - client_id The client ID (optional)
     *  - company_id The ID of the company for which this domain is available (optional)
     *  - excluded_pricing_term The pricing term by which to exclude results (optional)
     *  - module_id The module ID on which to filter packages (optional)
     *  - pricing_period The pricing period for which to fetch services (optional)
     *  - package_id The package ID (optional)
     *  - package_name The (partial) name of the packages for which to fetch services (optional)
     *  - service_meta The (partial) value of meta data on which to filter services (optional)
     *  - status The status type of the services to fetch (optional, default 'active'):
     *    - active All active services
     *    - canceled All canceled services
     *    - pending All pending services
     *    - suspended All suspended services
     *    - in_review All services that require manual review before they may become pending
     *    - scheduled_cancellation All services scheduled to be canceled
     *    - all All active/canceled/pending/suspended/in_review
     * @return int The total number of TLDs for the given filters
     */
    public function getListCount(array $filters = [])
    {
        Loader::loadModels($this, ['Services', 'Companies', 'ModuleManager']);

        // Filter by type
        $filters['type'] = 'domains';

        // Set service status
        $status = $filters['status'] ?? 'active';
        $client_id = $filters['client_id'] ?? null;

        return $this->Services->getListCount($client_id, $status, true, null, $filters);
    }

    /**
     * Returns the number of results available for the given status
     *
     * @param string $status The status value to select a count of ('active', 'canceled', 'pending', 'suspended')
     * @param array $filters A list of parameters to filter by, including:
     *
     *  - client_id The client ID (optional)
     *  - excluded_pricing_term The pricing term by which to exclude results (optional)
     *  - module_id The module ID on which to filter packages (optional)
     *  - pricing_period The pricing period for which to fetch services (optional)
     *  - package_id The package ID (optional)
     *  - package_name The (partial) name of the packages for which to fetch services (optional)
     *  - service_meta The (partial) value of meta data on which to filter services (optional)
     *  - status The status type of the services to fetch (optional, default 'active'):
     *    - active All active services
     *    - canceled All canceled services
     *    - pending All pending services
     *    - suspended All suspended services
     *    - in_review All services that require manual review before they may become pending
     *    - scheduled_cancellation All services scheduled to be canceled
     *    - all All active/canceled/pending/suspended/in_review
     *  - type The type of the services, it can be 'services', 'domains' or null for all (optional, default null)
     * @return int The number representing the total number of services for this client with that status
     */
    public function getStatusCount($status = 'active', array $filters = [])
    {
        Loader::loadModels($this, ['Services']);

        // Filter by type
        $filters['type'] = 'domains';

        // Set service status
        $status = $status ?? $filters['status'] ?? 'active';
        $client_id = $filters['client_id'] ?? null;

        unset($filters['status']);

        return $this->Services->getListCount($client_id, $status, true, null, $filters);
    }

    /**
     * Renews a domain name
     *
     * @param int $service_id The id of the service where the domain belongs
     * @param int $years The number of years to renew the domain
     * @return int The ID of the invoice for the domain to be renewed
     */
    public function renewDomain($service_id, $years = 1)
    {
        Loader::loadModels($this, ['Services', 'Invoices', 'Packages']);

        // Get service
        $service = $this->Services->get($service_id);
        if (!$service) {
            return;
        }

        // Determine whether invoices for this service remain unpaid
        $unpaid_invoices = $this->Invoices->getAllWithService($service->id, $service->client_id, 'open');

        // Disallow renew if the current service has not been paid
        if (!empty($unpaid_invoices)) {
            $errors = [
                'error' => ['cycles' => Language::_('DomainsDomains.!error.invoices_renew_service', true)]
            ];
            $this->Input->setErrors($errors);

            return;
        }

        // Validate the TLD has a term for the amount of years to be renewed
        $package = $this->Packages->get($service->package->id);
        $pricings = $package->pricing;
        $terms = [];

        foreach ($pricings as $pricing) {
            if ($pricing->period == 'year') {
                $terms[$pricing->id] = $pricing->term;
            }
        }
        $terms_pricing = array_flip($terms);

        if (!in_array($years, $terms)) {
            $errors = [
                'error' => ['term' => Language::_('DomainsDomains.!error.invalid_term', true)]
            ];
            $this->Input->setErrors($errors);

            return;
        }


        // Create the invoice for these renewing services, by submitting $terms_pricing[$years] for the $pricing_id
        // parameter, we are also telling the Invoices model to update the pricing ID on the service
        $invoice_id = $this->Invoices->createRenewalFromService($service_id, 1, $terms_pricing[$years]);

        if (($errors = $this->Invoices->errors())) {
            $this->Input->setErrors($errors);

            return;
        }

        return $invoice_id;
    }

    /**
     * Updates the nameservers of a given domain name
     *
     * @param int $service_id The id of the service where the domain belongs
     * @param array $nameservers A list of name servers to assign (e.g. [ns1, ns2])
     */
    public function updateNameservers($service_id, array $nameservers)
    {
        Loader::loadModels($this, ['Services', 'ModuleManager']);

        // Get service
        $service = $this->Services->get($service_id);
        if (!$service) {
            return false;
        }

        // Get registrar module associated to the service
        $module_row = $this->ModuleManager->getRow($service->module_row_id ?? null);
        $module = $this->ModuleManager->get($module_row->module_id ?? null, false, false);

        if (empty($module)) {
            return false;
        }

        // Get service domain name
        $service_name = $this->ModuleManager->moduleRpc($module->id, 'getServiceDomain', [$service], $module_row->id);

        // Update nameservers
        $params = [
            $service_name,
            $module_row->id,
            $nameservers
        ];
        $result = $this->ModuleManager->moduleRpc($module->id, 'setDomainNameservers', $params, $module_row->id);

        if (($errors = $this->ModuleManager->errors())) {
            $this->Input->setErrors($errors);

            return;
        }

        return $result;
    }

    /**
     * Gets the domain expiration date
     *
     * @param int $service_id The id of the service where the domain belongs
     * @param string $format The format to return the expiration date in
     * @return string The domain expiration date in UTC time in the given format
     * @see Services::get()
     */
    public function getExpirationDate($service_id, $format = 'Y-m-d H:i:s')
    {

        if (is_null($format)) {
            $format = 'Y-m-d H:i:s';
        }

        if (is_numeric($service_id)) {
            // Check if there is an expiration date locally saved
            $domain = $this->Record->select()
                ->from('domains_domains')
                ->where('service_id', '=', $service_id)
                ->fetch();
            $expiration_date = $domain->expiration_date ?? null;

            if ($expiration_date == null) {
                if (!isset($this->Services) || !isset($this->ModuleManager)) {
                    Loader::loadModels($this, ['Services', 'ModuleManager']);
                }

                // Get the expiration date from the registrar
                $service = $this->Services->get($service_id);
                $remote_expiration_date = $this->ModuleManager->moduleRpc(
                    $service->package->module_id,
                    'getExpirationDate',
                    [$service, $format],
                    $service->module_row_id ?? null
                );

                $expiration_date = $remote_expiration_date;
            }

            if ($expiration_date) {
                return $this->Date->format(
                    $format,
                    $expiration_date
                );
            }
        }

        return null;
    }

    /**
     * Sets the expiration date of a given domain
     *
     * @param int $service_id The ID of the service belonging to the domain
     * @param string $expiration_date The expiration date of the domain
     */
    public function setExpirationDate($service_id, $expiration_date)
    {
        $this->Record->duplicate('domains_domains.expiration_date', '=', $expiration_date)
            ->insert('domains_domains', ['service_id' => $service_id, 'expiration_date' => $expiration_date]);
    }

    /**
     * Checks if the given service is a domain managed by the domain manager
     *
     * @param $service_id The ID of the service to evaluate
     * @return bool True if the service is a managed domain, false otherwise
     */
    public function isManagedDomain($service_id)
    {
        if (!isset($this->Companies)) {
            Loader::loadModels($this, ['Companies']);
        }
        if (!isset($this->Services)) {
            Loader::loadModels($this, ['Services']);
        }
        if (!isset($this->ModuleManager)) {
            Loader::loadModels($this, ['ModuleManager']);
        }

        // Validate if the service is being handled by the Domain Manager and the module type is registrar
        $package_group_id = $this->Companies->getSetting(Configure::get('Blesta.company_id'), 'domains_package_group');
        $service = $this->Services->get($service_id ?? null);
        $module = $this->ModuleManager->get($service->package->module_id ?? null, false, false);

        return $service
            && $module
            && $package_group_id->value == $service->package_group_id
            && $module->type == 'registrar';
    }
}
