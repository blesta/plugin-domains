<?php
/**
 * Domain Manager parent controller
 *
 * @link https://www.blesta.com Blesta
 */
class DomainsController extends AppController
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
            'domains_controller',
            null,
            dirname(__FILE__) . DS . 'language' . DS
        );

        // If this is an admin controller, set the portal type to admin
        $this->portal = 'client';

        if (substr($this->controller, 0, 5) == 'admin') {
            $this->portal = 'admin';
        }

        // Override default view directory
        $this->view->view = "default";
        $this->orig_structure_view = $this->structure->view;
        $this->structure->view = "default";

        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);

        // Set the left nav for all settings pages to affiliate_leftnav
        if ($this->portal == 'admin') {
            $this->set(
                'left_nav',
                $this->getLeftNav()
            );

            // Set the page title language term
            $page_title = Loader::toCamelCase($this->controller) . '.'
                . Loader::fromCamelCase($this->action ?? 'index') . '.page_title';
            $this->structure->set('page_title', Language::_($page_title, true));
        }
    }

    /**
     * Get the domains left navigation bar
     *
     * @return string The partial view of the domains left navigation bar
     */
    protected function getLeftNav()
    {
        Language::loadLang('admin_domains', null, PLUGINDIR . 'domains' . DS . 'language' . DS);

        return $this->partial('admin_domains_leftnav', ['current_tab' => $this->controller]);
    }
}
