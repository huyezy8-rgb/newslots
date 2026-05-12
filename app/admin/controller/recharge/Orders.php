<?php

namespace app\admin\controller\recharge;

use app\common\controller\Backend;
use app\common\model\recharge\Orders as OrdersModel;
use app\common\model\Account;
use app\common\service\AccountService;
use app\api\enum\CoinLog;
use think\facade\Db;
use think\facade\Log;
use think\exception\HttpResponseException;

/**
 * 充值订单管理
 */
class Orders extends Backend
{
    /**
     * Orders模型对象
     * @var object
     * @phpstan-var OrdersModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['id', 'order_no', 'user_id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new OrdersModel();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        // 添加渠道权限过滤
        $where = $this->addChannelFilter($where, 'channel_id');
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }



    /**
     * 手动回调订单
     * @throws Throwable
     */
    public function manualCallback(): void
    {
        $id = $this->request->post('id');
        if (!$id) {
            $this->error('参数错误');
        }

        $order = $this->model->find($id);
        if (!$order) {
            $this->error('订单不存在');
        }

        // 检查订单状态，只有未支付成功(0待支付或2失败)的订单才能手动回调
        if ($order->pay_status == 1) {
            $this->error('订单已支付成功，无需回调');
        }

        Db::startTrans();
        try {
            Log::info("手动回调开始，订单号: {$order->order_no}, 订单ID: {$id}");

            // 1. 更新订单状态为支付成功
            Db::name('recharge_orders')
                ->where('id', $id)
                ->update([
                    'pay_status' => 1,
                    'paid_at' => time(),
                    'remark' => '管理员手动回调',
                    'updated_at' => time()
                ]);

            Log::info("订单状态已更新为支付成功，订单号: {$order->order_no}");

            // 2. 增加用户余额（直接在事务中执行，避免嵌套事务问题）
            $logTypeId = CoinLog::Recharge;
            $walletType = CoinLog::getWalletType(CoinLog::walletType($logTypeId));
            $walletField = $walletType == 1 ? 'recharge_wallet' : 'experience_wallet';
            
            // 锁定用户账户并获取信息
            $account = Db::name('account')
                ->where('id', $order->user_id)
                ->lock(true)
                ->find();
            
            if (!$account) {
                throw new \Exception("用户不存在: {$order->user_id}");
            }
            
            $oldBalance = (float)$account[$walletField];
            $newBalance = bcadd($oldBalance, $order->reg_amount, 6);
            
            // 使用 Db::name() 直接更新余额，确保在事务中
            $updateResult = Db::name('account')
                ->where('id', $order->user_id)
                ->update([
                    $walletField => $newBalance,
                    'update_time' => time()
                ]);
            
            if ($updateResult === false) {
                throw new \Exception("更新用户余额失败");
            }
            
            Log::info("用户余额更新SQL执行完成，用户ID: {$order->user_id}, 更新行数: {$updateResult}");
            
            // 记录余额变动日志
            $logId = Db::name('account_coin_log')->insertGetId([
                'user_id'     => $order->user_id,
                'channel_id'  => $account['channel_id'],
                'wallet_type' => $walletType,
                'old_num'     => $oldBalance,
                'num'         => $order->reg_amount,
                'new_num'     => $newBalance,
                'log_type_id' => $logTypeId,
                'note'        => "充值完成 [充值单号:{$order->order_no}] [管理员手动回调]",
                'create_time' => time(),
                'update_time' => time(),
            ]);

            if (!$logId) {
                throw new \Exception("记录余额变动日志失败");
            }

            Log::info("用户余额已增加，用户ID: {$order->user_id}, 金额: {$order->reg_amount}, 旧余额: {$oldBalance}, 新余额: {$newBalance}, 日志ID: {$logId}");

            // 3. 更新用户充值累计
            $sumRechargeResult = Db::name('account')
                ->where('id', $order->user_id)
                ->inc('sum_recharge', $order->amount)
                ->update();
            
            if ($sumRechargeResult === false) {
                throw new \Exception("更新用户充值累计失败");
            }
            
            Log::info("用户充值累计更新完成，用户ID: {$order->user_id}, 金额: {$order->amount}, 更新行数: {$sumRechargeResult}");

            // 先提交核心数据的事务，避免嵌套事务问题
            Db::commit();
            Log::info("核心数据事务已提交，订单号: {$order->order_no}, 订单ID: {$id}");
            
            // 验证核心数据是否真正更新
            $verifyOrder = Db::name('recharge_orders')->where('id', $id)->find();
            $verifyAccount = Db::name('account')->where('id', $order->user_id)->find();
            
            Log::info("事务提交后验证 - 订单状态: " . ($verifyOrder['pay_status'] ?? 'null') . ", 用户余额: " . ($verifyAccount[$walletField] ?? 'null'));
            
                       // 核心数据提交成功后，再处理事件
            // 注意：这里所有附加逻辑都不能影响手动回调成功提示
            try {
                $notify = new \app\api\controller\Notify();
                $reflection = new \ReflectionClass($notify);

                // 获取订单数组格式，用于 handleEvent
                $orderArray = Db::name('recharge_orders')->where('id', $id)->find();

                $handleEventMethod = $reflection->getMethod('handleEvent');
                $handleEventMethod->setAccessible(true);
                $handleEventMethod->invoke($notify, $order->user_id, $orderArray);

                Log::info("充值活动处理完成，订单号: {$order->order_no}");
            } catch (\Throwable $e) {
                Log::warning("充值活动处理失败，但核心回调已成功，订单号: {$order->order_no}, 错误: " . $e->getMessage());
            }

            // 5. PDD邀请奖励检测
            try {
                \app\common\service\PddService::handleUserRechargeQualified($order->user_id);
            } catch (\Throwable $e) {
                Log::warning("PDD邀请奖励检测失败，用户ID: {$order->user_id}, 错误: " . $e->getMessage());
            }

            // 6. 触发事件（用户升级、有效用户等）
            try {
                // 用户升级事件
                event('LevelUp', $order->user_id);
                
                // 有效用户事件
                event('InviteMember', $order->user_id);
                
                Log::info("事件触发完成，订单号: {$order->order_no}");
            } catch (\Throwable $eventException) {
                // 事件触发失败不影响主流程，只记录日志
                Log::warning("事件触发失败，订单号: {$order->order_no}, 错误: " . $eventException->getMessage());
            }
            
            $this->success('手动回调成功');
        } catch (HttpResponseException $e) {
            // HttpResponseException 是框架正常的响应异常，直接抛出
            throw $e;
        } catch (\Throwable $e) {
            try {
                Db::rollback();
            } catch (\Throwable $rollbackException) {
                Log::warning("事务回滚失败或事务已提交: " . $rollbackException->getMessage());
            }

            $errorMsg = $e->getMessage() ?: '未知错误';
            $errorTrace = $e->getTraceAsString();
            
            Log::error("手动回调失败，订单ID: {$id}, 错误信息: {$errorMsg}, 错误堆栈: {$errorTrace}");
            
            $this->error('手动回调失败: ' . $errorMsg);
        }
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}