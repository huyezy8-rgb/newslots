<?php

namespace app\admin\model\jackpot\ladder;

use think\Model;

/**
 * Config
 */
class Config extends Model
{
    // 表名
    protected $name = 'jackpot_ladder_config';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    public function getAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }
}