<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

class MailSentListener
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
     * @param  MessageSent $event
     * @return void
     */
    public function handle(MessageSent $event)
    {
        $subject = $event->message->getSubject();
        $to = $event->message->getTo();

        Log::info("module=mail_sent\tsubject=" . $subject . "\tto=" . json_encode($to));
    }
}
