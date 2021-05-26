
<?php
/**
 * Domain Manager admin_main controller
 *
 * @link https://www.blesta.com Blesta
 */
class AdminMain extends DomainsController
{
    /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();

        $this->structure->set('page_title', Language::_('AdminMain.index.page_title', true));
    }

    /**
     * Returns the view for a list of extensions
     */
    public function index()
    {
        $this->redirect($this->base_uri . 'plugin/domains/admin_domains/browse/');
    }
}
