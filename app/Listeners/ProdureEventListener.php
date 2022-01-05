<?php

namespace App\Listeners;

use App\Events\ProdureEvent;
use Illuminate\Support\Facades\Log;

class ProdureEventListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ProdureEvent $event
     * @return void
     */
    public function handle(ProdureEvent $event)
    {

        Log::info("module=produre_event_listener\tevent_type=" . $event->eventType . "\trelation_id=" . $event->relationId);
    }
}
