<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\controller\Api;
use app\common\model\activity\DepositVipUser;
use app\common\model\activity\FirstDeposit270User;
use app\common\service\AccountService;
use app\common\service\PayGatewayService;
use think\App;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class DepositVip extends Base
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



        // 读取唯一配置，id=1
        $config =\app\common\model\activity\DepositVip::where(['id'=>1])->find();

        if (!$config) {
            $this->error(__('Recharge config not exist'));
        }

        // 初始化 VIP 状态（默认未完成）
        $vip1_status = 1;
        $vip2_status = 0;
        $vip3_status = 0;

// 查询数据库（合并查询，减少 SQL 请求）
        $tasks = DepositVipUser::where('user_id', $this->userInfo['id'])
            ->whereIn('level', ['vip1', 'vip2', 'vip3'])
            ->select()
            ->toArray();

// 按 level 分类
        $vipTasks = array_column($tasks, null, 'level');
        $vip1_task = $vipTasks['vip1'] ?? [];
        $vip2_task = $vipTasks['vip2'] ?? [];
        $vip3_task = $vipTasks['vip3'] ?? [];

// 更新 VIP 状态（递进关系）
        if (!empty($vip1_task)) {
            $vip1_task['lg_reward'] = $vip1_task['amount'] ?? 0;
            $vip1_task['bet_num_base_reward'] = 1;
            $vip1_status  = 2;//显示后续任务
            $vip2_status = 1;  // 解锁 vip2
        }
        if (!empty($vip2_task)) {
            $vip2_task['lg_reward'] = $vip2_task['amount'] ?? 0;
            $vip2_task['bet_num_base_reward'] = 1;
            $vip2_status  = 2;//显示后续任务
            $vip3_status = 1;  // 解锁 vip3
        }
        if (!empty($vip3_task)) {
            $vip3_task['lg_reward'] = $vip3_task['amount'] ?? 0;
            $vip3_task['bet_num_base_reward'] = 1;
            $vip3_status = 2;  //显示后续任务
        }

        $payChannels = is_array($config->pay_channels) ? $config->pay_channels : (get_object_vars($config->pay_channels) ?: []);
        $config['pay_channels'] = (new \app\common\service\PayGatewayService())->getAvailablePayChannels($this->userInfo['id'], $payChannels);

// 返回成功响应
        $this->success(__('Get recharge config success'), [
            'config' => $config,
            'vip1_status' => $vip1_status,
            'vip2_status' => $vip2_status,
            'vip3_status' => $vip3_status,
            'vip1_task' => $vip1_task,
            'vip2_task' => $vip2_task,
            'vip3_task' => $vip3_task,
        ]);

    }







    //领取投注奖励
    public function getBetNumReward(){
        $level = $this->request->param('level');
        if (!$level) {
            $this->error(__('Please provide vip level to receive'));
        }
        $task =DepositVipUser::where(['user_id'=>$this->userInfo['id'],'level'=>$level])->find();
        if(!$task){
            $this->error(__('Vip task not found'));
        }
        if (bccomp($task['bet_money_sum'], $task['bet_num_base'], 2) < 0) {
            $this->error(__('Not meet receive conditions'));
        }

        // 计算可领取次数（向下取整）
        $times = (int)bcdiv($task['bet_money_sum'], $task['bet_num_base'], 0);
        //设定每满足一次 1美元
        $reward_base = 1;
        $amount = bcmul($reward_base ,$times,2);

        // 计算本次发完之后总金额
        $totalAfter = bcadd($amount, $task['bet_num_reward'], 2);

        $maxAmount = $task['bet_num_max'];
        $alreadyAmount =$task['bet_num_reward'];
        //是否领取完
        $bet_num_status =0;
        if (bccomp($totalAfter, $maxAmount, 2) > 0) {
            // 超出 → 只发差额（补满）
            $diff = bcsub($maxAmount,$alreadyAmount  , 2);

            if (bccomp($diff, 0, 2) <= 0) {
                // 差额 ≤ 0，已领完
                $this->error(__('Reached maximum reward limit, cannot continue to receive'));
            }

            $amount = $diff;
            $bet_num_status =1;
        }

        Db::startTrans();
        try {
            $logTypeId = CoinLog::DepositVip;

            $this->getAccountService()->increaseBalance(
                userId: $this->userInfo['id'],
                amount: $amount,
                walletType: 1,
                logTypeId: $logTypeId,  // ✅ 使用定义的常量
                note: CoinLog::getTypeText($logTypeId) . ": " . __('Claim %s cumulative bet reward', [$level])
            );

            //增加已领取金额
            $task->setInc('bet_num_reward',$amount);
            //减去耗损次数
            $task->setDec('bet_money_sum',bcmul($times,$task->bet_num_base,0));
            if($bet_num_status) {
                $task->bet_num_status = $bet_num_status;
                $task->save();
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Receive failed: %s', [$e->getMessage()]));
        }
        $this->success('OK');
    }





    //领取累计投注金奖励
    public  function  getBetMoneyReward()
    {
        $level = $this->request->param('level');
        if (!$level) {
            $this->error(__('Please provide vip level to receive'));
        }
        $task =DepositVipUser::where(['user_id'=>$this->userInfo['id'],'level'=>$level])->find();
        if(!$task){
            $this->error(__('Not participate in activity'));
        }
        $bet_money_sum = $task['bet_money_sum'];
        $bet_money_multiple = $task['bet_money_multiple'];
        $reg_amount = $task['amount'];

        if (bcmul($reg_amount, $bet_money_multiple, 2) > $bet_money_sum) {
            $this->error(__('Not meet requirements'));
        }
        Db::startTrans();
        try {
            $amount = $task['bet_test_reward'];
            $task->bet_test_status = 1;
            $task->save();
            $logTypeId = CoinLog::DepositVip;

            $this->getAccountService()->increaseBalance(
                userId: $this->userInfo['id'],
                amount: $amount,
                walletType: 1,
                logTypeId: $logTypeId,  // ✅ 使用定义的常量
                note: CoinLog::getTypeText($logTypeId) . ": " . __('Claim %s cumulative investment reward', [$level])
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Receive failed: %s', [$e->getMessage()]));
        }
        $this->success('OK');
    }
}