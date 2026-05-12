<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\controller\Api;
use app\common\model\activity\FirstDeposit25User;
use app\common\model\activity\FirstDeposit270User;
use app\common\model\recharge\Orders;
use app\common\service\AccountService;
use app\common\service\PayGatewayService;
use think\App;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class FirstDeposit25 extends Base
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
        $reg_status = 0;

        // 读取活动配置
        $config = \app\common\model\activity\FirstDeposit25::where(['id'=>1])->find();
        if (!$config) {
            $this->error(__('Recharge config not exist'));
        }

        // 解析 amount_list，确保是数组格式
        $amountList = is_array($config->amount_list)
            ? $config->amount_list
            : json_decode(json_encode($config->amount_list), true);

        // 查找用户任务记录
        $task = FirstDeposit25User::where('user_id', $userId)->find();

        // 如果不存在，则新建一条记录
        if (!$task) {
            $task_status = array_fill(0, count($amountList), 0);
            $task = new FirstDeposit25User();
            $task->user_id = $this->userInfo['id'];
            $task->channel_id = $this->userInfo['channel_id'] ?? 0;
            $task->task_status = $task_status;
            $task->receive_status = 0;
            $task->create_time = time();
            $task->save();
        }

        if ($task->receive_status == 2) {
            $reg_status = 1;
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
                'reg_status' => $reg_status
            ]
        );
    }



}