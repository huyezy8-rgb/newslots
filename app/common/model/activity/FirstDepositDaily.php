<?php

namespace app\common\model\activity;



use think\Model;

/**
 * 每日首充配置模型
 */
class FirstDepositDaily extends Model
{

    // 表名
    protected $name = 'activity_first_deposit_daily';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    
    protected $pk = 'id';

    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'enable_reward' => 'integer',
        'task_reward' => 'decimal:6',
        'update_time' => 'integer',
    ];
    
    // JSON字段
    protected $json = ['amount_list', 'pay_channels', 'reward_value'];
    
    // JSON数据返回数组
    protected $jsonAssoc = true;
    
    /**
     * 获取配置信息
     * @param string $name
     * @return array|null
     */
    public function getConfig(string $name = '')
    {
        return self::where('id', 1)->find();
    }
    
    /**
     * 检查活动是否启用
     * @return bool
     */
    public static function isEnabled()
    {
        $config = (new FirstDepositDaily)->getConfig();
        return $config && $config->enable_reward == 1;
    }
    
    /**
     * 根据充值金额获取奖励配置
     * @param float $amount 充值金额
     * @return array|null
     */
    public static function getRewardByAmount($amount)
    {
        $config = (new FirstDepositDaily)->getConfig();
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
        $config = (new FirstDepositDaily)->getConfig();
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
        $config = (new FirstDepositDaily)->getConfig();
        if (!$config || $config->enable_reward != 1) {
            return 0;
        }
        
        $reward = 0;
        $strategy = $config->reward_strategy;
        $rewardValue = $config->reward_value;
        
        // 根据策略计算基础奖励
        switch ($strategy) {
            case 'fixed':
                $reward = $rewardValue['fixed'] ?? 0;
                break;
            case 'range':
                $min = $rewardValue['min'] ?? 0;
                $max = $rewardValue['max'] ?? 0;
                $reward = rand($min * 100, $max * 100) / 100;
                break;
            case 'percent':
                $percent = $rewardValue['percent'] ?? 0;
                $reward = bcmul($amount, bcdiv($percent, 100, 2), 2);
                break;
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

    protected static function onAfterWrite(FirstDepositDaily $model): void
    {
        try {
            \app\common\service\ActivitySyncService::sync(
                'first_deposit_daily',
                \app\api\enum\CoinLog::getTypeText(\app\api\enum\CoinLog::FirstDepositDaily),
                [
                    'title' => (string)($model->title ?? ''),
                    'context' => (string)($model->context ?? ''),
                    'enable_reward' => (int)($model->enable_reward ?? 1),
                    'task_reward' => (string)($model->task_reward ?? '0.00'),
                    'amount_list' => $model->amount_list ?? [],
                    'reward_value' => $model->reward_value ?? [],
                    'reward_strategy' => $model->reward_strategy ?? 'range',
                    'bet_multiplier'=>$model->bet_multiplier ?? 1,
                ]
            );
        } catch (\Throwable $e) {
            // 静默失败
        }
    }
}