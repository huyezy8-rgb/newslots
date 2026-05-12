<?php

namespace app\admin\model\team\commission;

use think\Model;

/**
 * Log
 */
class Log extends Model
{
    // 表名
    protected $name = 'team_commission_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;


    public function getBetAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getBaseRateAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getPointDiffAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getCommissionAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function channel(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\channel\Listsss::class, 'channel_id', 'id');
    }
}