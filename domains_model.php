<?php
use Blesta\Core\Util\Events\EventFactory;

/**
 * Domain Manager Parent Model
 *
 * @link https://www.blesta.com Blesta
 */
class DomainsModel extends AppModel
{
    /**
     * Initialize the Domains plugin model.
     */
    public function __construct()
    {
        parent::__construct();

        // Auto load language for these models
        Language::loadLang([Loader::fromCamelCase(get_class($this))], null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Triggers a plugin event
     *
     * @param string $name The name of the event to trigger
     * @param array $params An array of parameters to be held by this event (optional)
     * @return array The list of parameters that were submitted along with any modifications made to them
     *  by the event handlers. In addition a __return__ item is included with the return array from the event.
     */
    public function triggerEvent($name, array $params = [])
    {
        Loader::load(dirname(__FILE__) . DS . 'domains_observer.php');

        $eventFactory = new EventFactory();
        $eventListener = $eventFactory->listener();
        $eventListener->register('Domains.' . $name, ['DomainsObserver', $name]);

        $event = $eventListener->trigger($eventFactory->event('Domains.' . $name, $params));

        // Get the event return value
        $returnValue = $event->getReturnValue();

        // Put return in a special index
        $return = ['__return__' => $returnValue];

        // Any return values that match the submitted params should be put in their own index to support extract() calls
        if (is_array($returnValue)) {
            foreach ($returnValue as $key => $data) {
                if (array_key_exists($key, $params)) {
                    $return[$key] = $data;
                }
            }
        }

        return $return;
    }
}
