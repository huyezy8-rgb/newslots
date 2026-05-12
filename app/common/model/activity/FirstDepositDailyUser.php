<?php

namespace app\common\model\activity;

use think\Model;

class FirstDepositDailyUser extends Model
{
    protected $json = ['task_status'];
    // 表名
    protected $name = 'activity_first_deposit_daily_user';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
}