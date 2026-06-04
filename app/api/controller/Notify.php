<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\model\activity\DepositVip;
use app\common\model\activity\DepositVipUser;
use app\common\model\activity\FirstDeposit25;
use app\common\model\activity\FirstDeposit25User;
use app\common\model\activity\FirstDeposit270;
use app\common\model\activity\FirstDeposit270User;
use app\common\model\activity\FirstDepositDaily;
use app\common\model\activity\FirstDepositDailyUser;
use app\common\model\ChannelList;
use app\common\model\recharge\Config as rechargeConfigModel;
use app\common\model\recharge\Orders;
use app\common\service\{MessageService, PayGatewayService, AccountService};
use app\common\model\Account;
use app\common\model\Config;
use app\common\model\PddInvitation;
use app\common\model\withdraw\Orders as withdrawOrders;
use app\Request;
use think\Request as ThinkRequest;
use think\facade\{Cache, Db, Log};

class Notify
{
    // 存储事件的队列
    protected $eventQueue = [];
    protected PayGatewayService $payService;
    protected AccountService $accountService;
    protected MessageService $messageService;


    const PAY_SUCCESS = 2;

    protected function getPayService(): PayGatewayService
    {
        return $this->payService ??= new PayGatewayService();
    }

    protected function getAccountService(): AccountService
    {
        return $this->accountService ??= new AccountService();
    }

    protected function getMessageService(): MessageService
    {
        return $this->messageService ??= new MessageService();
    }

    protected function toArraySafe($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return [];
    }

