<?php

namespace app\common\model\activity;

use think\Model;

class FirstDeposit270 extends Model
{
    protected $json = ['amount_list', 'pay_channels', 'reward_value','day_reward_percent','bet_sum_reward','bet_test_reward'];
    // 表名
    protected $name = 'activity_first_deposit_270';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected static function onAfterWrite(FirstDeposit270 $model): void
    {
        try {
            \app\common\service\ActivitySyncService::sync(
                'first_deposit_270',
                \app\api\enum\CoinLog::getTypeText(\app\api\enum\CoinLog::FirstDeposit270),
                [
                    'title' => (string)($model->title ?? ''),
                    'context' => (string)($model->context ?? ''),
                    'amount_list' => $model->amount_list ?? [],
                    'enable_reward' => (int)($model->enable_reward ?? 1),
                    'reward_value' => $model->reward_value ?? [],
                    'reward_strategy' => $model->reward_strategy ?? '',
                    'countdown_seconds' => (int)($model->countdown_seconds ?? 7200),
                ]
            );
        } catch (\Throwable $e) {
            // 静默失败
        }
    }
}