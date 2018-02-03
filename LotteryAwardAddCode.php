<?php
/**
 * Created by PhpStorm.
 * User: lin.yang@aihuishou.com
 * Date: 9/26/16
 * Time: 2:55 PM
 */
namespace App\Console\Commands;

use App\Bll\DeliveryBll;
use Illuminate\Console\Command;
use App\Bll\TradeBll;
use Illuminate\Support\Facades\DB;

use App\Helper\KdApiSearch\KdApiSearch;

class LotteryAwardAddCode extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'lotteryAward:addcode';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动脚本投保--自动生成爱回收优惠券';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        //初始化
        //取出已发货数据 需要查询的签收 数据
        $tbl_lottery = DB::table('tbl_lottery')->select('tbl_lottery.lottery_type_id','tbl_lottery.id','tbl_lottery.status')       ->where([
            ['tbl_lottery.lottery_type_id', '=', 12],
            ['tbl_lottery.acode_group_id', '=', null],
        ])->orderBy('dt_created', 'desc')->get();





        if($tbl_lottery){
            foreach($tbl_lottery as $tbl_lottery){
                //修改单据的状态
                //如果成功跟新计划表状态
                $tbl_ahs_acode = DB::table('tbl_ahs_acode')->select('tbl_ahs_acode.group_id','tbl_ahs_acode.Id','tbl_ahs_acode.amount')       ->where([
                    ['tbl_ahs_acode.type', '=', 1],
                    ['tbl_ahs_acode.is_distribute', '=', 0],
                ])
                    ->orderBy('tbl_ahs_acode.Id', 'asc')->first();
                $update =DB::table('tbl_lottery')
                    ->where([
                        ['id', '=', $tbl_lottery->id],
                    ]) ->update(['acode_group_id' => $tbl_ahs_acode->group_id]);
                //更新使用的优惠券的表
                $update =DB::table('tbl_ahs_acode')
                    ->where([
                        ['group_id', '=', $tbl_ahs_acode->group_id],
                    ]) ->update(['is_distribute' =>1]);

                //并新增更新日志表
                echo '|'.$tbl_ahs_acode->group_id.'生成爱回收优惠券成功'.$update;

                self::_log($tbl_ahs_acode->group_id, $tbl_lottery->id, $update);

            }
        }


    }



    private static function _log($tracking_number, $id_carrier, $rs)
    {
        $arr = [
            date('Y-m-d H:i:s',time()),
            $tracking_number,
            $id_carrier == 1 ? '顺丰' : $id_carrier,
            $rs,
        ];

        file_put_contents('/tmp/code_award_log', implode("\t", $arr) . "\n", FILE_APPEND);
    }
}
