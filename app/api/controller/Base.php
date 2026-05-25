<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\service\ChannelResolver;
use think\App;

class Base extends Api
{
    public $userInfo;


    protected array $noNeedLogin = [];

    public function __construct(App $app)
    {
        parent::__construct($app);

        $needLogin = !action_in_arr($this->noNeedLogin);
        if ($needLogin) {
            //验证权限
            $token      = get_auth_token(['token']);
            if(!$token){
                $this->error(__('Token cannot be empty'), null, 2); // token不能为空
            }

            $userInfo = \app\common\model\Account::with(['channel' => function($query){
                $query->withoutField('create_time,update_time');
            }])->where('token', $token)->find();
         
            if(!$userInfo){
                $this->error(__('Token error'), null, 2); // token错误
            }
            if ($userInfo->is_black){
                $this->error(__('Token is black'), null, 2);
            }

            $this->userInfo = $userInfo;
            
            // 根据渠道语言设置切换语言
            if (isset($userInfo->channel) && !empty($userInfo->channel['lang'])) {
                $channelLang = $userInfo->channel['lang'];
                $allowLangList = \think\facade\Config::get('lang.allow_lang_list', ['zh-cn', 'en', 'ar']);
                if (in_array($channelLang, $allowLangList)) {
                    $this->app->lang->switchLangSet($channelLang);
                }
            }
        }

        // 对于未登录用户，尝试通过域名获取渠道语言
        if (!isset($this->userInfo) || !$this->userInfo) {
            $this->setLangByDomain();
        }

        if ($this->userInfo && isset($this->userInfo['id'])) {
            // 用户在线状态刷新逻辑
            // 在线状态存储在 Redis，key: user_online:{userId}，有效期5分钟
            // 并将用户ID加入在线用户集合 online_users，集合也设置5分钟过期
            $userId = $this->userInfo['id'];
            $ttl = 300; // 5分钟
            // 记录用户在线时间戳
            \think\facade\Cache::store('redis')->set("user_online:$userId", time(), $ttl);
            // 记录在线用户集合
            \think\facade\Cache::store('redis')->sAdd('online_users', $userId);
            // 设置集合过期时间
            \think\facade\Cache::store('redis')->expire('online_users', $ttl);
        }
    }

    /**
     * 根据域名设置语言
     */
    private function setLangByDomain(): void
    {
        try {
            $channelInfo = ChannelResolver::resolveByRequestDomain($this->request);
            if ($channelInfo && !empty($channelInfo['lang'])) {
                $channelLang = $channelInfo['lang'];
                $allowLangList = \think\facade\Config::get('lang.allow_lang_list', ['zh-cn', 'en', 'ar']);
                if (in_array($channelLang, $allowLangList)) {
                    $this->app->lang->switchLangSet($channelLang);
                }
            }
        } catch (\Exception $e) {
            // 静默失败，不影响主流程
        }
    }

    /**
     * 根据渠道双钱包开关获取奖励应使用的钱包类型
     * 如果关闭双钱包，统一使用充值钱包；否则根据用户当前使用的钱包类型
     * @param array|object $userInfo 用户信息
     * @return int 0=体验钱包, 1=充值钱包
     */
    protected function getWalletTypeForReward($userInfo): int
    {
        try {
            $channelId = is_array($userInfo) ? ($userInfo['channel_id'] ?? 0) : ($userInfo->channel_id ?? 0);
            if ($channelId > 0) {
                $channel = \app\common\model\ChannelList::withoutField('create_time,update_time')
                    ->where('id', $channelId)
                    ->find();
                if ($channel && intval($channel['double_wallet_enabled'] ?? 1) === 0) {
                    // 关闭双钱包，统一使用充值钱包
                    return 1;
                }
            }
        } catch (\Throwable $e) {
            // 异常时使用默认逻辑
        }
        
        // 开启双钱包或获取渠道信息失败时，根据用户当前使用的钱包类型
        $switchWallet = is_array($userInfo) ? ($userInfo['switch_wallet'] ?? 0) : ($userInfo->switch_wallet ?? 0);
        return intval($switchWallet);
    }
}
