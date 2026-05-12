<?php

namespace app\common\model\member\level;

use think\Model;

/**
 * Config
 */
class Config extends Model
{
    // 表名
    protected $name = 'member_level_config';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function getRechargeRequirementAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getWithdrawLimitAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getWithdrawFeePercentAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getBonusPercentAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getFloatAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }
}