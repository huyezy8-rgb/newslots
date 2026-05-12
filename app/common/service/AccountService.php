<?php

namespace app\common\service;

use app\common\model\Account;
use app\common\model\AccountCoinLog;
use think\Exception;
use think\facade\Db;
use think\Service;

class AccountService
{
    private array $walletMap = [
        0 => 'experience_wallet',
        1 => 'recharge_wallet',
        2 => 'commission_balance',
        3 => 'pdd_reward',
    ];
    /**
     * 增加用户余额（正数）
     */
    public function increaseBalance(int $userId, float $amount, int $walletType, int $logTypeId, ?string $note = null): bool
    {
        if ($amount <= 0) {
            throw new Exception(__('service.amount_must_be_positive'));
        }
        return $this->changeBalance($userId, $amount, $walletType, $logTypeId, $note, true);
    }

    /**
     * 扣除用户余额（负数）
     */
    public function decreaseBalance(int $userId, float $amount, int $walletType, int $logTypeId, ?string $note = null): bool
    {
        if ($amount <= 0) {
            throw new Exception(__('service.amount_must_be_positive'));
        }
        return $this->changeBalance($userId, -$amount, $walletType, $logTypeId, $note, false);
    }

    /**
     * 私有方法：核心余额变动逻辑（带方向验证）
     */
    private function changeBalance(int $userId, float $amount, int $walletType, int $logTypeId, ?string $note, bool $isIncrease): bool
    {
        return Db::transaction(function () use ($userId, $amount, $walletType, $logTypeId, $note, $isIncrease) {
            if (!isset($this->walletMap[$walletType])) {
                throw new Exception(__('service.wallet_type_invalid'));
            }
            $walletField = $this->walletMap[$walletType];


            $account = Account::lock(true)->find($userId);
            if (!$account) {
                throw new Exception(__('service.user_not_found'));
            }

            $oldBalance = (float)$account->$walletField;
            $newBalance = bcadd($oldBalance, $amount, 6);

            if (!$isIncrease && $newBalance < 0) {
                throw new Exception(__('service.insufficient_balance'));
            }

            $account->$walletField = $newBalance;
            $account->save();
            $channelId = $account->channel_id;

            $coinLog=AccountCoinLog::create([
                'user_id'     => $userId,
                'channel_id'  => $channelId,
                'wallet_type' => $walletType,
                'old_num'     => $oldBalance,
                'num'         => $amount,
                'new_num'     => $newBalance,
                'log_type_id' => $logTypeId,
                'note'        => $note ?? ($isIncrease ? __('service.balance_increase') : __('service.balance_decrease')),
                'create_time' => time(),
                'update_time' => time(),
            ]);
            if ($isIncrease&& $walletType ==1  && $coinLog && $coinLog->id) {
                // (new DmlService())->addDmlLogByLogTypeId($userId, $amount, $coinLog->id, $logTypeId,$newBalance);
            }
            return true;
        });
    }


}