    public function __construct()
    {

        set_timezone(); //设置时区
    }
    /**
     * AMO支付回调
     * @param Request $request
     * @return \think\Response
     */
    public function amopay(Request $request)
    {
        $data = $request->post();

        Log::channel('payment')->info("Amopay回调数据:" . json_encode($data, JSON_UNESCAPED_UNICODE));

//        // 验证签名
        if (!$this->getPayService()->handleNotify($request, 'Amo')) {
            Log::channel('payment')->error("Amopay验签失败, IP: {$request->ip()}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->responseFail('Signature verification failed');
        }

        // 检查关键字段
        $requiredFields = ['orderId', 'merchantReference', 'amount', 'status'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                Log::channel('payment')->error("缺少字段 {$field}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
                return $this->responseFail("Missing field: $field");
            }
        }
        //优先记录回调参数
        if (str_starts_with(strtoupper($data['merchantReference']), 'PAY')) {
            Orders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => time()], ['order_no' => $data['merchantReference']]);
        }else{
            withdrawOrders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'update_time' => time()], ['order_no' => $data['merchantReference']]);
        }
        Db::startTrans();
        try {
            //重新定义参数 防止回调后续不正确
            $data['mchOrderNo']=$data['merchantReference'];
            $data['orderNo']=$data['orderId'];
            if ($data['status'] === 'COMPLETED') {
                $data['state'] = 2;
            }elseif ($data['status'] === 'FAILED') {
                $data['state'] = 3;
            }else{
                return $this->responseFail("未支付成功");
            }
            $data['amount'] = $data['amount']*100;//兼容金额验证
            $data['successTime'] = strtotime($data['paymentTime']);
            $this->processOrder(
                $data['mchOrderNo'],
                $data['orderNo'],
                $data,
                $request
            );

            Db::commit();

            //统一触发事件
            $this->triggerEvents();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::channel('payment')->error("订单处理异常: {$e->getMessage()}, Trace: {$e->getTraceAsString()}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->responseFail($e->getMessage());
        }
        return $this->responseSuccess();
    }
    /**
     * CashApp支付回调处理
     */
    public function cashapp(Request $request)
    {
        $data = $request->post();

        Log::channel('payment')->info("CashApp回调数据:" . json_encode($data, JSON_UNESCAPED_UNICODE));

//        // 验证签名
//            if (!$this->getPayService()->handleNotify($request, 'Succus')) {
//                Log::channel('payment')->error("CashApp验签失败, IP: {$request->ip()}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
//                return $this->responseFail('Signature verification failed');
//            }

        // 检查关键字段
        $requiredFields = ['orderNo', 'mchOrderNo', 'amount', 'state'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                Log::channel('payment')->error("缺少字段 {$field}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
                return $this->responseFail("Missing field: $field");
            }
        }
        //优先记录回调参数
        if (str_starts_with(strtoupper($data['mchOrderNo']), 'PAY')) {
            Orders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => time()], ['order_no' => $data['mchOrderNo']]);
        }else{
            withdrawOrders::update(
                $this->buildWithdrawCallbackUpdateData($data, (string)$data['orderNo']),
                ['order_no' => $data['mchOrderNo']]
            );
        }
        Db::startTrans();
        try {
            $this->processOrder(
                $data['mchOrderNo'],
                $data['orderNo'],
                $data,
                $request
            );

            Db::commit();

            //统一触发事件
            $this->triggerEvents();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::channel('payment')->error("订单处理异常: {$e->getMessage()}, Trace: {$e->getTraceAsString()}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->responseFail($e->getMessage());
        }
        return $this->responseSuccess();
    }

    /**
     * 订单类型分发
     */
    protected function processOrder(string $localOrderNo, string $platformOrderNo, array $data, ThinkRequest $request = null)
    {
        if (str_starts_with(strtoupper($localOrderNo), 'PAY')) {
            $this->handleRecharge($localOrderNo, $platformOrderNo, $data, $request);
        } else {
            $this->handleWithdraw($localOrderNo, $platformOrderNo, $data);
        }
    }

    protected function buildWithdrawCallbackUpdateData(array $data, string $platformOrderNo = ''): array
    {
        $updateData = [
            'callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'update_time' => time(),
        ];

        if ($platformOrderNo !== '') {
            $updateData['platform_order_no'] = $platformOrderNo;
        }

        if (!empty($data['channelOrderNo'])) {
            $updateData['channel_order_no'] = (string)$data['channelOrderNo'];
        }

        return $updateData;
    }

    /**
     * 处理充值订单
     */
    protected function handleRecharge(string $orderNo, string $platformOrderNo, array $data, ThinkRequest $request = null)
    {
        $order = Db::name('recharge_orders')
            ->where('order_no', $orderNo)
            ->lock(true)
            ->find();

        if (!$order) {
            throw new \Exception("Recharge order not found: $orderNo");
        }

        if ($order['pay_status'] == 1 && !empty($order['paid_at'])) {
            Log::channel('payment')->info("订单已处理, orderNo: {$orderNo}");
            return;
        }

        if ($order['pay_status'] == 1) {
            Log::channel('payment')->warning("Recharge order marked paid without paid_at, continue processing. orderNo: {$orderNo}");
        }


        $amount = bcdiv($data['amount'], 100, 2);
        if (bccomp($order['amount'], $amount, 2) !== 0) {
            throw new \Exception(sprintf(
                "Amount mismatch. Expected: %s, Actual: %s",
                $order['amount'],
                $amount
            ));
        }
        // 获取用户信息
        $userInfo = Account::find($order['user_id']);

        // 检查用户是否为当日新增用户
        $today = date('Y-m-d');
        $isTodayNewUser = $userInfo && strpos($userInfo->reg_time, $today) === 0;

        // 检查是否首次付费（通过switch_wallet字段判断）
        $isFirstPurchase = $userInfo && $userInfo->switch_wallet == 0;
        Log::info("用户：{$order['user_id']},是否为新增用户:{$isTodayNewUser},今日日期:{$today},注册日期:{$userInfo->reg_time},是否为首次付费:{$isFirstPurchase}");
        $this->updateRechargeOrder(
            $order['id'],
            $platformOrderNo,
            $data
        );

        if ($data['state'] == self::PAY_SUCCESS) {
            $this->creditUserBalance(
                $order['user_id'],
                $order['reg_amount'],
                $orderNo,
                $platformOrderNo
            );
            
             //修改用户充值累计,充值金额,并非到账金额
            Account::where(['id' => $order['user_id']])->setInc('sum_recharge', $order['amount']);
            //用户升级事件
            $this->addEventToQueue('LevelUp', $order['user_id']);
            
            // PDD邀请奖励检测：检查被邀请人是否满足充值50元条件
            \app\common\service\PddService::handleUserRechargeQualified($order['user_id']);
            
            //充值活动
            $this->handleEvent($order['user_id'], $order);

            //根据客户要求不要求是否为当日新增用户
            $isTodayNewUser = true;

            //获取订单缓存的fbc和fbp
            $fbcCacheKey = 'fbc_order_' . $orderNo;
            $fbpCacheKey = 'fbp_order_' . $orderNo;
            $fbc = Cache::get($fbcCacheKey);
            $fbp = Cache::get($fbpCacheKey);
            // Facebook购物事件（当日新增用户的所有首次付费）
            if ($isTodayNewUser && $isFirstPurchase) {
                $this->addEventToQueue('FacebookConversion', [
                    'user_id' => $order['user_id'],
                    'event_type' => 'purchase',
                    'custom_data' => [
                        'amount' => $order['amount'],
                        'currency' => 'USD',
                        'order_id' => $orderNo,
                        'is_first_purchase' => true,
                        'is_today_new_user' => true,
                        'pay_type' => $order['pay_type'],
                        'event_name' => $order['event_name'] ?? 'normal',
                        'reg_amount' => $order['reg_amount'] ?? $order['amount']
                    ],
                    'client_ip' => $request ? $request->ip() : '',
                    'client_user_agent' => $request ? $request->header('user-agent') : '',
                    'fbc' => $fbc ?? '',
                    'fbp' => $fbp ?? '',
                ]);
            }

            // Facebook加入心愿单事件（当日新增用户的所有付费）
            if ($isTodayNewUser) {
                $this->addEventToQueue('FacebookConversion', [
                    'user_id' => $order['user_id'],
                    'event_type' => 'add_to_wishlist',
                    'custom_data' => [
                        'amount' => $order['amount'],
                        'currency' => 'USD',
                        'order_id' => $orderNo,
                        'pay_type' => $order['pay_type'],
                        'is_today_new_user' => true,
                        'is_first_purchase' => $isFirstPurchase,
                        'event_name' => $order['event_name'] ?? 'normal',
                        'reg_amount' => $order['reg_amount'] ?? $order['amount']
                    ],
                    'client_ip' => $request ? $request->ip() : '',
                    'client_user_agent' => $request ? $request->header('user-agent') : '',
                    'fbc' => $fbc ?? '',
                    'fbp' => $fbp ?? '',
                ]);
            }

            // 有效用户事件
            $this->addEventToQueue('InviteMember', $order['user_id']);
        }
    }

    /**
     * 处理相关活动
     */
    protected function handleEvent(int $user_id, $order)
    {
        $user_info = Db::name('account')
            ->where('id', $user_id)
            ->find();
        if (!$user_info) {
            throw new \Exception("用户未找到");
        }

        if ($user_info['switch_wallet'] == 0) {
            //首充后,修改使用钱包,表示已经充值,删除体验的玩家id
            $user_info['switch_wallet'] = 1;
            $user_info['player_id'] = null;

            Db::name('account')->where('id', $user_id)->update($user_info);
            // 事务成功后重新生成游戏id,先添加到食物列表 事务结束后统一触发
            $this->addEventToQueue('GameRegister', $user_id);
            //修改体验金 ,配置$experience_wallet_reg_num 充值体验金最小额度 大于这个额度不变 小于则补充道这个额度
            $channel_info  = ChannelList::where(['id' => $user_info['channel_id']])->find();
            $experience_wallet_reg_num = $channel_info['experience_gold_limit'] ?? 30;
            
            // 根据渠道双钱包开关决定使用哪个钱包
            $walletType = 0; // 默认体验钱包
            if ($channel_info && intval($channel_info['double_wallet_enabled'] ?? 1) === 0) {
                // 关闭双钱包，使用充值钱包
                $walletType = 1;
            }
            
            if ($walletType === 0 && $user_info['experience_wallet'] < $experience_wallet_reg_num) {
                // 只有使用体验钱包时才检查补充
                // 计算需要补充的差额
                $supplement = $experience_wallet_reg_num - $user_info['experience_wallet'];
                $this->getAccountService()->increaseBalance(
                    userId: $user_id,
                    amount: $supplement,
                    walletType: $walletType,
                    logTypeId: CoinLog::ExWithdrawBc,
                    note: "首充后体验金补充到" . $experience_wallet_reg_num
                );
            } elseif ($walletType === 1) {
                // 关闭双钱包时，补充到充值钱包
                $supplement = $experience_wallet_reg_num;
                $this->getAccountService()->increaseBalance(
                    userId: $user_id,
                    amount: $supplement,
                    walletType: $walletType,
                    logTypeId: CoinLog::ExWithdrawBc,
                    note: "首充后补充到充值钱包（双钱包已关闭）"
                );
            }
        }
        //TODO::充值后根据活动赠送随机金额

        //TODO::充值一定金额修改等级
        $reg_amount = $order['reg_amount'];


        //根据活动进行
        $event_name = $order['event_name'];
        if ($event_name == 'normal') {
            $reg_config = rechargeConfigModel::where(['id' => '1'])->find();
            $reward_strategy = $reg_config['reward_strategy'];
            $reward = 0;
            $reg_config['reward_value'] = $this->toArraySafe($reg_config['reward_value']);
            if ($reward_strategy == 'fixed') {
                $reward = $reg_config['reward_value']['fixed'];
            } elseif ($reward_strategy == 'percent') {
                $reward = bcmul($order['amount'], bcdiv($reg_config['reward_value']['percent'], 100, 2), 2);
            } elseif ($reward_strategy == 'range') {
                $reward = rand($reg_config['reward_value']['min'] * 100, $reg_config['reward_value']['max'] * 100) / 100;
            }
            if ($reward > 0) {
                //发信 第二天可领取 10天后到期
                $this->getMessageService()->send(
                    [
                        'user_id' => $user_id,
                        'channel_id' => $user_info['channel_id'] ?? 0,
                        'type' => 'gift',
                        'title' => "Recharge Bonus", // 充值回馈
                        'content' => "Congratulations! You've earned a recharge bonus of $" . $reward . ". Enjoy your rewards!", // 充值奖励：X元
                        'amount' => $reward,
                        'wallet_type' => 'recharge_wallet',
                        'start_time' => (time() + 86400),
                        'expire_time' => (time() + 86400 * 11),
                        'event_name' => $event_name,

                    ]
                );
            }
        }elseif ($event_name == 'first_vip_6') {
            $reg_config = \app\common\model\activity\FirstVip6::where(['id' => '1'])->find();
            $reward_strategy = $reg_config['reward_strategy'];
            $reward = 0;

            if ($reward_strategy == 'fixed') {
                $reward = $reg_config['reward_value']['fixed'];
            } elseif ($reward_strategy == 'percent') {
                $reward = bcmul($order['amount'], bcdiv($reg_config['reward_value']['percent'], 100, 2), 2);
            } elseif ($reward_strategy == 'range') {
                $reward = rand($reg_config['reward_value']['min'] * 100, $reg_config['reward_value']['max'] * 100) / 100;
            }
            if ($reward > 0) {
                //发信 第二天可领取 10天后到期
                $this->getMessageService()->send(
                    [
                        'user_id' => $user_id,
                        'channel_id' => $user_info['channel_id'] ?? 0,
                        'type' => 'gift',
                        'title' => "Recharge Bonus", // 充值回馈
                        'content' => "Congratulations! You've earned a recharge bonus of $" . $reward . ". Enjoy your rewards!", // 充值奖励：X元
                        'amount' => $reward,
                        'wallet_type' => 'recharge_wallet',
                        'start_time' => (time() + 86400),
                        'expire_time' => (time() + 86400 * 11),
                        'event_name' => $event_name,

                    ]
                );
            }
        }  elseif ($event_name == 'first_deposit_25') {
            //生涯首冲活动
            $reg_config = FirstDeposit25::where(['id' => '1'])->find();
            $reward_strategy = $reg_config['reward_strategy'];


            $reward = 0;

            if ($reward_strategy == 'fixed') {
                $reward = $reg_config['reward_value']['fixed'];
            } elseif ($reward_strategy == 'percent') {
                $reward = bcmul($order['amount'], bcdiv($reg_config['reward_value']['percent'], 100, 2), 2);
            } elseif ($reward_strategy == 'range') {
                $reward = rand($reg_config['reward_value']['min'] * 100, $reg_config['reward_value']['max'] * 100) / 100;
            }

            if ($reward > 0) {
                //发信 第二天可领取 10天后到期
                $this->getMessageService()->send(
                    [
                        'user_id' => $user_id,
                        'channel_id' => $user_info['channel_id'] ?? 0,
                        'type' => 'gift',
                        'title' => "Recharge Bonus", // 充值回馈
                        'content' => "Congratulations! You've earned a recharge bonus of $" . $reward . ". Enjoy your rewards!", // 充值奖励：X元
                        'amount' => $reward,
                        'wallet_type' => 'recharge_wallet',
                        'start_time' => (time() + 86400),
                        'expire_time' => (time() + 86400 * 11),
                        'event_name' => $event_name,

                    ]
                );
            }

            $task = FirstDeposit25User::where(['user_id' => $user_id])->find();
            if (!$task) {
                Log::channel('payment')->info("FirstDeposit25User：未找到该活动，订单有问题, orderNo: {$order['order_no']}");
                throw new \Exception("未找到用户参与过活动，订单有问题");
            }

            // 查找匹配的充值档位
            $amount_list = $reg_config['amount_list'];
            $amounts = array_column($amount_list, 'amount');
            $index = array_search($order['amount'], $amounts);
            $taskStatus = $this->toArraySafe($task->task_status);
            $taskStatus[$index] = 2;
            if (count(array_filter($taskStatus, fn($v) => $v == 2)) == count($taskStatus)) {
                $task->receive_status = 2;
            }
            // 更新 task_status
            $task->task_status = $taskStatus;
            $task->save();

        } elseif (in_array($event_name, ['deposit_vip1', 'deposit_vip2', 'deposit_vip3'])) {
            //vip充值
            $reg_config = DepositVip::where(['id' => '1'])->find();
            $reward_strategy = $reg_config['reward_strategy'];
            $reward = 0;
            if ($reward_strategy == 'fixed') {
                $reward = $reg_config['reward_value']->fixed;
            } elseif ($reward_strategy == 'percent') {
                $reward = bcmul($order['amount'], bcdiv($reg_config['reward_value']->percent, 100, 2), 2);
            } elseif ($reward_strategy == 'range') {
                $reward = rand($reg_config['reward_value']->min * 100, $reg_config['reward_value']->max * 100) / 100;
            }
            if ($reward > 0) {
                //发信 第二天可领取 10天后到期
                $this->getMessageService()->send(
                    [
                        'user_id' => $user_id,
                        'channel_id' => $user_info['channel_id'] ?? 0,
                        'type' => 'gift',
                        'title' => "Recharge Bonus", // 充值回馈
                        'content' => "Congratulations! You've earned a recharge bonus of $" . $reward . ". Enjoy your rewards!", // 充值奖励：X元
                        'amount' => $reward,
                        'wallet_type' => 'recharge_wallet',
                        'start_time' => (time() + 86400),
                        'expire_time' => (time() + 86400 * 11),
                        'event_name' => $event_name,
                    ]
                );
                $level = explode('_', $event_name);
                $current_level = $level[1];
                $taskRecord = new DepositVipUser();
                $amount = $order['amount'];
                $bet_num_max = bcdiv(bcmul($reg_config->$current_level->bet_num_bonus, $amount, 2), 100, 2);
                $bet_test_reward = bcdiv(bcmul($reg_config->$current_level->bet_money_bonus, $amount, 2), 100, 2);
                $data = [
                    'user_id' => $user_id,
                    'channel_id' => $user_info['channel_id'] ?? 0,
                    'level' => $current_level,
                    'amount' => $amount,
                    'bet_num' => 0,
                    'bet_num_base' => 100,
                    'bet_num_reward' => 0,
                    'bet_num_max' => ceil($bet_num_max),
                    'bet_num_status' => 0,
                    'bet_money_sum' => 0,
                    'bet_money_multiple' => 60,
                    'bet_test_reward' => ceil($bet_test_reward),
                    'bet_test_status' => 0,
                    'expire_time' => time() +  7 * 86400, // 默认7天有效期
                    'create_time' => time(),
                    'update_time' => time()
                ];
                $taskRecord->save($data);
            }
        } elseif ($event_name == 'first_deposit_daily') {
            //每日首充
            $reg_config = FirstDepositDaily::where(['id' => '1'])->find();
            $reward_strategy = $reg_config['reward_strategy'];
            $reward = 0;
            if ($reward_strategy == 'fixed') {
                $reward = $reg_config['reward_value']['fixed'];
            } elseif ($reward_strategy == 'percent') {
                $reward = bcmul($order['amount'], bcdiv($reg_config['reward_value']['percent'], 100, 2), 2);
            } elseif ($reward_strategy == 'range') {
                $reward = rand($reg_config['reward_value']['min'] * 100, $reg_config['reward_value']['max'] * 100) / 100;
            }
            if ($reward > 0) {
                //发信 第二天可领取 10天后到期
                $this->getMessageService()->send(
                    [
                        'user_id' => $user_id,
                        'channel_id' => $user_info['channel_id'] ?? 0,
                        'type' => 'gift',
                        'title' => "Recharge Bonus", // 充值回馈
                        'content' => "Congratulations! You've earned a recharge bonus of $" . $reward . ". Enjoy your rewards!", // 充值奖励：X元
                        'amount' => $reward,
                        'wallet_type' => 'recharge_wallet',
                        'start_time' => (time() + 86400),
                        'expire_time' => (time() + 86400 * 11),
                        'event_name' => $event_name,

                    ]
                );
            }
            $task = FirstDepositDailyUser::where(['user_id' => $user_id])->find();
            if (!$task) {
                Log::channel('payment')->info("FirstDepositDaily：未找到该活动，订单有问题, orderNo: {$order['order_no']}");
                throw new \Exception("未找到用户参与过活动，订单有问题");
            }
            // 查找匹配的充值档位
            $amount_list = $reg_config['amount_list'];
            $amounts = array_column($amount_list, 'amount');
            $index = array_search($order['amount'], $amounts);
            $taskStatus = $this->toArraySafe($task->task_status);
            $taskStatus[$index] = 2;
            if (count(array_filter($taskStatus, fn($v) => $v == 2)) == count($taskStatus)) {
                $task->receive_status = 1;
            }
            // 更新 task_status
            $task->task_status = $taskStatus;
            $task->save();
        } elseif ($event_name == 'first_deposit_270') {
            //限时充值活动
            //如果已经参加过
            $record = FirstDeposit270User::where(['user_id' => $user_id])->find();
            //m没参加活动执行活动逻辑
            if (!$record) {
                $reg_config = FirstDeposit270::where(['id' => '1'])->find();

                $reward_strategy = $reg_config['reward_strategy'];
                $reward = 0;
                $reg_config['reward_value'] = $this->toArraySafe($reg_config['reward_value']);
                if ($reward_strategy == 'fixed') {
                    $reward = $reg_config['reward_value']['fixed'];
                } elseif ($reward_strategy == 'percent') {
                    $reward = bcmul($order['amount'], bcdiv($reg_config['reward_value']['percent'], 100, 2), 2);
                } elseif ($reward_strategy == 'range') {
                    $reward = rand($reg_config['reward_value']['min'] * 100, $reg_config['reward_value']['max'] * 100) / 100;
                }

                if ($reward > 0) {
                    //发信 第二天可领取 10天后到期
                    $this->getMessageService()->send(
                        [
                            'user_id' => $user_id,
                            'channel_id' => $user_info['channel_id'] ?? 0,
                            'type' => 'gift',
                            'title' => "Recharge Bonus", // 充值回馈
                            'content' => "Congratulations! You've earned a recharge bonus of $" . $reward . ". Enjoy your rewards!", // 充值奖励：X元
                            'amount' => $reward,
                            'wallet_type' => 'recharge_wallet',
                            'start_time' => (time() + 86400),
                            'expire_time' => (time() + 86400 * 11),
                            'event_name' => $event_name,

                        ]
                    );
                }

                //创建参与任务记录
                $taskRecord = new FirstDeposit270User();
                $day_reward_arr = array_map(
                    function ($percent) use ($order) {
                        $reward = bcdiv(bcmul($percent, $order['amount'], 2), 100, 2);
                        return [
                            'reward' => $reward,
                            'status' => 0,
                        ];
                    },
                    $this->toArraySafe($reg_config['day_reward_percent'])
                );
                $bet_num_max = $reg_config['bet_sum_reward']->max_reward_percent
                    ? bcdiv(bcmul($reg_config['bet_sum_reward']->max_reward_percent, $order['amount'], 2), 100, 2)
                    : 0;
                $bet_test_reward = $reg_config['bet_test_reward']->reward_percent
                    ? bcdiv(bcmul($reg_config['bet_test_reward']->reward_percent, $order['amount'], 2), 100, 2)
                    : 0;
                $data = [
                    'user_id' => $user_id,
                    'amount' => $order['amount'],
                    'channel_id' => $user_info['channel_id'],
                    'day_reward' => json_encode($day_reward_arr),
                    'day_reward_time' => 0, // 初始未领取
                    'bet_num' => 0, // 初始投注次数
                    'bet_num_reward' => 0, // 已领取投注奖励
                    'bet_num_base' =>  $reg_config['bet_sum_reward']->base ?? 100,
                    'bet_num_max' => ceil($bet_num_max),
                    'bet_money_sum' => 0, // 初始累计投注金额
                    'bet_money_multiple' => $reg_config['bet_test_reward']->multiple ?? 60, // 默认3倍
                    'bet_test_reward' => ceil($bet_test_reward),
                    'expire_time' => time() + ($reg_config['task_valid_days'] ?? 7) * 86400, // 默认7天有效期
                    'create_time' => time(),
                    'update_time' => time()
                ];

                $taskRecord->save($data);
            }
        } elseif ($event_name == 'first_vip_49') {
            //VIP首充49.9 赠送30
            $reward = 30;
            if ($reward > 0) {
                //发信 第二天可领取 10天后到期

                //30元 分三个邮件
                $this->getMessageService()->send(
                    [
                        'user_id' => $user_id,
                        'channel_id' => $user_info['channel_id'] ?? 0,
                        'type' => 'gift',
                        'title' => "VIP First Recharge Reward",
                        'content' => "1.VIP First Recharge Reward: " . $reward / 3,
                        'amount' => $reward / 3,
                        'wallet_type' => 'recharge_wallet',
                        'start_time' => (time() + 86400),
                        'expire_time' => (time() + 86400 * 11),
                        'event_name' => $event_name,
                    ]
                );
                $this->getMessageService()->send(
                    [
                        'user_id' => $user_id,
                        'channel_id' => $user_info['channel_id'] ?? 0,
                        'type' => 'gift',
                        'title' => "VIP First Recharge Reward",
                        'content' => "2.VIP First Recharge Reward: " . $reward / 3,
                        'amount' => $reward / 3,
                        'wallet_type' => 'recharge_wallet',
                        'start_time' => (time() + 86400*2),
                        'expire_time' => (time() + 86400 * 11),
                        'event_name' => $event_name,
                    ]
                );
                $this->getMessageService()->send(
                    [
                        'user_id' => $user_id,
                        'channel_id' => $user_info['channel_id'] ?? 0,
                        'type' => 'gift',
                        'title' => "VIP First Recharge Reward",
                        'content' => "3.VIP First Recharge Reward: " . $reward / 3,
                        'amount' => $reward / 3,
                        'wallet_type' => 'recharge_wallet',
                        'start_time' => (time() + 86400*3),
                        'expire_time' => (time() + 86400 * 11),
                        'event_name' => $event_name,
                    ]
                );
            }
        } elseif ($event_name == 'seven_day_card') {
            // 七天卡开通记录
            $config = Db::name('seven_day_card_config')->where('id',1)->find();
            if ($config) {
                // 再次校验：禁止重复开通（容错：如果存在未过期记录则不再插入）
                $exists = Db::name('seven_day_card_user')
                    ->where('user_id', $user_id)
                    ->where('end_time', '>', time())
                    ->order('id','desc')
                    ->find();
                if ($exists) {
                    // 已存在有效七天卡，跳过生成记录
                    return;
                }
                $main = json_decode($config['seven_day_rewards'], true) ?: [];
                $rescue = json_decode($config['rescue_rewards'], true) ?: [];
                $daily = json_decode($config['daily_rewards'], true) ?: [];

                $wrap = function($arr) {
                    $arr = array_values($arr);
                    $out = [];
                    for ($i=0; $i<7; $i++) {
                        $val = isset($arr[$i]) ? (float)$arr[$i] : 0.0;
                        $out[] = ['reward' => $val, 'status' => 0];
                    }
                    return $out;
                };

                // 包装后的奖励结构
                $wrappedMain = $wrap($main);
                $wrappedRescue = $wrap($rescue);
                $wrappedDaily = $wrap($daily);

                $record = [
                    'user_id' => $user_id,
                    'channel_id' => $user_info['channel_id'] ?? 0,
                    'order_no' => $order['order_no'],
                    'amount' => $order['amount'],
                    'start_time' => time(),
                    'end_time' => time() + 7*86400,
                    'reward_main' => json_encode($wrappedMain),
                    'reward_rescue' => json_encode($wrappedRescue),
                    'reward_daily' => json_encode($wrappedDaily),
                    'created_at' => time(),
                    'updated_at' => time(),
                ];
                // 插入记录并获取ID
                $recordId = Db::name('seven_day_card_user')->insertGetId($record);

                // 立即发放第1天的每日奖励（其余6天走定时脚本）
                $firstDailyAmount = isset($wrappedDaily[0]['reward']) ? (float)$wrappedDaily[0]['reward'] : 0.0;
                if ($recordId && $firstDailyAmount > 0) {
                    // 标记第1天为已领取
                    $wrappedDaily[0]['status'] = 1;
                    Db::name('seven_day_card_user')->where('id', $recordId)->update([
                        'reward_daily' => json_encode($wrappedDaily),
                        'updated_at' => time(),
                    ]);

                    // 发放到账户充值钱包
                    $this->getAccountService()->increaseBalance(
                        userId: $user_id,
                        amount: $firstDailyAmount,
                        walletType: 1,
                        logTypeId: CoinLog::SevenDayCard,
                        note: '七天卡每日奖励'
                    );
                }
            }
        }
    }
    /**
     * 更新充值订单状态
     */
    protected function updateRechargeOrder(int $orderId, string $platformOrderNo, array $data)
    {
        $updateData = [
            'pay_status' => $data['state'] == self::PAY_SUCCESS ? 1 : 2,
            'callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'paid_at' => $this->normalizePaidAt($data),
            'updated_at' => time()
        ];
        if ($platformOrderNo !== '') {
            $updateData['platform_order_no'] = $platformOrderNo;
        }
        if (!empty($data['remark'])) {
            $updateData['remark'] = $data['remark'];
        }

        Db::name('recharge_orders')
            ->where('id', $orderId)
            ->update($updateData);
    }

    protected function normalizePaidAt(array $data): ?int
    {
        if ($data['state'] != self::PAY_SUCCESS) {
            return null;
        }

        $successTime = $data['successTime'] ?? time();

        if (is_numeric($successTime)) {
            $timestamp = (int)$successTime;
            return $timestamp > 9999999999 ? (int)floor($timestamp / 1000) : $timestamp;
        }

        $timestamp = strtotime((string)$successTime);
        return $timestamp ?: time();
    }

    /**
     * 管理员手动回调充值订单（复用 handleRecharge 支付成功逻辑）
     */
    public function processManualRecharge(string $orderNo): void
    {
        $order = Db::name('recharge_orders')->where('order_no', $orderNo)->find();
        if (!$order) {
            throw new \Exception("订单不存在: {$orderNo}");
        }
        if ((int)$order['pay_status'] === 1 && !empty($order['paid_at'])) {
            throw new \Exception('订单已支付成功，无需回调');
        }

        $data = [
            'state' => self::PAY_SUCCESS,
            'amount' => (int)bcmul((string)$order['amount'], '100', 0),
            'successTime' => time() * 1000,
            'remark' => '管理员手动回调',
            'manual' => true,
        ];

        Db::startTrans();
        try {
            $this->handleRecharge($orderNo, 'MANUAL_' . time(), $data, null);
            Db::commit();
            $this->triggerEvents();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 用户余额到账
     */
    protected function creditUserBalance(int $userId, float $amount, string $orderNo, string $platformOrderNo)
    {
        try {
            $logTypeId = CoinLog::Recharge;
            $walletType = CoinLog::getWalletType(CoinLog::walletType($logTypeId));

            $this->getAccountService()->increaseBalance(
                userId: $userId,
                amount: $amount,
                walletType: $walletType,
                logTypeId: $logTypeId,
                note: "充值完成 [充值单号:$orderNo]"
            );
        } catch (\Throwable $e) {
            throw new \Exception("Balance update failed: " . $e->getMessage());
        }
    }

    /**
     * 返回成功响应（必须为纯字符串 success，不能有空格和换行）
     */
    protected function responseSuccess(string $message='success'): \think\Response
    {
        return response($message??'success', 200, ['Content-Type' => 'text/plain'], 'html');
    }

    /**
     * 返回失败响应
     */
    protected function responseFail(string $message): \think\Response
    {
        return response($message ?: 'fail', 200);
    }


    /**
     * 提现处理方法（预留，可扩展）
     */
    protected function handleWithdraw(string $orderNo, string $platformOrderNo, array $data)
    {
        Log::channel('payment')->info("进行提现订单回调, orderNo: {$orderNo}");
        $order = withdrawOrders::where('order_no', $orderNo)->find();
        if (!$order) {
            throw new \Exception("Withdraw order not found: {$orderNo}");
        }

        $hasOrderNoUpdate = false;
        if ($platformOrderNo !== '' && (string)($order->platform_order_no ?? '') !== $platformOrderNo) {
            $order->platform_order_no = $platformOrderNo;
            $hasOrderNoUpdate = true;
        }
        if (!empty($data['channelOrderNo']) && (string)($order->channel_order_no ?? '') !== (string)$data['channelOrderNo']) {
            $order->channel_order_no = (string)$data['channelOrderNo'];
            $hasOrderNoUpdate = true;
        }

        if (in_array($order['status'], [2,3,4])) {
            if ($hasOrderNoUpdate) {
                $order->save();
            }
            Log::channel('payment')->info("提现订单已处理, orderNo: {$orderNo}");
            return;
        }
        if ($data['state'] == self::PAY_SUCCESS) {
            $order->status = 2;
            if (!$order->save()) {
                Log::channel('payment')->info("提现订单修改状态错误, orderNo: {$orderNo}");
                throw new \Exception("提现订单修改状态错误, orderNo: {$orderNo}");
            }
            return;
        }

        if ($data['state'] == 3) {
            // 第三方代付失败，直接走驳回并退回用户余额
            $order->status = 3;

            if (!$order->save()) {
                Log::channel('payment')->info("提现订单修改状态错误, orderNo: {$orderNo}");
                throw new \Exception("提现订单修改状态错误, orderNo: {$orderNo}");
            }

            if ($order->wallet_type == 'recharge_wallet') {
                // 退回充值钱包余额
                (new \app\common\service\AccountService())->increaseBalance(
                    userId: $order->user_id,
                    amount: $order->amount,
                    walletType: 1,
                    logTypeId: \app\api\enum\CoinLog::WithdrawRefund,
                    note: \app\api\enum\CoinLog::getTypeText(\app\api\enum\CoinLog::WithdrawRefund)
                );

                // 重算可提现余额
                $dmlService = new \app\common\service\DmlService();
                $userBalance = Db::name('account')->where('id', $order->user_id)->value('recharge_wallet');
                $dmlService->recalculateWithdrawAvailable($order->user_id, $userBalance);
            }
            return;
        }

        if ($hasOrderNoUpdate) {
            $order->save();
        }

    }

    /**
     * 将事件添加到队列
     */
    protected function addEventToQueue($event, $data)
    {
        $this->eventQueue[] = ['event' => $event, 'data' => $data];
    }
    /**
     * FiatPay支付回调处理
     */
    public function fiatpay(Request $request)
    {
        $data = $request->post();

        Log::channel('payment')->info("FiatPay回调数据:" . json_encode($data, JSON_UNESCAPED_UNICODE));

        // 验证签名
        // if (!$this->getPayService()->handleNotify($request, 'Fiat')) {
        //     Log::channel('payment')->error("FiatPay验签失败, IP: {$request->ip()}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        //     return $this->responseFail('Signature verification failed');
        // }

        // 检查关键字段
        $requiredFields = ['txid', 'type', 'customer_uid', 'status'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                Log::channel('payment')->error("缺少字段 {$field}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
                return $this->responseFail("Missing field: $field");
            }
        }

        // 优先记录回调参数
        if ($data['type'] === 'deposit') {
            Orders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => time()], ['order_no' => $data['txid']]);
        } else {
            withdrawOrders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'update_time' => time()], ['order_no' => $data['txid']]);
        }

        Db::startTrans();
        try {
            // 转换FiatPay数据格式为统一格式
            $convertedData = $this->convertFiatPayData($data);

            $this->processOrder(
                $data['txid'],  // 本地订单号
                $data['txid'],  // 平台订单号 (FiatPay使用同一个txid)
                $convertedData,
                $request
            );

            Db::commit();

            // 统一触发事件
            $this->triggerEvents();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::channel('payment')->error("FiatPay订单处理异常: {$e->getMessage()}, Trace: {$e->getTraceAsString()}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->responseFail($e->getMessage());
        }

        return $this->responseSuccess('true'); // FiatPay要求返回"true"
    }
    
    //SaxPay 回调
    public function saxpay(Request $request){
        file_put_contents("/www/wwwroot/admincs.tapmc.net/.idea/notify.txt",json_encode($_POST));
        $data = $request->post();
        if(empty($data)) return;
        Log::channel('payment')->info("FiatPay回调数据:" . json_encode($data, JSON_UNESCAPED_UNICODE));
        ksort($data);
        $nsign=$data['sign'];
        unset($data['sign']);
        $temp='';
        foreach ($data as $k=>$v){
            if($v != ''){
                $temp.="{$k}={$v}&";
            }
        }
        $temp.="key=7c775a2fabbd457a9bfc5c3addee486f";
        $sign = md5($temp);
        if($sign == $nsign){
            Orders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => time()], ['order_no' => $data['orderNo']]);
            
            $convertedData = array(
                'state'=> $data['statePayd'] == 1?2:3,
                'mchOrderNo'=>$data['orderNo'],
                'orderNo'=>$data['orderNo'],
                'amount'=>bcmul($data['amountPayd'], '100', 0),
                'successTime'=>time()*1000
            );

            $this->processOrder(
                $data['orderNo'],  // 本地订单号
                $data['uid'],  // 平台订单号 (FiatPay使用同一个txid)
                $convertedData,
                $request
            );
            
            echo 'ok';
        }
    }
    
     //SaxPay 回调
    public function ouspay(Request $request){
        //file_put_contents("/www/wwwroot/admincs.tapmc.net/.idea/notify.txt",json_encode($_POST));
        $data = $request->post();
        if(empty($data)) return;
        Log::channel('payment')->info("OusPay回调数据:" . json_encode($data, JSON_UNESCAPED_UNICODE));
    
        if($data['returncode'] == '00'){
            Orders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => time()], ['order_no' => $data['orderid']]);
            
            $convertedData = array(
                'state'=> 2,
                'mchOrderNo'=>$data['transaction_id'],
                'orderNo'=>$data['orderid'],
                'amount'=>bcmul($data['amount'], '100', 0),
                'successTime'=>time()*1000
            );

            $this->processOrder(
                $data['orderid'],  // 本地订单号
                $data['transaction_id'],  // 平台订单号 (FiatPay使用同一个txid)
                $convertedData,
                $request
            );
            
            echo 'ok';
        }
    }
    
    //SaxPay 代付回调
    public function ouspay_daifu(Request $request){
        //file_put_contents("/www/wwwroot/admincs.tapmc.net/.idea/notify.txt",json_encode($_POST));
        $data = $request->post();
        if(empty($data)) return;
        Log::channel('payment')->info("OusPay回调数据:" . json_encode($data, JSON_UNESCAPED_UNICODE));

        if($data['returncode'] == '1' || $data['returncode'] == '2'){
            Orders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => time()], ['order_no' => $data['orderid']]);
            
            $convertedData = array(
                'state'=> $data['returncode'] == '1' ? 2 : 3,
                'mchOrderNo'=>$data['transaction_id'],
                'orderNo'=>$data['orderid'],
                'amount'=>bcmul($data['amount'], '100', 0),
                'successTime'=>time()*1000
            );

            $this->processOrder(
                $data['orderid'],  // 本地订单号
                $data['transaction_id'],  // 平台订单号 (FiatPay使用同一个txid)
                $convertedData,
                $request
            );
            
            echo 'ok';
        }
    }


    /**
     * 转换FiatPay回调数据为统一格式
     */
    protected function convertFiatPayData(array $data): array
    {
        $converted = $data;

        // 转换状态
        switch ($data['status']) {
            case 'completed':
                $converted['state'] = 2; // 成功
                break;
            case 'rejected':
            case 'expired':
                $converted['state'] = 3; // 失败
                break;
            default:
                $converted['state'] = 1; // 处理中
        }

        // 设置订单号
        $converted['mchOrderNo'] = $data['txid'];
        $converted['orderNo'] = $data['txid'];

        // 转换金额 (FiatPay返回的是实际金额，需要转换为分)
        if (isset($data['actual_amount'])) {
            $converted['amount'] = bcmul($data['actual_amount'], '100', 0);
        } elseif (isset($data['request_amount'])) {
            $converted['amount'] = bcmul($data['request_amount'], '100', 0);
        }

        // 设置成功时间
        $converted['successTime'] = time()*1000;

        return $converted;
    }

    /**
     * 统一触发所有事件
     */
    protected function triggerEvents()
    {
        foreach ($this->eventQueue as $event) {
            // 使用事件触发器统一触发
            event($event['event'], $event['data']);
        }

        // 触发完事件后，清空队列
        $this->eventQueue = [];
    }

    public function succuspay(Request $request)
    {
        $data = $request->post();

        file_put_contents(runtime_path() . 'succuspay_notify.txt',
            date('Y-m-d H:i:s') . PHP_EOL .
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL,
            FILE_APPEND
        );

        $requiredFields = ['mchOrderNo', 'state', 'amount'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                Log::channel('payment')->error("SuccusPay missing field {$field}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
                return $this->responseFail("Missing field: $field");
            }
        }

        $localOrderNo = (string)$data['mchOrderNo'];
        $platformOrderNo = (string)($data['payOrderNo'] ?? $data['orderNo'] ?? $data['platformOrderNo'] ?? $localOrderNo);
        $data['state'] = (int)$data['state'];
        $data['amount'] = (string)$data['amount'];
        $data['successTime'] = $data['successTime'] ?? $data['paidTime'] ?? $data['payTime'] ?? (time() * 1000);

        if (str_starts_with(strtoupper($localOrderNo), 'PAY')) {
            $orderAmount = Db::name('recharge_orders')->where('order_no', $localOrderNo)->value('amount');
            if ($orderAmount !== null && bccomp((string)$orderAmount, bcdiv($data['amount'], '100', 2), 2) !== 0 && bccomp((string)$orderAmount, $data['amount'], 2) === 0) {
                $data['amount'] = bcmul($data['amount'], '100', 0);
            }

            Orders::update(
                ['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => time()],
                ['order_no' => $localOrderNo]
            );
        } else {
            withdrawOrders::update(
                $this->buildWithdrawCallbackUpdateData($data, $platformOrderNo),
                ['order_no' => $localOrderNo]
            );
        }

        Db::startTrans();
        try {
            $this->processOrder(
                $localOrderNo,
                $platformOrderNo,
                $data,
                $request
            );

            Db::commit();
            $this->triggerEvents();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::channel('payment')->error("SuccusPay order process error: {$e->getMessage()}, Trace: {$e->getTraceAsString()}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->responseFail($e->getMessage());
        }

        return $this->responseSuccess();
    }
}
