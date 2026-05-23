<?php

namespace app\api\controller;


use app\common\model\activity\DepositVipUser;
use app\common\model\activity\FirstDeposit25User;
use app\common\model\activity\FirstDepositDaily;
use app\common\model\activity\FirstDepositDailyUser;
use app\common\model\api\Log;
use app\common\model\ChannelList;
use app\common\model\recharge\Config;
use app\common\model\recharge\Orders;
use app\common\service\PayGatewayService;
use app\common\service\ChannelInfoService;
use app\common\service\TestPaymentCallbackService;
use app\Request;
use ba\Exception;
use ba\PaymentHelper;
use think\App;
use think\facade\Cache;
use think\facade\Db;

class Recharge extends Base
{
    protected PayGatewayService $payService;

    // 所有启用的活动标识
    protected array $activeNames = [
        'first_deposit_daily',
        'first_deposit_25',
        'first_vip_49',     //vip 49.9赠送30现金
        'seven_day_card',   // 七天卡活动
        'first_vip_6',
        'first_deposit_270',
        'deposit_vip1',
        'deposit_vip2',
        'deposit_vip3',
        'normal',
    ];

    protected array $noNeedLogin = [];
    // 构造函数需要接收 App $app，然后传给 Base
    public function __construct(App $app)
    {
        parent::__construct($app);
    }
    protected function getPayService(): PayGatewayService
    {
        return $this->payService ??= new PayGatewayService();
    }

    /**
     * 将下单事件名映射到渠道 activity 的 key
     */
    private function mapEventToActivityKey(string $eventName): string
    {
        // deposit_vip1/2/3 归一到 deposit_vip
        if (in_array($eventName, ['deposit_vip1', 'deposit_vip2', 'deposit_vip3'], true)) {
            return 'deposit_vip';
        }
        return $eventName;
    }


    private function isTestPay(string $payType): bool
    {
        return strtolower($payType) === 'testpay';
    }

    private function applyPaymentChannelReward(array $config, string $payType, float $price, $regAmount)
    {
        if ($this->isTestPay($payType)) {
            return $regAmount;
        }

        $payChannels = json_decode($config['pay_channels'] ?? '[]', true) ?: [];
        $channels = array_column($payChannels, 'channel');
        $result = array_filter($channels, fn($item) => strcasecmp($item, $payType) === 0);
        $index = array_key_first($result);

        if ($index === null) {
            $this->error(__('Payment method param error'));
        }

        $rewardPercent = floatval($payChannels[$index]['reward_percent'] ?? 0);
        return bcadd($regAmount, bcmul($price, $rewardPercent / 100, 2), 2);
    }

    private function buildTestPayCashierUrl(string $orderNo, float $amount, int $expiredAt, string $returnUrl): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl = $host ? "{$scheme}://{$host}" : '';
        $cashierPath = root_path('public') . 'testpay' . DIRECTORY_SEPARATOR . 'cashier.html';

