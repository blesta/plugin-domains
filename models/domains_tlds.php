<?php

use Blesta\Core\Util\Input\Fields\InputFields;
use Blesta\Core\Util\Input\Fields\Html as FieldsHtml;

/**
 * Domain Manager TLDs Management Model
 *
 * @link https://www.blesta.com Blesta
 */
class DomainsTlds extends DomainsModel
{
    /**
     * @var array A list of config option controlled potential features
     */
    private $config_option_features = ['dns_management', 'email_forwarding', 'id_protection'];
    /**
     * @var array A list of package meta controlled potential features
     */
    private $package_meta_features = ['epp_code'];

    /**
     * Append features to the TLD and marks them based on which configurable option groups are assigned to the package
     *
     * @param stdClass $tld The TLD object to which features are appended
     * @return stdClass The modified TLD object
     */
    private function appendFeatures($tld)
    {
        Loader::loadModels($this, ['Companies']);
        Loader::loadHelpers($this, ['Form']);
        // Get company settings
        $company_settings = $this->Form->collapseObjectArray(
            $this->Companies->getSettings($tld->company_id),
            'value',
            'key'
        );

        // Get all the option groups assigned to the package
        $package_option_groups = $this->Form->collapseObjectArray(
            $this->Record->select()->from('package_option')->where('package_id', '=', $tld->package_id)->fetchAll(),
            'package_id',
            'option_group_id'
        );

        // Assign a feature if the TLD package is assigned the correct option_group
        foreach ($this->config_option_features as $feature) {
            $option_group_id = $company_settings['domains_' . $feature . '_option_group'] ?? null;
            $tld->{$feature} = (array_key_exists($option_group_id, $package_option_groups) ? '1' : '0');
        }
        
        // Assign a feature if the TLD package is assigned the correct meta data
        foreach ($this->package_meta_features as $feature) {
            $meta_data = $this->Record->select()->from('package_meta')
                ->where('package_id', '=', $tld->package_id)
                ->where('key', '=', $feature)
                ->fetch();
            $tld->{$feature} = $meta_data->value ?? '0';
        }

        return $tld;
    }

    /**
     * Returns a list of TLDs for the given company
     *
     * @param array $filters A list of filters for the query
     *
     *  - tld The TLD
     *  - company_id The ID of the company for which this TLD is available
     *  - package_id The package to be used for pricing and sale of this TLD
     * @param int $page The page number of results to fetch
     * @param array $order A key/value pair array of fields to order the results by
     * @return array An array of stdClass objects
     */
    public function getList(array $filters = [], $page = 1, array $order = ['package_group.order' => 'asc'])
    {
        $results = $this->getTlds($filters)
            ->order($order)
            ->limit($this->getPerPage(), (max(1, $page) - 1) * $this->getPerPage())
            ->fetchAll();

        foreach ($results as &$result) {
            $result = $this->appendFeatures($result);
        }

        return $results;
    }

    /**
     * Returns the total number of TLDs for the given filters
     *
     * @param array $filters A list of filters for the query
     *
     *  - tld The TLD
     *  - company_id The ID of the company for which this TLD is available
     *  - package_id The package to be used for pricing and sale of this TLD
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
     * @param array $order A key/value pair array of fields to order the results by
     * @return array An array of stdClass objects
     */
    public function getAll(array $filters = [], array $order = ['package_group.order' => 'asc'])
    {
        $results = $this->getTlds($filters)->order($order)->fetchAll();

        foreach ($results as &$result) {
            $result = $this->appendFeatures($result);
        }

        return $results;
    }

    /**
     * Fetches the given TLD
     *
     * @param int $tld The TLD of the record to fetch
     * @param int $company_id The ID of the company for which to filter by
     * @return mixed A stdClass object representing the TLD, false if no such record exists
     */
    public function get($tld, $company_id = null)
    {
        $company_id = !is_null($company_id) ? $company_id : Configure::get('Blesta.company_id');

        $result = $this->getTlds([
            'tld' => $tld,
            'company_id' => $company_id
        ])->fetch();

        if ($result) {
            $result = $this->appendFeatures($result);
        }

        return $result;
    }


