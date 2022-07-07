<?php
use Blesta\Core\Util\Events\Observer;
use Blesta\Core\Util\Events\Common\EventInterface;

/**
 * The Domains plugin event observer
 *
 * @link http://www.blesta.com/ Blesta
 */
class DomainsObserver extends Observer
{
    /**
     * Handle Domains.addBefore events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.addBefore events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function addBefore(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.addAfter events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.addAfter events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function addAfter(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.editBefore events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.editBefore events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function editBefore(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.editAfter events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.editAfter events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function editAfter(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.updatePricingBefore events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.updatePricingBefore events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function updatePricingBefore(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.updatePricingAfter events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.updatePricingAfter events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function updatePricingAfter(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.updateDomainsCompanySettingsAfter events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.updateDomainsCompanySettingsAfter events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function updateDomainsCompanySettingsAfter(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.updateDomainsCompanySettingsBefore events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.updateDomainsCompanySettingsBefore events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function updateDomainsCompanySettingsBefore(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.updateTax events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.updateTax events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function updateTax(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.delete events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.delete events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function delete(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.enable events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.enable events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function enable(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }

    /**
     * Handle Domains.disable events
     *
     * @param Blesta\Core\Util\Events\Common\EventInterface $event An event object for Domains.disable events
     * @return Blesta\Core\Util\Events\Common\EventInterface The processed event object
     */
    public static function disable(EventInterface $event)
    {
        return parent::triggerEvent($event);
    }
}