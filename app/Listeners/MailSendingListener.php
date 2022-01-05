<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

class MailSendingListener
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
     * @param  MessageSending $event
     * @return void
     */
    public function handle(MessageSending $event)
    {
        $subject = $event->message->getSubject();
        $to = $event->message->getTo();

        Log::info("module=mail_sending\tsubject=" . $subject . "\tto=" . json_encode($to));
    }
}
