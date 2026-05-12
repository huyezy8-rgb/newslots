<?php

namespace app\common\model;

use think\Model;

class LuckyWheelLogs extends Model
{
    protected $table = 'slot_lucky_wheel_logs';
    
    protected $autoWriteTimestamp = true;
    
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    
    /**
     * 获取用户转盘记录
     */
    public static function getUserLogs($userId, $wheelId = null, $limit = 10)
    {
        $query = self::where('user_id', $userId);
        if ($wheelId) {
            $query->where('wheel_id', $wheelId);
        }
        return $query->order('createtime', 'desc')->limit($limit)->select();
    }
    
    /**
     * 获取用户转盘使用次数
     */
    public static function getUserUsageCount($userId, $wheelId)
    {
        return self::where('user_id', $userId)
            ->where('wheel_id', $wheelId)
            ->count();
    }
    
    /**
     * 创建转盘记录
     */
    public static function createLog($userId, $wheelId, $prizeTitle, $prizeAmount)
    {
        return self::create([
            'user_id' => $userId,
            'wheel_id' => $wheelId,
            'prize_title' => $prizeTitle,
            'prize_amount' => $prizeAmount,
            'status' => 0 // 默认未发放
        ]);
    }
} 