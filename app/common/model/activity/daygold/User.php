<?php

namespace app\common\model\activity\daygold;

use think\Model;

/**
 * User
 */
class User extends Model
{
    // 表主键
    protected $pk = 'uid';

    // 表名
    protected $name = 'activity_daygold_user';

    //json
    protected $json = ['rewards','receive_status'];
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'last_receive_time' => 'timestamp:Y-m-d H:i:s',
    ];

}