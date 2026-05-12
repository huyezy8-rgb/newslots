<?php

namespace app\api\controller;

use app\common\service\MessageService;
use think\App;
use think\facade\Cache;
use think\facade\Log;
use think\Request;
use think\Response;
use think\facade\Db;

class Sse extends Base
{
    protected MessageService $messageService;
    public function __construct(App $app)
    {
        parent::__construct($app);

    }
    protected function getMessageService(): MessageService
    {
        return $this->messageService ??= new MessageService();
    }
    public function message(Request $request): Response
    {
        $uid = $this->userInfo['id'];
        $cacheKey = "sse:{$uid}:unread_message";

        // === 关键：关闭所有可能的缓冲 ===
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        ini_set('zlib.output_compression', 'Off');
        ini_set('output_buffering', 'Off');
        ini_set('implicit_flush', 1);

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        ob_implicit_flush(true);

        // === 关键：直接输出 header（不要使用 response 对象）===
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');



        // 每 5 秒检查一次，共持续 60 秒
        for ($i = 0; $i < 12; $i++) {
            // 尝试从缓存获取
            $data = Cache::get($cacheKey);
            if ($data === null) {
                Log::info("没有缓存数,查询数据库，");
                // 缓存不存在，查数据库并缓存 600 秒
                $count = $this->getMessageService()->getUnreadCount($uid);
                $list  = $this->getMessageService()->getUnreadMsg($uid,2);
                $data = [
                    'unread' => $count,
                    'list'  => $list,
                ];
                Cache::set($cacheKey, $data, 600);
            }
            echo "event: message\n";
            echo "data: " . json_encode($data) . "\n\n";

            flush();
            sleep(5);
        }

        return response('', 200);
    }

    public function userinfo(Request $request): Response
    {


        // === 关键：关闭所有可能的缓冲 ===
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        ini_set('zlib.output_compression', 'Off');
        ini_set('output_buffering', 'Off');
        ini_set('implicit_flush', 1);

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        ob_implicit_flush(true);

        // === 关键：直接输出 header（不要使用 response 对象）===
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');



        // 每 5 秒检查一次，共持续 60 秒
        $userId = $this->userInfo->id;
        $cacheKey = "sse:userinfo:{$userId}";
        $lastUpdateTime = 0;
        
        for ($i = 0; $i < 12; $i++) {
            $currentTime = time();
            
            // 检查是否需要更新用户信息（每30秒更新一次，或者缓存不存在）
            $shouldUpdate = false;
            $cachedData = Cache::get($cacheKey);
            
            if (!$cachedData || ($currentTime - $lastUpdateTime) >= 30) {
                $shouldUpdate = true;
            }
            
            if ($shouldUpdate) {
                // 查询数据库获取最新用户信息
                $userInfo = \app\common\model\Account::where('id', $userId)->find();
                
                if (!$userInfo) {
                    // 如果用户不存在，发送错误信息
                    echo "event: error\n";
                    echo "data: " . json_encode(['error' => 'User not found']) . "\n\n";
                    flush();
                    sleep(5);
                    continue;
                }
                
                // 构建用户数据
                $data = [
                    "id" => $userInfo->id,
                    "channel_id" => $userInfo->channel_id,
                    "nickname" => $userInfo->nickname,
                    "mobile" => $userInfo->mobile,
                    "token" => $userInfo->token,
                    "vip" => $userInfo->vip,
                    "invite_code" => $userInfo->invite_code,
                    "experience_wallet" => $userInfo->experience_wallet,
                    "recharge_wallet" => $userInfo->recharge_wallet,
                    "switch_wallet" => $userInfo->switch_wallet,
                    "sum_recharge" => $userInfo->sum_recharge,
                    "sum_bet" => $userInfo->sum_bet,
                    "ex_withdraw_bet" => $userInfo->ex_withdraw_bet,
                    "withdraw_available" => $userInfo->withdraw_available,
                    "ex_withdraw_bet_base" => get_sys_config("ex_withdraw_bet_base") ?? 9000,
                    "ex_withdraw_amount" => get_sys_config('ex_withdraw_amount') ?? 30,
                    "update_time" => $currentTime,
                    "data_source" => "realtime", // 标记为实时数据
                    "cache_status" => "updated" // 缓存状态
                ];
                
                // 缓存用户信息，有效期5分钟，使用标签管理
                Cache::tag(\app\common\model\Account::$cacheTag)->set($cacheKey, $data, 300);
                $lastUpdateTime = $currentTime;
            } else {
                // 使用缓存数据
                $data = $cachedData;
                $data["data_source"] = "cache"; // 标记为缓存数据
                $data["cache_status"] = "hit"; // 缓存命中
            }
            
            echo "event: userinfo\n";
            echo "data: " . json_encode(['user_info' => $data]) . "\n\n";

            flush();
            sleep(5);
        }

        return response('', 200);
    }

