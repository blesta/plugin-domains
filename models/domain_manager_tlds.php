<?php
use Blesta\Core\Util\Input\Fields\InputFields;

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
    public function getList(array $filters = [], $page = 1, array $order = ['order' => 'asc'])
    {
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
    public function getAll(array $filters = [], array $order = ['order' => 'asc'])
    {
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

            // Set the package configurable options
            $this->assignConfigurableOptions($vars['package_id'], $vars);

            // Set the TLD order
            $vars['order'] = 0;
            $last_tld = $this->getTlds(['company_id' => $vars['company_id']])
                ->order(['order' => 'desc'])
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
        Loader::loadHelpers($this, ['Form']);

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

        // Get company settings
        $company_settings = $this->Form->collapseObjectArray(
            $this->Companies->getSettings($vars['company_id']),
            'value',
            'key'
        );

        // Fetch company default currency
        $default_currency = isset($company_settings['default_currency']) ? $company_settings['default_currency'] : 'USD';

        // Fetch all company languages
        $languages = $this->Languages->getAll($vars['company_id']);

        // Create package
        $package_params = [
            'module_id' => $vars['module_id'],
            'names' => [],
            'descriptions' => [],
            'hidden' => '1',
            'status' => 'inactive',
            'company_id' => $vars['company_id'],
            'pricing' => [
                ['term' => 1, 'period' => 'year', 'currency' => $default_currency]
            ],
            'groups' => [$vars['package_group_id']]
        ];

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
        if (isset($vars['ns'])) {
            $fields = [
                'package_id' => $package_id,
                'key' => 'ns',
                'value' => serialize($vars['ns']),
                'serialized' => '1'
            ];
            $this->Record->duplicate('package_meta.value', '=', $fields['value'])
                ->insert('package_meta', $fields);
        }

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
     *  - package_id The ID of the package to be used for pricing and sale of this TLD
     *  - dns_management Whether to include DNS management for this TLD
     *  - email_forwarding Whether to include email forwarding for this TLD
     *  - id_protection Whether to include ID protection for this TLD
     *  - epp_code Whether to include EPP Code for this TLD
     *  - ns A numerically indexed array, containing the nameservers for the given TLD
     * @return int The identifier of the TLD that was updated, void on error
     */
    public function edit($tld, array $vars)
    {
        Loader::loadModels($this, ['Packages', 'ModuleManager', 'Companies']);
        Loader::loadHelpers($this, ['Form']);

        $vars['tld'] = $tld;
        $tld = $this->get($vars['tld']);

        $this->Input->setRules($this->getRules($vars, true));

        if ($this->Input->validates($vars)) {
            // Get package
            $package = $this->Packages->get(isset($vars['package_id']) ? $vars['package_id'] : $tld->package_id);

            // Update package
            $fields = [
                'module_id' => isset($vars['module_id'])
                    ? $vars['module_id']
                    : (isset($package->module_id) ? $package->module_id : null),
                'names' => $package->names,
                'descriptions' => $package->descriptions,
                'module_row' => isset($vars['module_row'])
                    ? $vars['module_row']
                    : (isset($package->module_row) ? $package->module_row : null),
                'module_group' => isset($vars['module_group'])
                    ? $vars['module_group']
                    : (isset($package->module_group) ? $package->module_group : null),
                'status' => $package->status,
                'email_content' => isset($vars['email_content'])
                    ? $vars['email_content']
                    : (isset($package->email_content) ? $package->email_content : null),
                'pricing' => $package->pricing,
                'option_groups' => isset($vars['option_groups']) ? $vars['option_groups'] : [],
                'meta' => isset($vars['meta'])
                    ? $vars['meta']
                    : (isset($package->meta) ? $package->meta : []),
            ];
            $fields = json_decode(json_encode($fields), true);

            foreach ($fields as $key => $value) {
                if (empty($value) && !is_array($value)) {
                    unset($fields[$key]);
                }
            }

            $this->Packages->edit($package->id, $fields);

            if (($errors = $this->Packages->errors())) {
                $this->Input->setErrors($errors);
                return;
            }

            // Get company settings
            $company_settings = $this->Form->collapseObjectArray(
                $this->Companies->getSettings(Configure::get('Blesta.company_id')),
                'value',
                'key'
            );

            // Update configurable options
            if (!empty($fields['option_groups'])) {
                $options = ['dns_management', 'email_forwarding', 'id_protection', 'epp_code'];
                $option_groups = array_flip($fields['option_groups']);

                foreach ($options as $option) {
                    $option_group_id = isset($company_settings['domain_manager_' . $option . '_option_group'])
                        ? $company_settings['domain_manager_' . $option . '_option_group']
                        : null;

                    if (!is_null($option_group_id)) {
                        $vars[$option] = isset($option_groups[$option_group_id]) ? 1 : 0;
                    }
                }
            }

            $this->assignConfigurableOptions($tld->package_id, $vars);

            // Update TLD
            $fields = ['tld', 'package_id', 'dns_management', 'email_forwarding', 'id_protection', 'epp_code'];
            $this->Record->where('tld', '=', $vars['tld'])
                ->where('company_id', '=', Configure::get('Blesta.company_id'))
                ->update('domain_manager_tlds', $vars, $fields);

            return $vars['tld'];
        }
    }

    /**
     * Updates the pricings of a TLD
     *
     * @param int $tld The identifier of the TLD to edit
     * @param array $pricings A key => value array, where the key is the package pricing ID
     *  and the value the pricing row
     */
    public function updatePricings($tld, array $pricings)
    {
        Loader::loadModels($this, ['Pricings', 'Currencies']);
        Loader::loadHelpers($this, ['Form']);

        $tld = $this->get($tld);

        // Get company currencies
        $currencies = $this->Form->collapseObjectArray(
            $this->Currencies->getAll(Configure::get('Blesta.company_id')),
            'code',
            'code'
        );

        // Set empty checkboxes
        $enabled_pricings = 0;
        for ($i = 1; $i <= 10; $i++) {
            foreach ($currencies as $currency) {
                $pricings[$i][$currency]['enabled'] =
                    isset($pricings[$i][$currency]['enabled']) ? $pricings[$i][$currency]['enabled'] : null;

                if ($pricings[$i][$currency]['enabled']) {
                    $enabled_pricings++;
                }
            }
        }

        // Update pricing
        if (!empty($pricings)) {
            foreach ($pricings as $term => $term_pricing) {
                foreach ($term_pricing as $currency => $pricing) {
                    $pricing['currency'] = $currency;
                    $pricing['term'] = $term;

                    $pricing_row = $this->getPricing($tld->package_id, $term, $currency);

                    if (!empty($pricing_row)) {
                        if ((bool)$pricing['enabled']) {
                            $this->updatePricing($pricing_row->id, $pricing);
                        } else if ($enabled_pricings >= 1) {
                            $this->disablePricing($pricing_row->id);
                        } else {
                            $this->Input->setErrors([
                                'count' => [
                                    'message' => Language::_('DomainManagerTlds.!error.package_pricing.count', true)
                                ]
                            ]);

                            return;
                        }
                    } else if ((bool) $pricing['enabled']) {
                        $this->addPricing($tld->package_id, $pricing);
                    }
                }
            }
        }
    }

    /**
     * Get the pricing of a TLD by term and currency
     *
     * @param int $package_id The ID of the package belonging ot the TLD
     * @param int $term The term of the pricing to look for
     * @param string $currency The currency of the pricing to look for
     * @return stdClass An object containing the pricing matching the given term and currency,
     *  void if a match could not be found
     */
    public function getPricing($package_id, $term, $currency)
    {
        // Verify if the given package id belongs to a TLD
        if (!($tld = $this->getByPackage($package_id))) {
            return false;
        }

        return $this->Record->select('pricings.*')
            ->from('pricings')
            ->innerJoin('package_pricing', 'package_pricing.pricing_id', '=', 'pricings.id', false)
            ->where('package_pricing.package_id', '=', $package_id)
            ->where('pricings.term', '=', $term)
            ->where('pricings.period', '=', 'year')
            ->where('pricings.currency', '=', $currency)
            ->fetch();
    }

    /**
     * Disable a TLD pricing
     *
     * @param int $pricing_id The ID of the pricing to disable
     * @return int The ID of the pricing, void on error
     */
    private function disablePricing($pricing_id)
    {
        Loader::loadModels($this, ['Packages', 'Pricings']);

        // Get package
        $package = $this->Record->select('packages.*')
            ->from('packages')
            ->innerJoin('package_pricing', 'package_pricing.package_id', '=', 'packages.id', false)
            ->where('package_pricing.pricing_id', '=', $pricing_id)
            ->fetch();

        if (isset($package->id)) {
            $package = $this->Packages->get($package->id);
        }

        // Get pricing
        $pricing = $this->Pricings->get($pricing_id);

        // Check if is the last pricing of the package
        if (count($package->pricing) <= 1) {
            $this->Input->setErrors([
                'count' => [
                    'message' => Language::_('DomainManagerTlds.!error.package_pricing.count', true)
                ]
            ]);

            return;
        }

        // Check if there are any services using this pricing
        $one_year_term = $this->getPricing($package->id, 1, $pricing->currency);
        $services_pricing = $this->Record->select('services.*')
            ->from('services')
            ->innerJoin('package_pricing', 'package_pricing.id', '=', 'services.pricing_id', false)
            ->where('package_pricing.pricing_id', '=', $pricing_id)
            ->fetchAll();

        if (!empty($services_pricing) && $one_year_term !== $pricing_id) {
            // Migrate all pricing services to the 1 year pricing
            $pricing_package = $this->Record->select()
                ->from('package_pricing')
                ->where('package_pricing.pricing_id', '=', $one_year_term->id)
                ->fetch();

            foreach ($services_pricing as $service) {
                if (isset($pricing_package->id)) {
                    $this->Record->where('id', '=', $service->id)
                        ->update('services', ['pricing_id' => $pricing_package->id]);
                }
            }
        } else if (!empty($services_pricing) && $one_year_term == $pricing_id) {
            $this->Input->setErrors([
                'service' => [
                    'message' => Language::_('DomainManagerTlds.!error.package_pricing.service', true)
                ]
            ]);

            return;
        }

        // Delete pricing
        $this->Record->from('pricings')
            ->where('pricings.id', '=', $pricing_id)
            ->delete();
        $this->Record->from('package_pricing')
            ->where('package_pricing.pricing_id', '=', $pricing_id)
            ->delete();

        return $pricing_id;
    }

    /**
     * Updates an existing pricing
     *
     * @param int $pricing_id The ID of the pricing to update
     * @param array $vars An array of pricing info including:
     *
     *  - term The term as an integer 1-65535 (optional, default 1)
     *  - price The price of this term (optional, default 0.00)
     *  - price_renews The renewal price of this term (optional, default null)
     *  - price_transfer The transfer price of this term (optional, default null)
     *  - setup_fee The setup fee for this pricing (optional, default 0.00)
     *  - cancel_fee The cancellation fee for this pricing (optional, default 0.00)
     *  - currency The ISO 4217 currency code for this pricing (optional, default USD)
     */
    private function updatePricing($pricing_id, array $vars)
    {
        Loader::loadModels($this, ['Pricings']);

        $vars = array_merge($vars, [
            'company_id' => Configure::get('Blesta.company_id'),
            'period' => 'year'
        ]);
        $this->Pricings->edit($pricing_id, $vars);
    }

    /**
     * Adds a new pricing for the given package
     *
     * @param int $package_id The ID of the package to add the new pricing
     * @param array $vars An array of pricing info including:
     *
     *  - term The term as an integer 1-65535 (optional, default 1)
     *  - price The price of this term (optional, default 0.00)
     *  - price_renews The renewal price of this term (optional, default null)
     *  - price_transfer The transfer price of this term (optional, default null)
     *  - setup_fee The setup fee for this pricing (optional, default 0.00)
     *  - cancel_fee The cancellation fee for this pricing (optional, default 0.00)
     *  - currency The ISO 4217 currency code for this pricing (optional, default USD)
     */
    private function addPricing($package_id, array $vars)
    {
        Loader::loadModels($this, ['Pricings']);

        // Verify if the given package id belongs to a TLD
        if (!($tld = $this->getByPackage($package_id))) {
            return false;
        }

        // Add pricing
        $vars = array_merge($vars, [
            'company_id' => Configure::get('Blesta.company_id'),
            'period' => 'year'
        ]);
        $pricing_id = $this->Pricings->add($vars);

        // Map pricing to package
        $this->Record->insert('package_pricing', [
            'package_id' => $package_id,
            'pricing_id' => $pricing_id
        ]);
    }

    /**
     * Assigns the configurable options group to the given package
     *
     * @param int $package_id The ID of the package to assign the configurable options
     * @param array $vars An array of input data including:
     *
     *  - dns_management Whether to include DNS management for this TLD
     *  - email_forwarding Whether to include email forwarding for this TLD
     *  - id_protection Whether to include ID protection for this TLD
     *  - epp_code Whether to include EPP Code for this TLD
     */
    private function assignConfigurableOptions($package_id, array $vars)
    {
        // Verify if the given package id belongs to a TLD
        if (!($tld = $this->getByPackage($package_id))) {
            return false;
        }

        Loader::loadModels($this, ['Companies']);
        Loader::loadHelpers($this, ['Form']);

        // Get company settings
        $company_settings = $this->Form->collapseObjectArray(
            $this->Companies->getSettings(Configure::get('Blesta.company_id')),
            'value',
            'key'
        );

        // Update configurable options
        $options = ['dns_management', 'email_forwarding', 'id_protection', 'epp_code'];

        foreach ($options as $option) {
            if (isset($company_settings['domain_manager_' . $option . '_option_group'])) {
                $option_group_id = $company_settings['domain_manager_' . $option . '_option_group'];

                if (isset($vars[$option]) && (bool)$vars[$option]) {
                    $fields = [
                        'package_id' => $package_id,
                        'option_group_id' => $option_group_id
                    ];
                    $this->Record->duplicate('package_option.option_group_id', '=', $fields['option_group_id'])
                        ->insert('package_option', $fields);
                } else {
                    $this->Record->from('package_option')
                        ->where('package_option.package_id', '=', $package_id)
                        ->where('package_option.option_group_id', '=', $option_group_id)
                        ->delete();
                }
            }
        }
    }

    /**
     * Fetches the package fields, html, email tags and email template of the given package id
     *
     * @param int $package_id The ID of the package to fetch the fields
     * @return array An array containing the package fields, html, tags and email template
     */
    public function getTldFields($package_id)
    {
        // Verify if the given package id belongs to a TLD
        if (!($tld = $this->getByPackage($package_id))) {
            return false;
        }

        Loader::loadModels($this, ['Packages', 'ModuleManager', 'PackageOptionGroups']);
        Loader::loadHelpers($this, ['Form', 'DataStructure']);

        $this->ArrayHelper = $this->DataStructure->create('Array');

        // Get package
        $package = $this->Packages->get($package_id);

        // Initialize module
        $module = $this->ModuleManager->initModule($package->module_id);

        // Fetch all package fields this module requires
        $package_fields = $module->getPackageFields();
        $fields = $package_fields->getFields();
        $html = $package_fields->getHtml();
        $tags = $module->getEmailTags();
        $template = $module->getEmailTemplate();
        $row_name = $module->moduleRowName();
        $group_name = $module->moduleGroupName();

        if (empty($group_name)) {
            $group_name = Language::_('DomainManagerTlds.getTldFields.group', true);
        }

        // Remove TLDs and Nameservers fields
        $remove_fields = ['meta[ns][]', 'meta[tlds][]'];

        foreach ($fields as $key => $field) {
            $remove_field = false;
            foreach ($field->fields as $sub_field) {
                if (in_array($sub_field->params['name'], $remove_fields)) {
                    $remove_field = true;
                }
            }

            if ($remove_field) {
                unset($fields[$key]);
            }
        }

        // Get module groups and rows
        $groups = $this->ArrayHelper->numericToKey(
            (array) $this->ModuleManager->getGroups($package->module_id),
            'id',
            'name'
        );
        $rows = $this->ArrayHelper->numericToKey(
            (array) $this->ModuleManager->getRows($package->module_id),
            'id',
            'meta'
        );

        $row_key = $module->moduleRowMetaKey();
        foreach ($rows as $key => &$value) {
            $value = $value->$row_key;
        }

        // Fetch all available package option groups
        $package_option_groups = $this->Form->collapseObjectArray(
            $this->PackageOptionGroups->getAll(Configure::get('Blesta.company_id')),
            'name',
            'id'
        );
        $selected_option_groups = $this->Form->collapseObjectArray($package->option_groups, 'name', 'id');

        // Fetch selectable package option groups
        foreach ($package_option_groups as $id => $name) {
            if (isset($selected_option_groups[$id])) {
                unset($package_option_groups[$id]);
            }
        }

        return compact(
            'fields',
            'html',
            'tags',
            'template',
            'row_name',
            'group_name',
            'groups',
            'rows',
            'package_option_groups',
            'selected_option_groups'
        );
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
     *  - epp_code Whether to include EPP Code for this TLD
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

        if (isset($filters['epp_code'])) {
            $this->Record->where('domain_manager_tlds.epp_code', '=', $filters['epp_code']);
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
