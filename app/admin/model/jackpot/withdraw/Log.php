<?php

namespace app\admin\model\jackpot\withdraw;

use think\Model;

/**
 * Log
 */
class Log extends Model
{
    // 表名
    protected $name = 'jackpot_withdraw_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    public function getCurrentAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function getWithdrawAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Account::class, 'user_id', 'id');
    }
}