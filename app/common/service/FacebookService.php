<?php

namespace app\common\service;


use app\common\model\Account;
use app\common\model\activity\daygold\User;
use app\common\model\ChannelList;
use think\facade\Config;
use think\facade\Log;
use think\facade\Request;
class FacebookService
{
    protected $config;

    // 默认事件参数
    protected $defaults = [
        'action_source' => 'website',
        'event_time' => null,
        'event_id' => null,
        'event_source_url' => null,
    ];

    public function __construct()
    {
        $this->config = Config::get('facebook');
        $this->defaults['event_time'] = time();
    }

    /**
     * 发送单个转化事件
     * @param array $eventData 事件数据
     * @return array 返回结果
     */
    public function sendEvent($user_id,array $eventData): array
    {
        $user=Account::where(['id'=>$user_id])->find();
        $channel = ChannelList::where(['id'=>$user['channel_id']])->find();
        $this->config['pixel_id'] = $channel['facebook_pixel_id'];
        $this->config['access_token'] = $channel['facebook_token'];
        Log::info("重新定义pixel_id：".$this->config['pixel_id'] . "||access_token：".$this->config['access_token']);
        try {
            // 准备事件数据
            $event = $this->prepareEvent($eventData);

            // 构建请求负载
            $payload = [
                'data' => [$event],
                'access_token' => $this->config['access_token'],
                'test_event_code' => $this->isTestMode() ? $this->config['test_code'] : null
            ];

            // 调用API
            $response = $this->callFacebookApi($payload);

            // 记录成功日志
            $this->logSuccess($event, $response);

            return [
                'status' => true,
                'event_id' => $event['event_id'],
                'fb_trace_id' => $response['fbtrace_id'] ?? null
            ];

        } catch (\Exception $e) {
            // 记录错误日志
            $this->logError($eventData, $e);

            return [
                'status' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 准备事件数据
     */
    protected function prepareEvent(array $data): array
    {
        // 验证必要字段
        if (empty($data['event_name'])) {
            throw new \InvalidArgumentException(__('service.event_name_required'));
        }

        // 合并默认数据
        $event = array_merge($this->defaults, [
            'event_name' => $data['event_name'],
            'event_id' => $data['event_id'] ?? $this->generateEventId(),
            'event_source_url' => $data['event_source_url'] ?? Request::header('referer'),
        ]);

        // 添加用户数据
        $event['user_data'] = $this->prepareUserData($data);

        // 添加自定义数据
        if (!empty($data['custom_data'])) {
            $this->validateCustomData($data['custom_data']);
            $event['custom_data'] = $data['custom_data'];
        }

        return $event;
    }

    /**
     * 准备用户数据
     */
    protected function prepareUserData(array $data): array
    {
        // 不需要哈希处理的字段
        $userData = [

        ];

        // 合并显式传递的用户数据（不覆盖已处理字段）
        if (!empty($data['user_data']) && is_array($data['user_data'])) {
            $userData = array_merge($userData, $data['user_data']);
        }

        // 需要哈希处理的字段映射
        $hashFields = [
            'em' => ['email', 'em'],       // 邮箱
            'ph' => ['phone', 'ph'],       // 手机号
            'fn' => ['first_name', 'fn'],  // 名字
            'ln' => ['last_name', 'ln'],   // 姓氏
            'ge' => ['gender', 'ge'],      // 性别
            'db' => ['birthday', 'db'],    // 出生日期
            'ct' => ['city', 'ct'],        // 城市
            'st' => ['state', 'st'],       // 州/省
            'zp' => ['zip', 'zp'],         // 邮编
            'country' => ['country'],      // 国家/地区
            'external_id' => ['external_id'] // 外部编号
        ];

        // 处理需要哈希的字段
        foreach ($hashFields as $fieldKey => $sourceKeys) {
            foreach ($sourceKeys as $sourceKey) {
                if (!empty($data[$sourceKey])) {
                    $userData[$fieldKey] = $this->hashData($data[$sourceKey]);
                    break; // 找到第一个非空值就停止
                }
            }
        }


        return array_filter($userData, function($value) {
            return $value !== '' && $value !== null;
        });
    }

    /**
     * 验证自定义数据
     */
    protected function validateCustomData(array $customData)
    {
        // 验证货币代码
        if (isset($customData['currency'])) {
            $validCurrencies = ['USD', 'EUR', 'GBP', 'CNY'];
            if (!in_array(strtoupper($customData['currency']), $validCurrencies)) {
                throw new \InvalidArgumentException(__('service.invalid_currency_code', ['currency' => $customData['currency']]));
            }
        }

        // 验证金额
        if (isset($customData['value'])) {
            if (!is_numeric($customData['value']) || $customData['value'] < 0) {
                throw new \InvalidArgumentException(__('service.amount_must_be_positive_number'));
            }
        }
    }

    /**
     * 调用Facebook API
     */
    protected function callFacebookApi(array $payload): array
    {
        Log::info(__('service.conversion_event_request_parameters').": ".json_encode($payload));
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/events',
            $this->config['api_version'],
            $this->config['pixel_id']
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 3, // 3秒超时
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST =>0
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true) ?? [];
            throw new \RuntimeException($errorData['error']['message'] ?? __('service.api_request_failed', ['error' => $error]));
        }
        return json_decode($response, true);
    }

    /**
     * 生成事件ID
     */
    protected function generateEventId(): string
    {
        return uniqid('fb_', true);
    }

    /**
     * 哈希数据
     */
    protected function hashData(string $value): string
    {
        return hash('sha256', trim(strtolower($value)));
    }

    /**
     * 是否测试模式
     */
    protected function isTestMode(): bool
    {
        return $this->config['test_mode'] ?? false;
    }

    /**
     * 记录成功日志
     */
    protected function logSuccess(array $event, array $response)
    {
        Log::info(__('service.facebook_conversion_event_sent_successfully').json_encode( [
            'event' => $event['event_name'],
            'event_id' => $event['event_id'],
            'response' => $response
        ])  );
    }

    /**
     * 记录错误日志
     */
    protected function logError(array $eventData, \Exception $e)
    {
        Log::error(__('service.facebook_conversion_event_send_failed').json_encode([
            'event_data' => $eventData,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]));
    }
}