<?php

namespace app\event;

use app\common\model\Account;
use app\common\model\ChannelList;
use app\common\model\FacebookEventsLog;
use app\common\service\FacebookService;
use think\facade\Config;
use think\facade\Log;
use think\facade\Request;

class FacebookConversion
{
    // 事件类型映射
    protected $eventMap = [
        'register' => 'CompleteRegistration',
        'purchase' => 'Purchase',
        'add_to_cart' => 'AddToCart',
        'add_to_wishlist' => 'AddToWishlist'
    ];

    protected $config;
    protected $facebookService;

    protected function getFacebookService(): FacebookService
    {
        if (!$this->facebookService) {
            $this->facebookService = new FacebookService();
        }
        return $this->facebookService;
    }

    /**
     * 处理Facebook转化事件
     */
    public function handle(array $data): void
    {
        $userId = (int)$data['user_id'];
        $eventType = $data['event_type'];
        $customData = $data['custom_data'] ?? [];
        $event_source_url = $data['event_source_url'] ?? Request::url(true);
        // 6. 生成唯一event_id
        $eventId = (isset($data['event_id']) && trim($data['event_id']) !== '')
            ? $data['event_id']
            : $this->generateEventId($eventType, $userId, $customData);
        try {
            // 1. 验证参数
            if (empty($userId) || empty($eventType)) {
                Log::warning("Facebook事件参数缺失: " . json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return;
            }

            // 2. 获取用户数据
            $userData = Account::where('id', $userId)->find();
            if (!$userData) {
                Log::warning("用户不存在: " . json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return;
            }

            // 3. 获取渠道数据
            $channel = ChannelList::where('id', $userData->channel_id)->find();
            if (!$channel || empty($channel->facebook_pixel_id) || empty($channel->facebook_token)) {
                Log::warning("渠道Facebook配置不完整: " . json_encode(['channel_id' => $userData->channel_id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return;
            }

            // 4. 检查Facebook配置
            $this->config = Config::get('facebook');
            if (!$this->config || !$this->config['open']) {
                Log::warning("Facebook CAPI 未启用");
                return;
            }

            // 5. 验证事件类型
            if (!isset($this->eventMap[$eventType])) {
                Log::warning("不支持的事件类型: " . json_encode(['event_type' => $eventType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return;
            }



            // 7. 构建事件数据
            $eventData = [
                'event_name' => $this->eventMap[$eventType],
                'event_time' => time(),
                'event_source_url' => $event_source_url,
                'event_id' => $eventId,
                'user_data' => $this->prepareUserData($userData, $data),
                'custom_data' => $this->prepareCustomData($eventType, $customData)
            ];

            // 8. 记录事件日志
            $log = $this->createEventLog($userId, $userData->channel_id, $eventType, $eventData, $channel, $eventId);

            // 9. 发送事件
            $fbService = $this->getFacebookService();
            $result = $fbService->sendEvent($userId, $eventData);

            // 10. 更新日志状态
            if ($result['status']) {
                $log->updateStatus(FacebookEventsLog::STATUS_SUCCESS, $result);
                Log::info("Facebook事件发送成功: " . json_encode([
                    'user_id' => $userId,
                    'event_type' => $eventType,
                    'event_id' => $eventId,
                    'fb_event_id' => $result['event_id'] ?? null
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $log->updateStatus(FacebookEventsLog::STATUS_FAILED, $result);
                Log::error("Facebook事件发送失败: " . json_encode([
                    'user_id' => $userId,
                    'event_type' => $eventType,
                    'event_id' => $eventId,
                    'error' => $result['error'] ?? '未知错误'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

        } catch (\Throwable $e) {
            Log::error("Facebook事件处理异常: " . json_encode([
                'user_id' => $userId,
                'event_type' => $eventType,
                'event_id' => $eventId ?? null,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            // 如果有日志记录，更新失败状态
            if (isset($log)) {
                $log->updateStatus(FacebookEventsLog::STATUS_FAILED, ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 生成唯一的event_id
     * @param string $eventType 事件类型
     * @param int $userId 用户ID
     * @param array $customData 自定义数据
     * @return string
     */
    protected function generateEventId(string $eventType, int $userId, array $customData = []): string
    {
        // 1. 优先使用业务唯一ID（如订单号、充值单号等）
        if (isset($customData['order_id']) && !empty($customData['order_id'])) {
            return $eventType . '_' . $customData['order_id'];
        }

        if (isset($customData['recharge_id']) && !empty($customData['recharge_id'])) {
            return $eventType . '_' . $customData['recharge_id'];
        }

        if (isset($customData['withdraw_id']) && !empty($customData['withdraw_id'])) {
            return $eventType . '_' . $customData['withdraw_id'];
        }

        // 2. 使用用户ID + 时间戳 + 随机数
        $timestamp = time();
        $randomStr = bin2hex(random_bytes(4)); // 8位随机字符串

        return $eventType . '_' . $userId . '_' . $timestamp . '_' . $randomStr;
    }

    /**
     * 创建事件日志
     */
    protected function createEventLog(int $userId, int $channelId, string $eventType, array $eventData, ChannelList $channel, string $eventId): FacebookEventsLog
    {
        return FacebookEventsLog::createLog([
            'user_id' => $userId,
            'channel_id' => $channelId,
            'event_type' => $eventType,
            'event_name' => $this->eventMap[$eventType],
            'event_data' => $eventData,
            'custom_data' => $eventData['custom_data'] ?? [],
            'user_data' => $eventData['user_data'] ?? [],
            'fb_pixel_id' => $channel->facebook_pixel_id,
            'fb_token' => $channel->facebook_token,
            'event_time' => $eventData['event_time'],
            'event_id' => $eventId
        ]);
    }

    /**
     * 准备用户数据
     */
    protected function prepareUserData($userData, array $data): array
    {
        $userInfo = [
            'client_ip_address' => $data['client_ip'] ?? Request::ip(),
            'client_user_agent' => $data['client_user_agent'] ?? Request::header('user-agent'),
            'fbc' => $data['fbc'] ?? $_COOKIE['_fbc'] ?? '',
            'fbp' => $data['fbp'] ?? $_COOKIE['_fbp'] ?? '',
        ];

        // 添加用户标识
        if (!empty($userData->id)) {
            $userInfo['external_id'] = $this->hashData((string)$userData->id);
        }

        if (!empty($userData->mobile)) {
            $userInfo['ph'] = $this->hashData($userData->mobile);
        }

        return array_filter($userInfo);
    }

    /**
     * 准备自定义数据
     */
    protected function prepareCustomData(string $eventType, array $data): array
    {
        $customData = [];

        switch ($eventType) {
            case 'purchase':
                $customData = [
                    'value' => isset($data['amount']) ? (float)$data['amount'] : 0,
                    'currency' => $data['currency'] ?? 'USD',
                    'order_id' => $data['order_id'] ?? ''
                ];
                break;

            case 'add_to_cart':
            case 'add_to_wishlist':
                $customData = [
                    'value' => isset($data['amount']) ? (float)$data['amount'] : 0,
                    'currency' => $data['currency'] ?? 'USD',
                    'order_id' => $data['order_id'] ?? ''
                ];
                break;

            case 'register':
                $customData = [
                    'status' => 'success',
                    'method' => $data['method'] ?? 'h5'
                ];
                break;
        }

        return array_filter($customData, function($value) {
            return $value !== '' && $value !== null;
        });
    }

    /**
     * 哈希数据
     */
    protected function hashData(string $value): string
    {
        return hash('sha256', trim(strtolower($value)));
    }
}