<?php

namespace App\Helper;

use aiershou\aliyunmq\AliyunMQ;
use aiershou\aliyunmq\MQException;
use App\Helper\Config;

class MessageQueue
{
    /**
     * @var string  the MQ's base http url
     */
    protected $base_url;

    /**
     * @var string
     */
    protected $access_key;

    /**
     * @var string
     */
    protected $secret_key;

    /**
     * @var array
     */
    protected $topics;

    private $mq;

    public function __construct()
    {
        $conf = Config::get('mq.aliyunmq');
        $this->base_url = $conf['url'];
        $this->access_key = $conf['access_key'];
        $this->secret_key = $conf['secret_key'];
        $this->topics = $conf['topics'];

        $this->mq = new AliyunMQ($this->base_url, $this->access_key, $this->secret_key);
    }

    protected static $_instance = null;

    /**
     * @return static
     */
    final public static function getInstance()
    {
        if (static::$_instance === null) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }


    /**
     * 生产消息
     * @param string $topic
     * @param string $body
     * @param string $tag
     * @param string $key
     * @param array $curlOptions
     */
    private function produce(string $topic, string $body, string $tag = "http", string $key = "http", array $curlOptions = [])
    {
        $producerId = $this->topics[$topic]['producer_id'];
        return $this->mq->produce($topic, $producerId, $body, $tag, $key, $curlOptions);

    }

    private function consume(string $topic, string $consumerId)
    {
        return $this->mq->consume($topic, $consumerId);
    }

    private function delete(string $topic, string $consumerId, string $messageHandle)
    {
        $this->mq->delete($topic, $consumerId, $messageHandle);
    }

    CONST LOTTERY_TOPIC = KD_DEBUG ? 'debug_airent_lottery_log' : 'airent_lottery_log';

    public function produceLotteryLog(string $body, string $tag = "http", string $key = "http")
    {
        $topic = self::LOTTERY_TOPIC;
        $curlOptions = [
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
//            'connect_timeout' => 0.1,
//            'timeout' => 0.1,
        ];
        return $this->produce($topic, $body, $tag, $key, $curlOptions);
    }

    public function consumeLotteryLog()
    {
        $topic = self::LOTTERY_TOPIC;
        $consumerId = $this->topics[$topic]['consumer_id'];
        return $this->consume($topic, $consumerId);
    }

    public function deleteLotteryLog(string $messageHandle)
    {
        $topic = self::LOTTERY_TOPIC;
        $consumerId = $this->topics[$topic]['consumer_id'];
        $this->delete($topic, $consumerId, $messageHandle);
    }
}
