<?php

namespace app\common\model\activity;

use think\Model;

/**
 * ChestConfig - 宝箱活动配置模型
 */
class ChestConfig extends Model
{
    // 表名
    protected $name = 'chest_config';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 允许写入的字段
    protected $allowField = [
        'name', 'status', 'bet_multiple', 'banner_image', 'default_image', 'waiting_image', 'received_image'
    ];

    /**
     * 获取打码倍数
     */
    public function getBetMultipleAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    /**
     * 获取状态
     */
    public function getStatusAttr($value): int
    {
        return (int)$value;
    }

    /**
     * 设置打码倍数
     */
    public function setBetMultipleAttr($value): float
    {
        return (float)$value;
    }

    /**
     * 设置状态
     */
    public function setStatusAttr($value): int
    {
        return (int)$value;
    }

    protected static function onAfterWrite(ChestConfig $model): void
    {
        try {
            \app\common\service\ActivitySyncService::sync(
                'chest',
                \app\api\enum\CoinLog::getTypeText(\app\api\enum\CoinLog::ChestBox),
                [
                    'bet_multiple' => is_null($model->bet_multiple) ? 1 : (float)$model->bet_multiple,
                ]
            );
        } catch (\Throwable $e) {
            // 静默失败，避免影响业务保存
        }
    }
} 