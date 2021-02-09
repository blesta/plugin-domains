<?php
/**
 * domain_manager_tlds Management
 *
 * @link https://www.blesta.com Blesta
 */
class DomainManagerTlds extends DomainManagerModel
{
    /**
     * Returns a list of TLDs for the given company
     *
     * @param array $filters A list of filters for the query
     *
     *  - tld The TLD
     *  - company_id The ID of the company for which this TLD is available
     *  - package_id The package to be used for pricing and sale of this TLD
     *  - dns_management Whether DNS management is included for the TLDs
     *  - email_forwarding Whether email forwarding is included for the TLDs
     *  - id_protection Whether ID protection is included for the TLDs
     * @param int $page The page number of results to fetch
     * @param array $order A key/value pair array of fields to order the results by
     * @return array An array of stdClass objects
     */
    public function getList(
        array $filters = [],
        $page = 1,
        array $order = ['tld' => 'desc']
    ) {
        $tlds = $this->getTlds($filters)
            ->order($order)
            ->limit($this->getPerPage(), (max(1, $page) - 1) * $this->getPerPage())
            ->fetchAll();

        return $tlds;
    }

    /**
     * Returns the total number of TLDs for the given filters
     *
     * @param array $filters A list of filters for the query
     *
     *  - tld The TLD
     *  - company_id The ID of the company for which this TLD is available
     *  - package_id The package to be used for pricing and sale of this TLD
     *  - dns_management Whether DNS management is included for the TLDs
     *  - email_forwarding Whether email forwarding is included for the TLDs
     *  - id_protection Whether ID protection is included for the TLDs
     * @return int The total number of TLDs for the given filters
     */
    public function getListCount(array $filters = [])
    {
        return $this->getTlds($filters)->numResults();
    }

    /**
     * Returns all TLDs in the system for the given filters
     *
     * @param array $filters A list of filters for the query
     *
     *  - tld The TLD
     *  - company_id The ID of the company for which this TLD is available
     *  - package_id The package to be used for pricing and sale of this TLD
     *  - dns_management Whether DNS management is included for the TLDs
     *  - email_forwarding Whether email forwarding is included for the TLDs
     *  - id_protection Whether ID protection is included for the TLDs
     * @param array $order A key/value pair array of fields to order the results by
     * @return array An array of stdClass objects
     */
    public function getAll(
        array $filters = [],
        array $order = ['tld' => 'desc']
    ) {
        $tlds = $this->getTlds($filters)->order($order)->fetchAll();

        return $tlds;
    }

    /**
     * Fetches the given TLD
     *
     * @param int $tld The TLD of the record to fetch
     * @return mixed A stdClass object representing the TLD, false if no such record exists
     */
    public function get($tld)
    {
        return $this->getTlds(['tld' => $tld])->fetch();
    }

    /**
     * Add a TLD
     *
     * @param array $vars An array of input data including:
     *
     *  - tld The TLD
     *  - company_id The ID of the company for which this TLD is available
     *  - package_id The ID of the package to be used for pricing and sale of this TLD
     *  - dns_management Whether to include DNS management for this TLD
     *  - email_forwarding Whether to include email forwarding for this TLD
     *  - id_protection Whether to include ID protection for this TLD
     * @return int The identifier of the record that was created, void on error
     */
    public function add(array $vars)
    {
        $this->Input->setRules($this->getRules($vars));

        if ($this->Input->validates($vars)) {
            $fields = ['tld', 'company_id', 'package_id', 'dns_management', 'email_forwarding', 'id_protection'];
            $this->Record->insert('domain_manager_tlds', $vars, $fields);

            return $this->Record->lastInsertId();
        }
    }

