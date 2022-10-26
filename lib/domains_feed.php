<?php
use Blesta\Core\Util\DataFeed\Common\AbstractDataFeed;
use Blesta\Core\Util\Input\Fields\InputFields;

/**
 * Domains pricing feed
 *
 * @link https://www.blesta.com Blesta
 */
class DomainsFeed extends AbstractDataFeed
{
    /**
     * @var array An array of options
     */
    private $options = [];

    /**
     * Initialize client feed
     */
    public function __construct()
    {
        parent::__construct();

        // Autoload the language file
        Language::loadLang(
            'domains_feed',
            $this->options['language'] ?? null,
            dirname(__FILE__) . DS . '..' . DS . 'language' . DS
        );
    }

    /**
     * Returns the name of the data feed
     *
     * @return string The name of the data feed
     */
    public function getName()
    {
        return Language::_('DomainsFeed.name', true);
    }

    /**
     * Returns the description of the data feed
     *
     * @return string The description of the data feed
     */
    public function getDescription()
    {
        return Language::_('DomainsFeed.description', true);
    }

    /**
     * Executes and returns the result of a given endpoint
     *
     * @param string $endpoint The endpoint to execute
     * @param array $vars An array containing the feed parameters
     * @return mixed The data feed response
     */
    public function get($endpoint, array $vars = [])
    {
        switch ($endpoint) {
            case 'pricing':
                return $this->pricingEndpoint($vars);
            default:
                return Language::_('DomainsFeed.!error.invalid_endpoint', true);
        }
    }

    /**
     * Sets options for the data feed
     *
     * @param array $options An array of options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * Gets a list of the options input fields
     *
     * @param array $vars An array containing the posted fields
     * @return InputFields An object representing the list of input fields
     */
    public function getOptionFields(array $vars = [])
    {
        $fields = new InputFields();

        $base_url = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 's' : '')
            . '://' . Configure::get('Blesta.company')->hostname . WEBDIR;
        $fields->setHtml('
            <div class="title_row"><h3>' . Language::_('DomainsFeed.getOptionFields.title_row_example_code', true) . '</h3></div>
            <div class="pad">
                <small>' . Language::_('DomainsFeed.getOptionFields.example_code_table', true) . '</small>
                <pre class="rounded bg-light text-secondary border border-secondary p-2 m-0 my-1">'
                    . '&lt;script src="' . $base_url . 'feed/domain/pricing/?currency=USD&style=bootstrap&term=1,2,3,4,5,10"&gt;&lt;/script&gt;'
                . '</pre>
                <h4><a id="domain_params" href="#" class="show_content"><i class="fas fa-chevron-down"></i> ' . Language::_('DomainsFeed.getOptionFields.params', true) . '</a></h4>
                <div id="domain_params_content" class="pad_top hidden">
                    <div>
                        <table class="table table-striped">
                            <thead>
                                <tr class="heading_row">
                                    <td>' . Language::_('DomainsFeed.getOptionFields.header_name', true) . '</td>
                                    <td>' . Language::_('DomainsFeed.getOptionFields.header_description', true) . '</td>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>' . Language::_('DomainsFeed.getOptionFields.param_currency', true) . '</td>
                                    <td>' . Language::_('DomainsFeed.getOptionFields.param_currency_description', true) . '</td>
                                </tr>
                                <tr>
                                    <td>' . Language::_('DomainsFeed.getOptionFields.param_style', true) . '</td>
                                    <td>' . Language::_('DomainsFeed.getOptionFields.param_style_description', true) . '</td>
                                </tr>
                                <tr>
                                    <td>' . Language::_('DomainsFeed.getOptionFields.param_term', true) . '</td>
                                    <td>' . Language::_('DomainsFeed.getOptionFields.param_term_description', true) . '</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <script>
                    $(document).ready(function () {
                        $(this).blestaBindToggleEvent("#domain_params", "#domain_params_content");
                    });
                </script>
            </div>
        ');

        return $fields;
    }

    /**
     * Returns an HTML table containing the TLDs pricing
     *
     * @param array $vars An array containing the following items:
     *
     *  - currency In what currency the pricing must be display (optional)
     *  - term The pricing term to return (optional, default null for all)
     *  - style It could be 'html' or 'bootstrap' (optional, default html)
     */
    private function pricingEndpoint(array $vars)
    {
        Loader::loadModels($this, ['Domains.DomainsTlds']);
        Loader::loadComponents($this, ['SettingsCollection']);

        if (!isset($vars['style'])) {
            $vars['style'] = 'bootstrap';
        }

        if (!isset($vars['currency'])) {
            // Set currency
            $default_currency = $this->SettingsCollection->fetchSetting(null, $this->options['company_id'], 'default_currency');
            $vars['currency'] = ($default_currency['value'] ?? '');
        }

        if (isset($vars['term']) && str_contains($vars['term'], ',')) {
            $vars['term'] = explode(',', trim($vars['term']));
        }

        $requested_terms = [];
        if (in_array($vars['style'], ['html', 'bootstrap'])) {
            // Get TLDs pricing
            $tlds = $this->DomainsTlds->getAll(['company_id' => $this->options['company_id'], 'status' => 'active']);
            foreach ($tlds as &$tld) {
                $pricing = [];
                for ($term = 1; $term <= 10; $term++) {
                    if (
                        (isset($vars['term']) && !is_array($vars['term']) && $vars['term'] != $term)
                        || (isset($vars['term']) && is_array($vars['term']) && !in_array($term, $vars['term']))
                    ) {
                        continue;
                    }

                    $requested_terms[$term] = $term;
                    $pricing[$term] = $this->DomainsTlds->getPricing($tld->package_id, $term, $vars['currency']);
                }

                $tld->pricing = $pricing;
            }

            // Load the data feed view
            $this->view = new View($vars['style'] . '_table', 'domains.feeds');

            Loader::loadHelpers($this, ['CurrencyFormat']);

            $this->view->set('terms', $requested_terms);
            $this->view->set('tlds', $tlds);

            return $this->view->fetch();
        }

        return Language::_('DomainsFeed.!error.invalid_style', true);
    }
}
