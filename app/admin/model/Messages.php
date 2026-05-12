<?php

namespace app\admin\model;

use think\Model;

/**
 * Messages
 */
class Messages extends Model
{
    // 表名
    protected $name = 'messages';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    public function getAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }

    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Account::class, 'user_id', 'id');
    }
}