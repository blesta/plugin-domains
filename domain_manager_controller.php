<?php
/**
 * Domain Manager parent controller
 *
 * @link https://www.blesta.com Blesta
 */
class DomainManagerController extends AppController
{
    /**
     * Require admin to be login and setup the view
     */
    public function preAction()
    {
        $this->structure->setDefaultView(APPDIR);
        parent::preAction();

        $this->requireLogin();

        // Auto load language for the controller
        Language::loadLang(
            [Loader::fromCamelCase(get_class($this))],
            null,
            dirname(__FILE__) . DS . 'language' . DS
        );
        Language::loadLang(
            'domain_manager_controller',
            null,
            dirname(__FILE__) . DS . 'language' . DS
        );

        // Override default view directory
        $this->view->view = "default";
        $this->orig_structure_view = $this->structure->view;
        $this->structure->view = "default";

        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);
    }
}
