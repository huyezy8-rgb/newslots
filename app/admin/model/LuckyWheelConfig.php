<?php

namespace app\admin\model;

use think\Model;

class LuckyWheelConfig extends Model
{
    protected $name = 'lucky_wheel_config';
    
    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'title'           => 'string',
        'banner_image'    => 'string',
        'bet_multiple'    => 'decimal',
        'status'          => 'int',
        'createtime'      => 'int',
        'updatetime'      => 'int',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 状态获取器
    public function getStatusTextAttr($value, $data)
    {
        $status = [0 => '禁用', 1 => '启用'];
        return $status[$data['status']] ?? '未知';
    }
} 