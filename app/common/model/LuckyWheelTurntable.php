<?php

namespace app\common\model;

use think\Model;

class LuckyWheelTurntable extends Model
{
    protected $table = 'slot_lucky_wheel_turntable';
    
    protected $autoWriteTimestamp = true;
    
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    
    /**
     * 获取奖项配置
     */
    public function getPrizesAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }
    
    /**
     * 设置奖项配置
     */
    public function setPrizesAttr($value)
    {
        return json_encode($value);
    }
    
    /**
     * 获取规则配置
     */
    public function getRulesAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }
    
    /**
     * 设置规则配置
     */
    public function setRulesAttr($value)
    {
        return json_encode($value);
    }
    
    /**
     * 获取可用的转盘列表
     */
    public static function getAvailableWheels()
    {
        return self::where('status', 1)->order('id', 'asc')->select();
    }
    
    /**
     * 根据用户充值金额获取可用的转盘
     */
    public static function getAvailableWheelsByRecharge($rechargeAmount)
    {
        return self::where('status', 1)
            ->where('unlock_condition', '<=', $rechargeAmount)
            ->order('id', 'asc')
            ->select();
    }
} 