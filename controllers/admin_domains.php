
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

    public function configuration()
    {
        $this->uses(['Companies', 'PackageGroups', 'PackageOptionGroups', 'DomainManager.DomainManagerTlds']);
        $company_id = Configure::get('Blesta.company_id');
        $vars = $this->Form->collapseObjectArray($this->Companies->getSettings($company_id), 'value', 'key');
        $vars['domain_manager_spotlight_tlds'] = isset($vars['domain_manager_spotlight_tlds'])
            ? json_decode($vars['domain_manager_spotlight_tlds'], true)
            : [];
        if (!empty($this->post)) {
            $accepted_settings = [
                'domain_manager_spotlight_tlds',
                'domain_manager_package_group',
                'domain_manager_dns_management_option_group',
                'domain_manager_email_forwarding_option_group',
                'domain_manager_id_protection_option_group',
            ];
            if (!isset($this->post['domain_manager_spotlight_tlds'])) {
                $this->post['domain_manager_spotlight_tlds'] = [];
            }
            $this->post['domain_manager_spotlight_tlds'] = json_encode($this->post['domain_manager_spotlight_tlds']);
            $this->Companies->setSettings($company_id, array_intersect_key($this->post, array_flip($accepted_settings)));

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
}
