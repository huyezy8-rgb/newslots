<?php

namespace app\common\model;

use think\Model;

class UserCollectGame extends Model
{
    // 设置表名
    protected $name = 'user_collect_game';

    // 自动时间戳
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 允许字段
    protected $field = [
        'user_id',
        'game_id',
        'game_name',
        'game_name_en',
        'game_icon',
        'create_time',
        'update_time',
    ];
}