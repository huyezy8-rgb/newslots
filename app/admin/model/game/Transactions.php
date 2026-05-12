<?php

namespace app\admin\model\game;

use think\Model;

/**
 * Transactions
 */
class Transactions extends Model
{
    // 表名
    protected $name = 'game_transactions';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    public function getAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getRealAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }
}