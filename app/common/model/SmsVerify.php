<?php

namespace app\common\model;

use think\Model;

class SmsVerify  extends Model
{
    // 表名
    protected $name = 'sms_verify';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
}