        return $baseUrl . '/testpay/cashier.html?' . http_build_query([
            'v' => is_file($cashierPath) ? filemtime($cashierPath) : time(),
            'order_no' => $orderNo,
            'amount' => number_format($amount, 2, '.', ''),
            'expired_at' => $expiredAt,
            'token' => $this->userInfo['token'] ?? '',
            'return_url' => $returnUrl,
        ]);
    }

    private function buildRechargeReturnUrl(string $channelName): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? 'https://h5cs.tapmc.net/';
        $parts = parse_url($referer);

        if (!is_array($parts) || empty($parts['host'])) {
            $parts = parse_url('https://h5cs.tapmc.net/');
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'h5cs.tapmc.net';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $origin = "{$scheme}://{$host}{$port}";

        $channelSegment = rawurlencode($channelName);
        $pathSegments = array_values(array_filter(explode('/', trim((string)($parts['path'] ?? ''), '/')), 'strlen'));
        $returnSegments = [$channelSegment];

        foreach ($pathSegments as $index => $segment) {
            if (urldecode($segment) === $channelName) {
                $returnSegments = array_slice($pathSegments, 0, $index + 1);
                break;
            }
        }

        return rtrim($origin, '/') . '/' . implode('/', $returnSegments) . '/#/pages/view/payment/paySuccess';
    }

    public function index()
    {
        // 读取唯一配置，id=1
        $config = Db::name('recharge_config')->where('id', 1)->find();

        if (!$config) {
            $this->error(__('Recharge config not exist'));
        }

        // JSON字段转成数组方便前端处理（可选）
        $config['amount_list'] = json_decode($config['amount_list'], true) ?: [];
        $config['pay_channels'] = json_decode($config['pay_channels'], true) ?: [];
        $config['reward_value'] = json_decode($config['reward_value'], true) ?: [];

        // 过滤可用支付方式
        $userId = $this->userInfo['id'] ?? 0;
        $service = new \app\common\service\PayGatewayService();
        $config['pay_channels'] = $service->getAvailablePayChannels($userId, $config['pay_channels']);

        // 返回成功响应
        $this->success(__('Get recharge config success'), $config);
    }

    /**
     * 检查未支付并关闭订单 接口未实现
     * @return void
     */
    private function closeOrder()
    {
        $orderlist = Orders::where([
            'user_id' => $this->userInfo['user_id'],
            'pay_status' => 0
        ])->select();  // 修改为select()获取多条记录

        if (!$orderlist->isEmpty()) {  // 修改判断方式
            Db::startTrans();
            try {
                foreach ($orderlist as $order) {
                    $res = $this->getPayService()->closeOrder([
                        'order_no' => $order['order_no']
                    ]);

                    // 更严谨的错误判断
                    if (!$res || (isset($res['success']) && !$res['success'])) {
                        throw new \Exception($res['msg'] ?? '关闭支付订单失败');
                    }

                    Orders::where(['order_no' => $order['order_no']])
                        ->update(['pay_status' => 2]);
                }
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                // 保持原有错误处理方式
                $this->error(__('Close order failed'), $e->getMessage());
            }
        }
    }
    /**
     * @return void
     */
    public function create(Request $request)
    {
        $param = $request->param();
        $price = isset($param['price']) ? floatval($param['price']) : floatval($param['price']);

        $payType = $param['pay_type'];
        $event_name = $param['event_name'];
        //fiat_支付银行参数
        $bank_code = $param['bank_code'] ?? "";
        $depositor_name = $param['depositor_name'] ?? "";


        if (!in_array($event_name, $this->activeNames)) {
            $this->error(__('Please input recharge activity')); // 请输入充值活动
        }

        // 校验该渠道是否开启本次充值所选活动
        try {
            ChannelInfoService::assertChannelActivityEnabled($this->userInfo, $this->mapEventToActivityKey($event_name));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        //初始化充值到账金额
        $reg_amount = $price;

        //检查是否有未支付订单
        //        $this->closeOrder();
        //活动传递参数
        $event_data = [];
        if ($event_name == 'normal') {
            //常规充值
            // 获取充值配置
            $reg_config = Db::name('recharge_config')->where('id', 1)->find();
            if (!$reg_config) {
                $this->error(__('Recharge config not exist')); // 充值配置不存在
            }
            $amount_list = json_decode($reg_config['amount_list'], true);
            $price = floatval($price); // 确保金额为浮点数
            // 查找匹配的充值档位
            $amounts = array_column($amount_list, 'amount');
            $index = array_search($price, $amounts);

            if ($index !== false) {
                // 如果找到匹配档位，计算奖励金额
                $rewardPercent = floatval($amount_list[$index]['reward_percent'] ?? 0);
                $reg_amount = bcadd($price, bcmul($price, $rewardPercent / 100, 2), 2);
            } else {
                //$this->error(__('Recharge amount error')); // 充值金额错误
            }
            //支付方式补贴
            if ($payType) {
                $reg_amount = $this->applyPaymentChannelReward($reg_config, $payType, $price, $reg_amount);
            } else {
                $this->error(__('Please select payment method')); // 请选择支付方式
            }
        } elseif ($event_name == 'first_vip_6') {
            //6%vip充值
            if (!$this->userInfo['vip'] > 0)   $this->error('You are not a VIP');
            //是否已充值该活动
            if (Db::name('recharge_orders')->where('user_id', $this->userInfo['id'])->where('event_name', $event_name)->where('pay_status', 1)->value('id')) {
                $this->error('This activity can only be participated in once.');
            }
            // 获取充值配置
            $reg_config = Db::name('activity_first_vip_6')->where('id', 1)->find();
            if (!$reg_config) {
                $this->error(__('Recharge config not exist')); // 充值配置不存在
            }
            $amount_list = json_decode($reg_config['amount_list'], true);
            $price = floatval($price); // 确保金额为浮点数
            // 查找匹配的充值档位
            $amounts = array_column($amount_list, 'amount');
            $index = array_search($price, $amounts);

            if ($index !== false) {
                // 如果找到匹配档位，计算奖励金额
                $rewardPercent = floatval($amount_list[$index]['reward_percent'] ?? 0);
                $reg_amount = bcadd($price, bcmul($price, $rewardPercent / 100, 2), 2);
            } else {
                //$this->error(__('Recharge amount error')); // 充值金额错误
            }
            //支付方式补贴
            if ($payType) {
                $reg_amount = $this->applyPaymentChannelReward($reg_config, $payType, $price, $reg_amount);
            } else {
                $this->error(__('Please select payment method')); // 请选择支付方式
            }
        } elseif (in_array($event_name, ['deposit_vip1', 'deposit_vip2', 'deposit_vip3'])) {
            //每日首充活动
            //TODO::增加缓存防止重复参加活动
            $level = explode('_', $event_name);
            $current_level = $level[1];
            //根据充值记录判断是否充值
            $activity_order = Orders::where(['user_id' => $this->userInfo['id'], 'event_name' => $event_name])
                ->whereIn('pay_status', [1])
                ->find();
            if ($activity_order) {
                $this->error(__('This level already recharged')); // 该档位已充值
            }
            //根据参与活动记录，与充值记录冲突
            //            $task = DepositVipUser::where(['user_id'=>$param['user_id'],'level'=>$current_level])->find();
            //            if ($task) {
            //                $this->error('该档位已充值');
            //            }
            // 判断是否可以解锁当前等级
            $level_num = intval(substr($current_level, 3)); // 提取数字，比如 vip2 => 2

            if ($level_num > 1) {
                $prev_level = 'vip' . ($level_num - 1);

                $prev_task = DepositVipUser::where([
                    'user_id' => $this->userInfo['id'],
                    'level'   => $prev_level,
                ])->find();

                if (!$prev_task) {
                    $this->error(__('Please complete previous level activity') . ":{$prev_level}"); // 请先完成上一等级活动
                }
            }
            // 获取充值配置
            $reg_config = Db::name('activity_deposit_vip')->where('id', 1)->find();
            if (!$reg_config) {
                $this->error(__('Recharge config not exist')); // 充值配置不存在
            }
            $amount_list = json_decode($reg_config['amount_list'], true);
            $price = floatval($price); // 确保金额为浮点数
            // 查找匹配的充值档位
            $amounts = array_column($amount_list, 'amount');
            $index = array_search($price, $amounts);

            if ($index !== false) {
                // 如果找到匹配档位，计算奖励金额
                $rewardPercent = floatval($amount_list[$index]['reward_percent'] ?? 0);
                $reg_amount = bcadd($price, bcmul($price, $rewardPercent / 100, 2), 2);
            } else {
                //$this->error(__('Recharge amount error')); // 充值金额错误
            }
            //支付方式补贴
            if ($payType) {
                $reg_amount = $this->applyPaymentChannelReward($reg_config, $payType, $price, $reg_amount);
            } else {
                $this->error(__('Please select payment method')); // 请选择支付方式
            }
        } elseif ($event_name == 'first_deposit_daily') {
            //每日首充活动
            //TODO::增加缓存防止重复参加活动
            $task = FirstDepositDailyUser::where('user_id', $this->userInfo['id'])->find();
            if (!$task) {
                $this->error(__('Activity participation failed, please notify admin to check')); // 参与活动失败,请通知管理员检查
            }
            $task_status = get_object_vars($task->task_status);
            // 获取充值配置
            $reg_config = Db::name('activity_first_deposit_daily')->where('id', 1)->find();
            if (!$reg_config) {
                $this->error(__('Recharge config not exist')); // 充值配置不存在
            }
            $amount_list = json_decode($reg_config['amount_list'], true);
            $price = floatval($price); // 确保金额为浮点数
            // 查找匹配的充值档位
            $amounts = array_column($amount_list, 'amount');
            $index = array_search($price, $amounts);
            if ($task_status[$index] == 2) {
                $this->error(__('This level today already recharged')); // 该档位今日已充值
            } else {
                $task_status[$index] = 1;
                $event_data['task_status'] = $task_status;
            }
            if ($index !== false) {
                // 如果找到匹配档位，计算奖励金额
                $rewardPercent = floatval($amount_list[$index]['reward_percent'] ?? 0);
                $reg_amount = bcadd($price, bcmul($price, $rewardPercent / 100, 2), 2);
            } else {
                //$this->error(__('Recharge amount error')); // 充值金额错误
            }
            //支付方式补贴
            if ($payType) {
                $reg_amount = $this->applyPaymentChannelReward($reg_config, $payType, $price, $reg_amount);
            } else {
                $this->error(__('Please select payment method')); // 请选择支付方式
            }
        } elseif ($event_name == 'first_deposit_25') {
            //生涯首充
            //TODO::增加缓存防止重复参加活动
            $task = FirstDeposit25User::where('user_id', $this->userInfo['id'])->find();
            if (!$task) {
                $this->error(__('Activity participation failed, please notify admin to check')); // 参与活动失败,请通知管理员检查
            }
            $task_status = get_object_vars($task->task_status);
            // 获取充值配置
            $reg_config = Db::name('activity_first_deposit_25')->where('id', 1)->find();
            if (!$reg_config) {
                $this->error(__('Recharge config not exist')); // 充值配置不存在
            }
            $amount_list = json_decode($reg_config['amount_list'], true);
            $price = floatval($price); // 确保金额为浮点数
            // 查找匹配的充值档位
            $amounts = array_column($amount_list, 'amount');
            $index = array_search($price, $amounts);
            if ($task_status[$index] == 2) {
                $this->error(__('This level today already recharged')); // 该档位今日已充值
            } else {
                $task_status[$index] = 1;
                $event_data['task_status'] = $task_status;
            }
            if ($index !== false) {
                // 如果找到匹配档位，计算奖励金额
                $rewardPercent = floatval($amount_list[$index]['reward_percent'] ?? 0);
                $reg_amount = bcadd($price, bcmul($price, $rewardPercent / 100, 2), 2);
            } else {
                $this->error(__('Recharge amount error')); // 充值金额错误
            }
            //支付方式补贴
            if ($payType) {
                $reg_amount = $this->applyPaymentChannelReward($reg_config, $payType, $price, $reg_amount);
            } else {
                $this->error(__('Please select payment method')); // 请选择支付方式
            }
        } elseif ($event_name == 'first_deposit_270') {

            //TODO::增加缓存防止重复参加活动
            //检查是否已经有活动的支付订单
            $activity_order = Orders::where(['user_id' => $this->userInfo['id']])
                ->whereIn('event_name', ['first_deposit_270'])
                ->whereIn('pay_status', [1])
                ->find();
            if ($activity_order) {
                $this->error(__('Already participate in this activity or other activities cannot be repeated')); // 已参加该活动或其他活动不可重复参加
            }
            //first_deposit_270 活动
            // 获取first_deposit_270活动配置
            $reg_config = Db::name('activity_first_deposit_270')->where('id', 1)->find();
            if (!$reg_config) {
                $this->error(__('Recharge config not exist')); // 充值配置不存在
            }
            $amount_list = json_decode($reg_config['amount_list'], true);
            $price = floatval($price); // 确保金额为浮点数
            // 查找匹配的充值档位
            $amounts = array_column($amount_list, 'amount');
            $index = array_search($price, $amounts);

            if ($index !== false) {
                // 如果找到匹配档位，计算奖励金额
                $rewardPercent = floatval($amount_list[$index]['reward_percent'] ?? 0);
                $reg_amount = bcadd($price, bcmul($price, $rewardPercent / 100, 2), 2);
            } else {
                //$this->error(__('Recharge amount error')); // 充值金额错误
            }
            //支付方式补贴
            if ($payType) {
                $reg_amount = $this->applyPaymentChannelReward($reg_config, $payType, $price, $reg_amount);
            } else {
                $this->error(__('Please select payment method')); // 请选择支付方式
            }

            //lg_reward_percent 到账是包含 充值金额的 所以lg_reward_percent百分比 - 100
            $base = $price;
            $percent = bcsub($reg_config['lg_reward_percent'], 100, 2); // 奖励比例 = 配置 - 100
            $bonus = bcdiv(bcmul($base, $percent, 2), 100, 2);           // 奖励金额 = 充值 * 奖励比例 / 100
            $reg_amount += $bonus;
        } elseif ($event_name == 'first_vip_49') {
            if (!$this->userInfo['vip'] > 0)   $this->error('You are not a VIP');
            // 读取唯一配置，id=1
            $reg_config = Db::name('activity_first_vip_49')->where('id', 1)->find();
            if (!$reg_config) {
                $this->error(__('Recharge config not exist')); // 充值配置不存在
            }
            $amount_list = json_decode($reg_config['amount_list'], true);
            $price = floatval($price); // 确保金额为浮点数

            // 查找匹配的充值档位
            $amounts = array_column($amount_list, 'amount');
            $index = array_search($price, $amounts);

            if ($index === false) {
                $this->error(__('Recharge amount error'));
            }


            //是否已充值该活动
            if (Db::name('recharge_orders')->where('user_id', $this->userInfo['id'])->where('event_name', $event_name)->where('pay_status', 1)->value('id')) {
                $this->error('This activity can only be participated in once.');
            }
        } elseif ($event_name == 'seven_day_card') {
            // 七天卡购买：校验金额需与现价一致
            $cardConfig = Db::name('seven_day_card_config')->where('id', 1)->find();
            if (!$cardConfig) {
                $this->error(__('Seven day card config not exist'));
            }
            $currentPrice = (float)$cardConfig['current_price'];
            if (bccomp($price, $currentPrice, 2) !== 0) {
                $this->error(__('Recharge amount error'));
            }
            // 禁止重复购买：存在未过期的七天卡
            $active = Db::name('seven_day_card_user')
                ->where('user_id', $this->userInfo['id'])
                ->where('end_time', '>', time())
                ->order('id', 'desc')
                ->find();
            if ($active) {
                $this->error(__('You have already purchased the seven day card'));
            }
        }
        //查询渠道
        $channel_name = ChannelList::where('id', $this->userInfo['channel_id'])->value('name');
        if (!$channel_name) {
            $this->error(__('Please select channel'));
        }

        // 根据来源域名构造回跳地址，避免携带旧 query/hash 参数
        $return_url = $this->buildRechargeReturnUrl($channel_name);


        $orderno = PaymentHelper::generateOrderNo('PAY');

        Db::startTrans();
        try {


            //过期时间半小时
            $expiredTime = 1800;
            //创建记录
            $data = [
                'order_no'       => $orderno,
                'user_id'        => $this->userInfo['id'],
                'channel_id'     => $this->userInfo['channel_id'],
                'amount'         => $price,
                'reg_amount'         => $reg_amount,
                'pay_type'       => $payType,
                'pay_status'     => 0,
                'expired_time'   => time() + $expiredTime + 120, //误差2分钟
                'event_name'     => $event_name,
                'created_at'     => time(),
                'updated_at'     => time(),
            ];

            Db::name('recharge_orders')->insert($data);


        $res = null;
$response = null;

/**
 * 临时兼容：
 * 现在后台启用的是 SuccusPay，但前端/数据库里可能传的是 saxpay / cashapp / card / zelle / paypal / googleorapple / btclightning
 * 这里统一走 SuccusPay 下单接口
 */
if ($this->isTestPay($payType)) {
    $res = $this->getPayService()->createOrder([
        'order_no' => $orderno,
        'amount' => $price,
        'pay_type' => $payType,
        'extra' => [
            'id' => $this->userInfo['id'],
            'return_url' => $return_url,
            'expiredTime' => $expiredTime,
        ],
    ]);

    $res['data']['cashierUrl'] = $this->buildTestPayCashierUrl($orderno, $price, time() + $expiredTime, $return_url);
    $response = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} elseif (in_array(strtolower($payType), ['saxpay', 'cashapp', 'card', 'zelle', 'paypal', 'googleorapple', 'google', 'apple', 'btclightning', 'btconchain', 'pcashapp', 'pyusd'])) {

    // ====== 这里改成你自己的 SuccusPay 配置 ======
    $succusApiUrl = 'https://www.succuspay.com/api';
    $succusMchNo  = '2025067994';
    $succusKey    = 'ABbVB47I093X7wa7ss6rc15Z85H32z37';
    // ==========================================

    // 前端传进来的 pay_type 映射成 SuccusPay 的 wayCode
    $wayCodeMap = [
        'saxpay'        => 'cashapp',
        'cashapp'       => 'cashapp',
        'card'          => 'card',
        'zelle'         => 'zelle',
        'paypal'        => 'paypal',
        'googleorapple' => 'googleorapple',
        'google'        => 'google',
        'apple'         => 'apple',
        'btclightning'  => 'btclightning',
        'btconchain'    => 'btconchain',
        'pcashapp'      => 'pcashapp',
        'pyusd'         => 'pyusd',
    ];

    $payTypeLower = strtolower($payType);
    $wayCode = $wayCodeMap[$payTypeLower] ?? 'cashapp';

    // SuccusPay 文档要求 amount 单位是“分”
    $amountCent = (int) round(floatval($price) * 100);

    // 13位毫秒时间戳
    $timestamp = (int) round(microtime(true) * 1000);

$payway = rtrim($succusApiUrl, '/') . '/pay/create';
    $payarr = [
        "mchNo"       => $succusMchNo,
        "mchOrderNo"  => $orderno,
        "amount"      => $amountCent,
        "currency"    => "usd",
        "wayCode"     => $wayCode,
        "notifyUrl"   => "https://{$_SERVER['HTTP_HOST']}/index.php/api/notify/succuspay",
        "returnUrl"   => $return_url,
        "expiredTime" => 1800,
        "timestamp"   => $timestamp,
        "signType"    => "MD5",
        // wayParam 必须是对象，不能是字符串
        "wayParam"    => [
            "clientId" => (string)$this->userInfo['id']
        ]
    ];

    // 递归排序 + 去空值
    $sortValueRecursively = function ($node) use (&$sortValueRecursively) {
        if (is_array($node)) {
            $isAssoc = array_keys($node) !== range(0, count($node) - 1);

            if ($isAssoc) {
                $filtered = [];
                foreach ($node as $k => $v) {
                    if ($v !== null && $v !== '') {
                        $filtered[$k] = $sortValueRecursively($v);
                    }
                }
                ksort($filtered, SORT_STRING);
                return $filtered;
            } else {
                $result = [];
                foreach ($node as $item) {
                    $result[] = $sortValueRecursively($item);
                }
                return $result;
            }
        }
        return $node;
    };

    $jsonDumpsV = function ($node) {
        if (is_array($node)) {
            return json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string)$node;
    };

    $sortedMap = $sortValueRecursively($payarr);

    $signPairs = [];
    foreach ($sortedMap as $k => $v) {
        if ($v !== null && $v !== '') {
            $signPairs[] = $k . '=' . $jsonDumpsV($v);
        }
    }

    $signStr = implode('&', $signPairs) . '&key=' . $succusKey;
    $payarr['sign'] = strtoupper(md5($signStr));

    // 调试日志
    file_put_contents(runtime_path() . 'succuspay_debug.txt',
        date('Y-m-d H:i:s') . PHP_EOL .
        'pay_type=' . $payType . PHP_EOL .
        'request=' . json_encode($payarr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL .
        'sign_str=' . $signStr . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $payway);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payarr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new \Exception('SuccusPay curl error: ' . curl_error($ch));
    }

    curl_close($ch);

    $resdata = json_decode($response, true);

    file_put_contents(runtime_path() . 'succuspay_debug.txt',
        date('Y-m-d H:i:s') . PHP_EOL .
        'response=' . $response . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    if (!is_array($resdata)) {
        throw new \Exception('SuccusPay response parse failed: ' . $response);
    }

    if (!isset($resdata['code']) || intval($resdata['code']) !== 0) {
        throw new \Exception($resdata['data'] ?? $resdata['msg'] ?? 'SuccusPay create order failed');
    }

    if (empty($resdata['data']['cashierUrl']) || empty($resdata['data']['payOrderNo'])) {
        throw new \Exception('SuccusPay返回缺少cashierUrl或payOrderNo');
    }

    $res['data']['cashierUrl'] = $resdata['data']['cashierUrl'];
    $res['data']['payOrderNo'] = $resdata['data']['payOrderNo'];
    $res['data']['code'] = 200;

} elseif ($payType == 'ouspay') {

    $payway = "https://ouspay.me/Pay_Index.html";
    $payarr = array(
        "pay_memberid" => '220010141',
        "pay_orderid" => $orderno,
        "pay_amount" => $price,
        "pay_bankcode" => '1000',
        "pay_applydate" => date('Y-m-d H:i:s'),
        "pay_notifyurl" => "https://{$_SERVER['HTTP_HOST']}/index.php/api/notify/ouspay",
        "pay_callbackurl" => $return_url,
    );
    ksort($payarr);
    $temp = '';
    foreach ($payarr as $k => $v) {
        $temp .= "{$k}={$v}&";
    }
    $temp .= "key=6mlcxpdw9i48s0vi2nmbzrprqvusuwr1";
    $payarr['pay_md5sign'] = strtoupper(md5($temp));
    $payarr['pay_productname'] = '充值';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $payway);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payarr));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $resdata = json_decode($response, true);

    if ($resdata['status'] != 'SUCCESS') {
        $this->error(__('Create order failed'));
    }

    $res['data']['cashierUrl'] = $resdata['payInfo'];
    $res['data']['payOrderNo'] = $resdata['orderNo'];
    $res['data']['code'] = 200;
}

if (!$res || empty($res['data']['payOrderNo']) || (!$this->isTestPay($payType) && empty($res['data']['cashierUrl']))) {
    throw new \Exception('支付方式未匹配或下单失败');
}


            //为记录增加第三方平台订单号
            Db::name('recharge_orders')->where('order_no', $orderno)->update(['platform_order_no' => $res['data']['payOrderNo']]);



            if ($event_name == 'first_deposit_daily') {
                $task = FirstDepositDailyUser::where(['user_id' => $this->userInfo['id']])->update(['task_status' => $event_data['task_status'], 'update_time' => time()]);
                if (!$task) {
                    throw new \Exception(__('Modify daily recharge activity status error')); // 修改每日充值活动状态错误
                }
            } else if ($event_name == 'first_deposit_25') {
                $task = FirstDeposit25User::where(['user_id' => $this->userInfo['id']])->update(['task_status' => $event_data['task_status'], 'update_time' => time()]);
                if (!$task) {
                    throw new \Exception(__(' recharge activity status error')); // 修改生涯充值活动状态错误
                }
            }

            // Facebook加入购物车事件（点击下单拉起支付）
            event('FacebookConversion', [
                'user_id' => $this->userInfo['id'],
                'event_type' => 'add_to_cart',
                'custom_data' => [
                    'amount' => $price,
                    'currency' => 'USD',
                    'order_id' => $orderno,
                    'pay_type' => $payType,
                    'event_name' => $event_name,
                    'reg_amount' => $reg_amount,
                    'channel_name' => $channel_name
                ],
                'client_ip' => $this->request->ip(),
                'client_user_agent' => $this->request->header('user-agent'),
                'fbc' => $this->fbc,
                'fbp' => $this->fbp,
            ]);

            // 根据订单编号缓存fbc和fbp，缓存24小时
            $this->cacheFbcFbpByOrderNo($orderno, $this->fbc, $this->fbp);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Create order failed'), $e->getMessage()); // 创建订单失败
        }

        $this->success(__('Create success'), [
            'order_no'    => $orderno,
            'cashier_url' => $res['data']['cashierUrl'] ?? '',
            'pay_status'  => 0,
            'notify_result' => $response,
        ]);
    }

    /**
     * 充值记录
     */
    public function record()
    {
        $data = $this->request->only([
            'size' => "10",
            'page' => "1",
        ]);

        $list = Orders::field("id,pay_type,amount,pay_status,created_at")
            ->where('user_id', $this->userInfo['id'])
            ->where('pay_status', 1)
            ->order('id', 'desc')
            ->paginate(["page" => $data["page"], "list_rows" => $data["size"]]);

        $this->success('', $list);
    }

    /**
     * Manually finish a TestPay recharge order from the client.
     */
    public function testpayManual(Request $request)
    {
        $orderNo = trim((string)$request->param('order_no', ''));
        $status = strtolower(trim((string)$request->param('status', '')));
        $allowedStatuses = ['success', 'cancel', 'fail'];

        if ($orderNo === '') {
            $this->error(__('Please input order no'));
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $this->error(__('Payment status param error'));
        }

        $order = Db::name('recharge_orders')
            ->where('order_no', $orderNo)
            ->where('user_id', $this->userInfo['id'])
            ->find();
        /*testPay支付后给上级返回佣金-开始*/
        $pid = Db::name('account')->where('id', $this->userInfo['id'])->value('p_id');/*父级id*/
        if($pid > 0){
            $pdd_progress = Db::name('pdd_progress')->where('user_id', $pid)->find();
            $is_progress = Db::name('pdd_progress')->where('user_id', $pid)->find();

            if($is_progress < $is_progress['target_amount']){

                /*奖励金额*/
                $oAmount = $order['amount'] * 0.1;
                $newMoney = $oAmount + $pdd_progress['invite_reward'];
                if($newMoney > $is_progress['target_amount']){
                    $newMoney = $is_progress['target_amount'];
                }
                Db::name('pdd_progress')->where('user_id', $pid)->update(['invite_reward' => $newMoney]);
                $account = Db::name('account')->where('id', $pid)->value('pdd_reward');
                $pdd_rewardNew = $oAmount + $account;
                if($pdd_rewardNew > $is_progress['target_amount']){
                    $pdd_rewardNew = $is_progress['target_amount'];
                }
                Db::name('account')->where('id', $pid)->update(['pdd_reward'=>$pdd_rewardNew]);
                $is_progress = Db::name('pdd_progress')->where('user_id', $pid)->find();
                if($is_progress['invite_reward'] >= $is_progress['target_amount']){
                    Db::name('pdd_progress')->where('user_id', $pid)->update(['status' => 1]);
                }

                $arr = [0.2,0.3,0.4];
                $oAmount = array_rand($arr);
                $newMoney = $oAmount + $pdd_progress['invite_reward'];
                if($newMoney > $is_progress['target_amount']){
                    $newMoney = $is_progress['target_amount'];
                }
                Db::name('pdd_progress')->where('user_id', $pid)->update(['invite_reward' => $newMoney]);
                $account = Db::name('account')->where('id', $pid)->value('pdd_reward');
                $pdd_rewardNew = $oAmount + $account;
                if($pdd_rewardNew > $is_progress['target_amount']){
                    $pdd_rewardNew = $is_progress['target_amount'];
                }
                Db::name('account')->where('id', $pid)->update(['pdd_reward'=>$pdd_rewardNew]);
                Db::name('account')->where('id', $pid)->inc('pdd_reward',$oAmount)->update();
                $is_progress = Db::name('pdd_progress')->where('user_id', $pid)->find();
                if($is_progress['invite_reward'] >= $is_progress['target_amount']){
                    Db::name('pdd_progress')->where('user_id', $pid)->update(['status' => 1]);
                }

            }




        }

        /*testPay支付后给上级返回佣金-结束*/

        if (!$order) {
            $this->error(__('Order not found'));
        }
        if (!$this->isTestPay((string)$order['pay_type'])) {
            $this->error(__('Payment method param error'));
        }

        if ((int)$order['pay_status'] !== 0) {
            $this->error(__('Order already processed'));
        }

        $testPaymentCallbackService = new TestPaymentCallbackService();

        try {
            if ($status === 'success') {
                $payStatus = $testPaymentCallbackService->processPendingRecharge($order, $status, $request);
            } else {
                $payStatus = $testPaymentCallbackService->markRechargeTerminal($order, $status);
            }
        } catch (\Throwable $e) {
            $this->error(__('Payment callback failed'), $e->getMessage());
        }

        $this->success(__('Operate success'), [
            'order_no' => $orderNo,
            'pay_status' => $payStatus,
            'testpay_status' => $status,
        ]);
    }

    /**
     * Get FiatPay bank list.
     */
    public function getFiatPayBankList()
    {
        // 参数获取
        $paycode = $this->request->param('paycode', 'online_banking');
        $type = $this->request->param('type', 'deposit');
        $useCache = $this->request->param('cache', true);

        try {
            // 调用PayGatewayService获取银行列表
            $result = $this->getPayService()->getFiatPayBankList($paycode, $type, $useCache);

            $this->success(__('Get bank list success'), $result);
        } catch (\Exception $e) {
            $this->error(__('Get bank list failed') . ': ' . $e->getMessage());
        }
    }







    /**
     * 记录支付相关信息日志
     */
    private function logPaymentInfo(string $message, array $data = [])
    {
        if (function_exists('Log')) {
            \think\facade\Log::channel('payment')->info($message, $data);
        }
    }

    /**
     * 记录支付相关错误日志
     */
    private function logPaymentError(string $message, array $data = [])
    {
        if (function_exists('Log')) {
            \think\facade\Log::channel('payment')->error($message, $data);
        }
    }

    /**
     * 根据订单编号缓存fbc和fbp，缓存24小时
     * @param string $orderNo 订单编号
     * @param string|null $fbc Facebook点击ID
     * @param string|null $fbp Facebook浏览器ID
     */
    private function cacheFbcFbpByOrderNo(string $orderNo,  $fbc,  $fbp): void
    {
        try {
            // 分别缓存fbc和fbp
            $fbcCacheKey = 'fbc_order_' . $orderNo;
            Cache::set($fbcCacheKey, $fbc, 24 * 3600);

            $fbpCacheKey = 'fbp_order_' . $orderNo;
            Cache::set($fbpCacheKey, $fbp, 24 * 3600);

            \think\facade\Log::info('FBC/FBP分开缓存成功' . json_encode([
                'order_no' => $orderNo,
                'fbc' => $fbc,
                'fbp' => $fbp,
                'fbc_cache_key' => $fbc ? 'fbc_order_' . $orderNo : null,
                'fbp_cache_key' => $fbp ? 'fbp_order_' . $orderNo : null
            ]));
        } catch (\Exception $e) {
            \think\facade\Log::error('FBC/FBP缓存失败', [
                'order_no' => $orderNo,
                'error' => $e->getMessage()
            ]);
        }
    }
}
