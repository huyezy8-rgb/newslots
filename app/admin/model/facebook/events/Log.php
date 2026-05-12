<?php

namespace app\admin\model\facebook\events;

use think\Model;

/**
 * Log
 */
class Log extends Model
{
    // 表名
    protected $name = 'facebook_events_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 字段类型转换
    protected $type = [
        'event_time' => 'timestamp:Y-m-d H:i:s',
    ];

}