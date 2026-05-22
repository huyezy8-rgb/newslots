<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\controller\Api;
use app\common\model\Config;
use app\common\model\GameLists;
use app\common\model\GameTransactions;
use app\common\model\PddInvitation;
use app\common\model\PddProgress;
use app\common\model\SmsVerify;
use app\common\model\country\Code as CountryCode;
use app\common\service\ChannelInfoService;
use think\facade\Db;
use think\Request;

class Account extends Base
{
    /**
     * 发送短信验证码 (优化版)
     */
    public function send_sms()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only([
                'mobile',
                'event',
            ]);

            // 参数验证
            $this->validateSmsParams($data);

            // 频率限制检查
            $this->checkSmsRateLimit($data['mobile'], $data['event']);

            // 根据事件类型验证业务逻辑
            try {
                $userId = $this->validateSmsEvent($data);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }

            // 生成验证码
            $code = $this->generateSmsCode();

            // 发送短信
            $this->sendSmsMessage($data['mobile'], $code);

            // 保存验证码记录
            $this->saveSmsRecord($userId, $data['mobile'], $data['event'], $code);

            $this->success(__('SMS sent successfully'), [
                'expire_time' => 300, // 5分钟过期
                'message' => __('Verification code sent to your mobile')
            ]);
        }
    }

    /**
     * 验证短信参数
     */
    private function validateSmsParams(array $data): void
    {
        if (empty($data['mobile'])) {
            $this->error(__('Mobile cannot be empty'));
        }

        if (empty($data['event'])) {
            $this->error(__('Event cannot be empty'));
        }

        // 验证手机号格式
        if (!$this->isValidMobile($data['mobile'])) {
            $this->error(__('Invalid mobile format'));
        }

        // 验证事件类型
        $allowedEvents = ['bind', 'login', 'forgot_password'];
        if (!in_array($data['event'], $allowedEvents)) {
            $this->error(__('Invalid event type'));
        }
    }

    /**
     * 验证手机号格式
     */
    private function isValidMobile(string $mobile): bool
    {
        // 基础手机号格式验证 (支持国际格式)
        return preg_match('/^\+?[1-9]\d{1,14}$/', $mobile);
    }

    /**
     * 检查短信发送频率限制
     */
    private function checkSmsRateLimit(string $mobile, string $event): void
    {
        // 检查1分钟内是否已发送过同类型的未使用验证码
        $recentSms = SmsVerify::where('mobile', $mobile)
            ->where('event', $event)
            ->where('status', 0) // 只检查未使用的验证码
            ->where('create_time', '>', time() - 60)
            ->find();

        if ($recentSms) {
            $this->error(__('SMS sent too frequently, please try again later'));
        }

        // 检查一天内同类型发送次数限制
        $todaySmsCount = SmsVerify::where('mobile', $mobile)
            ->where('event', $event)
            ->whereDay('create_time', date('Y-m-d'))
            ->count();

        if ($todaySmsCount >= 10) { // 每天最多10次
            $this->error(__('SMS daily limit reached'));
        }
    }

    /**
     * 根据事件类型验证业务逻辑
     */
    private function validateSmsEvent(array $data): int
    {
        $mobile = $data['mobile'];
        $event = $data['event'];

        switch ($event) {
            case 'bind':
                // 绑定手机号验证
                if ($this->userInfo['mobile']) {
                    throw new \Exception(__('Account already bound mobile'));
                }

                $existingAccount = \app\common\model\Account::where('mobile', $mobile)->find();
                if ($existingAccount) {
                    throw new \Exception(__('Mobile already bound'));
                }

                return $this->userInfo['id'];

            case 'login':
            case 'forgot_password':
                // 登录和忘记密码验证
                $account = \app\common\model\Account::where('mobile', $mobile)->find();
                if (!$account) {
                    throw new \Exception(__('Mobile not exist'));
                }

                return $account['id'];

            default:
                throw new \Exception(__('Invalid event type'));
        }
    }

    /**
     * 生成短信验证码
     */
    private function generateSmsCode(): string
    {
        return (string)mt_rand(100000, 999999);
    }

    /**
     * 发送短信消息
     */
    private function sendSmsMessage(string $mobile, string $code): void
    {
        try {
            // 移除手机号前的加号
            $mobile = ltrim($mobile, '+');
            
            \think\facade\Log::info("准备调用sendSmsPost：".json_encode([
                'mobile' => $this->maskMobile($mobile),
                'code' => $code,
                'function_exists' => function_exists('sendSmsPost')
            ]));
            
            $result = sendSmsPost($mobile, $code);
            
            \think\facade\Log::info("sendSmsPost调用完成：".json_encode([
                'result_type' => gettype($result),
                'result' => $result
            ]));

            // 记录发送请求日志
            \think\facade\Log::info("短信发送请求：".json_encode([
                'mobile' => $this->maskMobile($mobile),
                'code_length' => strlen($code),
                'result' => $result
            ]));

            if (!$result || !isset($result['status'])) {
                \think\facade\Log::error("短信发送返回格式错误: " . json_encode($result));
                $this->error(__('SMS service response error'));
            }

            if ($result['status'] != 0) {
                $errorMsg = $result['message'] ?? $result['msg'] ?? $result['reason'] ?? 'Unknown error';
                \think\facade\Log::error("短信发送失败：".json_encode([
                    'mobile' => $this->maskMobile($mobile),
                    'status' => $result['status'],
                    'error' => $errorMsg,
                    'full_result' => $result
                ]));

                // 根据错误状态码返回更具体的错误信息，不抛出异常
                $this->returnSmsError($result['status'], $errorMsg);
                return; // 直接返回，不继续执行
            }

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage() ?: 'Unknown error';
            $errorCode = $e->getCode() ?: 0;
            $errorFile = $e->getFile() ?: 'unknown';
            $errorLine = $e->getLine() ?: 0;
            
            \think\facade\Log::error("短信发送异常: " . $errorMessage .json_encode([
                'mobile' => $this->maskMobile($mobile),
                'error_code' => $errorCode,
                'file' => $errorFile,
                'line' => $errorLine,
                'trace' => $e->getTraceAsString()
            ]));
            $this->error(__('SMS service unavailable') . ': ' . $errorMessage);
        }
    }

    /**
     * 处理短信发送错误（不抛出异常）
     */
    private function returnSmsError(int $status, string $message): void
    {
        // 根据不同的错误状态码返回不同的错误信息
        switch ($status) {
            case 1001:
                $this->error(__('SMS service parameter error'));
                break;
            case 1002:
                $this->error(__('SMS service authentication failed'));
                break;
            case 1003:
                $this->error(__('SMS service insufficient balance'));
                break;
            case 1004:
                $this->error(__('SMS service rate limit exceeded'));
                break;
            case 1005:
                $this->error(__('Invalid mobile number'));
                break;
            case -9:
                // 特殊处理 CUSER_MCCMNC_LIMIT 错误
                if (strpos($message, 'CUSER_MCCMNC_LIMIT') !== false) {
                    $this->error(__('SMS service does not support this mobile number region'));
                } else {
                    $this->error(__('SMS service error') . ": {$message}");
                }
                break;
            default:
                $errorMsg = __('Send sms error');
                if (config('app.debug')) {
                    $errorMsg .= ": {$message}";
                }
                $this->error($errorMsg);
        }
    }


    /**
     * 标准化手机号：去掉国家代码前缀
     */
    private function normalizeMobile(string $mobile): string
    {
        // 去掉开头的+号
        $mobile = ltrim($mobile, '+');
        
        // 常见国家代码前缀映射
        $countryCodes = [
            '86' => '',      // 中国
            '1' => '',       // 美国/加拿大
            '44' => '',      // 英国
            '33' => '',      // 法国
            '49' => '',      // 德国
            '81' => '',      // 日本
            '82' => '',      // 韩国
            '91' => '',      // 印度
            '61' => '',      // 澳大利亚
            '55' => '',      // 巴西
            '7' => '',       // 俄罗斯
            '39' => '',      // 意大利
            '34' => '',      // 西班牙
            '31' => '',      // 荷兰
            '46' => '',      // 瑞典
            '47' => '',      // 挪威
            '45' => '',      // 丹麦
            '41' => '',      // 瑞士
            '43' => '',      // 奥地利
            '32' => '',      // 比利时
            '351' => '',     // 葡萄牙
            '30' => '',      // 希腊
            '48' => '',      // 波兰
            '420' => '',     // 捷克
            '36' => '',      // 匈牙利
            '40' => '',      // 罗马尼亚
            '359' => '',     // 保加利亚
            '385' => '',     // 克罗地亚
            '386' => '',     // 斯洛文尼亚
            '421' => '',     // 斯洛伐克
            '370' => '',     // 立陶宛
            '371' => '',     // 拉脱维亚
            '372' => '',     // 爱沙尼亚
            '358' => '',     // 芬兰
            '353' => '',     // 爱尔兰
            '352' => '',     // 卢森堡
            '356' => '',     // 马耳他
            '357' => '',     // 塞浦路斯
            '90' => '',      // 土耳其
            '20' => '',      // 埃及
            '27' => '',      // 南非
            '234' => '',     // 尼日利亚
            '254' => '',     // 肯尼亚
            '233' => '',     // 加纳
            '225' => '',     // 科特迪瓦
            '212' => '',     // 摩洛哥
            '213' => '',     // 阿尔及利亚
            '216' => '',     // 突尼斯
            '218' => '',     // 利比亚
            '966' => '',     // 沙特阿拉伯
            '971' => '',     // 阿联酋
            '965' => '',     // 科威特
            '973' => '',     // 巴林
            '974' => '',     // 卡塔尔
            '968' => '',     // 阿曼
            '964' => '',     // 伊拉克
            '98' => '',      // 伊朗
            '93' => '',      // 阿富汗
            '92' => '',      // 巴基斯坦
            '880' => '',     // 孟加拉国
            '94' => '',      // 斯里兰卡
            '977' => '',     // 尼泊尔
            '975' => '',     // 不丹
            '960' => '',     // 马尔代夫
            '95' => '',      // 缅甸
            '66' => '',      // 泰国
            '84' => '',      // 越南
            '855' => '',     // 柬埔寨
            '856' => '',     // 老挝
            '60' => '',      // 马来西亚
            '65' => '',      // 新加坡
            '62' => '',      // 印度尼西亚
            '63' => '',      // 菲律宾
            '673' => '',     // 文莱
            '670' => '',     // 东帝汶
            '886' => '',     // 台湾
            '852' => '',     // 香港
            '853' => '',     // 澳门
        ];
        
        // 检查并去掉国家代码前缀
        foreach ($countryCodes as $code => $replacement) {
            if (strpos($mobile, $code) === 0) {
                $mobile = substr($mobile, strlen($code));
                break;
            }
        }
        
        return $mobile;
    }

    /**
     * 脱敏手机号
     */
    private function maskMobile(string $mobile): string
    {
        if (strlen($mobile) <= 4) {
            return $mobile;
        }
        return substr($mobile, 0, 3) . '****' . substr($mobile, -2);
    }

    /**
     * 保存短信验证码记录
     */
    private function saveSmsRecord(int $userId, string $mobile, string $event, string $code): void
    {
        try {
            $result = SmsVerify::create([
                'user_id' => $userId,
                'mobile' => $mobile,
                'event' => $event,
                'code' => $code,
                'exp_time' => date('Y-m-d H:i:s', time() + 300), // 5分钟过期
                'status' => 0,
                // 移除 create_time，让模型自动处理时间戳
            ]);

            if (!$result) {
                \think\facade\Log::error("保存短信记录失败: 创建记录返回空值", [
                    'user_id' => $userId,
                    'mobile' => $this->maskMobile($mobile),
                    'event' => $event
                ]);
                $this->error(__('Save SMS record failed'));
            }

            \think\facade\Log::info("短信记录保存成功", [
                'user_id' => $userId,
                'mobile' => $this->maskMobile($mobile),
                'event' => $event,
                'record_id' => $result->id ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            \think\facade\Log::error("保存短信记录失败: " . $e->getMessage(), [
                'user_id' => $userId,
                'mobile' => $this->maskMobile($mobile),
                'event' => $event,
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error(__('Save SMS record failed'));
        }
    }

    /**
     * 绑定手机号
     */
    public function bind_mobile()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only([
                'mobile',
                'sms_code',
                'password',
                'confirm_password',
            ]);

            if ($this->userInfo['mobile']) {
                $this->error(__('Account already bound mobile')); // 当前账户已绑定手机号
            }

            // 验证密码
            if (empty($data['password'])) {
                $this->error(__('Password cannot be empty')); // 密码不能为空
            }

            if (strlen($data['password']) < 6) {
                $this->error(__('Password must be at least 6 characters')); // 密码至少6位
            }

            if ($data['password'] !== $data['confirm_password']) {
                $this->error(__('Passwords do not match')); // 两次密码不一致
            }
            
            // 根据验证码开关判断是否需要验证验证码
            $smsSwitch = get_sys_config('is_switch');
            $needVerify = $smsSwitch === true || $smsSwitch === 1 || $smsSwitch === '1';
            
            if ($needVerify) {
                // 验证码开关开启，需要验证验证码
                if (empty($data['sms_code'])) {
                    $this->error(__('Verification code cannot be empty')); // 验证码不能为空
                }
                
                $verifyInfo = SmsVerify::where([
                    "user_id" => $this->userInfo['id'],
                    "mobile"  => $data["mobile"],
                    "event"   => "bind",
                    "status"  => 0,
                ])->order('id', 'desc')->find();
                
                if (!$verifyInfo) {
                    $this->error(__('Verification code error')); // 验证码错误
                }
                
                // 验证验证码是否正确
                if ($verifyInfo['code'] != $data['sms_code']) {
                    $this->error(__('Verification code error')); // 验证码错误
                }
                
                // 验证验证码是否过期
                if (strtotime($verifyInfo['exp_time']) < time()) {
                    $this->error(__('Verification code expired')); // 验证码已过期
                }
            }

            Db::startTrans();
            try {
                // 更新手机号和密码
                // 更新手机号和密码
               $updateData = [
    "mobile" => $data["mobile"],
    "password" => password_hash($data['password'], PASSWORD_DEFAULT),
];

// 如果当前账号没有邀请码/展示ID，就用用户ID生成一个
if (empty($this->userInfo['invite_code'])) {
    $updateData["invite_code"] = (string)$this->userInfo['id'];
}

\app\common\model\Account::update($updateData, [
    "id" => $this->userInfo['id'],
]);
                // 如果验证码开关开启，更新验证码状态
                if ($needVerify) {
                    \app\common\model\SmsVerify::update([
                        "status" => 1,
                    ], [
                        "user_id" => $this->userInfo['id'],
                        "event" => "bind",
                    ]);
                }

                //绑定手机送钱
                $configInfo = \app\admin\model\Config::where("name",
                    "bind_mobile_free")->find();
                $bind_mobile_free  = 0;
                if ($configInfo) {
                    $bind_mobile_free = $configInfo["value"];
                }

                // 根据渠道双钱包开关决定使用哪个钱包
                $walletType = $this->getWalletTypeForReward($this->userInfo);
                
                $coinLog = new \app\common\model\AccountCoinLog();
                $coinLog->UpdateBalance($this->userInfo['id'], CoinLog::BindMobile, $walletType,
                    $bind_mobile_free, CoinLog::getTypeText(CoinLog::BindMobile));



                            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error($e->getMessage());
        }

        // 绑定成功后，重新查询最新用户信息返回给前端
       // 绑定成功后，重新查询最新用户信息返回给前端
$newUserInfo = \app\common\model\Account::where('id', $this->userInfo['id'])->find();

if (!$newUserInfo) {
    $this->error(__('Account not found'));
}

$userData = [
    "id" => $newUserInfo["id"],
    "user_id" => $newUserInfo["id"],
    "uid" => $newUserInfo["id"],
    "account_id" => $newUserInfo["id"],

    "channel_id" => $newUserInfo["channel_id"],
    "nickname" => $newUserInfo["nickname"],
    "mobile" => $newUserInfo["mobile"],
    "token" => $newUserInfo["token"],
    "vip" => $newUserInfo["vip"],
    "invite_code" => $newUserInfo["invite_code"],
    "experience_wallet" => $newUserInfo["experience_wallet"],
    "recharge_wallet" => $newUserInfo["recharge_wallet"],
    "switch_wallet" => $newUserInfo["switch_wallet"],
    "sum_recharge" => $newUserInfo["sum_recharge"],
    "sum_bet" => $newUserInfo["sum_bet"],
    "ex_withdraw_bet" => $newUserInfo["ex_withdraw_bet"],
    "withdraw_available" => $newUserInfo["withdraw_available"],
    "sms_switch" => get_sys_config('is_switch'),
];

$this->success(__('Bind mobile successfully'), [
    // 平铺返回，兼容原来的写法
    "id" => $userData["id"],
    "user_id" => $userData["user_id"],
    "uid" => $userData["uid"],
    "account_id" => $userData["account_id"],
    "channel_id" => $userData["channel_id"],
    "nickname" => $userData["nickname"],
    "mobile" => $userData["mobile"],
    "token" => $userData["token"],
    "vip" => $userData["vip"],
    "invite_code" => $userData["invite_code"],
    "experience_wallet" => $userData["experience_wallet"],
    "recharge_wallet" => $userData["recharge_wallet"],
    "switch_wallet" => $userData["switch_wallet"],
    "sum_recharge" => $userData["sum_recharge"],
    "sum_bet" => $userData["sum_bet"],
    "ex_withdraw_bet" => $userData["ex_withdraw_bet"],
    "withdraw_available" => $userData["withdraw_available"],
    "sms_switch" => $userData["sms_switch"],

    // 嵌套返回，兼容前端可能读取 userInfo/account/user
    "userInfo" => $userData,
    "account" => $userData,
    "user" => $userData,
]);
        }
    }

    /**
     * 获取用户信息
     */
    public function info()
    {
        $Info = \app\common\model\AccountCoinLog::where([
            "user_id" => $this->userInfo->id,
            "log_type_id" => CoinLog::Pwa,
        ])->find();
        $pwa_status = 0;
        if ($Info) {
            $pwa_status = 1;
        }

        $today           = date('Y-m-d');
        $userInfo        = $this->userInfo;
        $rescue_funds_received_count = \app\common\model\RescueFunds::where('user_id',
            $userInfo->id)
            ->whereDay('rescue_date', $today)
            ->count();
        $exWithdrawStageInfo = ChannelInfoService::getExperienceWithdrawStageInfo((int)$this->userInfo->id);
       $this->success("", array_merge([
    "id" => $this->userInfo->id,
    "user_id" => $this->userInfo->id,
    "uid" => $this->userInfo->id,
    "account_id" => $this->userInfo->id,
    "pdd_reward" => $this->userInfo->pdd_reward,

    "channel_id" => $this->userInfo->channel_id,
             "nickname"=> $this->userInfo->nickname,
             "mobile"=>  $this->userInfo->mobile,
             "token"=>  $this->userInfo->token,
             "vip" => $this->userInfo->vip,
             "invite_code" => $this->userInfo->invite_code,
             "experience_wallet" => $this->userInfo->experience_wallet,
             "recharge_wallet" => $this->userInfo->recharge_wallet,
             "switch_wallet" => $this->userInfo->switch_wallet,
             "sum_recharge" => $this->userInfo->sum_recharge,
             "sum_bet" => $this->userInfo->sum_bet,
             "ex_withdraw_bet" => $this->userInfo->ex_withdraw_bet,
             "withdraw_available" => $this->userInfo->withdraw_available,
             "pwa_status" => $pwa_status,
             'sms_switch' => get_sys_config('is_switch'),
             "rescue_funds_received_count" => $rescue_funds_received_count,
            "ex_withdraw_amount"  => ChannelInfoService::getExperienceWithdrawAmount(),
        ], $exWithdrawStageInfo));
    }
    /**
     * 获取用户默认提现账户信息
     */
    public function info_accounts()
    {
        $list = Db::name("withdraw_accounts")->where('user_id', $this->userInfo->id)
            ->order( 'update_time','desc')
            ->find();
        $this->success('', $list);

    }

    /**
     * 获取游戏记录
     */
    public function game_record()
    {
        $data = $this->request->only([
            'size'=> "10",
            'page'=> "1",
        ]);

        $list = GameTransactions::field("id,game_id,req_time,amount,round_id")->where('user_id', $this->userInfo['id'])
            ->where("reason", "bet")
            ->order('id', 'desc')
            ->paginate(["page"=>$data["page"], "list_rows"=>$data["size"]]);

        foreach ($list as $k=>$v){
           $gameInfo =  GameLists::where('game_id', $v->game_id)->find();
            $list[$k]["game_img"] = $gameInfo->icon;
            $list[$k]["game_name_cn"] = $gameInfo->game_name;
            $list[$k]["game_name_en"] = $gameInfo->game_name_en;
        }

        $this->success('', $list);
    }

    /**
     * 获取国家区号列表
     */
    public function country_codes()
    {
        $list = CountryCode::where('status', 1)
            ->order('name_en', 'asc')
            ->field('name,name_en,code,image')
            ->select();

        $this->success('', $list);
    }

    /**
     * 获取用户收益流水
     * @param Request $request
     * @return \think\Response
     */
    public function incomeLog(Request $request)
    {
        if (!$request->isPost()) {
            $this->error(__('Request method must be POST'));
        }

        $userId = $this->userInfo['id'];
        $page = max(1, intval($request->post('page', 1)));
        $pageSize = max(1, min(100, intval($request->post('page_size', 20))));
        $type = $request->post('type', ''); // 收益类型筛选
        $walletType = $request->post('wallet_type', ''); // 钱包类型筛选
        $startDate = $request->post('start_date', '');
        $endDate = $request->post('end_date', '');
        $searchTime = $request->post('search_time', ''); // 快捷时间筛选 0-5

        try {
            // 构建查询条件
            $query = Db::name('account_coin_log')
                ->where('user_id', $userId)
                ->where('num', '>', 0) // 只查询收入记录（正数）
                ->whereNotIn('log_type_id', [CoinLog::CommissionBet]); // 只排除返佣下注，保留返佣提现到余额

            // 按类型筛选
            if (!empty($type)) {
                $typeMap = $this->getIncomeTypeMap();
                if (isset($typeMap[$type])) {
                    $query->whereIn('log_type_id', $typeMap[$type]);
                }
            }

            // 按钱包类型筛选
            if ($walletType !== '') {
                $query->where('wallet_type', intval($walletType));
            }

            // 按日期筛选
            if (!empty($startDate)) {
                $query->where('create_time', '>=', strtotime($startDate));
            }
            if (!empty($endDate)) {
                $query->where('create_time', '<=', strtotime($endDate . ' 23:59:59'));
            }

            // 快捷时间筛选
            if ($searchTime !== '') {
                $timeRange = $this->getTimeRange(intval($searchTime));
                if ($timeRange) {
                    $query->where('create_time', '>=', $timeRange['start']);
                    $query->where('create_time', '<=', $timeRange['end']);
                }
            }

            // 获取总数
            $total = $query->count();

            // 分页查询
            $offset = ($page - 1) * $pageSize;
            $logs = $query->field('id, log_type_id, wallet_type, num, note, create_time')
                ->limit($offset, $pageSize)
                ->order('create_time', 'desc')
                ->select()
                ->toArray();

            // 格式化数据
            foreach ($logs as &$log) {
                $log['amount'] = floatval($log['num']);
                $log['type_text'] = CoinLog::getTypeText($log['log_type_id']);
                $log['type'] = $this->getIncomeTypeByLogType($log['log_type_id']); // 添加英文类型字段
                $log['wallet_type_text'] = $this->getWalletTypeText($log['wallet_type']);
                $log['date'] = date('Y-m-d H:i:s', $log['create_time']);
                
                // 移除不需要的字段
                unset($log['num'], $log['create_time']);
            }

            $data = [
                'list' => $logs,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'pages' => $pageSize > 0 ? ceil($total / $pageSize) : 0
            ];

        } catch (\Exception $e) {
            $this->error(__('Failed to get income log') . ': ' . $e->getMessage());
        }

        $this->success(__('Income log retrieved successfully'), $data);
    }

    /**
     * Get PDD activity/team/chest reward logs.
     * @param Request $request
     * @return \think\Response
     */
    public function pddTeamChestLog(Request $request)
    {
        if (!$request->isPost()) {
            $this->error(__('Request method must be POST'));
        }

        $userId = $this->userInfo['id'];
        $page = max(1, intval($request->post('page', 1)));
        $pageSize = max(1, min(100, intval($request->post('page_size', 20))));
        $type = $request->post('type', '');
        $startDate = $request->post('start_date', '');
        $endDate = $request->post('end_date', '');
        $searchTime = $request->post('search_time', '');

        try {
            $typeMap = $this->getPddTeamChestTypeMap();
            $logTypeIds = [];

            if (!empty($type) && isset($typeMap[$type])) {
                $logTypeIds = $typeMap[$type];
            } else {
                foreach ($typeMap as $types) {
                    $logTypeIds = array_merge($logTypeIds, $types);
                }
                $logTypeIds = array_values(array_unique($logTypeIds));
            }

            $query = Db::name('account_coin_log')
                ->where('user_id', $userId)
                ->where('num', '>', 0)
                ->whereIn('log_type_id', $logTypeIds);

            if (!empty($startDate)) {
                $query->where('create_time', '>=', strtotime($startDate));
            }
            if (!empty($endDate)) {
                $query->where('create_time', '<=', strtotime($endDate . ' 23:59:59'));
            }

            if ($searchTime !== '') {
                $timeRange = $this->getTimeRange(intval($searchTime));
                if ($timeRange) {
                    $query->where('create_time', '>=', $timeRange['start']);
                    $query->where('create_time', '<=', $timeRange['end']);
                }
            }

            $total = $query->count();
            $offset = ($page - 1) * $pageSize;
            $logs = $query->field('id, log_type_id, num, note, create_time')
                ->limit($offset, $pageSize)
                ->order('create_time', 'desc')
                ->select()
                ->toArray();

            foreach ($logs as &$log) {
                $amount = floatval($log['num']);
                $amountText = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

                $log['type'] = $this->getPddTeamChestTypeByLogType($log['log_type_id']);
                $log['type_text'] = CoinLog::getTypeText($log['log_type_id']);
                $log['amount'] = $amount;
                $log['amount_text'] = '$ ' . $amountText;
                $log['time'] = date('Y-m-d H:i:s', $log['create_time']);

                unset($log['num'], $log['create_time']);
            }
            unset($log);

            $data = [
                'list' => $logs,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'pages' => $pageSize > 0 ? ceil($total / $pageSize) : 0,
                'types' => array_keys($typeMap),
            ];
        } catch (\Exception $e) {
            $this->error(__('Failed to get income log') . ': ' . $e->getMessage());
        }

        $this->success(__('Income log retrieved successfully'), $data);
    }

    /**
     * 获取收益类型映射
     * @return array
     */
    private function getIncomeTypeMap(): array
    {
        return [
            'deposit' => [CoinLog::Recharge, CoinLog::FirstDeposit270, CoinLog::FirstDeposit25, CoinLog::FirstDepositDaily, CoinLog::DepositVip, CoinLog::FirstVip49, CoinLog::FirstVip6], // 充值相关
            'game_win' => [CoinLog::GameWin, CoinLog::GameRefund], // 游戏相关
            'activity_reward' => [CoinLog::RegFree, CoinLog::InternalMessage, CoinLog::DayGold, CoinLog::BindMobile, CoinLog::PopUp, CoinLog::Pwa, CoinLog::RescueFunds, CoinLog::RedEnvelope, CoinLog::GameVip375, CoinLog::system, CoinLog::MemberUpgrade, CoinLog::ChestBox, CoinLog::LuckyWheel, CoinLog::MemberWeeklyReward, CoinLog::MemberMonthlyReward], // 活动相关
            'leaderboard_reward' => [CoinLog::LeaderboardDaily, CoinLog::LeaderboardWeekly, CoinLog::LeaderboardMonthly], // 排行榜奖励
            'commission' => [CoinLog::CommissionWithdraw], // 佣金相关 - 只保留返佣提现到余额
            'pdd_reward' => [CoinLog::PDDWithdraw, CoinLog::PDDWithdrawRefund, CoinLog::PDDInitReward, CoinLog::PDDInviteReward, CoinLog::PDDQualifiedFill], // 拼多多相关
            'refund' => [CoinLog::WithdrawRefund, CoinLog::ExWithdrawRefund], // 退款相关
            'system_operation' => [CoinLog::SystemOperation, CoinLog::ExWithdrawBc, CoinLog::ExWithdrawGift], // 系统操作
            'jackpot' => [CoinLog::JackpotWithdraw], // Jackpot相关
        ];
    }

    /**
     * Get PDD activity/team/chest reward type map.
     * @return array
     */
    private function getPddTeamChestTypeMap(): array
    {
        return [
            'pdd_activity' => [CoinLog::PDDWithdraw, CoinLog::PDDWithdrawRefund, CoinLog::PDDInitReward, CoinLog::PDDInviteReward, CoinLog::PDDQualifiedFill],
            'team_reward' => [CoinLog::CommissionBet],
            'chest_reward' => [CoinLog::ChestBox],
        ];
    }

    /**
     * Get PDD activity/team/chest reward type by log type id.
     * @param int $logTypeId
     * @return string
     */
    private function getPddTeamChestTypeByLogType(int $logTypeId): string
    {
        $typeMap = $this->getPddTeamChestTypeMap();

        foreach ($typeMap as $type => $logTypes) {
            if (in_array($logTypeId, $logTypes)) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Get wallet type text.
     * @param int $walletType
     * @return string
     */
    private function getWalletTypeText(int $walletType): string
    {
        return match ($walletType) {
            0 => __('Experience wallet'),
            1 => __('Recharge wallet'),
            2 => __('Commission wallet'),
            3 => __('Pdd wallet'),
            default => __('Unknown wallet'),
        };
    }

    /**
     * 根据log_type_id获取英文类型
     * @param int $logTypeId
     * @return string
     */
    private function getIncomeTypeByLogType(int $logTypeId): string
    {
        $typeMap = $this->getIncomeTypeMap();
        
        foreach ($typeMap as $type => $logTypes) {
            if (in_array($logTypeId, $logTypes)) {
                return $type;
            }
        }
        
        return 'unknown';
    }

    /**
     * 获取时间范围
     * @param int $searchTime 时间筛选类型 0-5
     * @return array|null
     */
    private function getTimeRange(int $searchTime): ?array
    {
        $now = time();
        
        switch ($searchTime) {
            case 0: // 今天
                return [
                    'start' => strtotime('today'),
                    'end' => strtotime('today 23:59:59')
                ];
            case 1: // 昨天
                return [
                    'start' => strtotime('yesterday'),
                    'end' => strtotime('yesterday 23:59:59')
                ];
            case 2: // 本周
                return [
                    'start' => strtotime('monday this week'),
                    'end' => strtotime('sunday this week 23:59:59')
                ];
            case 3: // 上周
                return [
                    'start' => strtotime('monday last week'),
                    'end' => strtotime('sunday last week 23:59:59')
                ];
            case 4: // 本月
                return [
                    'start' => strtotime('first day of this month'),
                    'end' => strtotime('last day of this month 23:59:59')
                ];
            case 5: // 上月
                return [
                    'start' => strtotime('first day of last month'),
                    'end' => strtotime('last day of last month 23:59:59')
                ];
            default:
                return null;
        }
    }

    /**
     * 获取收益类型列表
     * @return \think\Response
     */
    public function incomeTypes()
    {
        $types = [
            ['key' => 'deposit', 'name' => __('Deposit income')],
            ['key' => 'game_win', 'name' => __('Game income')],
            ['key' => 'activity_reward', 'name' => __('Activity income')],
            ['key' => 'leaderboard_reward', 'name' => __('Leaderboard reward')],
            ['key' => 'commission', 'name' => __('Commission income')], // 保留佣金收益选项，但只显示返佣提现到余额
            ['key' => 'pdd_reward', 'name' => __('Pdd income')],
            ['key' => 'refund', 'name' => __('Refund income')],
            ['key' => 'system_operation', 'name' => __('System income')],
            ['key' => 'jackpot', 'name' => __('Jackpot income')],
        ];

        $this->success(__('Income types retrieved successfully'), $types);
    }

    /**
     * 获取时间筛选选项列表
     * @return \think\Response
     */
    public function timeFilterOptions()
    {
        $options = [
            ['key' => 0, 'name' => __('Today')],
            ['key' => 1, 'name' => __('Yesterday')],
            ['key' => 2, 'name' => __('This week')],
            ['key' => 3, 'name' => __('Last week')],
            ['key' => 4, 'name' => __('This month')],
            ['key' => 5, 'name' => __('Last month')],
        ];

        $this->success(__('Time filter options retrieved successfully'), $options);
    }
}
