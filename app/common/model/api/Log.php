<?php

namespace app\common\model\api;

use think\Model;

/**
 * Log
 */
class Log extends Model
{
    // 表名
    protected $name = 'api_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;

}