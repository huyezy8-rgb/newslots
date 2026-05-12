<?php

namespace app\common\service;

use app\api\enum\CoinLog;
use app\common\model\Account;
use app\common\model\AccountCoinLog;
use app\common\model\RescueFunds;


class AccountInfoService
{
    /**
     * 获取账户详细信息
     * @param Account $account
     * @param bool $isNewUser 是否为新用户（新用户跳过一些查询）
     * @return array
     */
    public function getAccountDetailInfo(Account $account, bool $isNewUser = false): array
    {
        
        $pwa_status = 0;
        $rescue_funds_received_count = 0;

        // 新用户跳过一些查询，因为这些数据在注册时都是空的
        if (!$isNewUser) {
            // PWA状态查询
            $Info = AccountCoinLog::where([
                "user_id" => $account->id,
                "log_type_id" => CoinLog::Pwa,
            ])->find();
            $pwa_status = $Info ? 1 : 0;

            // 救援金查询
            $today = date('Y-m-d');
            $rescue_funds_received_count = RescueFunds::where('user_id', $account->id)
                ->whereDay('rescue_date', $today)
                ->count();
        }

        return [
            "id" => $account->id,
            "channel_id" => $account->channel_id,
            "nickname" => $account->nickname,
            "mobile" => $account->mobile,
            "token" => $account->token,
            "vip" => $account->vip ?? 0, // 默认为0
            "invite_code" => $account->invite_code,
            "experience_wallet" => $account->experience_wallet ?? 0, // 默认为0
            "recharge_wallet" => $account->recharge_wallet ?? 0, // 默认为0
            "switch_wallet" => $account->switch_wallet ?? 0, // 默认为0
            "sum_recharge" => $account->sum_recharge ?? 0, // 默认为0
            "sum_bet" => $account->sum_bet ?? 0, // 默认为0
            "ex_withdraw_bet" => $account->ex_withdraw_bet ?? 0, // 默认为0
            "withdraw_available" => $account->withdraw_available ?? 0, // 默认为0
            "pwa_status" => $pwa_status,
            "rescue_funds_received_count" => $rescue_funds_received_count,
        ];
    }
} 