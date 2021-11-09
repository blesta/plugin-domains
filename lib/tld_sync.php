<?php
class TldSync
{
    /**
     * Synchronize the price for the given TLDs with those set by the registrar
     *
     * @param array $tlds A list of TLDs for which to sync registrar prices
     */
    public function synchronizePrices(array $tlds)
    {
        Loader::loadModels($this, ['Domains.DomainsTlds', 'ModuleManager']);
        $tld_records = $this->DomainsTlds->getAll(
            ['tlds' => $tlds, 'company_id' => Configure::get('Blesta.company_id')]
        );

        $module_tlds = [];
        foreach ($tld_records as $tld_record) {
            $module_tlds[$tld_record->module_id][] = $tld_record->tld;
        }

        foreach ($module_tlds as $module_id => $module_tlds) {
            $module = $this->ModuleManager->initModule($module_id);
            $module->setModuleRow($module->getModuleRows()[0] ?? null);
            $module_pricing = $module->getTldPricing();

            $tlds_pricing = array_intersect_key($module_pricing, array_keys($module_tlds));
            foreach ($tlds_pricing as $tld => $pricing) {
                $this->DomainsTlds->updatePricings($tld, $this->formatPricing($pricing));
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
        $formatted_pricing = [];
        foreach ($pricing as $currency => $terms) {
            foreach ($terms as $year => $prices) {
                ##
                # TODO Apply markup and rounding
                ##
                $formatted_pricing[$year][$currency] = [
                    'price' => $prices['register'],
                    'price_renews' => $prices['renew'],
                    'price_transfer' => $prices['transfer'],
                ];
            }
        }
        return $formatted_pricing;
    }
}