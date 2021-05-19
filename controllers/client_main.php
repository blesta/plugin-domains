
<?php
/**
 * Domain Manager client_main controller
 *
 * @link https://www.blesta.com Blesta
 */
class ClientMain extends DomainsController
{
    /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();

        $this->structure->set('page_title', Language::_('ClientMain.index.page_title', true));
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
}
