<?php

namespace app\api\controller;


use app\api\enum\CoinLog;
use app\common\model\activity\FirstDeposit270User;
use app\common\model\activity\FirstDepositDailyUser;
use app\common\service\AccountService;
use think\App;
use think\facade\Db;


class FirstDepositDaily extends Base
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


        // 读取唯一配置，id=1
        $config = \app\common\model\activity\FirstDepositDaily::where(['id' => 1])->find();
        if (!$config) {
            $this->error(__('Recharge config not exist')); // 充值配置不存在
        }
        //查找用户任务记录
        $task = FirstDepositDailyUser::where('user_id', $userId)->find();
        $amountList = is_array($config->amount_list)
            ? $config->amount_list
            : json_decode($config->amount_list, true); // 解析 JSON 配置
        // 如果不存在，则新建一条记录
        if (!$task) {

            $task_status = array_fill(0, count($amountList), 0); // 初始化所有档位为 0$
            $task = new FirstDepositDailyUser();
            $task->user_id = $this->userInfo['id'];
            $task->channel_id = $this->userInfo['channel_id']; // 如果没有 channelId，默认 0
            $task->task_status = $task_status; // 默认 3 个档位，状态 0
            $task->receive_status = 0; // 默认未领取
            $task->create_time = time();
            $task->save();
        }

        // 处理 task_status 数据
        $taskStatus = $task->task_status;
        if (is_object($taskStatus)) {
            $taskStatus = (array)$taskStatus;
        } elseif (is_string($taskStatus)) {
            $taskStatus = json_decode($taskStatus, true);
        }

        // 合并 amount_list 和 task_status
        $mergedAmountList = [];
        foreach ($amountList as $index => $item) {
            $mergedAmountList[] = [
                'amount' => $item['amount'] ?? $item, // 兼容新旧格式
                'recommend' => $item['recommend'] ?? false,
                'reward_percent' => $item['reward_percent'] ?? 0,
                'status' => $taskStatus[$index] ?? 0
            ];
        }
        $payChannels = is_array($config->pay_channels) ? $config->pay_channels : (get_object_vars($config->pay_channels) ?: []);
        $availableChannels = (new \app\common\service\PayGatewayService())->getAvailablePayChannels($this->userInfo['id'], $payChannels);
        // 返回成功响应
        $this->success(
            __('Get recharge config success'),
            [
                'title' => $config->title,
                'context' => $config->context,
                'amount_list' => $mergedAmountList,
                'pay_channels' => $availableChannels,
                'enable_reward' => $config->reward_strategy,
                'reward_value' => $config->reward_value,
                'task_reward' => $config->task_reward,
                'task_status' => $task->task_status,
                'receive_status' =>    $task->receive_status,
            ]
        );
    }

    public  function  getReward()
    {
        $config = \app\common\model\activity\FirstDepositDaily::where(['id' => 1])->find();
        if (!$config) {
            $this->error(__('Config not exist')); // 配置不存在
        }
        $task = FirstDepositDailyUser::where(['user_id' => $this->userInfo['id']])->find();
        if (!$task) {
            $this->error(__('Not participate in activity')); // 未参与活动
        }

        if ($task->receive_status != 1) {
            $this->error(__('Cannot receive')); // 不可领取
        }
        Db::startTrans();
        try {

            $task->receive_status = 2;
            $task->save();
            $logTypeId = CoinLog::FirstDepositDaily;

            $this->getAccountService()->increaseBalance(
                userId: $this->userInfo['id'],
                amount: $config->task_reward,
                walletType: 1,
                logTypeId: $logTypeId,  // ✅ 使用定义的常量
                note: CoinLog::getTypeText($logTypeId) . ":领取每日首充完成任务奖励"
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Receive failed') . '：' . $e->getMessage()); // 领取失败
        }
        $this->success(__('OK')); // 成功
    }
}
