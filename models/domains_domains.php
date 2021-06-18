<?php

/**
 * Domain Manager TLDs Management Model
 *
 * @link https://www.blesta.com Blesta
 */
class DomainsDomains extends DomainsModel
{
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
}
