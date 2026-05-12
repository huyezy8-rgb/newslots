<?php

namespace app\common\model\activity;

use think\Model;

/**
 * VIP独有充值配置模型
 */
class FirstVip49 extends Model
{
    // 表名
    protected $name = 'activity_first_vip_49';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    
    protected $pk = 'id';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'update_time' => 'integer',
    ];
    
    // JSON字段
    protected $json = ['amount_list', 'pay_channels'];
    
    // JSON数据返回数组
    protected $jsonAssoc = true;
    
    /**
     * 获取配置信息
     * @return array|null
     */
    public function getConfig()
    {
        return self::where('id', 1)->find();
    }
    
    /**
     * 根据充值金额获取奖励配置
     * @param float $amount 充值金额
     * @return array|null
     */
    public static function getRewardByAmount($amount)
    {
        $config = (new FirstVip49)->getConfig();
        if (!$config || !$config->amount_list) {
            return null;
        }
        
        $amountList = $config->amount_list;
        foreach ($amountList as $item) {
            if ($item['amount'] == $amount) {
                return $item;
            }
        }
        
        return null;
    }
    
    /**
     * 根据支付通道获取奖励配置
     * @param string $channel 支付通道
     * @return array|null
     */
    public static function getRewardByChannel($channel)
    {
        $config = (new FirstVip49)->getConfig();
        if (!$config || !$config->pay_channels) {
            return null;
        }
        
        $payChannels = $config->pay_channels;
        foreach ($payChannels as $item) {
            if ($item['channel'] == $channel) {
                return $item;
            }
        }
        
        return null;
    }
    
    /**
     * 计算奖励金额
     * @param float $amount 充值金额
     * @param string $channel 支付通道
     * @return float
     */
    public static function calculateReward($amount, $channel = '')
    {
        $config = (new FirstVip49)->getConfig();
        if (!$config) {
            return 0;
        }
        
        $reward = 0;
        
        // 根据充值金额获取基础奖励
        $amountReward = self::getRewardByAmount($amount);
        if ($amountReward) {
            $reward = $amountReward['reward'] ?? 0;
        }
        
        // 根据支付通道调整奖励
        if ($channel) {
            $channelReward = self::getRewardByChannel($channel);
            if ($channelReward) {
                $channelPercent = $channelReward['reward_percent'] ?? 0;
                $channelBonus = bcmul($amount, bcdiv($channelPercent, 100, 2), 2);
                $reward = bcadd($reward, $channelBonus, 2);
            }
        }
        
        return $reward;
    }

    protected static function onAfterWrite(FirstVip49 $model): void
    {
        try {
            \app\common\service\ActivitySyncService::sync(
                'first_vip_49',
                \app\api\enum\CoinLog::getTypeText(\app\api\enum\CoinLog::FirstVip49),
                [
                    'amount_list' => $model->amount_list ?? [],
                    'bet_multiplier'=>$model->bet_multiplier ?? 1,
                ]
            );
        } catch (\Throwable $e) {
            // 静默失败
        }
    }
}