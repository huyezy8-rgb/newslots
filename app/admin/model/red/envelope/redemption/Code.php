<?php

namespace app\admin\model\red\envelope\redemption;

use think\Model;

/**
 * Code
 */
class Code extends Model
{
    // 表名
    protected $name = 'red_envelope_redemption_code';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function getAmountMinAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getAmountMaxAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getPerUserLimitAttr($value): int
    {
        return is_null($value) ? 1 : (int)$value;
    }

    public function getExpireHoursAttr($value): int
    {
        return is_null($value) ? 0 : (int)$value;
    }

    public function used(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Account::class, 'used_id', 'id');
    }
}
