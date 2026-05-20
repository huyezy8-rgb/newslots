<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\controller\Api;
use app\common\controller\Frontend;
use app\common\model\withdraw\Orders;
use app\common\service\ChannelInfoService;
use app\common\service\PayGatewayService;
use app\common\service\AccountService;
use think\facade\Db;

class Withdraw extends Base
{
    protected array $noNeedLogin = [];


    public function index()
    {
        $params = $this->request->param();
        $typeid = intval($params['typeid'] ?? 3);

        $data = [];
        $todayStart = strtotime(date('Y-m-d 00:00:00')); // 今日开始时间
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));   // 今日结束时间
        $level = Db::name("member_level_config")->where(['level' => $this->userInfo['vip']])->find();
        if ($typeid === 3) {

            $data = [
               'withdraw_limit'=>$level['withdraw_limit'],
                'daily_withdraw_times'=>$level['daily_withdraw_times'],
                'withdraw_fee_percent'=>$level['withdraw_fee_percent'],
                'withdraw_available' =>  $this->userInfo['withdraw_available'],
                'min_withdraw_limit' => get_sys_config('min_withdraw_limit')??30,
            ];
        }elseif ($typeid === 4) {
            $data = [
                'ex_withdraw_bet'=> $this->userInfo['ex_withdraw_bet'],
                'ex_withdraw_fee_percent'=> $level['withdraw_fee_percent']??(get_sys_config('ex_withdraw_fee_percent')??0),
                'ex_withdraw_bet_base'=> ChannelInfoService::getExperienceWithdrawBetBase(),
                'ex_withdraw_amount'=> ChannelInfoService::getExperienceWithdrawAmount(),
            ];
        }


        $withdraw_count =Db::name('withdraw_orders')
            ->where(['user_id' => $this->userInfo['id'], 'wallet_type' => CoinLog::walletType($typeid)])
            ->whereIn('status',[0,1,2,4])
            // ->whereBetweenTime('create_time',$todayStart,$todayEnd)
            ->count();
        $data['daily_withdraw_had']  = $withdraw_count;
        
        // 添加提现方式列表
        try {
            $payGatewayService = new PayGatewayService();
            $withdrawChannels = $payGatewayService->getAvailableWithdrawChannels();
            $data['withdraw_channels'] = $withdrawChannels;
        } catch (\Exception $e) {
            // 如果获取提现方式失败，记录错误但不影响主要功能
            \think\facade\Log::error('获取提现方式失败: ' . $e->getMessage());
            $data['withdraw_channels'] = [];
        }
        
        // 添加已保存的账号信息
        $data['saved_accounts'] = \app\common\model\WithdrawAccount::getUserAccounts($this->userInfo['id']);

        $this->success(__('OK'), $data);
    }

    /**
     * @return void
     */
    public  function submit()
    {

        $params = $this->request->param();
        $typeid = $params['typeid'] ?? 3;
        $amount = floatval($params['amount'] ?? 0);
        $name = $params['name'] ?? null; // 不转floatval
        $account_name = $params['account_name'] ?? null;
        
        $pay_type = $params['pay_type'] ?? 'ecashapp'; // 默认提现方式（体验钱包划转不再需要）
        $user = $this->userInfo;

        // 体验钱包划转到充值钱包：不需要校验提现方式与账户信息//不需要验证手机号
        if (intval($typeid) === 4) {
            $res = $this->handleExperienceWalletWithdraw($params, $this->userInfo, '', '');
            if (!$res) {
                $this->error(__('Withdraw failed'));
            }
            $this->success($res['msg'], $res['data'] ?? []);
        }
        // 验证必须绑定手机号
        if (empty($user['mobile'])) {
            $this->error(__('Please bind mobile number before withdrawal'), [], 1001);
        }

        if ($amount <= 0) {
            $this->error(__('Withdraw amount must be greater than 0'));
        }
        
        if($user['sum_bet'] < 377*25){
            $this->error(__('Bet sum must reach 377*25 times to unlock withdrawal'));
        }

        //验证VIP等级，必须要达成VIP，否则不能提现，达到VIP1时提示需要达到VIP2
        if($user['vip']==0){
            $this->error(__('Vip level is 0, please upgrade to VIP to withdraw'));
        }
        if($user['vip']==1){
            $this->error(__('Vip level is 1, please upgrade to VIP2 to withdraw'));
        }


        // 处理账号信息（充值钱包提现需要）
        $accountInfo = $this->getAccountInfo($params, $pay_type);

        // 验证提现方式是否有效（充值钱包提现需要）
        $paymentMethod = \app\common\model\payment\Methods::where([
            'unique_tag' => $pay_type,
            'status' => 1,
            ['pay_method', 'in', ['0', '2']] // 0=所有方式，2=提现
        ])->find();

        if (!$paymentMethod) {
            $this->error(__('Invalid withdraw method'));
        }
        $walletField = CoinLog::walletType($typeid);
        if (!isset($walletField) || !isset($user[$walletField])) {
            $this->error(__('Wallet type not exist'));
        }
        if ($user[$walletField] < $amount) {
            $this->error(__('Insufficient balance'));
        }

        // 分发到不同钱包类型的处理函数

            switch ($typeid) {
                case 3:
                    $res=$this->handleRechargeWalletWithdraw($params, $this->userInfo,$accountInfo,$pay_type);
                    break;
                case 4:
                    // 上面已处理，这里保底不应触发
                    $res=['code'=>0,'msg'=>__('Withdraw failed')];
                    break;
                default:
                    $this->error(__('Withdraw type not supported'));
            }
            if (!$res) {
                $this->error(__('Withdraw failed') . ': ' . $res['msg']);
            }
        $this->success($res['msg']);
    }

    protected function handleRechargeWalletWithdraw(array $params, $user,$accountInfo,$pay_type)
    { //获取提现记录
        $order=Orders::where(['user_id' => $user['id'],'status'=>0,'wallet_type'=>'recharge_wallet'])->find();
        if ($order) {
            $this->error(__('Have ongoing withdraw order'));
        }
        $amount = floatval($params['amount']);
        $typeid = $params['typeid'] ?? 3;
        $walletField = CoinLog::walletType($typeid);
        $withdraw_available = floatval($user['recharge_wallet'] ?? 0);
        $recharge_wallet = floatval($user['recharge_wallet'] ?? 0);

        if ($amount>$recharge_wallet){
            $this->error(__('Insufficient balance'));
        }
        if ($withdraw_available < $amount) {
            $this->error(__('Withdraw available amount insufficient'));
        }
        //提现最小限制
        $min_withdraw_limit = get_sys_config('min_withdraw_limit')??30;
        if ($amount < $min_withdraw_limit) {
            $this->error(__('Min withdraw limit', [$min_withdraw_limit]));
        }
        $level = Db::name("member_level_config")->where(['level' => $this->userInfo['vip']])->find();
        //提现最大限制
        if($amount > $level['withdraw_limit']){
            $this->error(__('Each withdrawal cannot exceed') . '$'.$level['withdraw_limit']);
        }
        //提现次数
        $todayStart = strtotime(date('Y-m-d 00:00:00')); // 今日开始时间
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));   // 今日结束时间
        $withdraw_count =Db::name('withdraw_orders')
            ->where(['user_id' => $this->userInfo['id'], 'wallet_type' => CoinLog::walletType($typeid)])
            ->whereIn('status',[0,1,2,4])
            // ->whereBetweenTime('create_time',$todayStart,$todayEnd)
            ->count();
        if ($withdraw_count >= $level['daily_withdraw_times']) {
            $this->error(__('Withdraw times insufficient'));
        }

        $fee_bl = $level['withdraw_fee_percent'] ; // 手续费比例可从配置读取
        $fee = round($amount * $fee_bl / 100, 2);
        $realAmount = $amount - $fee;

        if ($realAmount <= 0) {
            $this->error(__('Withdraw amount insufficient to pay fee'));
        }

        $balance = $recharge_wallet ?? 0;
        Db::startTrans();
        try {
            // 只扣除充值钱包余额，不手动扣除可提现余额
            $updated = Db::name('account')
                ->where('id', $user['id'])
                ->where($walletField, '>=', $amount)
                ->dec($walletField, $amount)
                ->update();
            if (!$updated) {
                throw new \Exception(__('Deduct balance failed'));
            }

            $logId = Db::name('account_coin_log')->insertGetId([
                'user_id'     => $user['id'],
                'wallet_type' => 1,
                'old_num'     => $balance,
                'num'         => -$amount,
                'new_num'     => $balance - $amount,
                'log_type_id' => CoinLog::Withdraw,
                'note'        => CoinLog::getTypeText(3) . '，' . __('Amount: %s', [$amount]),
                'create_time' => time(),
                'update_time' => time(),
            ]);
            if (!$logId) {
                throw new \Exception(__('Record log failed'));
            }

            $orderId = Db::name('withdraw_orders')->insertGetId([
                'user_id'       => $user['id'],
                'channel_id'    => $user['channel_id'],
                'amount'        => $amount,
                'real_amount'   => $realAmount,
                'fee'           => $fee,
                'pay_type'      => $pay_type,
                'wallet_type'   => $walletField,
                'account_info'  => $accountInfo,
                'status'        => 0,
                'create_time'   => time(),
            ]);
            if (!$orderId) {
                throw new \Exception(__('Withdraw order create failed'));
            }

            // 重算可提现余额
            $dmlService = new \app\common\service\DmlService();
            $dmlService->recalculateWithdrawAvailable($user['id'], $balance - $amount);

            Db::commit();
            return [
                "code" => 0,
                "msg" => __('Withdraw application submitted successfully, please wait for review'),
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            return [
                "code" => 0,
                "msg" => __('Withdraw failed') . ":" . $e->getMessage(),
            ];
        }
    }

    protected function handleExperienceWalletWithdraw(array $params, $user,$accountInfo,$pay_type)
    {
        // 体验钱包划转到充值钱包（免手续费，不创建提现订单）
        // 若渠道关闭双钱包，则禁止体验钱包提现
        try {
            $channel = \app\common\model\ChannelList::withoutField('create_time,update_time')->where('id', $user['channel_id'])->find();
            if ($channel && intval($channel['double_wallet_enabled'] ?? 1) === 0) {
                $this->error(__('Experience wallet withdraw is disabled for this channel'));
            }
        } catch (\Throwable $e) {
            // 忽略异常，走原逻辑
        }
        $ex_withdraw_bet_base= ChannelInfoService::getExperienceWithdrawBetBase();
        $ex_withdraw_amount= ChannelInfoService::getExperienceWithdrawAmount();
        $amount = floatval($params['amount']);
        $typeid = $params['typeid'] ?? 4;
        $walletField = CoinLog::walletType($typeid);
        $balance = floatval($user['experience_wallet'] ?? 0);
        

        // 检查打码量要求
        if ($user['ex_withdraw_bet']<$ex_withdraw_bet_base){
            $this->error(__('Turnover not reached'));
        }
        
        // 计算实际扣除金额和到账金额
        $actualDeductAmount = min($amount, $balance); // 实际扣除的体验金金额
        $transferAmount = $ex_withdraw_amount; // 固定到账30元
        // 免手续费，直接划转固定金额，然后赠送体验金
        Db::startTrans();
        try {
            $accountService = new AccountService();
            //大于0才有扣除
            if ($balance > 0) {
                // 扣除实际体验金余额
                $accountService->decreaseBalance($user['id'], $actualDeductAmount, 0, CoinLog::ExWithdraw, __('Experience wallet withdraw transfer out'));
            }
            // 转入充值钱包固定金额
            $accountService->increaseBalance($user['id'], $transferAmount, 1, CoinLog::ExWithdraw, __('Experience wallet withdraw transfer in'));

            // 扣减打码量门槛
            Db::name('account')
                ->where('id', $user['id'])
                ->dec('ex_withdraw_bet',$ex_withdraw_bet_base )
                ->update();

            // 检查体验金是否归零，如果归零则赠送体验金
            $remainingBalance = $balance - $actualDeductAmount;
            if ($remainingBalance <= 0) {
                $accountService->increaseBalance($user['id'], $ex_withdraw_amount, 0, CoinLog::ExWithdrawGift, __('Experience wallet withdraw gift'));
            }

            Db::commit();
            
            $msg = __('Withdrawal successful');

            
            return [
                "code" => 1,
                "msg" => $msg,
                "data" => [
                    'deducted_amount' => $actualDeductAmount,
                    'transferred_amount' => $transferAmount,
                    'gift_amount' => $remainingBalance <= 0 ? $ex_withdraw_amount : 0,
                    'remaining_balance' => max(0, $remainingBalance)
                ]
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            return [
                "code" => 0,
                "msg" => __('Withdraw failed') . ":" . $e->getMessage(),
            ];
        }

    }

    /**
     * 提现记录
     */
    public function record()
    {
        $data = $this->request->only([
            'size'=> "10",
            'page'=> "1",
        ]);

        $list = Orders::field("id,wallet_type,amount,real_amount,fee,status,create_time")->where('user_id', $this->userInfo['id'])
            ->order('id', 'desc')
            ->paginate(["page"=>$data["page"], "list_rows"=>$data["size"]]);

        $this->success('', $list);
    }

    /**
     * 获取账号信息
     */
    private function getAccountInfo(array $params, string $payType): string
    {
        // 如果提供了账号ID，使用已保存的账号
        if (isset($params['account_id']) && !empty($params['account_id'])) {
            $account = \app\common\model\WithdrawAccount::where('user_id', $this->userInfo['id'])
                                ->where('id', $params['account_id'])
                                ->where('status', 1)
                                ->find();

            if (!$account) {
                $this->error(__('Account not found'));
            }

            // 直接使用JSON数据，无需解密
            $accountInfo = $account->account_info ?: [];
        } else {
            // 使用临时账号信息
            $accountInfo = $this->buildAccountInfoFromParams($params, $payType);
        }

        return json_encode($accountInfo, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 从参数构建账号信息
     */
    private function buildAccountInfoFromParams(array $params, string $payType): array
    {
        switch ($payType) {
            case 'testpay':
                return [
                    'name' => $params['name'] ?? 'TestPay',
                    'account_name' => $params['account_name'] ?? 'testpay',
                ];

            case 'ecashapp':
                if (empty($params['name']) || empty($params['account_name'])) {
                    $this->error(__('Name and account name cannot be empty'));
                }
                return [
                    'name' => $params['name'],
                    'account_name' => '$' . ltrim($params['account_name'], '$'),
                ];

            case 'fiat_withdrawal':
                if (empty($params['name']) || empty($params['account_name']) ||
                    empty($params['bank_name']) || empty($params['bank_code'])) {
                    $this->error(__('Bank account information cannot be empty'));
                }
                return [
                    'name' => $params['name'],
                    'account_name' => $params['account_name'],
                    'bank_name' => $params['bank_name'],
                    'bank_code' => $params['bank_code'],
                ];

            default:
                $this->error(__('Invalid withdraw method'));
        }

    }
}
