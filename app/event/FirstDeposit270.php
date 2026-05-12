<?php

// app/event/GameRegister.php
namespace app\event;

use app\common\model\Account;
use app\common\model\activity\FirstDeposit270User;
use ba\GameHelper;
use think\facade\Log;

class FirstDeposit270
{
    public function handle($data): void
    {
        try {
            $userId = $data['user_id'];
            $amount = $data['amount'];
            Log::info("FirstDeposit270 事件触发，用户ID: $userId");

            $user = Account::find($userId);
            if (!$user) {
                Log::error("FirstDeposit270 事件：未找到用户 ID $userId");
                throw new \Exception("未找到用户 ID $userId");
            }
            $task=FirstDeposit270User::where(['user_id'=>$userId])->find();
            if (!$task) {
                Log::error("FirstDeposit270s 事件 未找到用户 $userId 的任务");
                throw new \Exception("未找到用户 $userId 的任务");
            }
            $task->setInc('bet_num',1);
            $task->setInc('bet_money_sum',$amount);

        } catch (\Throwable $e) {
            Log::error("FirstDeposit270s 执行异常：" . $e->getMessage());
        }
    }

}
