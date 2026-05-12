<?php

namespace app\admin\model;

use think\Model;

class LuckyWheelLogs extends Model
{
    protected $name = 'lucky_wheel_logs';
    
    // 设置字段信息
    protected $schema = [
        'id'            => 'int',
        'user_id'       => 'int',
        'wheel_id'      => 'int',
        'prize_title'   => 'string',
        'prize_amount'  => 'decimal',
        'status'        => 'int',
        'createtime'    => 'int',
        'updatetime'    => 'int',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 状态获取器
    public function getStatusTextAttr($value, $data)
    {
        $status = [0 => '未发放', 1 => '已发放'];
        return $status[$data['status']] ?? '未知';
    }

    // 关联转盘
    public function turntable()
    {
        return $this->belongsTo(LuckyWheelTurntable::class, 'wheel_id', 'id');
    }
} 