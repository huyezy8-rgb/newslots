<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\controller\Api;
use app\common\model\activity\FirstDeposit270User;
use app\common\model\recharge\Orders;
use app\common\service\AccountService;
use app\common\service\PayGatewayService;
use think\App;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class FirstDeposit270 extends Base
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
        $popupKey = "first_deposit_2hr_last_popup:$userId";
        $startKey = "first_deposit_2hr_start:$userId";

        // 检查是否已充值
        $reg_status = 0;
        $hasDeposit = \app\common\model\Account::where(['id'=>$userId,'switch_wallet'=>1])->find();
        if($hasDeposit){
            $reg_status = 1;
        }
        //检查是否参与活动
        $activity_status =0;
        $order = Orders::where(['user_id'=>$userId,'event_name'=>'first_deposit_270','pay_status'=>1])->find();
        if($order){
            $activity_status = 1;
        }
        
        // 读取唯一配置，id=1
        $config = \app\common\model\activity\FirstDeposit270::where(['id'=>1])->find();

        if (!$config) {
            $this->error(__('Recharge config not exist')); // 充值配置不存在
        }
        $endTime = 0;
        $task_status = 0;
        //查询270 活动参与
        $task = FirstDeposit270User::where(['user_id' => $userId])->find();
        
        if ($task) {
            // 只要在进行任务，就不设置倒计时和缓存

            
            // 判断任务领取状态，只要一个字段：所有奖励领取完成后 task_status = 1
            if ($this->isTaskCompleted($task)) {
                $task_status = 1;
            }
        } else {
            // 未参与任务时，才设置倒计时和缓存

            
            // 获取上次弹窗时间
            $lastPopup = Cache::get($popupKey);
            $currentTime = time();
            $countdownSeconds = (int)($config->countdown_seconds ?: 7200);
            $lastPopupDay = $lastPopup ? date('Y-m-d', (int)$lastPopup) : '';
            
            // 检查是否可以弹窗（每个自然日或从未弹过）
            $canPopup = !$lastPopup || $lastPopupDay !== date('Y-m-d', $currentTime);

            if ($canPopup) {
                // 可以弹窗，设置新的弹窗时间和倒计时
                $startTime = $currentTime;
                Cache::set($popupKey, $startTime, 86400 * 2);      // 记录最近弹窗日期
                Cache::set($startKey, $startTime, $countdownSeconds);       // 设置倒计时
                $endTime = $startTime + $countdownSeconds; // 倒计时
            } else {
                // 当天已弹窗，检查是否有未过期的倒计时
                $startTime = Cache::get($startKey);
                if ($startTime && ($currentTime - $startTime) < $countdownSeconds) {
                    $endTime = $startTime + $countdownSeconds; // 继续显示倒计时
                } else {
                    $endTime = 0; // 倒计时已过期
                }
            }
        }

        $payChannels = is_array($config->pay_channels) ? $config->pay_channels : (get_object_vars($config->pay_channels) ?: []);
        $availableChannels = (new \app\common\service\PayGatewayService())->getAvailablePayChannels($this->userInfo['id'], $payChannels);

        // 返回成功响应
        $this->success(__('Get recharge config success'), [
                'title'=>$config->title,
                'context'=>$config->context,
                'amount_list'=>$config->amount_list,
                'pay_channels'=>$availableChannels,
                'reward_strategy'=>$config->reward_strategy,
                'enable_reward'=>$config->enable_reward,
                'reward_value'=>$config->reward_value,
                'popup_enabled' => $config->popup_enabled,
                'endTime'=> $endTime,
                'reg_status'=>$reg_status,
                'task_status' => $task_status,
                'activity_status' => $activity_status
                ]
        );
    }

    //获取任务详情
    public function task()
    {
        // 读取唯一配置，id=1
        $config = \app\common\model\activity\FirstDeposit270::where(['id'=>1])->find();
        $task =FirstDeposit270User::where(['user_id'=>$this->userInfo['id']])->find();
        if(!$task){
            $this->success('Not participate in activity');// 未参与活动
        }

        $bonus = bcdiv(bcmul($task->amount,$config->reward_percent,2),100,2);
        //TODO::改为sql设置
        $title = "You have obtainedBonus $bonus";
        $content = "Deposit any amount to unlock withdrawals.Withdrawals may arrive in as fast as 30seconds.Get over $10 in free bonuses daily";
        //判断今日是否能领取
        $lastRewardTime = $task['day_reward_time'] ?? 0;
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        if ($lastRewardTime < $todayStart) {
            // 可以领取
            $day_status = 1;
        } else {
            // 今天已领取过
            $day_status = 0;
        }
        $start_day = strtotime(date('Y-m-d', $task['create_time'])); // 起始日 00:00:00

        $diff_days = floor(($todayStart - $start_day) / 86400) + 1; // 加 1 表示第 N 天
        if ($diff_days<2) {
            $day_status = 0;
        }
        if (time() > $task['expire_time']) {
            $day_status = 0;
        }
        unset($task->user_id);
        unset($task->channel_id);
        
        // 判断任务领取状态
        $task_status = $this->isTaskCompleted($task) ? 1 : 0;

        //
        $task['bet_num_base_reward']=$config['bet_sum_reward']->reward;
        // 返回成功响应
        $this->success('ok', [
                'title'=>$title,
                'context'=>$content,
                'day_status'=>$day_status,
                'lg_reward'=> bcdiv(bcmul($task->amount,$config->lg_reward_percent,2),100,2),
                'bonus'=>$bonus,
                'task'=>$task,
                'task_status' => $task_status
            ]
        );
    }


    //领取投注奖励
    public function getBetNumReward(){
        // 读取唯一配置，id=1
        $config = \app\common\model\activity\FirstDeposit270::where(['id'=>1])->find();
        $task =FirstDeposit270User::where(['user_id'=>$this->userInfo['id']])->find();
        if(!$task){
            $this->error(__('Not participate in activity')); // 未参与活动
        }
        if (bccomp($task['bet_money_sum'], $task['bet_num_base'], 2) < 0) {
            $this->error(__('Not meet receive conditions')); // 不满足领取条件
        }

        // 计算可领取次数（向下取整）
        $times = (int)bcdiv($task['bet_money_sum'], $task['bet_num_base'], 0);

        $amount = bcmul($config['bet_sum_reward']->reward ,$times,2);

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
                $this->error(__('Reached maximum reward limit, cannot continue to receive')); // 已达最大奖励上限，无法继续领取
            }

            $amount = $diff;
            $bet_num_status =1;
        }

        Db::startTrans();
        try {
            $logTypeId = CoinLog::FirstDeposit270;

            $this->getAccountService()->increaseBalance(
                userId: $this->userInfo['id'],
                amount: $amount,
                walletType: 1,
                logTypeId: $logTypeId,  // ✅ 使用定义的常量
                note: CoinLog::getTypeText($logTypeId) . ":领取累计投注奖励"
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
           $this->error(__('Receive failed') . '：'.$e->getMessage()); // 领取失败
        }
        $this->success(__('OK')); // 成功
    }


    //领取每日奖励
    public  function  getDayReward()
    {
        $task =FirstDeposit270User::where(['user_id'=>$this->userInfo['id']])->find();
        if(!$task){
            $this->error(__('Not participate in activity')); // 未参与活动
        }
        //判断今日是否能领取
        $lastRewardTime = $task['day_reward_time'] ?? 0;
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        if ($lastRewardTime > $todayStart) {
            // 今天已领取过
           $this->error(__('Today has received')); // 今日已领取
        }
        $start_day = strtotime(date('Y-m-d', $task['create_time'])); // 起始日 00:00:00

        $diff_days = floor(($todayStart - $start_day) / 86400) + 1; // 加 1 表示第 N 天
        if ($diff_days < 2) {
            $this->error(__('Not reach receive time')); // 未到领取时间
        }
        $index = $diff_days - 2;
        Db::startTrans();
        try {
//            $day_reward = get_object_vars($task['day_reward']);
//            $amount = $day_reward[$diff_days-2]['reward'];
//            $day_reward[$diff_days-2]['status'] =1;
//            $task->day_reward = $day_reward;
//            $task->day_reward_time = time();
//            $task->save();

            // ========== 原逻辑：获取奖励数组 ==========
            $day_reward = $task['day_reward'] ?? [];
            if (is_object($day_reward)) {
                $day_reward = get_object_vars($day_reward);
            }
            $day_reward = (array)$day_reward;

            // 判断当前奖励是否存在
            if (!isset($day_reward[$index])) {
                throw new \Exception(__('Reward configuration error, cannot receive'));
            }
            if (!empty($day_reward[$index]['status'])) {
                throw new \Exception(__('Already received today'));
            }

            $amount = $day_reward[$index]['reward'] ?? 0;
            if ($amount <= 0) {
                throw new \Exception(__('Reward amount error'));
            }

            foreach ($day_reward as $key => &$item) {
                // 只处理【今天之前】的天数
                if ($key < $index) {
                    // 如果之前没领（0），就改成 3
                    if ($item['status'] == 0) {
                        $item['status'] = 3;
                    }
                }
            }
            unset($item);
            $day_reward[$index]['status'] = 1;
            $task->day_reward = $day_reward;
            $task->day_reward_time = time();
            $task->save();

            $logTypeId = CoinLog::FirstDeposit270;
            $this->getAccountService()->increaseBalance(
                userId: $this->userInfo['id'],
                amount: $amount,
                walletType: 1,
                logTypeId: $logTypeId,  // ✅ 使用定义的常量
                note: CoinLog::getTypeText($logTypeId) . ":领取每日奖励"
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Receive failed') . '：'.$e->getMessage()); // 领取失败
        }
        $this->success(__('OK')); // 成功
    }


    //领取累计投注金奖励
    public  function  getBetMoneyReward()
    {
        $task =FirstDeposit270User::where(['user_id'=>$this->userInfo['id']])->find();
        if(!$task){
            $this->error(__('Not participate in activity')); // 未参与活动
        }
        $bet_money_sum = $task['bet_money_sum'];
        $bet_money_multiple = $task['bet_money_multiple'];
        $reg_amount = $task['amount'];

        if (bcmul($reg_amount, $bet_money_multiple, 2) > $bet_money_sum) {
            $this->error(__('Not meet requirements')); // 未满足要求
        }
        Db::startTrans();
        try {
            $amount = $task['bet_test_reward'];
            $task->bet_test_status = 1;
            $task->save();
            $logTypeId = CoinLog::FirstDeposit270;

            $this->getAccountService()->increaseBalance(
                userId: $this->userInfo['id'],
                amount: $amount,
                walletType: 1,
                logTypeId: $logTypeId,  // ✅ 使用定义的常量
                note: CoinLog::getTypeText($logTypeId) . ":领取累计投资金额奖励"
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Receive failed') . '：'.$e->getMessage()); // 领取失败
        }
        $this->success(__('OK')); // 成功
    }

    /**
     * 判断任务是否完成
     * @param $task FirstDeposit270User 任务记录
     * @return bool 是否完成
     */
    private function isTaskCompleted($task)
    {
        // 检查任务是否过期
        if (time() > $task['expire_time']) {
            return true; // 过期也算任务完成
        }

        // 判断每日奖励完成状态
        $dayReward = $task['day_reward'] ?? [];
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $startDay = strtotime(date('Y-m-d', $task['create_time']));
        $currentDay = floor(($todayStart - $startDay) / 86400) + 1;
        
        $dayRewardCompleted = true;
        foreach ($dayReward as $index => $reward) {
            $dayNumber = $index + 1;
            if ($dayNumber <= $currentDay && (!isset($reward['status']) || $reward['status'] != 1)) {
                $dayRewardCompleted = false;
                break;
            }
        }

        // 判断投注次数奖励完成状态
        $betNumCompleted = $task['bet_num_status'] == 1;

        // 判断累计投注金额奖励完成状态
        $betMoneyCompleted = $task['bet_test_status'] == 1;

        // 所有奖励都完成
        return $dayRewardCompleted && $betNumCompleted && $betMoneyCompleted;
    }

}