    /**
     * 系统通知滚动消息和随机奖励消息
     */
    public function notification(Request $request): Response
    {
        // === 关键：关闭所有可能的缓冲 ===
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        ini_set('zlib.output_compression', 'Off');
        ini_set('output_buffering', 'Off');
        ini_set('implicit_flush', 1);

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        ob_implicit_flush(true);

        // === 关键：直接输出 header（不要使用 response 对象）===
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');

        // 从数据库配置获取系统通知消息
        $noticeInfo = get_sys_config('nitice_info');
        $notifications = [];
        
        if ($noticeInfo) {
            // 解码HTML实体
            $message = html_entity_decode($noticeInfo, ENT_QUOTES, 'UTF-8');
            $message = trim($message);
            if (!empty($message)) {
                // 添加喇叭图标
                $message = "📢 " . $message;
                $notifications[] = $message;
            }
        }
        
        // 如果没有配置消息，使用默认消息
        if (empty($notifications)) {
            $notifications = [
                "📢 🎮 Welcome to our gaming platform! Start your adventure now!",
                "📢 🎯 New games are waiting for you! Try your luck today!",
                "📢 🎊 Join thousands of players and win amazing prizes!",
                "📢 🌟 Discover exciting games and earn real rewards!",
                "📢 🎪 Experience the thrill of gaming with us!"
            ];
        }

        // 从数据库配置获取奖励通知开关
        $rewardsOpen = get_sys_config('notice_rewards_open');
        $hasRewards = $rewardsOpen == '1' || $rewardsOpen === 1;

        // 从数据库配置获取奖励设置
        $rewardTypes = [];
        if ($hasRewards) {
            $rewardsConfig = get_sys_config('notice_rewards_config');
            if ($rewardsConfig && is_array($rewardsConfig)) {
                foreach ($rewardsConfig as $config) {
                    if (isset($config['key']) && isset($config['value'])) {
                        // 解析配置值，格式：name:VIP Reward, min:10,max:20
                        $value = $config['value'];
                        $name = '';
                        $min = 10;
                        $max = 100;
                        
                        // 解析name
                        if (preg_match('/name:\s*([^,]+)/', $value, $matches)) {
                            $name = trim($matches[1]);
                        }
                        
                        // 解析min
                        if (preg_match('/min:\s*(\d+)/', $value, $matches)) {
                            $min = intval($matches[1]);
                        }
                        
                        // 解析max
                        if (preg_match('/max:\s*(\d+)/', $value, $matches)) {
                            $max = intval($matches[1]);
                        }
                        
                        if (!empty($name)) {
                            $rewardTypes[$config['key']] = [
                                'name' => $name,
                                'min' => $min,
                                'max' => $max
                            ];
                        }
                    }
                }
            }
        }
        
        // 如果没有配置奖励类型，使用默认设置
        if (empty($rewardTypes)) {
            $rewardTypes = [
                'vip' => ['name' => 'VIP Reward', 'min' => 100, 'max' => 1000],
                'invite' => ['name' => 'Referral Reward', 'min' => 50, 'max' => 500],
                'daily' => ['name' => 'Daily Check-in', 'min' => 20, 'max' => 200],
                'task' => ['name' => 'Task Reward', 'min' => 40, 'max' => 800]
            ];
        }

        // 获取用户渠道信息中的货币符号
        $currency = '$'; // 默认美元符号
        if (isset($this->userInfo->channel) && isset($this->userInfo->channel['currency_symbol'])) {
            $currency = $this->userInfo->channel['currency_symbol'];
        }
        
        // 首先必定推送系统通知消息
        $randomMessage = $notifications[array_rand($notifications)];
        $data = [
            'type' => 'notice',
            'message' => $randomMessage
        ];
        
        echo "event: notice\n";
        echo "data: " . json_encode($data) . "\n\n";
        flush();
        
        // 整个推送持续 60 秒，每 5 秒推送一次（共 12 次）
        for ($i = 0; $i < 12; $i++) {
            sleep(5); // 等待 5 秒
            
            if ($hasRewards && !empty($rewardTypes)) {
                // 如果有奖励信息，推送奖励消息
                $randomSuffix = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
                $randomUsername = 'guest' . $randomSuffix;
                
                $rewardType = array_rand($rewardTypes);
                $typeInfo = $rewardTypes[$rewardType];
                $amount = rand($typeInfo['min'], $typeInfo['max']);
                
                // 对用户名进行关键信息加密
                $encryptedUsername = $this->encryptUsername($randomUsername);
                
                $message = "🎉 Congratulations! User {$encryptedUsername} won {$currency}{$amount} in {$typeInfo['name']}!";
                
                $data = [
                    'type' => 'reward',
                    'message' => $message
                ];
            } else {
                // 如果没有奖励信息，推送通知消息
                $randomMessage = $notifications[array_rand($notifications)];
                
                $data = [
                    'type' => 'notification',
                    'message' => $randomMessage
                ];
            }
            
            echo "event: notice\n";
            echo "data: " . json_encode($data) . "\n\n";
            flush();
        }

        return response('', 200);
    }

    /**
     * 对用户名进行关键信息加密
     * @param string $username 原始用户名
     * @return string 加密后的用户名
     */
    private function encryptUsername(string $username): string
    {
        // 方法1：部分隐藏 - 保留前4位，中间用*替换，保留后2位
        if (strlen($username) > 6) {
            $prefix = substr($username, 0, 4);
            $suffix = substr($username, -2);
            $middle = str_repeat('*', strlen($username) - 6);
            return $prefix . $middle . $suffix;
        }
        
        // 方法2：如果用户名太短，只保留前2位，其余用*替换
        if (strlen($username) > 2) {
            $prefix = substr($username, 0, 2);
            $suffix = str_repeat('*', strlen($username) - 2);
            return $prefix . $suffix;
        }
        
        // 方法3：如果用户名太短，全部用*替换
        return str_repeat('*', strlen($username));
    }
}