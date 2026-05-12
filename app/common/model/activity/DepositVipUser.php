<?php

namespace app\common\model\activity;

use think\Model;

class DepositVipUser extends Model
{
    protected $json = [];
    // 表名
    protected $name = 'activity_deposit_vip_user';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
}