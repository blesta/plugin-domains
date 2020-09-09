
<?php
/**
 * Domain Manager admin_main controller
 *
 * @link https://www.blesta.com Blesta
 */
class AdminMain extends DomainManagerController
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
        return $this->renderAjaxWidgetIfAsync(
            isset($this->get['sort']) ? true : (isset($this->get[1]) || isset($this->get[0]) ? false : null)
        );
    }
}
