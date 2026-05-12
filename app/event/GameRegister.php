<?php

// app/event/GameRegister.php
namespace app\event;

use app\common\model\Account;
use ba\GameHelper;
use think\facade\Log;

class GameRegister
{
    public function handle($userId): void
    {
        try {
            Log::info("GameRegister 事件触发，用户ID: $userId");

            $user = Account::find($userId);
            if (!$user) {
                Log::error("GameRegister 事件：未找到用户 ID $userId");
                return;
            }
            if (empty($user->player_id)) {
                $playerId = GameHelper::get_player_id($user);
                $user->player_id = $playerId;
                $user->save();
            }

        } catch (\Throwable $e) {
            Log::error("GameRegister 执行异常：" . $e->getMessage());
        }
    }

}
