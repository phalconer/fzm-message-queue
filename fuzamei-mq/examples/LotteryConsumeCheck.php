<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LotteryConsumeCheck extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'lotteryConsumeCheck:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动脚本--监控消息消费进程是否正常';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        for(;;) {
            sleep(1);
            $consumeNum = 2;
            $ret = system("ps aux|grep [l]otteryConsume:consume | wc -l");
//            var_dump($ret);
            if ($ret < $consumeNum) {
                //$count = system("[ `ps aux|grep [l]otteryConsume:consume | wc -l` -lt {$consumeNum} ] && /usr/bin/php /app/max/config-local/bbf/artisan.php  lotteryConsume:consume  1>> /var/log/airent/lottery_consume.log 2>&1 & ");

                if (KD_DEBUG) {
                    system("`/usr/bin/php /app/max/config-local/bbf/artisan.php  lotteryConsume:consume  1>> /var/log/airent/lottery_consume.log 2>&1 &` ");
                } else {
                    system("/usr/local/php70/bin/php /var/www/config/airent-config/current/bbf/artisan.php  lotteryConsume:consume  1>> /var/log/airent/lottery_consume.log 2>&1 & ");
                }
            }
        }
    }
}
