<?php

namespace app\common\model\withdraw;

use app\common\model\Account;
use think\Model;

/**
 * Orders
 */
class Orders extends Model
{
    protected $json = ['account_info'];
    // 表名
    protected $name = 'withdraw_orders';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    public function getAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getRealAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getFeeAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function user()
    {
        return $this->hasOne(Account::class, 'id', 'user_id');
    }
}