    /**
     * Edit a TLD
     *
     * @param int $tld The identifier of the TLD to edit
     * @param array $vars An array of input data including:
     *
     *  - package_id The package to be used for pricing and sale of this TLD
     *  - dns_management Whether to include DNS management for this TLD
     *  - email_forwarding Whether to include email forwarding for this TLD
     *  - id_protection Whether to include ID protection for this TLD
     * @return int The identifier of the TLD that was updated, void on error
     */
    public function edit($tld, array $vars)
    {

        $vars['tld'] = $tld;
        $this->Input->setRules($this->getRules($vars, true));

        if ($this->Input->validates($vars)) {
            $fields = ['tld', 'package_id', 'dns_management', 'email_forwarding', 'id_protection'];
            $this->Record->where('tld', '=', $tld)->update('domain_manager_tlds', $vars, $fields);

            return $tld;
        }
    }

    /**
     * Permanently deletes the given TLD
     *
     * @param int $tld The identifier of the TLD to delete
     */
    public function delete($tld)
    {
        // Delete a TLD
        $this->Record->from('domain_manager_tlds')->
            where('domain_manager_tlds.tld', '=', $tld)->
            delete();
    }

    /**
     * Returns a partial query
     *
     * @param array $filters A list of filters for the query
     *
     *  - tld The TLD
     *  - company_id The ID of the company for which this TLD is available
     *  - package_id The package to be used for pricing and sale of this TLD
     *  - dns_management Whether DNS management is included for the TLDs
     *  - email_forwarding Whether email forwarding is included for the TLDs
     *  - id_protection Whether ID protection is included for the TLDs
     * @return Record A partially built query
     */
    private function getTlds(array $filters = [])
    {
        $this->Record->select()->from('domain_manager_tlds');

        if (isset($filters['tld'])) {
            $this->Record->where('domain_manager_tlds.tld', '=', $filters['tld']);
        }

        if (isset($filters['package_id'])) {
            $this->Record->where('domain_manager_tlds.package_id', '=', $filters['package_id']);
        }

        if (isset($filters['dns_management'])) {
            $this->Record->where('domain_manager_tlds.dns_management', '=', $filters['dns_management']);
        }

        if (isset($filters['email_forwarding'])) {
            $this->Record->where('domain_manager_tlds.email_forwarding', '=', $filters['email_forwarding']);
        }

        if (isset($filters['id_protection'])) {
            $this->Record->where('domain_manager_tlds.id_protection', '=', $filters['id_protection']);
        }

        return $this->Record;
    }

    /**
     * Gets a list TLDs to include with Blesta by default
     *
     * @return array A list of TLDs
     */
    public function getDefaultTlds()
    {
        return ['.com', '.net', '.org'];
    }

    /**
     * Returns all validation rules for adding/editing extensions
     *
     * @param array $vars An array of input key/value pairs
     *
     *  - tld The TLD
     *  - package_id The package to be used for pricing and sale of this TLD
     *  - dns_management Whether to include DNS management for this TLD
     *  - email_forwarding Whether to include email forwarding for this TLD
     *  - id_protection Whether to include ID protection for this TLD
     * @param bool $edit True if this if an edit, false otherwise
     * @return array An array of validation rules
     */
    private function getRules(array $vars, $edit = false)
    {
        $rules = [
            'tld' => [
                'valid' => [
                    'if_set' => $edit,
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('DomainManagerTlds.!error.tld.empty', true)
                ]
            ],
            'package_id' => [
                'valid' => [
                    'if_set' => $edit,
                    'rule' => [[$this, 'validateExists'], 'id', 'packages'],
                    'message' => Language::_('DomainManagerTlds.!error.package_id.exists', true)
                ]
            ],
            'company_id' => [
                'valid' => [
                    'if_set' => $edit,
                    'rule' => [[$this, 'validateExists'], 'id', 'companies'],
                    'message' => Language::_('DomainManagerTlds.!error.company_id.exists', true)
                ]
            ],
            'dns_management' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', [0, 1]],
                    'message' => Language::_('DomainManagerTlds.!error.dns_management.valid', true)
                ]
            ],
            'email_forwarding' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', [0, 1]],
                    'message' => Language::_('DomainManagerTlds.!error.email_forwarding.valid', true)
                ]
            ],
            'id_protection' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', [0, 1]],
                    'message' => Language::_('DomainManagerTlds.!error.id_protection.valid', true)
                ]
            ]
        ];

        return $rules;
    }
}
