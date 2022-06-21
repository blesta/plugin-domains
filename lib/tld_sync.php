<?php
/**
 * Domain Manager TLD Sync Tool
 *
 * @link https://www.blesta.com Blesta
 */
class TldSync
{
    /**
     * Initialize TLD Sync class
     */
    public function __construct()
    {
        Loader::loadModels($this, ['Domains.DomainsTlds']);

        // Fetch domains company settings
        $this->tld_settings = $this->DomainsTlds->getDomainsCompanySettings();
    }

    /**
     * Synchronize the price for the given TLDs with those set by the registrar
     *
     * @param array $tlds A list of TLDs for which to sync registrar prices
     * @param int $company_id The ID of the company where the TLDs will be synchronized (optional)
     * @param array $filters A list of filters for the process
     *
     *  - module_id If given, only TLDs belonging to this module ID will be updated
     *  - terms A list of terms to import for the TLD, if supported
     */
    public function synchronizePrices(array $tlds, $company_id = null, array $filters = [])
    {
        Loader::loadModels($this, ['ModuleManager', 'Currencies']);
        Loader::loadHelpers($this, ['Form']);

        if (is_null($company_id)) {
            $company_id = Configure::get('Blesta.company_id');
        }

        set_time_limit(60*60*15); // 15 minutes

        // Get TLD records
        $tld_records = $this->DomainsTlds->getAll(
            ['tlds' => $tlds, 'company_id' => $company_id]
        );

        // Organize TLD records by registrar module
        $module_tlds = [];
        foreach ($tld_records as $tld_record) {
            if (isset($filters['module_id']) && $tld_record->module_id != $filters['module_id']) {
                continue;
            }

            $module_tlds[$tld_record->module_id][] = $tld_record->tld;
        }

        // Get company currencies
        $currencies = $this->Form->collapseObjectArray($this->Currencies->getAll($company_id), 'code', 'code');

        // Get TLD prices from the registrar module
        foreach ($module_tlds as $module_id => $list_tlds) {
            $module = $this->ModuleManager->initModule($module_id);
            $module->setModuleRow($module->getModuleRows()[0] ?? null);
            $module_pricing = $module->getFilteredTldPricing(
                null,
                ['tlds' => $tlds, 'currencies' => array_values($currencies)]
            );

            // Set the price for each TLD
            $tlds_pricing = array_intersect_key($module_pricing, array_flip($list_tlds));
            foreach ($tlds_pricing as $tld => $pricing) {
                $this->DomainsTlds->updatePricings(
                    $tld,
                    $this->formatPricing($pricing),
                    $company_id,
                    $filters
                );
            }
        }
    }

    /**
     * Format a pricing record returned by the registrar module
     *
     * @param array $pricing TLDs pricing
     *    [currency => [year# => ['register' => price, 'transfer' => price, 'renew' => price]]]
     * @return array The formatted pricing record
     */
    private function formatPricing($pricing)
    {
        // Set TLD rounding
        $tld_rounding = null;
        if (($this->tld_settings['domains_enable_rounding'] ?? 0) == 1) {
            $tld_rounding = $this->tld_settings['domains_markup_rounding'] ?? '.00';
        }

        $formatted_pricing = [];
        foreach ($pricing as $currency => $terms) {
            foreach ($terms as $year => $prices) {
                // Apply markup and rounding
                $prices['register'] = $this->markup(
                    $prices['register'],
                    $this->tld_settings['domains_sync_price_markup'] ?? 0,
                    $tld_rounding
                );
                $prices['renew'] = $this->markup(
                    $prices['renew'],
                    $this->tld_settings['domains_sync_renewal_markup'] ?? 0,
                    $tld_rounding
                );
                $prices['transfer'] = $this->markup(
                    $prices['transfer'],
                    $this->tld_settings['domains_sync_transfer_markup'] ?? 0,
                    $tld_rounding
                );

                $formatted_pricing[$year][$currency] = [
                    'price' => $prices['register'],
                    'price_renews' => $prices['renew'],
                    'price_transfer' => $prices['transfer']
                ];
            }
        }

        return $formatted_pricing;
    }

    /**
     * Applies a markup to a given price
     *
     * @param float $price The price to add a markup
     * @param int $markup The percentage of markup to add
     * @param string $rounding The nearest decimal to round up the final price
     * @return float The total amount of the price plus the markup
     */
    private function markup($price, $markup, $rounding = null)
    {
        if ($price == 0) {
            return null;
        }

        $final_price = number_format($price * (($markup / 100.00) + 1), 4, '.', '');
        if (!is_null($rounding) && is_numeric($rounding)) {
            $subtracted_rounding_price = $final_price - (float) $rounding;
            $floored_price = floor($subtracted_rounding_price);
            $final_price = $floored_price + (float) $rounding + ($subtracted_rounding_price == $floored_price ? 0 : 1);
        }

        return $final_price;
    }
}
