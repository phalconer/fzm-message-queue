<?php

namespace App\Console\Commands;

use App\Bll\LotteryBll;
use App\Helper\AiRedis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class LotteryAward extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'lotteryAward:pushAward {type=-1:奖品类型} {num=-1:奖品数量}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动脚本--更新奖品池';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $inputAwardType = (int)$this->argument('type');
        $num = (int)$this->argument('num');

        var_dump($inputAwardType);
        var_dump($num);

        if ($inputAwardType < 0 || $num < 0) {
            $arr = [
                3 => 50,//1000/10
                9 => 0,//10/10
                10 => 0,//10/10
                11 => 15,//2500/10
                12 => 4200,//20000/10
            ];
        } else {
            $arr = [$inputAwardType => $num];
        }

        foreach ($arr as $awardType => $awardCount) {
            //插入前检查DB的可用奖品数量
            $row = DB::select('select total_count,used_count from tbl_lottery_type where id=? ', [$awardType]);
            $o = $row[0];
            $awardCount = min(($o->total_count - $o->used_count), $awardCount);
            AiRedis::setWithoutRedisVersion(AiRedis::LOTTERY_AWARD_QUEUE_PREFIX . $awardType, $awardCount, -1);
        }
    }
}
