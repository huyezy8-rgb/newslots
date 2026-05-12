<?php

namespace app\admin\model\iapp;

use think\Model;

/**
 * Account
 */
class Account extends Model
{
    // 表名
    protected $name = 'account';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    public function getExperienceWalletAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getRechargeWalletAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }
}