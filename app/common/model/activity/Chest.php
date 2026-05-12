<?php

namespace app\common\model\activity;

use think\Model;

/**
 * Chest
 */
class Chest extends Model
{
    // 表名
    protected $name = 'chest';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getRechargeAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getRewardAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }
}