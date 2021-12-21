<?php
/**
 * Domain Manager TLD Sync Tool
 *
 * @link https://www.blesta.com Blesta
 */
class TldSync
{
    /**
     * Synchronize the price for the given TLDs with those set by the registrar
     *
     * @param array $tlds A list of TLDs for which to sync registrar prices
     * @param int $company_id The ID of the company where the TLDs will be synchronized (optional)
     */
    public function synchronizePrices(array $tlds, $company_id = null)
    {
        Loader::loadModels($this, ['Domains.DomainsTlds', 'ModuleManager']);

        if (is_null($company_id)) {
            $company_id = Configure::get('Blesta.company_id');
        }

        $tld_records = $this->DomainsTlds->getAll(
            ['tlds' => $tlds, 'company_id' => $company_id]
        );

        $module_tlds = [];
        foreach ($tld_records as $tld_record) {
            $module_tlds[$tld_record->module_id][] = $tld_record->tld;
        }

        foreach ($module_tlds as $module_id => $list_tlds) {
            $module = $this->ModuleManager->initModule($module_id);
            $module->setModuleRow($module->getModuleRows()[0] ?? null);
            $module_pricing = $module->getTldPricing();

            $tlds_pricing = array_intersect_key($module_pricing, array_flip($list_tlds));

            foreach ($tlds_pricing as $tld => $pricing) {
                $this->DomainsTlds->updatePricings(
                    $tld,
                    $this->formatPricing($pricing, $tld, $company_id),
                    $company_id
                );
            }
        }
    }

    /**
     * Format a pricing record returned by the registrar module
     *
     * @param array $pricing TLDs pricing
     *    [currency => [year# => ['register' => price, 'transfer' => price, 'renew' => price]]]
     * @param string $tld The TLD to sync registrar prices
     * @param int $company_id The ID of the company where the TLDs will be synchronized (optional)
     * @return array The formatted pricing record
     */
    private function formatPricing($pricing, $tld, $company_id = null)
    {
        Loader::loadModels($this, ['Domains.DomainsTlds']);

        if (is_null($company_id)) {
            $company_id = Configure::get('Blesta.company_id');
        }

        // Fetch TLD sync settings
        $tld_settings = $this->DomainsTlds->getDomainsCompanySettings();

        $formatted_pricing = [];
        foreach ($pricing as $currency => $terms) {
            foreach ($terms as $year => $prices) {
                // Apply markup and rounding
                $prices['register'] = $this->markup(
                    $prices['register'],
                    $tld_settings['domains_sync_price_markup'] ?? 0,
                    $tld_settings['domains_markup_rounding'] ?? '.00'
                );
                $prices['renew'] = $this->markup(
                    $prices['renew'],
                    $tld_settings['domains_sync_renewal_markup'] ?? 0,
                    $tld_settings['domains_markup_rounding'] ?? '.00'
                );
                $prices['transfer'] = $this->markup(
                    $prices['transfer'],
                    $tld_settings['domains_sync_transfer_markup'] ?? 0,
                    $tld_settings['domains_markup_rounding'] ?? '.00'
                );

                // Check if the pricing row is enabled
                $tld_object = $this->DomainsTlds->get($tld, $company_id);
                $pricing_row = $this->DomainsTlds->getPricing($tld_object->package_id, $year, $currency);

                $formatted_pricing[$year][$currency] = [
                    'price' => $prices['register'],
                    'price_renews' => $prices['renew'],
                    'price_transfer' => $prices['transfer'],
                    'enabled' => !empty($pricing_row)
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
     * @return float The total amount of the price plus the markup
     */
    private function markup($price, $markup, $rounding = null)
    {
        $price = number_format($price * (($markup / 100) + 1), 2, '.', '');

        if (!is_null($rounding)) {
            $decimals = explode('.', $price, 2);
            $integer = ($decimals[0] ?? null);
            $decimals = ($decimals[1] ?? null);
            $round = (int) trim($rounding, '.');

            if (is_numeric($decimals) && is_numeric($rounding)) {
                if ($decimals > $round) {
                    $price = round($price, 0, PHP_ROUND_HALF_DOWN) + 1;
                } else if ($decimals > 0) {
                    $price = $integer . $rounding;
                } else {
                    $price = $integer;
                }
            }
        }

        return $price;
    }
}
