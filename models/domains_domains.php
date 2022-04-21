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
        Loader::loadModels($this, ['Services', 'Companies']);

        $filters['company_id'] = $filters['company_id'] ?? Configure::get('Blesta.company_id');

        // Filter by package group
        $package_group_id = $this->Companies->getSetting($filters['company_id'], 'domains_package_group');
        $filters['package_group_id'] = $package_group_id ? $package_group_id->value : null;

        return $this->Services->getAll($order, false, $filters);
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
        Loader::loadModels($this, ['Services', 'Companies']);

        $company_id = $filters['company_id'] ?? Configure::get('Blesta.company_id');

        // Filter by package group
        $package_group_id = $this->Companies->getSetting($company_id, 'domains_package_group');
        $filters['package_group_id'] = $package_group_id ? $package_group_id->value : null;

        // Set service status
        $status = $filters['status'] ?? 'active';

        return $this->Services->getList(null, $status, $page, $order, false, $filters);
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
        Loader::loadModels($this, ['Services', 'Companies']);

        $company_id = $filters['company_id'] ?? Configure::get('Blesta.company_id');

        // Filter by package group
        $package_group_id = $this->Companies->getSetting($company_id, 'domains_package_group');
        $filters['package_group_id'] = $package_group_id ? $package_group_id->value : null;

        // Set service status
        $status = $filters['status'] ?? 'active';

        return $this->Services->getListCount(null, $status, false, null, $filters);
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
        Loader::loadModels($this, ['Services', 'Invoices']);

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

        // Create the invoice for these renewing services
        $invoice_id = $this->Invoices->createRenewalFromService($service_id, $years);

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
        Loader::loadModels($this, ['Services', 'ModuleManager']);

        if (is_numeric($service_id)) {
            $service = $this->Services->get($service_id);

            // Check if the domain already has an expiration date
            $domain = $this->Record->select()
                ->from('domains_domains')
                ->where('service_id', '=', $service->id)
                ->fetch();
            $expiration_date = $domain->expiration_date ?? null;

            // Get the expiration date from the renewal
            if (empty($expiration_date)) {
                $expiration_date = $this->ModuleManager->moduleRpc(
                    $service->package->module_id,
                    'getExpirationDate',
                    [$service_id, $format],
                    $service->module_row_id ?? null
                );
            }

            if (empty($expiration_date)) {
                $expiration_date = $service->date_renews;
            }

            // Update expiration date
            $this->Record->duplicate('domains_domains.expiration_date', '=', $expiration_date)
                ->insert('domains_domains', ['service_id' => $service_id, 'expiration_date' => $expiration_date]);

            return $this->Date->format(
                $format,
                $expiration_date
            );
        }

        return null;
    }
}