    /**
     * Fetches a TLD by the given package id
     *
     * @param int $package_id The Package ID belonging to the TLD to fetch
     * @param int $company_id The ID of the company for which to filter by
     * @return mixed A stdClass object representing the TLD, false if no such record exists
     */
    public function getByPackage($package_id, $company_id = null)
    {
        $company_id = !is_null($company_id) ? $company_id : Configure::get('Blesta.company_id');

        $result = $this->getTlds([
            'package_id' => $package_id,
            'company_id' => $company_id
        ])->fetch();

        if ($result) {
            $result = $this->appendFeatures($result);
        }

        return $result;
    }

    /**
     * Sort the TLDs
     *
     * @param array $tlds A key => value array, where the key is the order of
     *  the TLD and the value the ID of the package belonging to the TLD
     */
    public function sort(array $tlds = [], $company_id = null)
    {
        Loader::loadModels($this, ['Companies']);
        Loader::loadModels($this, ['Packages']);

        $company_id = $company_id ?? Configure::get('Blesta.company_id');
        $domains_package_group = $this->Companies->getSetting($company_id, 'domains_package_group');
        $package_group_id = isset($domains_package_group->value)
            ? $domains_package_group->value
            : null;

        $package_ids = [];
        foreach ($tlds as $tld) {
            $packages = $this->getTldPackages($tld, null, $company_id);
            foreach ($packages as $package) {
                $package_ids[] = $package->package_id;
            }
        }

        $this->Packages->orderPackages($package_group_id, $package_ids);
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
     *  - module_id The ID of the registrar module to be used for this TLD
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

        // If the module id is null, use the Generic Domains module by default
        if ($this->ModuleManager->isInstalled('generic_domains', $vars['company_id']) && empty($vars['module_id'])) {
            $modules = $this->ModuleManager->getByClass('generic_domains', $vars['company_id']);
            $module = is_array($modules) ? reset($modules) : null;
            $vars['module_id'] = $module->id ?? null;
        }

        $this->Input->setRules($this->getRules($vars));

        if ($this->Input->validates($vars)) {
            // Update the package if a package is provided
            if (isset($vars['package_id'])) {
                $this->Record->where('id', '=', $vars['package_id'])
                    ->update('packages', ['module_id' => $vars['module_id']]);
            }

            // Create a new package, if a package id is not provided
            if (!isset($vars['package_id'])) {
                // Get module
                $module = $this->ModuleManager->get($vars['module_id']);

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

            // Set the package configurable options and meta data
            $this->assignFeatures($vars['package_id'], $vars);

            $fields = [
                'tld',
                'company_id',
                'package_id',
            ];
            $this->Record->insert('domains_tlds', $vars, $fields);
            $this->Record->insert(
                'domains_packages',
                ['package_id' => $vars['package_id'], 'tld_id' => $this->lastInsertId()]
            );

            return $vars;
        }
    }

    /**
     * Adds a package to a given tld
     *
     * @param array $vars An array of input data including:
     *
     *  - tld The TLD to add (opional)
     *  - tld_id The id of the TLD to add (required if 'tld' is not submitted)
     *  - package_id The ID of the package to add
     */
    public function addPackage($vars)
    {
        // Get the tld based on the TLD
        if (isset($vars['tld'])) {
            $tld = $this->get($vars['tld']);
            $vars['tld_id'] = ($tld ? $tld->id : null);
            unset($vars['tld']);
        }

        $rules = [
            'tld_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'domains_tlds'],
                    'message' => Language::_('DomainsTlds.!error.tld_id.exists', true)
                ]
            ],
            'package_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'packages'],
                    'message' => Language::_('DomainsTlds.!error.package_id.exists', true)
                ]
            ]
        ];

        $this->Input->setRules($rules);
        if ($this->Input->validates($vars)) {
            // Assign the new package to the TLD
            $this->Record->insert('domains_packages', $vars, ['tld_id', 'package_id']);
        }
    }

    /**
     * Creates a package for a given TLD
     *
     * @param array $vars An array of input data including:
     *
     *  - tld The TLD of the package
     *  - module_id The ID of the registrar module for this package
     *  - status The status for the new package (optional, default inactive)
     *  - ns A numerically indexed array, containing the nameservers for the given TLD (optional)
     *  - package_group_id The ID of the TLDs package group (optional)
     *  - company_id The ID of the company for which the TLD of this package is available (optional)
     * @return int The ID of the TLD package
     */
    private function createPackage(array $vars)
    {
        Loader::loadModels($this, ['ModuleManager', 'Currencies', 'Languages', 'Companies']);
        Loader::loadHelpers($this, ['Form']);

        // Set company id
        if (!isset($vars['company_id'])) {
            $vars['company_id'] = Configure::get('Blesta.company_id');
        }

        // Set package group id
        if (!isset($vars['package_group_id'])) {
            $domains_package_group = $this->Companies->getSetting(
                $vars['company_id'],
                'domains_package_group'
            );
            $vars['package_group_id'] = isset($domains_package_group->value)
                ? $domains_package_group->value
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
            'taxable' => $company_settings['domains_taxable'] ?? 0,
            'names' => [],
            'descriptions' => [],
            'hidden' => '1',
            'status' => $vars['status'] ?? 'inactive',
            'company_id' => $vars['company_id'],
            'pricing' => [
                ['term' => 1, 'period' => 'year', 'currency' => $default_currency]
            ],
            'groups' => [$vars['package_group_id']]
        ];

        // Fetch sample welcome email from the module
        $email_template = $this->ModuleManager->moduleRpc($vars['module_id'], 'getEmailTemplate');

        // Add a package name and email content for each language
        foreach ($languages as $language) {
            $package_params['names'][] = [
                'lang' => $language->code,
                'name' => $vars['tld']
            ];
            $package_params['email_content'][] = [
                'lang' => $language->code,
                'html' => $email_template[$language->code]['html'] ?? ($email_template['en_us']['html'] ?? ''),
                'text' => $email_template[$language->code]['text'] ?? ($email_template['en_us']['text'] ?? '')
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
        
        
        // Set the epp_code package meta
        $registrar = $this->ModuleManager->initModule($vars['module_id']);
        if (($vars['epp_code'] ?? '0') == '1' && !$registrar->supportsFeature('epp_code')) {
            $this->Input->setErrors([
                'feature' => [
                    'message' => Language::_('DomainsTlds.!error.feature.unsupported', true, 'epp_code')
                ]
            ]);
        } else {
            $fields = [
                'package_id' => $package_id,
                'key' => 'epp_code',
                'value' => $vars['epp_code'] ?? '0',
                'serialized' => '0'
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
     *  - company_id The ID of the company for which this TLD is available (optional)
     *  - package_id The ID of the package to be used for pricing and sale of this TLD
     *  - dns_management Whether to include DNS management for this TLD
     *  - email_forwarding Whether to include email forwarding for this TLD
     *  - id_protection Whether to include ID protection for this TLD
     *  - epp_code Whether to include EPP Code for this TLD
     *  - module_id The ID of the module this package belongs to (optional, default NULL)
     *  - module_row The module row this package belongs to (optional, default NULL)
     *  - module_group The module group this package belongs to (optional, default NULL)
     *  - taxable Whether or not this package is taxable (optional, default 0)
     *  - email_content A numerically indexed array of email content including:
     *      - lang The language of the email content
     *      - html The html content for the email (optional)
     *      - text The text content for the email, will be created automatically from html if not given (optional)
     *  - option_groups A numerically indexed array of package option group assignments (optional)
     *  - meta A set of miscellaneous fields to pass, in addition to the above
     *      fields, to the module when adding the package (optional)
     * @return string The identifier of the TLD that was updated, void on error
     */
    public function edit($tld, array $vars)
    {
        Loader::loadModels($this, ['Packages', 'ModuleManager', 'Companies']);
        Loader::loadHelpers($this, ['Form']);

        $company_id = $vars['company_id'] ?? Configure::get('Blesta.company_id');

        $vars['tld'] = $tld;
        $tld = $this->get($vars['tld'], $company_id);

        $this->Input->setRules($this->getRules($vars, true));

        if ($this->Input->validates($vars)) {
            // Get package
            $old_package = $this->Packages->get($tld->package_id);

            if (isset($vars['module_id'])) {
                // If email content is not given, and a package does not exist for the new module, use the default
                // module welcome email content
                if (empty($vars['email_content'])
                    && !($this->getTldPackageByModuleId($vars['tld'], $vars['module_id']))
                ) {
                    // Fetch sample welcome email from the module
                    $email_templates = $this->ModuleManager->moduleRpc($vars['module_id'], 'getEmailTemplate');
                    $vars['email_content'] = array_values($email_templates ?? []);
                }

                // Migrate module
                if ($this->requiresModuleMigration($vars['tld'], $vars['module_id'])) {
                    $vars['package_id'] = $this->migrateModule($vars['tld'], $vars['module_id']);
                }

                // Remove features unsupported by the new module
                if ($old_package->module_id !== $vars['module_id']) {
                    $company_settings = $this->Form->collapseObjectArray(
                        $this->Companies->getSettings($tld->company_id),
                        'value',
                        'key'
                    );

                    $registrar = $this->ModuleManager->initModule($vars['module_id']);
                    foreach ($this->config_option_features as $feature) {
                        $setting = $company_settings['domains_' . $feature . '_option_group'] ?? null;

                        if ($setting && !$registrar->supportsFeature($feature)) {
                            $vars[$feature] = '0';
                        } elseif ($tld->{$feature} == '1') {
                            $vars[$feature] = '1';
                        }
                    }
                }
            }

            // Get package
            $package = isset($vars['package_id']) ? $this->Packages->get($vars['package_id']) : $old_package;

            // Set the default module row only if the module row and module group have not been provided
            // and the module id has been provided and this is being updated to a new value.
            if (
                isset($vars['module_id'])
                && empty($vars['module_row'])
                && empty($vars['module_group'])
                && $package->module_id !== $vars['module_id']
            ) {
                $module = $this->ModuleManager->get($vars['module_id']);
                $module_row = null;

                if (!empty($module->rows)) {
                    $module_row = reset($module->rows);
                    $module_row = $module_row->id;
                }

                $vars['module_row'] = $module_row;
            }

            // Set module row and module group to null, if one isn't provided
            if (empty($vars['module_row'])) {
                $vars['module_row'] = null;
            }
            if (empty($vars['module_group'])) {
                $vars['module_group'] = null;
            }

            // Update package
            $fields = [
                'module_id' => isset($vars['module_id'])
                    ? $vars['module_id']
                    : (isset($package->module_id) ? $package->module_id : null),
                'module_row' => $vars['module_row'] ?? null,
                'module_group' => $vars['module_group'] ?? null,
                'taxable' => isset($vars['taxable'])
                    ? $vars['taxable']
                    : (isset($package->taxable) ? $package->taxable : 0),
                'email_content' => isset($vars['email_content'])
                    ? $vars['email_content']
                    : (isset($package->email_content) ? $package->email_content : null),
                'meta' => (array)(
                    isset($vars['meta'])
                        ? array_merge((isset($package->meta) ? (array)$package->meta : []), $vars['meta'])
                        : (isset($package->meta) ? $package->meta : [])
                )
            ];
            $fields = json_decode(json_encode($fields), true);

            // If the tld somehow got unset, reset it
            if (!isset($fields['meta']['tld'])) {
                $fields['meta']['tlds'] = [$vars['tld']];
            }

            // Unset empty fields
            foreach ($fields as $key => $value) {
                if (is_null($value)) {
                    unset($fields[$key]);
                }
            }

            $this->Packages->edit($package->id, $fields);

            if (($errors = $this->Packages->errors())) {
                $this->Input->setErrors($errors);

                return;
            }

            // Update configurable options and meta data
            $this->assignFeatures($package->id, $vars);

            // Update TLD
            $fields = ['tld', 'package_id'];
            $this->Record->where('tld', '=', $vars['tld'])
                ->where('company_id', '=', $company_id)
                ->update('domains_tlds', $vars, $fields);

            return $vars['tld'];
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
     * Validates if a package will require a module migration.
     *
     * @param string $tld The TLD to validate
     * @param int $new_module_id The ID of the new module
     * @param int $company_id The ID of the company for which to filter by (optional)
     * @return bool True if the package needs to be migrated to the new module, false otherwise
     */
    private function requiresModuleMigration($tld, $new_module_id, $company_id = null)
    {
        Loader::loadComponents($this, ['Record']);

        $company_id = !is_null($company_id) ? $company_id : Configure::get('Blesta.company_id');

        // Check if there is any existing package using the new module
        $package = $this->getTldPackageByModuleId($tld, $new_module_id);

        // Get package id
        $tld = $this->get($tld);
        $package_id = isset($tld->package_id) ? $tld->package_id : null;

        if (empty($package_id)) {
            return false;
        } elseif ($package && $package->id != $package_id) {
            return true;
        }

        // Check if the package is using the same module id
        $package = $this->Record->select()
            ->from('packages')
            ->where('id', '=', $package_id)
            ->where('company_id', '=', $company_id)
            ->fetch();

        if (isset($package->module_id) && ((int)$package->module_id == (int)$new_module_id)) {
            return false;
        }

        // Check if there are any services using this module and package
        $services = $this->Record->select(['services.*', 'packages.id' => 'package_id'])
            ->from('services')
            ->innerJoin('package_pricing', 'package_pricing.id', '=', 'services.pricing_id', false)
            ->innerJoin('packages', 'packages.id', '=', 'package_pricing.package_id', false)
            ->where('packages.id', '=', $package_id)
            ->numResults();

        return ($services > 0);
    }

    /**
     * Migrate the TLD to a new module
     *
     * @param string $tld The TLD to migrate
     * @param int $new_module_id The ID of the new module for the TLD
     * @return int The ID of the new package for the TLD
     */
    private function migrateModule($tld, $new_module_id)
    {
        Loader::loadModels($this, ['Packages']);

        $tld = $this->get($tld);

        // Get old package
        $old_package_id = isset($tld->package_id) ? $tld->package_id : null;
        $old_package = $this->Packages->get($old_package_id);

        // Check if there is any existing package using the new module
        $package = $this->getTldPackageByModuleId($tld->tld, $new_module_id);

        if (!empty($package)) {
            $package_id = $package->id;

            // Set the current status to the restored package
            $this->Packages->edit($package_id, ['status' => $old_package->status]);
        } else {
            // Create a new package with the same tld and using the new module
            $params = [
                'module_id' => $new_module_id,
                'tld' => $tld->tld,
                'status' => $old_package->status
            ];
            $package_id = $this->createPackage($params);

            // Set the old meta data and pricing to the new package
            $params = [
                'pricing' => ($old_package->pricing ?? []),
                'meta' => ($old_package->meta ?? [])
            ];
            $params = json_decode(json_encode($params), true);
            $this->Packages->edit($package_id, $params);

            if (($errors = $this->Packages->errors())) {
                $this->Input->setErrors($errors);

                return;
            }

            // Assign the new package to the TLD
            $this->Record->insert('domains_packages', ['tld_id' => $tld->id, 'package_id' => $package_id]);
        }

        // Set the status of the old package as inactive
        $this->Record->duplicate('domains_packages.package_id', '=', $old_package_id)
            ->insert('domains_packages', ['tld_id' => $tld->id, 'package_id' => $old_package_id]);
        $this->Packages->edit($old_package_id, ['status' => 'inactive']);

        return $package_id;
    }

    /**
     * Gets the package assigned to the given TLD that utilizes the provided module
     *
     * @param string $tld The TLD for which the package will be looked up
     * @param int $module_id The ID of the module the package should have
     * @param int $company_id The ID of the company for which to filter by
     * @return stdClass An object representing the package
     */
    private function getTldPackageByModuleId($tld, $module_id, $company_id = null)
    {
        $company_id = !is_null($company_id) ? $company_id : Configure::get('Blesta.company_id');

        return $this->Record->select(['packages.*', 'domains_tlds.tld'])
            ->from('domains_packages')
            ->innerJoin('packages', 'packages.id', '=', 'domains_packages.package_id', false)
            ->innerJoin('domains_tlds', 'domains_tlds.id', '=', 'domains_packages.tld_id', false)
            ->where('packages.module_id', '=', $module_id)
            ->where('domains_tlds.tld', '=', $tld)
            ->where('domains_tlds.company_id', '=', $company_id)
            ->fetch();
    }

    /**
     * Get all the packages assigned to a TLD.
     *
     * @param string $tld The TLD to fetch the packages
     * @param string $status The status of the packages to fetch, it can be "active", "inactive" or null (default, null)
     * @param int $company_id The ID of the company for which to filter by
     * @return mixed An array of objects containing the assigned packages to the given tld
     */
    public function getTldPackages($tld, $status = null, $company_id = null)
    {
        $company_id = !is_null($company_id) ? $company_id : Configure::get('Blesta.company_id');
        $packages = $this->Record->select(['domains_packages.*', 'packages.module_id'])
            ->from('domains_packages')
            ->innerJoin('packages', 'packages.id', '=', 'domains_packages.package_id', false)
            ->innerJoin('domains_tlds', 'domains_tlds.id', '=', 'domains_packages.tld_id', false)
            ->where('domains_tlds.tld', '=', $tld)
            ->where('domains_tlds.company_id', '=', $company_id);

        if (!is_null($status)) {
            $packages->where('packages.status', '=', $status);
        }

        return $packages->fetchAll();
    }

    /**
     * Updates the pricings of a TLD
     *
     * @param int $tld The identifier of the TLD to edit
     * @param array $pricings A key => value array, where the key is the package pricing ID
     *  and the value the pricing row
     * @param int $company_id The ID of the company for which to filter by
     */
    public function updatePricings($tld, array $pricings, $company_id = null)
    {
        Loader::loadModels($this, ['Pricings', 'Currencies']);
        Loader::loadHelpers($this, ['Form']);

        $company_id = !is_null($company_id) ? $company_id : Configure::get('Blesta.company_id');
        $tld = $this->get($tld, $company_id);

        // Get company currencies
        $currencies = $this->Form->collapseObjectArray(
            $this->Currencies->getAll($company_id),
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
                                    'message' => Language::_('DomainsTlds.!error.package_pricing.count', true)
                                ]
                            ]);

                            return;
                        }
                    } else if ((bool)$pricing['enabled']) {
                        $this->addPricing($tld->package_id, $pricing);
                    }
                }
            }
        }
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
                    'message' => Language::_('DomainsTlds.!error.package_pricing.count', true)
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
                    'message' => Language::_('DomainsTlds.!error.package_pricing.service', true)
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
     *  - company_id The ID of the company for which this TLD is available (optional)
     *  - term The term as an integer 1-65535 (optional, default 1)
     *  - price The price of this term (optional, default 0.00)
     *  - price_renews The renewal price of this term (optional, default null)
     *  - price_transfer The transfer price of this term (optional, default null)
     *  - currency The ISO 4217 currency code for this pricing (optional, default USD)
     */
    private function updatePricing($pricing_id, array $vars)
    {
        Loader::loadModels($this, ['Pricings']);

        $vars['company_id'] = $vars['company_id'] ?? Configure::get('Blesta.company_id');

        $vars = array_merge($vars, [
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
            'company_id' => $tld->company_id,
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
     * Updates the taxation status of the TLD packages
     *
     * @param int $taxable Whether or not this package is taxable (optional, default 0)
     */
    public function updateTax($taxable)
    {
        $tlds = $this->getAll();

        foreach ($tlds as $tld) {
            $this->edit($tld->tld, ['taxable' => (int)$taxable]);
        }
    }

    /**
     * Assigns feature configurable options groups to the given package
     *
     * @param int $package_id The ID of the package to assign the configurable options
     * @param array $vars An array of input data including:
     *
     *  - company_id The ID of the company for which this TLD is available (optional)
     *  - dns_management Whether to include DNS management for this TLD
     *  - email_forwarding Whether to include email forwarding for this TLD
     *  - id_protection Whether to include ID protection for this TLD
     *  - epp_code Whether to include EPP Code for this TLD
     */
    private function assignFeatures($package_id, array $vars)
    {
        // Verify if the given package id belongs to a TLD
        if (!($tld = $this->getByPackage($package_id))) {
            return false;
        }

        Loader::loadModels($this, ['Companies', 'ModuleManager', 'Packages']);
        Loader::loadHelpers($this, ['Form']);

        $package = $this->Packages->get($package_id);
        $registrar = $this->ModuleManager->initModule($package->module_id);

        $company_id = $vars['company_id'] ?? Configure::get('Blesta.company_id');

        // Get company settings
        $company_settings = $this->Form->collapseObjectArray(
            $this->Companies->getSettings($company_id),
            'value',
            'key'
        );

        // Update configurable options
        foreach ($this->config_option_features as $option) {
            if (isset($company_settings['domains_' . $option . '_option_group'])) {
                $option_group_id = $company_settings['domains_' . $option . '_option_group'];

                if (isset($vars[$option])) {
                    if ((bool)$vars[$option]) {
                        if (!$registrar->supportsFeature($option)) {
                            $this->Input->setErrors([
                                'feature' => [
                                    'message' => Language::_('DomainsTlds.!error.feature.unsupported', true, $option)
                                ]
                            ]);
                            continue;
                        }

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

        // Update package meta
        foreach ($this->package_meta_features as $feature) {
            if (($vars[$feature] ?? '0') == '1' && !$registrar->supportsFeature($feature)) {
                $this->Input->setErrors([
                    'feature' => [
                        'message' => Language::_('DomainsTlds.!error.feature.unsupported', true, $feature)
                    ]
                ]);
                continue;
            } else {
                // Set the package meta
                $fields = [
                    'package_id' => $package_id,
                    'key' => $feature,
                    'value' => $vars[$feature],
                    'serialized' => '0'
                ];
                $this->Record->duplicate('package_meta.value', '=', $fields['value'])
                    ->insert('package_meta', $fields);
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

        Loader::loadModels($this, ['Packages', 'ModuleManager', 'PackageOptionGroups', 'Services']);
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
            $group_name = Language::_('DomainsTlds.getTldFields.group', true);
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
            (array)$this->ModuleManager->getGroups($package->module_id),
            'id',
            'name'
        );
        $rows = $this->ArrayHelper->numericToKey(
            (array)$this->ModuleManager->getRows($package->module_id),
            'id',
            'meta'
        );

        $row_key = $module->moduleRowMetaKey();
        foreach ($rows as $key => &$value) {
            $value = $value->$row_key;
        }

        // Fetch all available package option groups
        $package_option_groups = $this->Form->collapseObjectArray(
            $this->PackageOptionGroups->getAll($package->company_id),
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

        // Build the available package tags
        $parser_options = Configure::get('Blesta.parser_options');
        $package_email_tags = '';
        $tags = $this->Services->getWelcomeEmailTags() + $tags;

        if (!empty($tags)) {
            $i = 0;
            foreach ($tags as $group => $group_tags) {
                foreach ($group_tags as $tag) {
                    $package_email_tags .= ($i++ > 0 ? ' ' : '') .
                        $parser_options['VARIABLE_START'] . $group . '.' . $tag . $parser_options['VARIABLE_END'];
                }
            }
        }
        $tags = $package_email_tags;

        // Build input fields HTML
        $input_fields = new InputFields();
        foreach ($fields as $field) {
            $input_fields->setField($field);
        }
        $input_fields->setHtml($html);
        $input_html = new FieldsHtml($input_fields);

        return compact(
            'fields',
            'html',
            'input_html',
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
     * @param int $company_id The ID of the company for which to filter by
     */
    public function delete($tld, $company_id = null)
    {
        $company_id = !is_null($company_id) ? $company_id : Configure::get('Blesta.company_id');

        // Delete TLD and packages assignments
        $this->Record->from('domains_tlds')->
            leftJoin('domains_packages', 'domains_packages.tld_id', '=', 'domains_tlds.id', false)->
            where('domains_tlds.tld', '=', $tld)->
            where('domains_tlds.company_id', '=', $company_id)->
            delete(['domains_packages.*', 'domains_tlds.*']);
    }

    /**
     * Enables the given TLD
     *
     * @param int $tld The identifier of the TLD to enable
     * @param int $company_id The ID of the company for which to filter by
     */
    public function enable($tld, $company_id = null)
    {
        // Get TLD
        $tld = $this->get($tld, $company_id);

        $this->Record->where('id', '=', $tld->package_id)
            ->update('packages', ['status' => 'active']);
    }

    /**
     * Disables the given TLD
     *
     * @param int $tld The identifier of the TLD to disable
     * @param int $company_id The ID of the company for which to filter by
     */
    public function disable($tld, $company_id = null)
    {
        // Get TLD
        $tld = $this->get($tld, $company_id);

        $this->Record->where('id', '=', $tld->package_id)
            ->update('packages', ['status' => 'inactive']);
    }

    /**
     * Returns a partial query
     *
     * @param array $filters A list of filters for the query
     *
     *  - tld The TLD
     *  - company_id The ID of the company for which this TLD is available
     *  - package_id The package to be used for pricing and sale of this TLD
     * @return Record A partially built query
     */
    private function getTlds(array $filters = [])
    {
        $this->Record->select(['domains_tlds.*'])->
            from('domains_tlds')->
            leftJoin('package_group', 'package_group.package_id', '=', 'domains_tlds.package_id', false);

        if (isset($filters['tld'])) {
            $this->Record->where('domains_tlds.tld', '=', $filters['tld']);
        }

        if (isset($filters['company_id'])) {
            $this->Record->where('domains_tlds.company_id', '=', $filters['company_id']);
        }

        if (isset($filters['package_id'])) {
            $this->Record->where('domains_tlds.package_id', '=', $filters['package_id']);
        }

        $this->Record->group('package_group.package_id');

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
     * Returns a list of the features supported by the domain manager
     *
     * @return array A list of the supported features by the plugin
     */
    public function getFeatures()
    {
        return array_merge($this->config_option_features, $this->package_meta_features);
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
                    'message' => Language::_('DomainsTlds.!error.tld.empty', true)
                ],
                'exists' => [
                    'if_set' => $edit,
                    'rule' => function ($tld) {
                        $parent = new stdClass();
                        Loader::loadComponents($parent, ['Record']);

                        $count = $this->Record->select()
                            ->from('domains_tlds')
                            ->where('tld', '=', $tld)
                            ->where('company_id', '=', Configure::get('Blesta.company_id'))
                            ->numResults();

                        return !($count > 0);
                    },
                    'message' => Language::_('DomainsTlds.!error.tld.exists', true)
                ],
                'length' => [
                    'if_set' => true,
                    'rule' => ['minLength', 3],
                    'message' => Language::_('DomainsTlds.!error.tld.length', true)
                ],
                'supported' => [
                    'if_set' => true,
                    'rule' => function($tld) use (&$vars) {
                        // Validate if the TLD is supported by the provided module
                        if (isset($vars['module_id'])) {
                            $parent = new stdClass();
                            Loader::loadModels($parent, ['ModuleManager']);

                            $module_tlds = $parent->ModuleManager->moduleRpc($vars['module_id'], 'getTlds');

                            return is_array($module_tlds) && !empty($module_tlds) && in_array($vars['tld'], $module_tlds);
                        }

                        return true;
                    },
                    'message' => Language::_('DomainsTlds.!error.tld.supported', true)
                ]
            ],
            'package_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'packages'],
                    'message' => Language::_('DomainsTlds.!error.package_id.exists', true)
                ]
            ],
            'package_group_id' => [
                'exists' => [
                    'if_set' => true,
                    'rule' => [[$this, 'validateExists'], 'id', 'package_groups'],
                    'message' => Language::_('DomainsTlds.!error.package_group_id.exists', true)
                ]
            ],
            'module_id' => [
                'exists' => [
                    'if_set' => $edit,
                    'rule' => [[$this, 'validateExists'], 'id', 'modules'],
                    'message' => Language::_('DomainsTlds.!error.module_id.exists', true)
                ]
            ],
            'company_id' => [
                'exists' => [
                    'if_set' => $edit,
                    'rule' => [[$this, 'validateExists'], 'id', 'companies'],
                    'message' => Language::_('DomainsTlds.!error.company_id.exists', true)
                ]
            ],
            'dns_management' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', [0, 1]],
                    'message' => Language::_('DomainsTlds.!error.dns_management.valid', true)
                ]
            ],
            'email_forwarding' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', [0, 1]],
                    'message' => Language::_('DomainsTlds.!error.email_forwarding.valid', true)
                ]
            ],
            'id_protection' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', [0, 1]],
                    'message' => Language::_('DomainsTlds.!error.id_protection.valid', true)
                ]
            ]
            ,
            'epp_code' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', [0, 1]],
                    'message' => Language::_('DomainsTlds.!error.epp_code.valid', true)
                ]
            ]
        ];

        if ($edit) {
            unset($rules['tld']['exists']);
        }

        return $rules;
    }
}
