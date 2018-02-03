<?php

namespace App\Console\Commands;

use App\Bll\LotteryBll;
use App\Helper\AiRedis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LotteryAwardCheck extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'lotteryAwardCheck:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动脚本--核对奖品的可用库存数量';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $awards = DB::select('select award_type_id,count(award_type_id) as used_count from tbl_lottery GROUP BY award_type_id');
        $awardMap = array_column($awards, 'used_count', 'award_type_id');

        $rows = DB::select('select id,total_count,used_count from tbl_lottery_type ');
        $sysSettingMap = array_column($rows, null, 'id');
        foreach ($sysSettingMap as $lotteryAwardTypeId => $row) {
            $calcUsedCount = ($awardMap[$lotteryAwardTypeId]??0);
            if ($row->used_count != $calcUsedCount) {
                echo "will update tbl_lottery_type.id={$lotteryAwardTypeId}\n";
                $affected = DB::update('update tbl_lottery_type set used_count =? where id=?', [$calcUsedCount, $lotteryAwardTypeId]);
                echo "affected rows ===> {$affected}\n\n";
            }
        }
    }
}
