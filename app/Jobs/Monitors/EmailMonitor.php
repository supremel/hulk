<?php

namespace App\Jobs\Monitors;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailMonitor extends Mailable
{
    use Queueable, SerializesModels;

    private $_subject;
    private $_data;
    private $_attach;

    /**
     * Create a new message instance.
     * EmailMonitor constructor.
     * @param $subject
     * @param null $data
     * @param null $attach
     */
    public function __construct($subject, $data = null, $attach = null)
    {
        $this->_subject = $subject;
        $this->_data = $data;
        $this->_data['t'] = base64_encode($subject);
        $this->_attach = $attach;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->_subject)
            ->view('emails.monitor')
            ->with(['data' => $this->_data]);
//            ->attach($this->_attach);
    }
}
