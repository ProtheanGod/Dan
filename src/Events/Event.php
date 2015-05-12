<?php namespace Dan\Events;


class Event {

    protected static $events = [];
    protected $name;
    protected $priority;
    protected $id;
    protected $function;

    public function __construct($name, $function, $priority = EventPriority::Normal)
    {
        $this->name     = $name;
        $this->function = $function;
        $this->priority = $priority;

        $id = md5(microtime().$this->name.$this->priority.serialize($this->function));

        static::$events[$name][$priority][$id] = $this;
    }

    /**
     * @param \Dan\Events\EventArgs $data
     * @return mixed
     */
    public function call(EventArgs $data)
    {
        return call_user_func_array($this->function, [$data]);
    }

    /**
     *
     */
    public function destroy()
    {
        debug("{yellow}[EVENTS] Destroying event {$this->id} for {$this->name}");
        unset(static::$events[$this->name][$this->priority][$this->id]);
    }

    /**
     * @param $event
     * @return bool
     */
    public static function has($event)
    {
        return array_key_exists($event, static::$events);
    }

    /**
     * @param $name
     * @param $function
     * @param int $priority
     */
    public static function subscribe($name, $function, $priority = EventPriority::Normal)
    {
        new static($name, $function, $priority);
    }

    /**
     * @param $event
     * @param $data
     * @return mixed
     */
    public static function fire($event, $data = null)
    {
        debug("{yellow}[EVENTS] Firing event {$event}");

        if(!static::has($event))
            return $data;

        $list = static::$events[$event];

        krsort($list);

        foreach($list as $priority => $events)
        {
            foreach($events as $id => $event)
            {
                /** @var static $event */
                $return = $event->call(new EventArgs($data));

                if($return === false)
                    return null;

                if(!empty($return))
                    return $return;
            }
        }

        return null;
    }
}