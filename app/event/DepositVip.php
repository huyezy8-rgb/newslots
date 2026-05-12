<?php

// app/event/GameRegister.php
namespace app\event;

use app\common\model\Account;
use app\common\model\activity\DepositVipUser;
use app\common\model\activity\FirstDeposit270User;
use ba\GameHelper;
use think\facade\Log;

class DepositVip
{
    public function handle($data): void
    {
        try {
            $userId = $data['user_id'];
            $amount = $data['amount'];
            Log::info("DepositVip 事件触发，用户ID: $userId");

            $user = Account::find($userId);
            if (!$user) {
                Log::error("DepositVip 事件：未找到用户 ID $userId");
                throw new \Exception("未找到用户 ID $userId");
            }
            $task=DepositVipUser::where(['user_id'=>$userId])->find();
            if (!$task) {
                Log::error("DepositVip 事件 未找到用户 $userId 的任务");
                throw new \Exception("未找到用户 $userId 的任务");
            }
            DepositVipUser::where('user_id', $userId)
                ->inc('bet_num', 1)
                ->inc('bet_money_sum', $amount)
                ->update();
            Log::error("DepositVip 事件 执行成功 $userId 的任务");
        } catch (\Throwable $e) {
            Log::error("DepositVip 执行异常：" . $e->getMessage());
        }
    }

}
