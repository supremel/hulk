<?php

namespace App\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use IlluminateDatabaseQueryExecuted;

class QueryListener
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
     * @param  QueryExecuted $event
     * @return void
     */
    public function handle(QueryExecuted $event)
    {
        if (env('APP_DEBUG', false) == true) {
            $sql = str_replace("?", "'%s'", $event->sql);
            try {
                $log = vsprintf($sql, $event->bindings);
            } catch (\Exception $e) {
                $log = $event->sql . "++" . json_encode($event->bindings);
            }

            Log::debug("module=sql_query_listener\tsql=" . $log);
        }
    }
}
