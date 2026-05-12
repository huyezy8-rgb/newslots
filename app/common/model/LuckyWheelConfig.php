<?php

namespace app\common\model;

use think\Model;

class LuckyWheelConfig extends Model
{
    protected $table = 'slot_lucky_wheel_config';
    
    protected $autoWriteTimestamp = true;
    
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    
    /**
     * 获取活动配置
     */
    public static function getActiveConfig()
    {
        return self::where('status', 1)->find();
    }
} 