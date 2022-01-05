<?php

namespace App\Listeners;

use App\Common\MnsClient;
use App\Events\InstallmentUpdateEvent;
use App\Models\Orders;
use Illuminate\Support\Facades\Log;

class InstallmentUpdateEventListener
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
     * @param  InstallmentUpdateEvent $event
     * @return void
     */
    public function handle(InstallmentUpdateEvent $event)
    {
        $content = "module=installment_update_event_listener\torder_id=" . $event->orderId;
        Log::info($content);
        try {
            $order = Orders::where('id', $event->orderId)->first();
            if ($order) {
                if (!MnsClient::sendMsg2Queue(env('CRM_ACCESS_ID'), env('CRM_ACCESS_KEY'), env('CRM_QUEUE_NAME'), $order['biz_no'])) {
                    throw new \Exception("send mns failed");
                }
            }
        } catch (\Exception $exception) {
            Log::warning($content . "\tmsg=handle error\terror=" . $exception->getMessage());
        }
    }
}
