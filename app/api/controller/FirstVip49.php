<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\controller\Api;
use app\common\model\activity\FirstDeposit270User;
use app\common\model\activity\FirstDepositDailyUser;
use app\common\model\recharge\Orders;
use app\common\service\AccountService;
use app\common\service\PayGatewayService;
use think\App;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;


class FirstVip49 extends Base
{
    protected AccountService $accountService;
    protected array $noNeedLogin = [];
    // 构造函数需要接收 App $app，然后传给 Base
    public function __construct(App $app)
    {
        parent::__construct($app);

    }
    protected function getAccountService(): AccountService
    {
        return $this->accountService ??= new AccountService();
    }


    public function index()
    {
        $userId = $this->userInfo['id'];

        // 检查是否已经满足每日充值
        $daily_status = 0;
        $DailyUser=FirstDepositDailyUser::where(['user_id'=>$userId])->find();
        if(isset($DailyUser)&&$DailyUser->status==2){
            $daily_status = 2;
        }

        // 读取唯一配置，id=1
        $config = \app\common\model\activity\FirstVip49::where(['id'=>1])->find();

        if (!$config) {
            $this->error(__('Recharge config not exist')); // 充值配置不存在
        }
        $activity_status =0;
        $order =Orders::where(['user_id'=>$userId,'event_name'=>'first_vip_49','pay_status'=>1])->find();
        if ($order) {
            $activity_status =1;
        }
        $payChannels = is_array($config->pay_channels) ? $config->pay_channels : (get_object_vars($config->pay_channels) ?: []);
        $availableChannels = (new \app\common\service\PayGatewayService())->getAvailablePayChannels($this->userInfo['id'], $payChannels);
        // 返回成功响应
        $this->success(__('Get recharge config success'), [
                'amount_list'=>$config->amount_list,
                'pay_channels'=>$availableChannels,
                'daily_status'=>$daily_status,
                'activity_status'=> $activity_status,
                ]
        );
    }



}