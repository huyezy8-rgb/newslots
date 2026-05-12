<?php

namespace app\common\model\activity;

use think\Model;

/**
 * 站内信活动配置模型
 */
class InternalMessage extends Model
{
    // 表名
    protected $name = 'activity_internal_message';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    protected $pk = 'id';
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'amount' => 'decimal:2',
        'valid_hours' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];

    /**
     * 获取配置信息
     * @param int $id 配置ID，默认为1
     * @return array|null
     */
    public  function getConfig($id = 1)
    {
        return self::where('id', $id)->find();
    }
    
    /**
     * 获取默认配置
     * @return array|null
     */
    public static function getDefaultConfig()
    {
        return self::getConfig(1);
    }
    
    /**
     * 检查配置是否存在
     * @param int $id 配置ID
     * @return bool
     */
    public static function configExists($id = 1)
    {
        return self::where('id', $id)->exists();
    }
    
    /**
     * 创建默认配置
     * @return bool
     */
    public static function createDefaultConfig()
    {
        if (self::configExists(1)) {
            return true;
        }
        
        $defaultData = [
            'id' => 1,
            'title' => '欢迎加入我们！',
            'content' => '感谢您注册成为我们的用户，这是您的专属欢迎礼包！',
            'amount' => 10.00,
            'wallet_type' => 'experience_wallet',
            'valid_hours' => 24,
        ];
        
        return self::create($defaultData);
    }
    
    /**
     * 获取钱包类型列表
     * @return array
     */
    public static function getWalletTypes()
    {
        return [
            'experience_wallet' => '体验钱包',
            'recharge_wallet' => '充值钱包',
            'game_wallet' => '游戏钱包'
        ];
    }
    
    /**
     * 获取钱包类型名称
     * @param string $type 钱包类型
     * @return string
     */
    public static function getWalletTypeName($type)
    {
        $types = self::getWalletTypes();
        return $types[$type] ?? $type;
    }
    
    /**
     * 计算过期时间
     * @param int $validHours 有效期小时数
     * @return int|null
     */
    public static function calculateExpireTime($validHours)
    {
        if ($validHours <= 0) {
            return null; // 永久有效
        }
        
        return time() + ($validHours * 3600);
    }
    
    /**
     * 格式化有效期显示
     * @param int $validHours 有效期小时数
     * @return string
     */
    public static function formatValidTime($validHours)
    {
        if ($validHours <= 0) {
            return '永久有效';
        }
        
        if ($validHours < 24) {
            return $validHours . '小时';
        }
        
        $days = floor($validHours / 24);
        $hours = $validHours % 24;
        
        if ($hours == 0) {
            return $days . '天';
        }
        
        return $days . '天' . $hours . '小时';
    }
    
    /**
     * 验证配置数据
     * @param array $data 配置数据
     * @return array
     */
    public static function validateConfig($data)
    {
        $errors = [];
        
        if (empty($data['title'])) {
            $errors[] = '消息标题不能为空';
        }
        
        if (empty($data['content'])) {
            $errors[] = '消息内容不能为空';
        }
        
        if (!isset($data['amount']) || $data['amount'] < 0) {
            $errors[] = '赠送金额不能小于0';
        }
        
        if (empty($data['wallet_type'])) {
            $errors[] = '钱包类型不能为空';
        }
        
        if (!isset($data['valid_hours']) || $data['valid_hours'] < 0) {
            $errors[] = '有效期不能小于0';
        }
        
        return $errors;
    }

    protected static function onAfterWrite(InternalMessage $model): void
    {
        try {
            \app\common\service\ActivitySyncService::sync(
                'internal_message',
                '站内信活动',
                [
                    'bet_multiplier'=>$model->bet_multiplier ?? 1,
                ]
            );
        } catch (\Throwable $e) {
            // 静默失败
        }
    }
} 