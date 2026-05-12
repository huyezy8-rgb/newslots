<?php

namespace app\event;

use app\common\model\Account;
use think\facade\Db;
use think\facade\Log;

class DayGold
{


    public function handle($userId): void
    {
        try {
            Log::info("DayGold 事件触发，用户ID: $userId");

            $user = Account::find($userId);
            if (!$user) {
                Log::error("DayGold 事件：未找到用户 ID $userId");
                return;
            }

            // 查询是否已参加活动
            $activityUser = Db::name("activity_daygold_user")
                ->where(["uid" => $userId])
                ->find();

            if (!$activityUser) {
                // 获取活动配置
                $config = Db::name("activity_daygold")
                    ->where(["id" => 1])
                    ->find();

                if (!$config) {
                    Log::error("DayGold 事件：未找到活动配置");
                    return;
                }

                // 初始化用户签到数据
                $rewards = json_decode($config['rewards'], true);
                $receiveStatus = array_fill(0, count($rewards), 0); // 初始全部不可领取
                $receiveStatus[0] = 1; // 第一天设置为可领取

                $data = [
                    "uid" => $userId,
                    "channel_id" => $user->channel_id ?? 1,
                    "rewards" => $config['rewards'],
                    "receive_status" => json_encode($receiveStatus),
                    "times" => 0, // 初始领取次数为0
                    "last_receive_time" => 0,
                    "create_time" => time(),
                    "update_time" => time()
                ];

                Db::name("activity_daygold_user")
                    ->insert($data);

                Log::info("DayGold 事件：用户 {$userId} 签到数据初始化成功");
            } else {
                Log::info("DayGold 事件：用户 {$userId} 已存在签到数据");
            }

        } catch (\Throwable $e) {
            Log::error("DayGold 执行异常：" . $e->getMessage());
        }
    }
}