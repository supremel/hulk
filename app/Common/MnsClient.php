<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-09
 * Time: 17:34
 */

namespace App\Common;


use AliyunMNS\Client;
use AliyunMNS\Exception\MnsException;
use AliyunMNS\Requests\SendMessageRequest;
use Illuminate\Support\Facades\Log;

class MnsClient
{
    const DEQUEUE_MAX_TIMES = 10;
    const VISIBILITY_TIMEOUT = 100; // 消息可见性超时时间，即多少秒后消息重新可见

    private static function _getMnsClient($accessId, $accessKey)
    {
        return new Client(env('MNS_END_POINT'), $accessId, $accessKey);
    }

    /**
     * 发送消息
     * @param $accessId
     * @param $accessKey
     * @param $queueName
     * @param $msg
     * @return bool
     */
    public static function sendMsg2Queue($accessId, $accessKey, $queueName, $msg)
    {
        try {
            $client = self::_getMnsClient($accessId, $accessKey);
            $queue = $client->getQueueRef($queueName);
            $request = new SendMessageRequest($msg);

            $res = $queue->sendMessage($request);
            Log::info("module=mns\tmethod=sendMsg2Queue\tqueueName="
                . $queueName . "\tmessage=" . $msg
                . "\tcode=0\tmessageId=" . $res->getMessageId());
        } catch (MnsException $e) {
            Log::warning("module=mns\tmethod=sendMsg2Queue\tqueueName="
                . $queueName . "\tmessage=" . $msg . "\tcode=" . $e->getCode()
                . "\tmsg=" . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 处理消息（成功则删除，否则重置消息状态为可见）
     * @param $accessId
     * @param $accessKey
     * @param $queueName
     * @param $handler
     * @return bool
     */
    public static function handleMsgFromQueue($accessId, $accessKey, $queueName, $handler)
    {
        // 获取消息（默认等待超时时间为30秒）
        try {
            $client = self::_getMnsClient($accessId, $accessKey);
            $queue = $client->getQueueRef($queueName);
            $res = $queue->receiveMessage(30);
        } catch (MnsException $e) {
            Log::warning("module=mns\tmethod=handleMsgFromQueue\tqueueName="
                . $queueName . "\tcode=" . $e->getMnsErrorCode()
                . "\tmsg=" . $e->getMessage());
            return false;
        }
        $msg = $res->getMessageBody();
        $msgHandle = $res->getReceiptHandle();

        $dequeueCount = $res->getDequeueCount();
        $logContent = "module=mns\tmethod=handleMsgFromQueue\tqueueName="
            . $queueName . "\tmessage=" . $msg . "\tmessage_id=" . $res->getMessageId()
            . "\tdequeue_count=" . $dequeueCount;

        // 处理消息
        $handlerRet = false;
        try {
            $handlerRet = $handler($msg);
        } catch (\Exception $e) {
            Log::warning($logContent . "\terror=handle error\tcode=" . $e->getCode()
                . "\tmsg=" . $e->getMessage());
        }

        // 根据处理结果，操作消息状态
        if ($handlerRet) {
            self::_deleteMsgFromQueue($accessId, $accessKey, $queueName, $msgHandle);
            Log::info($logContent . "\tret=handle success & delete");
        } else {
            if ($dequeueCount >= self::DEQUEUE_MAX_TIMES) {
                self::_deleteMsgFromQueue($accessId, $accessKey, $queueName, $msgHandle);
                Log::warning($logContent . "\tret=handle failed & dequeue count overrun & delete");
            } else {
                $queue->changeMessageVisibility($msgHandle, self::VISIBILITY_TIMEOUT);
                Log::warning($logContent . "\tmsg=handle failed & after " . self::VISIBILITY_TIMEOUT . " seconds the message visible again");
            }

        }

        return true;
    }

    /**
     * 删除消息
     * @param $accessId
     * @param $accessKey
     * @param $queueName
     * @param $msgHandle
     * @return bool
     */
    private static function _deleteMsgFromQueue($accessId, $accessKey, $queueName, $msgHandle)
    {
        try {
            $client = self::_getMnsClient($accessId, $accessKey);
            $queue = $client->getQueueRef($queueName);
            $queue->deleteMessage($msgHandle);
        } catch (MnsException $e) {
            Log::warning("module=mns\tmethod=deleteMsgFromQueue\tqueueName="
                . $queueName . "\tcode=" . $e->getMnsErrorCode()
                . "\tmsg=" . $e->getMessage());
            return false;
        }
        return true;
    }

}
