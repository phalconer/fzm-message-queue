<?php

namespace App\Http\Controllers;

use App\Bll\LotteryBll;
use App\Exceptions\Custom\ParamException;
use App\Helper\AiRedis;
use App\Helper\Config;
use App\Helper\MessageQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LotteryController extends BaseController
{

    const USER_AWARD_CACHE_TIMEOUT = 30;//30s

    /*
     * response.status（101-已经抽过奖了  102-未抽中  103-已抽中）
     */
    public function lottery(Request $request)
    {
        //$method_start = microtime(true);
        //\AiershouCustomLogger::log(\AiershouCustomLogger::TYPE_INFO, 'method begin >>> ' . ($method_start));

        $userId = (int)$request->input('user_id', 0);
        if ($userId <= 0) {
            throw new ParamException('参数错误');
        }

        $response = ['status' => 101];//（101-已经抽过奖了  102-未抽中  103-已抽中）
        $key = sprintf(AiRedis::LOTTERY_INCR_KEY, $userId);
        //$start = microtime(true);
        $val = AiRedis::incrWithoutRedisVersion($key);
        if ($val > 2000000000) {//base-10 64 bit signed integer max:‭9,223,372,036,854,775,807‬ ;  32 bit signed integer max:‭2,147,483,647‬
            AiRedis::setWithoutRedisVersion($key, 2, -1);
        }
        //$response['debug_duration_incr'] = (microtime(true) - $start);
        
        if ($val > 1) {
            //throw new UserException('您已经抽过奖了，请到个人中心查看抽奖结果');
            //$response['debug_method_duration'] = microtime(true) - $method_start;
            //\AiershouCustomLogger::log(\AiershouCustomLogger::TYPE_INFO, json_encode($response, JSON_UNESCAPED_UNICODE));
            return $this->returnSuccess($response);
        }

        //抽奖log (produce a message)
        //TODO: 测试是否是瓶颈
        $start = microtime(true);
        try {
            //, 'unique_id' => \AiershouUniqueRequest::getId()
            $body = json_encode(['user_id' => $userId, 'dt_lottery' => date('Y-m-d H:i:s'),]);
            MessageQueue::getInstance()->produceLotteryLog($body);
        } catch (\Throwable $e) {
            \AiershouCustomLogger::logException(\AiershouCustomLogger::TYPE_ERROR, $e);
        }
        //$response['debug_duration_mq_send'] = (microtime(true) - $start);

        //$start = microtime(true);
        $awardType = LotteryBll::randAward();
        //\AiershouCustomLogger::log(\AiershouCustomLogger::TYPE_INFO, 'rand award >>> ' . (microtime(true) - $start));
        try {
            if ($awardType > 0) {//随机到奖品了
                $start = microtime(true);
                $award_key = AiRedis::LOTTERY_AWARD_QUEUE_PREFIX . $awardType;
                $result = AiRedis::decrWithoutRedisVersion($award_key);
                if ($result < -2000000000) {//base-10 64 bit signed integer max:‭9,223,372,036,854,775,807‬ ;  32 bit signed integer max:‭2,147,483,647‬
                    AiRedis::setWithoutRedisVersion($award_key, -1, -1);
                }
                //$response['debug_duration_redis_decr'] = (microtime(true) - $start);
                if ($result >= 0) {
                    $start = microtime(true);
                    $award_dt_expired = Config::get('app.award_dt_expired');
                    //不用事务，后续提供定时脚本核对
                    $affected = DB::update('update tbl_lottery_type set used_count = `used_count`+1 where id=? and used_count < total_count', [$awardType]);
                    if ($affected == 1) {
                        $result = DB::insert('insert into tbl_lottery (id_user, lottery_type_id, dt_expired, dt_created) values (?, ?, ?, ?)', [$userId, $awardType, $award_dt_expired, date('Y-m-d H:i:s')]);
                        if (!$result) {
                            \AiershouCustomLogger::log(\AiershouCustomLogger::TYPE_ERROR, '没有抽到奖品-4');
                            throw new \Exception('没有抽到奖品-4');
                        }
                    } else {
                        \AiershouCustomLogger::log(\AiershouCustomLogger::TYPE_ERROR, '没有抽到奖品-3');
                        throw new \Exception('没有抽到奖品-3');
                    }
                    //$response['debug_duration_update_and_insert_db'] = (microtime(true) - $start);
                } else {
                    throw new \Exception('没有抽到奖品-2');
                }
            } else {
                throw new \Exception('没有抽到奖品-1');
            }
        } catch (\Throwable $e) {
            $response['status'] = 102;
            //$response['debug'] = $e->getMessage();
            //$response['debug_method_duration'] = microtime(true) - $method_start;
            //\AiershouCustomLogger::log(\AiershouCustomLogger::TYPE_INFO, json_encode($response, JSON_UNESCAPED_UNICODE));
            return $this->returnSuccess($response);
        }

        $response['status'] = 103;
        $response['award_type'] = $awardType;
        //$response['debug_method_duration'] = microtime(true) - $method_start;
        //\AiershouCustomLogger::log(\AiershouCustomLogger::TYPE_INFO, json_encode($response, JSON_UNESCAPED_UNICODE));
        return $this->returnSuccess($response);
    }

    /**
     * 用户抽奖结果（1-未抽奖  2-未抽中  3-已抽中）
     */
    public function userStatus(Request $request)
    {
        $userId = (int)$request->input('user_id', 0);
        if ($userId <= 0) {
            throw new ParamException('参数错误');
        }

        $response = ['status' => 1];
        $cacheKey = sprintf(AiRedis::LOTTERY_USER_AWARD_KEY, $userId);
        $cacheData = AiRedis::getWithoutRedisVersion($cacheKey);
        if ($cacheData) {
            return $this->returnSuccess($response);
        }

        $key = sprintf(AiRedis::LOTTERY_INCR_KEY, $userId);
        $val = AiRedis::getWithoutRedisVersion($key);
        if ($val) {
            $lotteryList = DB::select('select * from tbl_lottery where id_user=? limit 0,1', [$userId]);
            if (empty($lotteryList)) {
                $response['status'] = 2;
            } else {
                $response['status'] = 3;
                $response['award']['award_type'] = $lotteryList[0]->award_type_id;
                $response['award']['dt_expired'] = $lotteryList[0]->dt_expired;
                $response['award']['status'] = $lotteryList[0]->status;
            }
        }
        AiRedis::setWithoutRedisVersion($cacheKey, $response, self::USER_AWARD_CACHE_TIMEOUT);
        return $this->returnSuccess($response);
    }

}