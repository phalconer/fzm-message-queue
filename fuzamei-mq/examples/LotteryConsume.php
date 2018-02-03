<?php

namespace App\Console\Commands;

use App\Helper\MessageQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LotteryConsume extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'lotteryConsume:consume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动脚本--抽奖记录log';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $mq = MessageQueue::getInstance();
        $tryCount = 100;
        for (; ;) {
            $start = microtime(true);

            $messages = $mq->consumeLotteryLog();
            //\AiershouCustomLogger::log(\AiershouCustomLogger::TYPE_INFO, json_encode($messages));
            if (KD_DEBUG) {
                var_dump($messages);
            }

            foreach ($messages as $message) {
                try {
                    $body = json_decode($message->body, true);
                    $result = DB::insert('insert into tbl_lottery_log (id_user, dt_lottery, dt_created) values (?, ?, ?)', [$body['user_id'], $body['dt_lottery'], date('Y-m-d H:i:s')]);
                    if (!$result) {
                        throw new \Exception('log failed');
                    }

                    $mq->deleteLotteryLog($message->msgHandle);
                } catch (\Throwable $e) {
                    \AiershouCustomLogger::logException(\AiershouCustomLogger::TYPE_ERROR, $e);
                }
            }
            \AiershouCustomLogger::log(\AiershouCustomLogger::TYPE_INFO, 'consume end >>> ' . ' message count=' . count($messages) . '  ' . (microtime(true) - $start));
            --$tryCount;
            if ($tryCount < 0) {
                break;
            }
        }
    }
}
