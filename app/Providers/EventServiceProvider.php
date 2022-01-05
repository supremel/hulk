<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Illuminate\Mail\Events\MessageSending' => [
            'App\Listeners\MailSendingListener',
        ],
        'Illuminate\Mail\Events\MessageSent' => [
            'App\Listeners\MailSentListener',
        ],
        'Illuminate\Database\Events\QueryExecuted' => [
            'App\Listeners\QueryListener',
        ],
        'App\Events\ProdureEvent' => [
            'App\Listeners\ProdureEventListener',
        ],
        'App\Events\InstallmentUpdateEvent' => [
            'App\Listeners\InstallmentUpdateEventListener',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
