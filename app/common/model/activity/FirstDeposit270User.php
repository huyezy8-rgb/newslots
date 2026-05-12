<?php

namespace app\common\model\activity;

use think\Model;

class FirstDeposit270User extends Model
{
    protected $json = ['day_reward'];
    // 表名
    protected $name = 'activity_first_deposit_270_user';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
}