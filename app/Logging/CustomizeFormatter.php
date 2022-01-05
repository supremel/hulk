<?php
/**
 * 自定义日志格式
 * User: hexuefei
 * Date: 2019-06-18
 * Time: 13:59
 */

namespace App\Logging;

use Monolog\Formatter\LineFormatter;

class CustomizeFormatter
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new LineFormatter("log_time=%datetime%\tlevel=%level_name%\t%message%\n",
                null, true, true));
        }
    }
}