<?php

namespace App\Jobs\Alerts;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailAlert extends Mailable
{
    use Queueable, SerializesModels;

    private $_msg;
    private $_trace;
    private $_date;
    private $_params;
    private $_headers;

    /**
     * Create a new message instance.
     *
     * Alert constructor.
     * @param \Exception $exception
     * @param null $request
     */
    public function __construct(\Exception $exception, $request = null)
    {
        $this->_date = date('Y-m-d H:i:s');
        $this->_msg = $exception->getMessage();
        $this->_trace = str_replace('#', '+ ', $exception->getTraceAsString());
        $this->_headers = ($request) ? $request->header() : [];
        $this->_params = ($request) ? $request->input() : [];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.alert')->with([
            'date' => $this->_date,
            'msg' => $this->_msg,
            'trace' => $this->_trace,
            'params' => $this->_params,
            'headers' => $this->_headers,]);
    }
}
