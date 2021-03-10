<?php
/**
 * Domain Manager TLDs Management Model
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
        array $order = ['order' => 'asc']
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
        array $order = ['order' => 'asc']
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
        return $this->getTlds([
            'tld' => $tld,
            'company_id' => Configure::get('Blesta.company_id')
        ])->fetch();
    }

    /**
     * Fetches a TLD by the given package id
     *
     * @param int $package_id The Package ID belonging to the TLD to fetch
     * @return mixed A stdClass object representing the TLD, false if no such record exists
     */
    public function getByPackage($package_id)
    {
        return $this->getTlds([
            'package_id' => $package_id,
            'company_id' => Configure::get('Blesta.company_id')
        ])->fetch();
    }

    /**
     * Add a TLD
     *
     * @param array $vars An array of input data including:
     *
     *  - tld The TLD to add
     *  - ns A numerically indexed array, containing the nameservers for the given TLD
     *  - company_id The ID of the company for which this TLD is available (optional)
     *  - package_id The ID of the package to be used for pricing and sale of this TLD (optional)
     *  - package_group_id The ID of the TLDs package group (optional)
     *  - module_id The ID of the registrar module to be used for this TLD (optional)
     *  - dns_management Whether to include DNS management for this TLD
     *  - email_forwarding Whether to include email forwarding for this TLD
     *  - id_protection Whether to include ID protection for this TLD
     *  - epp_code Whether to include EPP Code for this TLD
     * @return array An array containing the TLD
     */
    public function add(array $vars)
    {
        Loader::loadModels($this, ['ModuleManager', 'Packages']);

        // Set company id
        if (!isset($vars['company_id'])) {
            $vars['company_id'] = Configure::get('Blesta.company_id');
        }

        $this->Input->setRules($this->getRules($vars));

        if ($this->Input->validates($vars)) {
            // Update the package if a package id and module id is provided
            if (isset($vars['package_id']) && isset($vars['module_id'])) {
                $this->Record->where('id', '=', $vars['package_id'])
                    ->update('packages', ['module_id' => $vars['module_id']]);
            }

            // Create a new package, if a package id is not provided
            if (!isset($vars['package_id'])) {
                if (isset($vars['module_id'])) {
                    $module = $this->ModuleManager->get($vars['module_id']);
                }

                // If a module id is not provided, use the None module by default
                if (!isset($vars['module_id'])) {
                    if (!$this->ModuleManager->isInstalled('none', $vars['company_id'])) {
                        $this->ModuleManager->add(['class' => 'none', 'company_id' => $vars['company_id']]);
                    }

                    if (($none_module = $this->ModuleManager->getByClass('none', $vars['company_id']))) {
                        $module = isset($none_module[0]) ? $none_module[0] : null;
                    }
                }

                if (is_null($module)) {
                    return;
                }

                // Create the package
                $params = [
                    'tld' => $vars['tld'],
                    'module_id' => $module->id,
                    'company_id' => $vars['company_id']
                ];

                if (isset($vars['package_group_id'])) {
                    $params['package_group_id'] = $vars['package_group_id'];
                }
                if (isset($vars['ns'])) {
                    $params['ns'] = $vars['ns'];
                }

                $package_id = $this->createPackage($params);
                $vars['package_id'] = $package_id;

                if (empty($package_id)) {
                    return;
                }
            }

            // Set the TLD order
            $vars['order'] = 0;
            $last_tld = $this->getTlds(['company_id' => $vars['company_id']])
                ->order(['package_id' => 'desc'])
                ->fetch();

            if (isset($last_tld->order)) {
                $vars['order'] = (int) $last_tld->order + 1;
            }

            $fields = [
                'tld',
                'company_id',
                'package_id',
                'order',
                'dns_management',
                'email_forwarding',
                'id_protection',
                'epp_code'
            ];
            $this->Record->insert('domain_manager_tlds', $vars, $fields);

            return $vars;
        }
    }

    /**
     * Creates a package for a given TLD
     *
     * @param array $vars An array of input data including:
     *
     *  - tld The TLD of the package
     *  - ns A numerically indexed array, containing the nameservers for the given TLD
     *  - module_id The ID of the registrar module for this package
     *  - package_group_id The ID of the TLDs package group (optional)
     *  - company_id The ID of the company for which the TLD of this package is available (optional)
     * @return int The ID of the TLD package
     */
    private function createPackage(array $vars)
    {
        Loader::loadModels($this, ['Currencies', 'Languages', 'Companies']);

        // Set company id
        if (!isset($vars['company_id'])) {
            $vars['company_id'] = Configure::get('Blesta.company_id');
        }

        // Set package group id
        if (!isset($vars['package_group_id'])) {
            $domain_manager_package_group = $this->Companies->getSetting(
                $vars['company_id'],
                'domain_manager_package_group'
            );
            $vars['package_group_id'] = isset($domain_manager_package_group->value)
                ? $domain_manager_package_group->value
                : null;
        }

        // Fetch all company currencies
        $currencies = $this->Currencies->getAll($vars['company_id']);

        // Fetch all company languages
        $languages = $this->Languages->getAll($vars['company_id']);

        // Create package
        $package_params = [
            'module_id' => $vars['module_id'],
            'names' => [],
            'descriptions' => [],
            'hidden' => '1',
            'company_id' => $vars['company_id'],
            'pricing' => [],
            'groups' => [$vars['package_group_id']]
        ];

        // Add a pricing for terms 1-10 years for each currency
        foreach ($currencies as $currency) {
            for ($i = 1; $i <= 10; $i++) {
                $package_params['pricing'][] = ['term' => $i, 'period' => 'year', 'currency' => $currency->code];
            }
        }

        // Add a package name and email content for each language
        foreach ($languages as $language) {
            $package_params['names'][] = [
                'lang' => $language->code,
                'name' => $vars['tld']
            ];
            $package_params['email_content'][] = [
                'lang' => $language->code,
                'html' => '',
                'text' => ''
            ];
        }

        // Add the package for this TLD
        $package_id = $this->Packages->add($package_params);

        if (($errors = $this->Packages->errors())) {
            $this->Input->setErrors($errors);

            return;
        }

        // Set TLD to the package meta
        $fields = [
            'package_id' => $package_id,
            'key' => 'tlds',
            'value' => serialize([$vars['tld']]),
            'serialized' => '1'
        ];
        $this->Record->duplicate('package_meta.value', '=', $fields['value'])
            ->insert('package_meta', $fields);

        // Set the nameservers to the package meta
        $fields = [
            'package_id' => $package_id,
            'key' => 'ns',
            'value' => serialize($vars['ns']),
            'serialized' => '1'
        ];
        $this->Record->duplicate('package_meta.value', '=', $fields['value'])
            ->insert('package_meta', $fields);

        // Set the default module row, if any
        $module = $this->ModuleManager->get($vars['module_id']);
        $module_row = null;

        if (!empty($module->rows)) {
            $module_row = reset($module->rows);
            $this->Record->where('id', '=', $package_id)
                ->update('packages', ['module_row' => $module_row->id]);
        }

        return $package_id;
    }

    /**
     * Edit a TLD
     *
     * @param int $tld The identifier of the TLD to edit
     * @param array $vars An array of input data including:
     *
     *  - ns A numerically indexed array, containing the nameservers for the given TLD
     *  - package_id The ID of the package to be used for pricing and sale of this TLD
     *  - module_id The ID of the registrar module to be used for this TLD
     *  - dns_management Whether to include DNS management for this TLD
     *  - email_forwarding Whether to include email forwarding for this TLD
     *  - id_protection Whether to include ID protection for this TLD
     *  - epp_code Whether to include EPP Code for this TLD
     * @return int The identifier of the TLD that was updated, void on error
     */
    public function edit($tld, array $vars)
    {
        $vars['tld'] = $tld;
        $tld = $this->get($vars['tld']);

        $this->Input->setRules($this->getRules($vars, true));

        if ($this->Input->validates($vars)) {
            // Update module
            Loader::loadModels($this, ['Packages', 'ModuleManager']);

            if (isset($vars['module_id']) && ($module = $this->ModuleManager->get($vars['module_id']))) {
                // Get module row
                $module_row = null;
                if (!empty($module->rows)) {
                    $module_row = reset($module->rows);
                }

                // Update package
                $fields = [
                    'module_id' => $vars['module_id']
                ];
                if (!is_null($module_row)) {
                    $fields['module_row'] = $module_row->id;
                }
                $this->Record->where('id', '=', $tld->package_id)
                    ->update('packages', $fields);
            }

            // Update name servers
            if (isset($vars['ns'])) {
                $fields = [
                    'package_id' => $tld->package_id,
                    'key' => 'ns',
                    'value' => serialize($vars['ns']),
                    'serialized' => '1'
                ];
                $this->Record->duplicate('package_meta.value', '=', $fields['value'])
                    ->insert('package_meta', $fields);
            }

            // Update TLD
            $fields = ['tld', 'package_id', 'dns_management', 'email_forwarding', 'id_protection', 'epp_code'];
            $this->Record->where('tld', '=', $vars['tld'])
                ->where('company_id', '=', Configure::get('Blesta.company_id'))
                ->update('domain_manager_tlds', $vars, $fields);

            return $vars['tld'];
        }
    }

    /**
     * Updates the pricing of a TLD
     *
     * @param int $tld The identifier of the TLD to edit
     * @param array $pricing A key => value array, where the key is the pricing ID
     *  and the value the pricing row
     */
    public function updatePricing($tld, array $pricing)
    {
        Loader::loadModels($this, ['Pricings']);
        Loader::loadHelpers($this, ['CurrencyFormat']);

        $tld = $this->get($tld);

        if (!empty($pricing)) {
            foreach ($pricing as $pricing_id => $pricing_row) {
                $package_pricing = $this->Record->select()
                    ->from('package_pricing')
                    ->where('pricing_id', '=', $pricing_id)
                    ->fetch();

                if ($package_pricing->package_id == $tld->package_id) {
                    $old_pricing = $this->Pricings->get($pricing_id);

                    // Format row
                    foreach ($pricing_row as $key => $value) {
                        if (empty($value)) {
                            unset($pricing_row[$key]);
                            continue;
                        }

                        $pricing_row[$key] = $this->CurrencyFormat->format(
                            $value,
                            $old_pricing->currency,
                            ['prefix' => false, 'suffix' => false, 'with_separator' => false, 'code' => false, 'decimals' => 4]
                        );
                    }

                    if (isset($old_pricing->enabled)) {
                        $pricing_row['enabled'] = isset($pricing_row['enabled']) ? $pricing_row['enabled'] : '0';
                    }

                    $this->Record->where('id', '=', $pricing_id)
                        ->update('pricings', $pricing_row);
                }
            }
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
            where('domain_manager_tlds.company_id', '=', Configure::get('Blesta.company_id'))->
            delete();
    }

    /**
     * Enables the given TLD
     *
     * @param int $tld The identifier of the TLD to enable
     */
    public function enable($tld)
    {
        // Get TLD
        $tld = $this->get($tld);

        $this->Record->where('id', '=', $tld->package_id)
            ->update('packages', ['status' => 'active']);
    }

    /**
     * Disables the given TLD
     *
     * @param int $tld The identifier of the TLD to disable
     */
    public function disable($tld)
    {
        // Get TLD
        $tld = $this->get($tld);

        $this->Record->where('id', '=', $tld->package_id)
            ->update('packages', ['status' => 'inactive']);
    }

    /**
     * Sort the TLDs
     *
     * @param array $tlds A key => value array, where the key is the order of
     *  the TLD and the value the ID of the package belonging to the TLD
     */
    public function sortTlds(array $tlds = [])
    {
        foreach($tlds as $order => $package_id) {
            $this->Record->where('package_id', '=', $package_id)
                ->update('domain_manager_tlds', ['order' => $order]);
        }
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

        if (isset($filters['company_id'])) {
            $this->Record->where('domain_manager_tlds.company_id', '=', $filters['company_id']);
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
                ],
                'exists' => [
                    'if_set' => $edit,
                    'rule' => function($tld) {
                        $parent = new stdClass();
                        Loader::loadComponents($parent, ['Record']);

                        $count = $this->Record->select()
                            ->from('domain_manager_tlds')
                            ->where('tld', '=', $tld)
                            ->where('company_id', '=', Configure::get('Blesta.company_id'))
                            ->numResults();

                        return !($count > 0);
                    },
                    'message' => Language::_('DomainManagerTlds.!error.tld.exists', true)
                ],
                'length' => [
                    'if_set' => true,
                    'rule' => ['minLength', 3],
                    'message' => Language::_('DomainManagerTlds.!error.tld.length', true)
                ]
            ],
            'package_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'packages'],
                    'message' => Language::_('DomainManagerTlds.!error.package_id.exists', true)
                ]
            ],
            'package_group_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'package_groups'],
                    'message' => Language::_('DomainManagerTlds.!error.package_group_id.exists', true)
                ]
            ],
            'module_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'modules'],
                    'message' => Language::_('DomainManagerTlds.!error.module_id.exists', true)
                ]
            ],
            'company_id' => [
                'exists' => [
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
            ,
            'epp_code' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', [0, 1]],
                    'message' => Language::_('DomainManagerTlds.!error.epp_code.valid', true)
                ]
            ]
        ];

        if ($edit) {
            unset($rules['tld']['exists']);
        }

        return $rules;
    }
}
