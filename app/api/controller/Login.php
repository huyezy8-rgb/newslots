<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\controller\Api;
use app\common\model\Config;

use app\common\model\SmsVerify;
use ba\GameHelper;
use think\facade\Log;
use think\facade\Request;
use Throwable;

class Login extends Api
{
    protected $noNeedLogin = ["*"];

    public function index()
    {

        if ($this->request->isPost()) {
                // 数据获取和验证
            $data = $this->request->only([
                'channel_name',
                'invite_code',
                'browser_fingerprinting',
                'event_id'
            ]);

            try {
                $validate = new \app\api\validate\Account();
                $validate->scene('reg')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
                            // 渠道信息查找
            $channelInfo = null;
            if (!empty($data["channel_name"])) {
                $channelInfo
                    = \app\common\model\ChannelList::withoutField("create_time,update_time")
                    ->where(["name" => $data["channel_name"]])
                    ->find();
            }

            if (!$channelInfo) {
                $domain = "";
                if(isset($_SERVER['HTTP_REFERER'])) {
                    $referer = $_SERVER['HTTP_REFERER'];
                    if ($referer) {
                        $referer_host = parse_url($referer, PHP_URL_HOST);
                        $domain = $referer_host;
                    }
                }
                if  ($domain) {
                    $channelInfo = \app\common\model\ChannelList::withoutField("create_time,update_time")
                        ->where(["domain" => $domain])
                        ->find();
                }

                if (!$channelInfo) {
                    $channelInfo = \app\common\model\ChannelList::withoutField("create_time,update_time")->order('id', 'asc')->find();
                }
            }

                            // 用户查找
            $account = \app\common\model\Account::where([
                'channel_id' => $channelInfo->id,
                'browser_fingerprinting' => $data["browser_fingerprinting"],
            ])->find();

                            if ($account) {
                    // 老用户事件处理
                    // 游戏注册事件异步处理
                    \think\facade\Queue::push(\app\job\GameRegisterJob::class, [
                        'user_id' => $account->id
                    ], 'game_register');

                    // 其他事件同步处理
                    event('InternalMessage', $account->id);
                    event('DayGold', $account->id);

                    // 用户信息更新
                $account->last_login_time = date("Y-m-d H:i:s", time());
                $account->save();

                                   $response = $this->buildLoginResponse($account, $channelInfo);

                $this->success(__('Success'), $response);
            }

            // 邀请码验证
            if (isset($data["invite_code"])) {
                //判断邀请码是否正确
                $pInfo = \app\common\model\Account::where('invite_code',
                    $data["invite_code"])->find();
                if (!$pInfo) {
                    $this->error(__('Invite code error'));
                }
                // 校验：邀请码必须与当前渠道一致
                if (intval($pInfo['channel_id'] ?? 0) !== intval($channelInfo->id)) {
                    $this->error(__('Invite code error'));
                }
                $data["p_id"] = $pInfo["id"];
            }else{
                $data["rebate_rate"] = get_sys_config("default_rebate_rate")??50;
            }

                            // 新用户数据准备
            $data["nickname"] = "guest".substr(bin2hex(random_bytes(16)), 0, 8);
            $data["token"]       = bin2hex(random_bytes(32 / 2));
            $data["channel_id"]       = $channelInfo->id;
            $data["reg_time"]    = date("Y-m-d H:i:s", time());
            $data["last_login_time"]    = date("Y-m-d H:i:s", time());
            $data["invite_code"] = \app\common\model\Account::getInviteCode();
                // 设置默认值
                $data["vip"] = 0;
                $data["experience_wallet"] = 0;
                $data["recharge_wallet"] = 0;
                $data["switch_wallet"] = 0;
                // 渠道双钱包开关：关闭时默认切换为主钱包
                try {
                    $doubleWalletEnabled = intval($channelInfo["double_wallet_enabled"] ?? 1);
                    if ($doubleWalletEnabled === 0) {
                        $data["switch_wallet"] = 1;
                    }
                } catch (\Throwable $e) {
                    // 忽略异常，保持默认
                }
                $data["sum_recharge"] = 0;
                $data["sum_bet"] = 0;
                $data["ex_withdraw_bet"] = 0;
                $data["withdraw_available"] = 0;
                //广告来源
            if($this->fbclid){
                $data["is_ad_source"] = 1;
                $data["fbclid"] = $this->fbclid;
                //广告来源返佣点位设置
                $data["rebate_rate"] = get_sys_config("ad_rebate_rate")??50;
            }

                            // 新用户注册事务
            $model = new \app\common\model\Account();
            $model->startTrans();
            try {
                    // 用户保存
                $model->save($data);



                                                                           // 新用户事件处理
                    // 游戏注册事件异步处理
                    \think\facade\Queue::push(\app\job\GameRegisterJob::class, [
                        'user_id' => $model->id
                    ], 'game_register');

                    // 其他事件同步处理
                    event('InternalMessage', $model->id);
                    event('DayGold', $model->id);
                    
                    // PDD邀请奖励：如果通过邀请注册，给邀请人增加邀请奖励
                    if (isset($data["p_id"]) && $data["p_id"] > 0) {
                        try {
                            \app\common\service\PddService::handleInviteRegistration($data["p_id"], $model->id);
                        } catch (\Throwable $e) {
                            \think\facade\Log::error("PDD邀请奖励处理失败: {$e->getMessage()}");
                        }
                    }
                \think\facade\Log::info('event_source_url：'.Request::url(true));
                    // Facebook事件异步处理
                $queueResult = \think\facade\Queue::push(\app\job\FacebookConversionJob::class, [
                        'event_data' => [
                            'user_id' => $model->id,
                            'event_id'=> $data["event_id"]??"",
                            'event_type' => 'register',
                            'event_source_url'=>Request::url(true),
                            'custom_data' => [
                                'method' => 'h5',
                                'channel_name' => $channelInfo->name,
                                'invite_code' => $data["invite_code"] ?? null,
                                'p_id' => $data["p_id"] ?? null
                            ],
                            'client_ip' => $this->request->ip(),
                            'client_user_agent' => $this->request->header('user-agent'),
                            'fbc' => $this->fbc,
                            'fbp' => $this->fbp,
                        ]
                    ], 'facebook_conversion');

                \think\facade\Log::info('队列推送结果：' . ($queueResult ? '成功' : '失败'));
                                    // 事务提交

                $model->commit();
            } catch (Throwable $e) {
                $model->rollback();
                $this->error($e->getMessage());
            }


                           $response = $this->buildNewUserResponse($model, $channelInfo);

            $this->success(__('Success'), $response);
        }

        $this->error(__('Unknown operation'));
    }

    /**
     * 构建登录响应数据
     * @param $account
     * @param $channelInfo
     * @param bool $isNewUser 是否为新用户
     * @return array
     */
    private function buildLoginResponse($account, $channelInfo, bool $isNewUser = false)
    {
        // 账户信息获取
        $accountInfoService = new \app\common\service\AccountInfoService();
        $accountDetailInfo = $accountInfoService->getAccountDetailInfo($account, $isNewUser);

        // 渠道信息获取
        $channelInfoService = new \app\common\service\ChannelInfoService();
        $channelDetailInfo = $channelInfoService->getChannelDetailInfo($account, $channelInfo, $isNewUser);

        return [
            "token" => $account["token"],
            "name" => $account["name"],
            "nickname" => $account["nickname"],
            "mobile" => $account["mobile"],
            "vip" => $account["vip"] ?? 0, // 默认为0
            "channel_id" => $account["channel_id"],
            "channel_name" => $channelInfo->name ?? '',
            "invite_code" => $account["invite_code"],
            "experience_wallet" => $account["experience_wallet"] ?? 0, // 默认为0
            "recharge_wallet" => $account["recharge_wallet"] ?? 0, // 默认为0
            "switch_wallet" => $account["switch_wallet"] ?? 0, // 默认为0
            // 整合账户详细信息
            "account_info" => $accountDetailInfo,
            // 整合渠道详细信息
            "channel_info" => $channelDetailInfo,
        ];
    }

    /**
     * 构建新用户响应数据（优化版本，减少数据库查询）
     * @param $account
     * @param $channelInfo
     * @return array
     */
    private function buildNewUserResponse($account, $channelInfo)
    {
        // 账户信息构建
        $accountDetailInfo = [
            "id" => $account->id,
            "channel_id" => $account->channel_id,
            "nickname" => $account->nickname,
            "mobile" => $account->mobile,
            "token" => $account->token,
            "vip" => $account->vip ?? 0, // 新用户默认为0
            "invite_code" => $account->invite_code,
            "experience_wallet" => $account->experience_wallet ?? 0, // 新用户默认为0
            "recharge_wallet" => $account->recharge_wallet ?? 0, // 新用户默认为0
            "switch_wallet" => $account->switch_wallet ?? 0, // 新用户默认为0
            "sum_recharge" => $account->sum_recharge ?? 0, // 新用户默认为0
            "sum_bet" => $account->sum_bet ?? 0, // 新用户默认为0
            "ex_withdraw_bet" => $account->ex_withdraw_bet ?? 0, // 新用户默认为0
            "withdraw_available" => $account->withdraw_available ?? 0, // 新用户默认为0
            "pwa_status" => 0, // 新用户默认为0
            "rescue_funds_received_count" => 0, // 新用户默认为0
        ];

        // 渠道信息构建
        $activity = json_decode($channelInfo["activity"], true) ?? [];

//        // 只处理270活动配置，跳过用户相关的活动过滤
//        $FirstDeposit270 = \app\common\model\activity\FirstDeposit270::where(['id' => 1])->find();
//        if ($FirstDeposit270) {
//            foreach ($activity as $key => $value) {
//                if ($value["key"] == "270") {
//                    $data = [
//                        "key" => "270",
//                        "title" => $FirstDeposit270["title"],
//                    ];
//                }
//            }
//        }

        $channelDetailInfo = $channelInfo->toArray();
        $channelDetailInfo["activity"] = array_values($activity);
        $channelDetailInfo["ex_withdraw_bet_base"] = get_sys_config("ex_withdraw_bet_base");



        return [
            "token" => $account["token"],
            "name" => $account["name"],
            "nickname" => $account["nickname"],
            "mobile" => $account["mobile"],
            "vip" => $account["vip"] ?? 0, // 默认为0
            "channel_id" => $account["channel_id"],
            "channel_name" => $channelInfo->name ?? '',
            "invite_code" => $account["invite_code"],
            "experience_wallet" => $account["experience_wallet"] ?? 0, // 默认为0
            "recharge_wallet" => $account["recharge_wallet"] ?? 0, // 默认为0
            "switch_wallet" => $account["switch_wallet"] ?? 0, // 默认为0
            // 整合账户详细信息
            "account_info" => $accountDetailInfo,
            // 整合渠道详细信息
            "channel_info" => $channelDetailInfo,
        ];
    }

    /**
     * 密码登录
     */
    public function password()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only([
                'mobile',
                'password',
            ]);

            // 验证参数
            if (empty($data['mobile'])) {
                $this->error(__('Mobile cannot be empty')); // 手机号不能为空
            }

            if (empty($data['password'])) {
                $this->error(__('Password cannot be empty')); // 密码不能为空
            }

            // 用户查找
            $account = \app\common\model\Account::where([
                'mobile' => $data["mobile"],
            ])->find();

            if (!$account) {
                $this->error(__('Mobile not exist')); // 手机号不存在
            }

            // 检查密码是否已设置
            if (empty($account['password'])) {
                $this->error(__('Password not set, please use SMS login')); // 密码未设置，请使用短信登录
            }

            // 验证密码
            if (!password_verify($data['password'], $account['password'])) {
                $this->error(__('Password error')); // 密码错误
            }

            // 更新登录时间
            $account->save([
                "last_login_time" => date("Y-m-d H:i:s", time()),
            ]);

            // 渠道信息获取
            $channelInfo = \app\common\model\ChannelList::withoutField("create_time,update_time")
                ->where("id", $account["channel_id"])
                ->find();

            if (!$channelInfo) {
                $this->error(__('Channel not found')); // 渠道不存在
            }

            $response = $this->buildLoginResponse($account, $channelInfo);

            $this->success(__('Login success'), $response); // 登录成功
        }
    }

    /**
     * 忘记密码
     */
    public function forgot_password()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only([
                'mobile',
                'sms_code',
                'new_password',
                'confirm_password',
            ]);

            // 验证参数
            if (empty($data['mobile'])) {
                $this->error(__('Mobile cannot be empty')); // 手机号不能为空
            }

            if (empty($data['new_password'])) {
                $this->error(__('Password cannot be empty')); // 密码不能为空
            }

            if (strlen($data['new_password']) < 6) {
                $this->error(__('Password must be at least 6 characters')); // 密码至少6位
            }

            if ($data['new_password'] !== $data['confirm_password']) {
                $this->error(__('Passwords do not match')); // 两次密码不一致
            }

            // 用户查找
            $account = \app\common\model\Account::where([
                'mobile' => $data["mobile"],
            ])->find();

            if (!$account) {
                $this->error(__('Mobile not exist')); // 手机号不存在
            }

            // 短信验证码验证
            if (config('app.debug')&& $data['sms_code'] == 123456) {
                \think\facade\Log::info("开发环境测试模式 - 跳过验证码: " . json_encode([
                    'mobile' => $this->maskMobile($data["mobile"]),
                    'message' => '开发环境跳过实际短信发送'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }else{
                $verifyInfo = \app\common\model\SmsVerify::where([
                    "mobile" => $data["mobile"],
                    "event"  => "forgot_password",
                    "status" => 0,
                ])->order('id', 'desc')->find();

                if (!$verifyInfo) {
                    $this->error(__('Verification code error')); // 验证码错误
                } else {
                    if ($verifyInfo['code'] != $data['sms_code']) {
                        $this->error(__('Verification code error')); // 验证码错误
                    }
                    if (strtotime($verifyInfo['exp_time']) < time()) {
                        $this->error(__('Verification code expired')); // 验证码已过期
                    }
                }
            }


            \think\facade\Db::startTrans();
            try {
                // 更新密码
                $account->save([
                    'password' => password_hash($data['new_password'], PASSWORD_DEFAULT),
                    'update_time' => date("Y-m-d H:i:s", time()),
                ]);

                // 标记短信验证码已使用
                \app\common\model\SmsVerify::update([
                    "status" => 1,
                ], [
                    "mobile" => $data["mobile"],
                    "event"  => "forgot_password",
                ]);
                \think\facade\Db::commit();

            } catch (\Exception $e) {
                \think\facade\Db::rollback();
                $this->error(__('Password reset failed')); // 密码重置失败
            }

            $this->success(__('Password reset success')); // 密码重置成功
        }
    }

    /**
     * 手机号登陆
     */
    public function mobile()
    {

        if ($this->request->isPost()) {
            // 数据获取
            $data = $this->request->only([
                'mobile',
                'sms_code',
            ]);

            // 用户查找
            $account = \app\common\model\Account::where([
                'mobile' => $data["mobile"],
            ])->find();

            if (!$account) {
                $this->error(__('Mobile not exist'));
            }

            // 短信验证
            if (config('app.debug')&& $data['sms_code'] == 123456) {
                \think\facade\Log::info("开发环境测试模式 - 跳过验证码: " . json_encode([
                    'mobile' => $this->maskMobile($data["mobile"]),
                    'message' => '开发环境跳过实际短信发送'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }else{
                $verifyInfo = \app\common\model\SmsVerify::where([
                    "mobile" => $data["mobile"],
                    "event"  => "login",
                    "status" => 0,
                ])->order('id', 'desc')->find();

                if (!$verifyInfo) {
                    $this->error(__('Verification code error'));
                } else {
                    if ($verifyInfo['code'] != $data['sms_code']) {
                        $this->error(__('Verification code error'));
                    }
                    if (strtotime($verifyInfo['exp_time']) < time()) {
                        $this->error(__('Verification code expired'));
                    }
                }
            }


            // 用户更新和短信状态更新
            $account->save([
                "last_login_time" => date("Y-m-d H:i:s", time()),
            ]);

            \app\common\model\SmsVerify::update([
                "status" => 1,
            ], [
                "mobile" => $data["mobile"],
                "event"  => "login",
            ]);

            // 渠道信息获取
            $channelInfo = \app\common\model\ChannelList::withoutField("create_time,update_time")
                ->where("id", $account["channel_id"])
                ->find();

            $response = $this->buildLoginResponse($account, $channelInfo);

            $this->success(__('Success'), $response);
        }
    }

}