<?php

namespace app\common\model\activity;

use think\Model;

/**
 * Daygold
 */
class Daygold extends Model
{
    protected $json  = ['rewards'];
    // 表名
    protected $name = 'activity_daygold';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    
    protected static function onAfterWrite(Daygold $model): void
    {
        try {
            \app\common\service\ActivitySyncService::sync(
                'daygold',
                \app\api\enum\CoinLog::getTypeText(\app\api\enum\CoinLog::DayGold),
                [
                    'rewards' => $model->rewards ?? [],
                    'deadline_hour' => $model->deadline_hour ?? 17,
                    'status' => 1,
                ]
            );
        } catch (\Throwable $e) {
            // 静默失败
        }
    }

}