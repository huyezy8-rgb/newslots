<?php

namespace app\common\model\activity;

use think\Model;

class DepositVip extends Model
{
    protected $json = ['amount_list', 'pay_channels', 'reward_value','vip1','vip2','vip3'];
    // 表名
    protected $name = 'activity_deposit_vip';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected static function onAfterWrite(DepositVip $model): void
    {
        try {
            \app\common\service\ActivitySyncService::sync(
                'deposit_vip',
                \app\api\enum\CoinLog::getTypeText(\app\api\enum\CoinLog::DepositVip),
                [
                    'amount_list' => $model->amount_list ?? [],
                    'pay_channels' => $model->pay_channels ?? [],
                    'enable_reward'=>$model->enable_reward ?? 1,
                    'reward_strategy'=>$model->reward_strategy ?? 'range',
                    'reward_value' => $model->reward_value ?? [],
                    'vip1' => $model->vip1 ?? [],
                    'vip2' => $model->vip2 ?? [],
                    'vip3' => $model->vip3 ?? [],
                    'bet_multiplier'=>$model->bet_multiplier ?? 1,
                ]
            );
        } catch (\Throwable $e) {
            // 静默失败
        }
    }
}