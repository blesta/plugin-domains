
<?php
/**
 * Domain Manager admin_domains controller
 *
 * @link https://www.blesta.com Blesta
 */
class AdminDomains extends DomainManagerController
{
    /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();

        $this->uses(['ModuleManager']);
        $this->structure->set('page_title', Language::_('AdminDomains.index.page_title', true));
    }

    /**
     * Returns the view for a list of extensions
     */
    public function index()
    {
        return $this->renderAjaxWidgetIfAsync(
            isset($this->get['sort']) ? true : (isset($this->get[1]) || isset($this->get[0]) ? false : null)
        );
    }

    /**
     * Fetches the view for the registrar list
     */
    public function registrars()
    {
        // Get installed and available registrar modules
        $installed_registrars = $this->ModuleManager->getAll(
            Configure::get('Blesta.company_id'),
            'name',
            'asc',
            ['type' => 'registrar']
        );
        $available_registrars = $this->ModuleManager->getAvailable(Configure::get('Blesta.company_id'), 'registrar');

        // Get installed module details when available
        $registrars = [];
        foreach ($installed_registrars as $installed_registrar) {
            $registrars[$installed_registrar->class] = $installed_registrar;
        }

        // Add available registrars to the end of the list
        foreach ($available_registrars as $available_registrar) {
            if (!isset($registrars[$available_registrar->class])) {
                $registrars[$available_registrar->class] = $available_registrar;
            }
        }
        $this->set('registrars', array_values($registrars));
    }

    /**
     * Install a registrar for this company
     */
    public function installRegistrar()
    {
        if (!isset($this->post['id'])) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
        }

        $module_id = $this->ModuleManager->add(['class' => $this->post['id'], 'company_id' => $this->company_id]);

        if (($errors = $this->ModuleManager->errors())) {
            $this->flashMessage('error', $errors);
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.registrar_installed', true));
            $this->redirect($this->base_uri . 'settings/company/modules/manage/' . $module_id);
        }
    }

    /**
     * Uninstall a registrar for this company
     */
    public function uninstallRegistrar()
    {
        if (!isset($this->post['id']) || !($module = $this->ModuleManager->get($this->post['id']))) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
        }

        $this->ModuleManager->delete($this->post['id']);

        if (($errors = $this->ModuleManager->errors())) {
            $this->flashMessage('error', $errors);
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.registrar_uninstalled', true));
        }
        $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
    }

    /**
     * Upgrade a registrar
     */
    public function upgradeRegistrar()
    {
        // Fetch the module to upgrade
        if (!isset($this->post['id']) || !($module = $this->ModuleManager->get($this->post['id']))) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
        }

        $this->ModuleManager->upgrade($this->post['id']);

        if (($errors = $this->ModuleManager->errors())) {
            $this->flashMessage('error', $errors);
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.registrar_upgraded', true));
        }
        $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/registrars/');
    }

    /**
     * Fetches the view for the configuration page
     */
    public function configuration()
    {
        $this->uses(['Companies', 'PackageGroups', 'PackageOptionGroups', 'DomainManager.DomainManagerTlds']);
        $company_id = Configure::get('Blesta.company_id');
        $vars = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');
        $vars['domain_manager_spotlight_tlds'] = isset($vars['domain_manager_spotlight_tlds'])
            ? json_decode($vars['domain_manager_spotlight_tlds'], true)
            : [];
        if (!empty($this->post)) {
            // Leave the spotlight tlds out for now as we don't intend to include them in the initial release
            $accepted_settings = [
//                'domain_manager_spotlight_tlds',
                'domain_manager_package_group',
                'domain_manager_dns_management_option_group',
                'domain_manager_email_forwarding_option_group',
                'domain_manager_id_protection_option_group',
            ];
            if (!isset($this->post['domain_manager_spotlight_tlds'])) {
                $this->post['domain_manager_spotlight_tlds'] = [];
            }
            $this->post['domain_manager_spotlight_tlds'] = json_encode($this->post['domain_manager_spotlight_tlds']);
            $this->Companies->setSettings(
                $company_id,
                array_intersect_key($this->post, array_flip($accepted_settings))
            );

            $this->flashMessage(
                'message',
                Language::_('AdminDomains.!success.configuration_updated', true),
                null,
                false
            );
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/configuration/');
        }

        $this->set('vars', $vars);
        $this->set('tlds', $this->DomainManagerTlds->getAll(['company_id' => $company_id]));
        $this->set(
            'package_groups',
            $this->Form->collapseObjectArray($this->PackageGroups->getAll($company_id, 'standard'), 'name', 'id')
        );
        $this->set(
            'package_option_groups',
            $this->Form->collapseObjectArray($this->PackageOptionGroups->getAll($company_id), 'name', 'id')
        );
    }

    /**
     * Fetches the view for all TLDs and their pricing
     */
    public function tlds()
    {
        $this->uses(['ModuleManager', 'Packages', 'DomainManager.DomainManagerTlds']);
        $this->helpers(['Form']);

        // Fetch all the TLDs and their pricing for this company
        $company_id = Configure::get('Blesta.company_id');
        $tlds = $this->DomainManagerTlds->getAll(['company_id' => $company_id]);

        foreach ($tlds as $key => $tld) {
            $package = $this->Packages->get($tld->package_id);
            $module = $this->ModuleManager->get($package->module_id);

            $tlds[$key]->package = $package;
            $tlds[$key]->module = $module;
        }

        // Fetch all modules for this company
        $modules = $this->ModuleManager->getAll(
            $company_id,
            'name',
            'asc',
            ['type' => 'registrar']
        );
        $none_module = $this->ModuleManager->getByClass('none', $company_id);
        $none_module = isset($none_module[0]) ? $none_module[0] : null;
        $select = ['' => Language::_('AppController.select.please', true)];
        $none = [$none_module->id => $none_module->name];
        $modules = $select + $none + $this->Form->collapseObjectArray($modules, 'name', 'id');

        // Save TLDs
        if (!empty($this->post)) {

        }

        $this->set('tlds', $tlds);
        $this->set('modules', $modules);
    }

    /**
     * Disables a TLD for this company
     */
    public function disableTld()
    {
        $this->uses(['Packages', 'DomainManager.DomainManagerTlds']);

        // Fetch the package belonging to this TLD
        if (
            !isset($this->post['id'])
            || !($package = $this->Packages->get($this->post['id']))
            || !($tld = $this->DomainManagerTlds->getByPackage($this->post['id']))
        ) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/tlds/');
        }

        $this->DomainManagerTlds->disable($tld->tld);

        if (($errors = $this->DomainManagerTlds->errors())) {
            $this->flashMessage('error', $errors);
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.tld_disabled', true));
        }
        $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/tlds/');
    }

    /**
     * Enables a TLD for this company
     */
    public function enableTld()
    {
        $this->uses(['Packages', 'DomainManager.DomainManagerTlds']);

        // Fetch the package belonging to this TLD
        if (
            !isset($this->post['id'])
            || !($package = $this->Packages->get($this->post['id']))
            || !($tld = $this->DomainManagerTlds->getByPackage($this->post['id']))
        ) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/tlds/');
        }

        $this->DomainManagerTlds->enable($tld->tld);

        if (($errors = $this->DomainManagerTlds->errors())) {
            $this->flashMessage('error', $errors);
        } else {
            $this->flashMessage('message', Language::_('AdminDomains.!success.tld_enabled', true));
        }
        $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/tlds/');
    }

    /**
     * Sort TLDs
     */
    public function sortTlds()
    {
        $this->uses(['DomainManager.DomainManagerTlds']);

        if (!$this->isAjax()) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/tlds/');
        }

        if (!empty($this->post)) {
            $this->DomainManagerTlds->sortTlds($this->post['tlds']);
        }

        return false;
    }

    /**
     * Add TLD
     */
    public function addTld()
    {
        $this->uses(['DomainManager.DomainManagerTlds']);

        if (!$this->isAjax() || empty($this->post['add_tld'])) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/tlds/');
        }

        if (!empty($this->post['add_tld'])) {
            $vars = $this->post['add_tld'];

            // Set checkboxes
            if (empty($vars['dns_management'])) {
                $vars['dns_management'] = '0';
            }
            if (empty($vars['email_forwarding'])) {
                $vars['email_forwarding'] = '0';
            }
            if (empty($vars['id_protection'])) {
                $vars['id_protection'] = '0';
            }
            if (empty($vars['epp_code'])) {
                $vars['epp_code'] = '0';
            }

            // Add new TLD
            $company_id = Configure::get('Blesta.company_id');
            $params = [
                'tld' => '.' . trim($vars['tld'], '.'),
                'company_id' => $company_id,
            ];
            $params = array_merge($vars, $params);

            if (!empty($vars['module'])) {
                $params['module_id'] = (int) $vars['module'];
            }

            $tld = $this->DomainManagerTlds->add($params);

            if (($errors = $this->DomainManagerTlds->errors())) {
                echo json_encode([
                    'error' => $this->setMessage(
                        'error',
                        $errors,
                        true,
                        null,
                        false
                    )
                ]);
            } else {
                $tld['message'] = $this->setMessage(
                    'message',
                    Language::_('AdminDomains.!success.tld_added', true),
                    true,
                    null,
                    false
                );
                echo json_encode($tld);
            }
        }

        return false;
    }

    /**
     * Update TLD
     */
    public function updateTlds()
    {
        $this->uses(['DomainManager.DomainManagerTlds']);

        if (!$this->isAjax()) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/tlds/');
        }

        if (!empty($this->post)) {
            foreach ($this->post['tlds'] as $tld => $vars) {
                // Set checkboxes
                if (empty($vars['dns_management'])) {
                    $vars['dns_management'] = '0';
                }
                if (empty($vars['email_forwarding'])) {
                    $vars['email_forwarding'] = '0';
                }
                if (empty($vars['id_protection'])) {
                    $vars['id_protection'] = '0';
                }
                if (empty($vars['epp_code'])) {
                    $vars['epp_code'] = '0';
                }

                // Update TLD
                $vars = array_merge($vars, [
                    'module_id' => $vars['module'],
                ]);

                $this->DomainManagerTlds->edit($tld, $vars);
            }
        }

        return false;
    }

    /**
     * Update TLD pricing
     */
    public function pricing()
    {
        $this->uses(['Packages', 'Currencies', 'DomainManager.DomainManagerTlds']);
        $this->helpers(['Form']);

        // Fetch the package belonging to this TLD
        if (
            !$this->isAjax()
            || !isset($this->get[0])
            || !($package = $this->Packages->get($this->get[0]))
            || !($tld = $this->DomainManagerTlds->getByPackage($this->get[0]))
        ) {
            $this->redirect($this->base_uri . 'plugin/domain_manager/admin_domains/tlds/');
        }

        // Get company settings
        $company_id = Configure::get('Blesta.company_id');
        $company_settings = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');

        // Get company default currency
        $default_currency = isset($company_settings['default_currency']) ? $company_settings['default_currency'] : 'USD';

        // Get company currencies
        $currencies = $this->Form->collapseObjectArray(
            $this->Currencies->getAll($company_id),
            'code',
            'code'
        );
        if (isset($currencies[$default_currency])) {
            $currencies = [$default_currency => $default_currency] + $currencies;
        }

        // Get TLD package
        $package = $this->Packages->get($this->get[0]);
        $tld = $this->DomainManagerTlds->getByPackage($this->get[0]);

        echo $this->partial(
            'admin_domains_pricing',
            compact('package', 'tld', 'currencies', 'default_currency')
        );

        return false;
    }
}
