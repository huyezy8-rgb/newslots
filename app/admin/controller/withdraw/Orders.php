<?php

namespace app\admin\controller\withdraw;

use app\api\controller\Jackpot;
use app\api\enum\CoinLog;
use app\common\controller\Backend;
use app\common\model\jackpot\JackpotWithdrawLog;
use app\common\model\payment\Methods;
use app\common\model\PddProgress;
use app\common\service\AccountService;
use app\common\service\PayGatewayService;
use ba\PaymentHelper;
use think\db\Query;
use think\facade\Db;

/**
 * 用户提现订单管理
 */
class Orders extends Backend
{
    /**
     * Orders模型对象
     * @var object
     * @phpstan-var \app\common\model\withdraw\Orders
     */
    protected object $model;
    protected AccountService $accountService;
    /**
     * 排除字段
     * @var array
     */
    protected array|string $preExcludeFields = ['id', 'update_time', 'create_time'];
    /**
     * 快速搜索字段
     * @var array
     */
    protected string|array $quickSearchField = ['id', 'order_no','user_id'];
    /**
     * 可编辑字段
     * @var array
     */
    protected function getPayService(): PayGatewayService
    {
        return $this->payService ??= new PayGatewayService();
    }
    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\withdraw\Orders();
        $this->accountService = app(AccountService::class);
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        // 添加渠道权限过滤
        $where = $this->addChannelFilter($where, 'channel_id');
        $res = $this->model
            ->with(['user' => function (Query $query) {
                $query->field('id,name,mobile');
            }])
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $this->success('', [
            'list' => $res->items(),
            'total' => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 通过提现订单
     */
    public function pass__()
    {
        $id = $this->request->post('id');
        if (!$id) {
            $this->error('参数错误');
        }
        $order = $this->model->find($id);
        if (!$order) {
            $this->error('订单不存在');
        }
        if (!in_array($order->status, [0, 4])) {
            $this->error('订单状态不可操作');
        }
        $order->startTrans();
        try {
            $orderno = PaymentHelper::generateOrderNo('Tran');

            // 根据不同的提现方式构建不同的参数
            $accountInfo = $order->account_info; // 直接使用对象属性
            $payType = $order->pay_type ?? 'ecashapp'; // 默认为 ecashapp
            
            // 验证支付方式是否有效
            $paymentMethod = Methods::where([
                'unique_tag' => $payType,
                'status' => 1,
                ['pay_method', 'in', ['0', '2']] // 0=所有方式，2=提现
            ])->find();
            
            if (!$paymentMethod) {
                throw new \Exception('无效的提现方式：' . $payType);
            }
            
            if ($payType == 'ecashapp') {
                // ECashApp 提现参数验证和构建
                if (empty($accountInfo->name)) {
                    throw new \Exception('ECashApp姓名不能为空');
                }
                if (empty($accountInfo->account_name)) {
                    throw new \Exception('ECashApp账户名不能为空');
                }
                $wayParam = [
                    'cashtag' => $accountInfo->account_name,
                ];
            } elseif ($payType == 'fiat_withdrawal') {
                // FiatPay 银行卡提现参数验证和构建
                if (empty($accountInfo->name)) {
                    throw new \Exception('银行账户姓名不能为空');
                }
                if (empty($accountInfo->account_name)) {
                    throw new \Exception('银行账户号码不能为空');
                }
                if (empty($accountInfo->bank_name)) {
                    throw new \Exception('银行名称不能为空');
                }
                
                // FiatPay 的参数直接放在 extra 中，而不是 wayParam
                $wayParam = [
                    'bank_account_name' => $accountInfo->name, // 银行账户姓名
                    'bank_account_number' => $accountInfo->account_name, // 银行账户号码
                    'bank_name' => $accountInfo->bank_name, // 银行名称
                ];
                
                // 可选参数：银行代码和分行代码（如果有的话）
                if (!empty($accountInfo->bank_code)) {
                    $wayParam['bank_code'] = $accountInfo->bank_code;
                }
                if (!empty($accountInfo->branch_code)) {
                    $wayParam['branch_code'] = $accountInfo->branch_code;
                }
            } else {
                // 其他提现方式的默认处理（向后兼容）
                if (empty($accountInfo->account_name)) {
                    throw new \Exception('账户名不能为空');
                }
                $wayParam = [
                    'name' => $accountInfo->name ?? '',
                    'cashtag' => $accountInfo->account_name,
                ];
            }
            
            // 根据支付方式构建不同的参数结构
            if ($payType == 'fiat_withdrawal') {
                // FiatPay 直接从 extra 中取参数，不使用 wayParam
                $extraParams = array_merge([
                    'id' => $order->user_id,
                    'customer_uid' => (string)$order->user_id,
                ], $wayParam);
            } else {
                // 其他支付方式使用 wayParam 结构
                $extraParams = [
                    'id' => $order->user_id,
                    'wayParam' => $wayParam,
                ];
            }
            
            $res = $this->getPayService()->createTransfer([
                'order_no' => $orderno,
                'amount' => $order->real_amount,
                'pay_type' => $payType,
                'extra' => $extraParams,
            ]);

            $order->order_no = $orderno;
            //不同提现 统一返回相应字段
            $order->platform_order_no = $res['data']['transferOrderNo'] ?? $orderno;
            
            $order->status = 1; // 1=已通过
            $order->save();
            $order->commit();

        } catch (\Exception $e) {
            $order->rollback();
            $this->error('操作失败：' . $e->getMessage());
        }
        $this->success('操作成功,等待打款');
    }


    public function pass()
    {
        $id = $this->request->post('id');
        $pass_type = $this->request->post('pass_type','SaxPay');
        if (!$id) {
            $this->error('参数错误');
        }
        $order = $this->model->find($id);
        if (!$order) {
            $this->error('订单不存在');
        }
        if (!in_array($order->status, [0, 4])) {
            $this->error('订单状态不可操作');
        }
        $order->startTrans();
        try {
            $orderno = PaymentHelper::generateOrderNo('Tran');

            // 根据不同的提现方式构建不同的参数
            $accountInfo = $order->account_info; // 直接使用对象属性
            $payType = $order->pay_type ?? 'ecashapp'; // 默认为 ecashapp
            
            // 验证支付方式是否有效
            $paymentMethod = Methods::where([
                'unique_tag' => $payType,
                'status' => 1,
                ['pay_method', 'in', ['0', '2']] // 0=所有方式，2=提现
            ])->find();
            
            if (!$paymentMethod) {
                throw new \Exception('无效的提现方式：' . $payType);
            }
            
            if ($payType == 'ecashapp') {
                // ECashApp 提现参数验证和构建
                if (empty($accountInfo->name)) {
                    // throw new \Exception('ECashApp姓名不能为空');
                }
                if (empty($accountInfo->account_name)) {
                    throw new \Exception('ECashApp账户名不能为空');
                }
                $wayParam = [
                    'cashtag' => $accountInfo->account_name,
                ];
            } elseif ($payType == 'fiat_withdrawal') {
                // FiatPay 银行卡提现参数验证和构建
                if (empty($accountInfo->name)) {
                    throw new \Exception('银行账户姓名不能为空');
                }
                if (empty($accountInfo->account_name)) {
                    throw new \Exception('银行账户号码不能为空');
                }
                if (empty($accountInfo->bank_name)) {
                    throw new \Exception('银行名称不能为空');
                }
                
                // FiatPay 的参数直接放在 extra 中，而不是 wayParam
                $wayParam = [
                    'bank_account_name' => $accountInfo->name, // 银行账户姓名
                    // 'bank_account_number' => $accountInfo->account_name, // 银行账户号码
                    // 'bank_name' => $accountInfo->bank_name, // 银行名称
                ];
                
                // 可选参数：银行代码和分行代码（如果有的话）
                if (!empty($accountInfo->bank_code)) {
                    $wayParam['bank_code'] = $accountInfo->bank_code;
                }
                if (!empty($accountInfo->branch_code)) {
                    $wayParam['branch_code'] = $accountInfo->branch_code;
                }
            } else {
                // 其他提现方式的默认处理（向后兼容）
                if (empty($accountInfo->account_name)) {
                    throw new \Exception('账户名不能为空');
                }
                $wayParam = [
                    'name' => $accountInfo->name ?? '',
                    'cashtag' => $accountInfo->account_name,
                ];
            }
            
            // 根据支付方式构建不同的参数结构
            if ($payType == 'fiat_withdrawal') {
                // FiatPay 直接从 extra 中取参数，不使用 wayParam
                $extraParams = array_merge([
                    'id' => $order->user_id,
                    'customer_uid' => (string)$order->user_id,
                ], $wayParam);
            } else {
                // 其他支付方式使用 wayParam 结构
                $extraParams = [
                    'id' => $order->user_id,
                    'wayParam' => $wayParam,
                ];
            }


            if ($pass_type == 'SaxPay') {
                $payway = "https://saxpay.payc2-sapi.com/apis/pay/order/bk_daifu";
                $payarr = array(
                    "merch" => '453',
                    "orderNo" => $orderno,
                    "amount" => $order->real_amount,
                    "channel" => '2002',
                    "accountNo"  => $accountInfo->account_name,
                    "accountOwner"  => $accountInfo->name,
                    "bankCode" => "",
                    "bankName" => "",
                    "bankBranch" => "",
                    "notifyUrl" => request()->domain() . '/index.php/api/notify/saxpay'
                );
                ksort($payarr);
                $temp = '';
                foreach ($payarr as $k => $v) {
                    $temp .= "{$k}={$v}&";
                }
                $temp .= "key=7c775a2fabbd457a9bfc5c3addee486f";
                $payarr['sign'] = md5($temp);
                file_put_contents("/www/wwwroot/admincs.tapmc.net/.idea/saxpay__.txt", json_encode($payway));
                $ch = curl_init();
                // 设置cURL选项
                curl_setopt($ch, CURLOPT_URL, $payway); // 目标URL
                curl_setopt($ch, CURLOPT_POST, 1); // 设置为POST请求
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payarr));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [ // 设置HTTP头信息，指定Content-Type为application/json
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 将curl_exec()获取的信息以文件流的形式返回，而不是直接输出。
                // 执行cURL会话
                $response = curl_exec($ch);
                $resdata = json_decode($response, true);
                file_put_contents("/www/wwwroot/admincs.tapmc.net/.idea/saxpay.txt", json_encode($resdata));

                // $res = $this->getPayService()->createTransfer([
                //     'order_no' => $orderno,
                //     'amount' => $order->real_amount,
                //     'pay_type' => $payType,
                //     'extra' => $extraParams,
                // ]);
                if ($resdata['errcode'] != 0) {
                    throw new \Exception($resdata['errmsg'].'66');
                }
                $platform_order_no = $resdata['datas']['uid'];
               
            } elseif ($pass_type == 'OusPay') {
                $payway = "https://ouspay.me/Payment_Dfpay_add.html";
                $payarr = array(
                    "mchid" => '220010141',
                    "out_trade_no" => $orderno,
                    "money" => $order->real_amount,
                    "bankname" => '1',
                    "accountname" => '1',
                    "cardnumber" => $accountInfo->account_name,
                    "subbranch" => '1',
                    "province" => '1',
                    "city" => '1',
                    "notifyurl" => "https://{$_SERVER['HTTP_HOST']}/index.php/api/notify/ouspay_daifu",
                );
                ksort($payarr);
                $temp = '';
                foreach ($payarr as $k => $v) {
                    $temp .= "{$k}={$v}&";
                }
                $temp .= "key=6mlcxpdw9i48s0vi2nmbzrprqvusuwr1";
                $payarr['pay_md5sign'] = strtoupper(md5($temp));

                $ch = curl_init();
                // 设置cURL选项
                curl_setopt($ch, CURLOPT_URL, $payway); // 目标URL
                curl_setopt($ch, CURLOPT_POST, 1); // 设置为POST请求
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payarr, JSON_UNESCAPED_UNICODE)); // 中文不转义
                curl_setopt($ch, CURLOPT_HTTPHEADER, [ // 设置HTTP头信息，指定Content-Type为application/json
                    'Content-Type: application/json; charset=utf-8'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 将curl_exec()获取的信息以文件流的形式返回，而不是直接输出。
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 设置超时时间
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 连接超时时间
                // 执行cURL会话
                $response = curl_exec($ch);

                $resdata = json_decode($response, true);
                file_put_contents("/www/wwwroot/admincs.tapmc.net/.idea/ouspay.txt", json_encode($resdata));

                if ($resdata['status'] != 'success') {
                    throw new \Exception($resdata['msg']);
                }

                $platform_order_no = $resdata['transaction_id'];
            }
            
             $order->order_no = $orderno;
                //不同提现 统一返回相应字段
                $order->platform_order_no = $platform_order_no ?? $orderno;
                $order->callback_data = $response;
                $order->status = 1; // 1=已通过
                $order->save();
                $order->commit();

        } catch (\Exception $e) {
            $order->rollback();
            $this->error('操作失败：' . $e->getMessage());
        }
        $this->success('操作成功,等待打款');
    }


    /**
     * 驳回提现订单（不需要理由，金额原路返回）
     */
    public function reject()
    {

        $id = $this->request->post('id');
        if (!$id) {
            $this->error('参数错误');
        }
        $order = $this->model->find($id);
        if (!$order) {
            $this->error('订单不存在');
        }
        if (!in_array($order->status, [0, 4])) {
            $this->error('订单状态不可操作');
        }

        // 只处理充值钱包提现的驳回
        if($order->wallet_type != 'recharge_wallet'){
            $this->error('只支持余额提现的驳回操作');
        }

        $this->model->startTrans();
        try {
            // 设置订单状态为驳回
            $order->status = 3;
            if (!$order->save()){
                throw new \Exception('驳回失败');
            };

            // 返还充值钱包余额
            $this->accountService->increaseBalance(
                userId: $order->user_id,
                amount: $order->amount,
                walletType: 1, // recharge_wallet
                logTypeId: CoinLog::WithdrawRefund,
                note: CoinLog::getTypeText(CoinLog::WithdrawRefund)
            );
            
            // 重算可提现余额
            $dmlService = new \app\common\service\DmlService();
            $userBalance = Db::name('account')->where('id', $order->user_id)->value('recharge_wallet');
            $dmlService->recalculateWithdrawAvailable($order->user_id, $userBalance);
            
            $this->model->commit();
        } catch (\Exception $e) {
            $this->model->rollback();
            $this->error('操作失败：' . $e->getMessage());
        }
        $this->success('操作成功，金额已原路返回');
    }

    /**
     * 查看提现订单详情
     */
    public function info()
    {
        $id = $this->request->param('id');
        if (!$id) {
            $this->error('参数错误');
        }
        
        $order = $this->model->with(['user' => function (Query $query) {
            $query->field('id,name,mobile,channel_id');
        }])->find($id);
        
        if (!$order) {
            $this->error('订单不存在');
        }
        
        // 解析账户信息并格式化显示
        $accountInfo = json_decode($order->account_info, true);
        $payType = $order->pay_type ?? 'ecashapp';
        
        // 根据支付方式格式化显示信息
        $formattedAccountInfo = $this->formatAccountInfo($payType, $accountInfo);
        
        // 获取支付方式信息
        $paymentMethod = Methods::where('unique_tag', $payType)->find();
        
        $result = $order->toArray();
        $result['formatted_account_info'] = $formattedAccountInfo;
        $result['payment_method_name'] = $paymentMethod ? $paymentMethod->name : $payType;
        
        $this->success('', $result);
    }
    
    /**
     * 格式化账户信息显示
     */
    private function formatAccountInfo($payType, $accountInfo)
    {
        if (empty($accountInfo)) {
            return [];
        }
        
        switch ($payType) {
            case 'ecashapp':
                return [
                    '提现方式' => 'ECashApp',
                    '姓名' => $accountInfo['name'] ?? '',
                    '账户名' => $accountInfo['account_name'] ?? '',
                ];
                
            case 'fiat_withdrawal':
                $info = [
                    '提现方式' => '银行卡提现 (FiatPay)',
                    '开户姓名' => $accountInfo['name'] ?? '',
                    '银行账号' => $accountInfo['account_name'] ?? '',
                    '银行名称' => $accountInfo['bank_name'] ?? '',
                ];
                
                // 添加可选信息
                if (!empty($accountInfo['bank_code'])) {
                    $info['银行代码'] = $accountInfo['bank_code'];
                }
                if (!empty($accountInfo['branch_code'])) {
                    $info['分行代码'] = $accountInfo['branch_code'];
                }
                
                return $info;
                
            default:
                return [
                    '提现方式' => $payType,
                    '姓名' => $accountInfo['name'] ?? '',
                    '账户名' => $accountInfo['account_name'] ?? '',
                ];
        }
    }
    
    /**
     * 批量审核提现订单
     */
    public function batchPass()
    {
        $ids = $this->request->post('ids');
        if (empty($ids) || !is_array($ids)) {
            $this->error('请选择要审核的订单');
        }
        
        $successCount = 0;
        $failedOrders = [];
        
        foreach ($ids as $id) {
            try {
                $this->request->withInput(['id' => $id]);
                $this->pass();
                $successCount++;
            } catch (\Exception $e) {
                $failedOrders[] = [
                    'id' => $id,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $message = "成功审核 {$successCount} 个订单";
        if (!empty($failedOrders)) {
            $message .= "，失败 " . count($failedOrders) . " 个订单";
        }
        
        $this->success($message, [
            'success_count' => $successCount,
            'failed_orders' => $failedOrders
        ]);
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}