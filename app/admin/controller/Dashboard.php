<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\service\OperationDataService;
use app\common\model\OperationData;
use think\facade\Db;

/**
 * 仪表板控制器 - 数据统计计算说明
 * 
 * ==================== 数据计算方式总览 ====================
 * 
 * 1. 用户相关统计：
 *    - 注册用户数：基于 account 表的 reg_time 字段统计当日新注册用户
 *    - 活跃用户数：基于 user_login_game_log 表统计当日有登录记录的去重用户数
 *    - 在线用户数：基于 Redis 的 online_users 集合或 account 表的 last_login_time 字段
 *    - 付费用户数：基于 recharge_orders 表统计当日有成功充值记录的去重用户数
 *    - 留存用户数：基于 game_transactions 表统计当日有游戏记录的去重用户数
 * 
 * 2. 充值相关统计：
 *    - 充值总额：基于 recharge_orders 表统计当日成功充值订单的金额总和
 *    - 首充用户数：统计当日充值的用户中，在此日期之前没有成功充值记录的用户数
 *    - 首充金额：统计当日首充用户的实际充值金额总和
 *    - 新用户首充：统计当日注册且当日充值的用户数和金额
 *    - 注充率：注册用户数 / 付费用户数
 *    - 支付成功率：成功充值订单数 / 总支付订单数 * 100%
 * 
 * 3. 提现相关统计：
 *    - 提现用户数：基于 withdraw_orders 表统计当日提现成功的去重用户数
 *    - 提现金额：基于 withdraw_orders 表统计当日提现成功订单的金额总和
 *    - 提现率：提现金额 / 充值总额
 * 
 * 4. 游戏相关统计：
 *    - 下注金额：基于 game_transactions 表统计 reason='bet' 且 wallet_type=1 的金额总和
 *    - 下注数量：基于 game_transactions 表统计 reason='bet' 的订单数量
 *    - 返奖金额：基于 game_transactions 表统计 reason='win' 且 wallet_type=1 的金额总和
 *    - 玩家盈亏：返奖金额 - 下注金额
 * 
 * 5. 活动相关统计：
 *    - 活动彩金：基于 account_coin_log 表统计活动相关的奖励金额（wallet_type=1，num>0）
 *    - 活动提现：基于 account_coin_log 表统计专门的提现 log_type_id（wallet_type=1，num>0）
 * 
 * 6. 数据源优先级：
 *    - 优先使用 OperationData 预计算数据表（提高性能）
 *    - 预计算数据不可用时，使用实时计算逻辑
 * 
 * 7. 渠道过滤：
 *    - 支持按渠道ID过滤统计数据
 *    - 渠道过滤会影响所有相关统计指标
 * 
 * ==================== 关键字段说明 ====================
 * 
 * wallet_type 字段含义：
 *    - 1：充值钱包（现金钱包）
 *    - 2：体验钱包
 * 
 * pay_status 字段含义：
 *    - 0：未支付
 *    - 1：支付成功
 * 
 * withdraw_orders.status 字段含义：
 *    - 1：待审核
 *    - 2：提现成功
 *    - 3：提现失败
 * 
 * game_transactions.reason 字段含义：
 *    - bet：下注
 *    - win：中奖
 * 
 * ==================== 性能优化策略 ====================
 * 
 * 1. 使用预计算数据表 OperationData 减少实时查询
 * 2. 批量查询减少数据库连接次数
 * 3. 使用子查询优化首充统计逻辑
 * 4. 合理使用索引（时间范围、用户ID、状态字段）
 * 5. 缓存在线用户数据到 Redis
 */
class Dashboard extends Backend
{
    /**
     * 仪表板主入口 - 数据统计计算
     * 
     * 计算流程：
     * 1. 解析日期参数（默认今日）
     * 2. 解析渠道参数（支持渠道过滤）
     * 3. 优先使用预计算数据（OperationData表）
     * 4. 预计算数据不可用时使用实时计算
     * 5. 计算支付成功率
     * 6. 返回格式化后的统计数据
     */
    public function index(): void
    {
        // ==================== 参数解析 ====================
        
        // 获取 date 参数，默认今日
        $date = $this->request->get('date');
        if ($date) {
            $startOfDay = strtotime($date . ' 00:00:00');
            $endOfDay = strtotime($date . ' 23:59:59');
        } else {
            $date = date('Y-m-d');
            $startOfDay = strtotime($date . ' 00:00:00');
            $endOfDay = strtotime($date . ' 23:59:59');
        }
        
        // 获取 channel_id 参数，支持渠道过滤
        $userIds = [];
        $channelId = $this->request->get('channel_id');
        
        // 获取当前登录管理员绑定的渠道id（权限控制）
        if ($this->getCurrentAdminChannelId() !== null) {
            $channelId = $this->getCurrentAdminChannelId();
        }
        
        // 如果指定了渠道，获取该渠道下的用户ID列表
       if ($channelId) {
    $userIds = Db::name('account')
        ->where('channel_id', $channelId)
        ->column('id');
}

        // ==================== 数据源选择策略 ====================
        
        // 优先使用预计算数据表（OperationData）提高性能
        $operationData = null;
        try {
            // 尝试从预计算数据表获取数据
            $operationData = OperationData::getByDateListAndChannel([$date], $channelId);
            if (isset($operationData[$date])) {
                $operationData = $operationData[$date]['data'];
            } else {
                $operationData = null;
            }
        } catch (\Exception $e) {
            // 如果获取失败，回退到实时计算
            $operationData = null;
        }

        // ==================== 统计数据计算 ====================
        $data = [];

        if ($operationData) {
            // ==================== 使用预计算数据模式 ====================
            // 从 OperationData 表获取预计算的数据，减少实时查询
            
            // 基础用户统计（仍需要实时计算，因为预计算数据可能不包含）
            $registeredUsers = $this->getRegisteredUsers($startOfDay, $endOfDay, $channelId, $userIds);
            
            // 从预计算数据获取核心指标
            $paidUsers = $this->getPaidUsers($startOfDay, $endOfDay, $channelId, $userIds);
$totalRechargeAmount = $this->getTotalRechargeAmount($startOfDay, $endOfDay, $channelId, $userIds);
            $withdrawalAmount = $operationData['all_withdraw_amount'] ?? 0; // 提现金额
            
            $data = [
                // ==================== 用户统计指标 ====================
                
                // 注册人数：基于 account 表的 reg_time 字段统计当日新注册用户
                'registeredUsers' => $registeredUsers,
                
                // 活跃人数：基于 user_login_game_log 表统计当日有登录记录的去重用户数
                'activeUsers' => $this->getActiveUsers($startOfDay, $endOfDay, $channelId, $userIds),
                
                // 付费人数：基于 recharge_orders 表统计当日有成功充值记录的去重用户数（使用预计算数据）
                'paidUsers' => $paidUsers,
                
                // 留存人数：基于 game_transactions 表统计当日有游戏记录的去重用户数（使用预计算数据）
                'retentionUsers' => $operationData['order_users'] ?? 0,
                
                // 实时在线人数：基于 Redis 的 online_users 集合或 account 表的 last_login_time 字段（仅今日显示）
                'onlineUsers' => $this->isToday($date) ? $this->getOnlineUsers($channelId, $userIds) : 0,
                
                // ==================== 首充相关统计 ====================
                
                // 首充用户数：统计当日充值的用户中，在此日期之前没有成功充值记录的用户数
                'firstChargeUsers' => $this->getFirstChargeUsers($startOfDay, $endOfDay, $channelId, $userIds),
                
                // 首充金额：统计当日首充用户的实际充值金额总和
                'firstChargeAmount' => $this->getFirstChargeAmount($startOfDay, $endOfDay, $channelId, $userIds),
                
                // 新用户首充用户数：统计当日注册且当日充值的用户数
                'newUserFirstChargeUsers' => $this->getNewUserFirstChargeUsers($startOfDay, $endOfDay, $channelId, $userIds),


'oldUserFirstChargeUsers' => max(
    $this->getFirstChargeUsers($startOfDay, $endOfDay, $channelId, $userIds)
    - $this->getNewUserFirstChargeUsers($startOfDay, $endOfDay, $channelId, $userIds),
    0
),

'oldUserFirstChargeAmount' => max(
    $this->getFirstChargeAmount($startOfDay, $endOfDay, $channelId, $userIds)
    - $this->getNewUserFirstChargeAmount($startOfDay, $endOfDay, $channelId, $userIds),
    0
),
                // 新用户首充金额：统计当日注册且当日充值的用户金额总和
                'newUserFirstChargeAmount' => $this->getNewUserFirstChargeAmount($startOfDay, $endOfDay, $channelId, $userIds),
                
                // ==================== 充值相关统计 ====================
                
                // 充值总额：基于 recharge_orders 表统计当日成功充值订单的金额总和（使用预计算数据）
                'totalRechargeAmount' => $totalRechargeAmount,
                
                // 注充率：注册用户数 / 付费用户数
                'registrationChargeRate' => $this->calculateRegistrationChargeRate(
    $registeredUsers,
    $this->getFirstChargeUsers($startOfDay, $endOfDay, $channelId, $userIds)
),
                
                // ==================== 提现相关统计 ====================
                
                // 提现用户数：基于 withdraw_orders 表统计当日提现成功的去重用户数
                'withdrawalUsers' => $this->getWithdrawalUsers($startOfDay, $endOfDay, $channelId, $userIds),
                
                // 提现金额：基于 withdraw_orders 表统计当日提现成功订单的金额总和（使用预计算数据）
                'withdrawalAmount' => $withdrawalAmount,
                
                // 提现率：提现金额 / 充值总额
                'withdrawalRate' => $this->calculateWithdrawalRate($totalRechargeAmount, $withdrawalAmount),
                
                // ==================== 游戏相关统计 ====================
                
                // 下注金额：基于 game_transactions 表统计 reason='bet' 且 wallet_type=1 的金额总和（使用预计算数据）
                'orderAmount' => $operationData['order_cash'] ?? 0,
                
                // 下注数量：基于 game_transactions 表统计 reason='bet' 的订单数量（使用预计算数据）
                'orderCount' => $operationData['order_count'] ?? 0,
                
                // 玩家盈亏：返奖金额 - 下注金额（使用预计算数据）
                'playerProfitLoss' => ($operationData['reward_amount'] ?? 0) - ($operationData['order_cash'] ?? 0),
                
                // ==================== 活动相关统计 ====================
                
                // 活动彩金：基于 account_coin_log 表统计活动相关的奖励金额（wallet_type=1，num>0）
                'activityCashGift' => $this->getActivityCashGift($startOfDay, $endOfDay, $channelId, $userIds),
                
                // 活动提现：基于 account_coin_log 表统计专门的提现 log_type_id（wallet_type=1，num>0）
                'activityWithdrawal' => $this->getActivityWithdrawal($startOfDay, $endOfDay, $channelId, $userIds),
                
                // ==================== 其他信息 ====================
                
                'selectedChannelId' => $channelId,
            ];
        } else {
            // ==================== 使用实时计算模式 ====================
            // 当预计算数据不可用时，使用实时计算逻辑
            $data = $this->getRealTimeData($startOfDay, $endOfDay, $channelId, $userIds, $date);
        }

        // ==================== 支付成功率计算 ====================
        // 计算公式：成功充值订单数 / 总支付订单数 * 100%
        $paymentOrderCount = $this->getPaymentOrderCount($startOfDay, $endOfDay, $channelId, $userIds);
        $successPaymentOrderCount = $this->getSuccessPaymentOrderCount($startOfDay, $endOfDay, $channelId, $userIds);
        $data['paymentSuccessRate'] = round(($successPaymentOrderCount / max($paymentOrderCount, 1)) * 100, 2);

        // ==================== 渠道信息获取 ====================
        // 获取所有渠道列表供前端选择
        $channels = Db::name('channel_list')->field("id, name")->select();
        $data["channel_list"] = $channels;

        // ==================== 数据格式化 ====================
        // 保留 4 位小数，确保数据精度
        array_walk($data, function (&$value) {
            if (is_numeric($value)) {
                $value = round($value, 4);
            }
        });

        $this->success(__('OK'), $data);
    }

    /**
     * 获取注册用户数
     * 
     * 计算方式：
     * - 数据源：account 表
     * - 条件：reg_time 在指定时间范围内
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return int 注册用户数
     */
    private function getRegisteredUsers(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): int
    {
        if ($channelId) {
            return Db::name('account')->whereBetweenTime('reg_time', $startOfDay, $endOfDay)->whereIn("id", $userIds)->count();
        } else {
            return Db::name('account')->whereBetweenTime('reg_time', $startOfDay, $endOfDay)->count();
        }
    }

    /**
     * 获取活跃用户数（基于登录日志）
     * 
     * 计算方式：
     * - 数据源：user_login_game_log 表
     * - 条件：create_time 在指定时间范围内
     * - 去重：使用 DISTINCT 确保每个用户只计算一次
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return int 活跃用户数
     */
    private function getActiveUsers(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): int
    {
        if ($channelId) {
            return Db::name('user_login_game_log')->whereBetweenTime('create_time', $startOfDay, $endOfDay)->distinct(true)->whereIn("user_id", $userIds)->count('user_id');
        } else {
            return Db::name('user_login_game_log')->whereBetweenTime('create_time', $startOfDay, $endOfDay)->distinct(true)->count('user_id');
        }
    }

    /**
     * 判断是否为今日
     * 
     * @param string $date 日期字符串
     * @return bool 是否为今日
     */
    private function isToday(string $date): bool
    {
        return $date === date('Y-m-d');
    }

    /**
     * 获取在线用户数（使用Redis查询）
     * 
     * 计算方式：
     * - 优先从 Redis 的 online_users 集合获取在线用户列表
     * - 如果 Redis 查询失败，回退到数据库查询 account 表的 last_login_time
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return int 在线用户数
     */
    private function getOnlineUsers(?int $channelId, array $userIds): int
    {
        try {
            // 从Redis获取在线用户列表
            $onlineUsers = \think\facade\Cache::store('redis')->sMembers('online_users');
            if (empty($onlineUsers)) {
                return 0;
            }

            // 处理在线用户数据
            if (is_array($onlineUsers)) {
                $onlineUsers = array_reduce($onlineUsers, function($carry, $item) {
                    return array_merge($carry, is_array($item) ? $item : [$item]);
                }, []);
                
                if (count($onlineUsers) === 1 && is_string($onlineUsers[0]) && strpos($onlineUsers[0], '[') === 0) {
                    $jsonDecoded = json_decode($onlineUsers[0], true);
                    if (is_array($jsonDecoded)) {
                        $onlineUsers = $jsonDecoded;
                    }
                }
                
                $onlineUsers = array_filter(array_map('intval', $onlineUsers));
            } else {
                $onlineUsers = [];
            }

            // 验证在线用户ID是否在数据库中真实存在，并应用渠道过滤
            if (!empty($onlineUsers)) {
                $query = Db::name('account')->whereIn('id', $onlineUsers);
                if ($channelId) {
                    $query->where('channel_id', $channelId);
                }
                // 只保留数据库中真实存在的用户ID
                $validOnlineUserIds = $query->column('id');
                $onlineUsers = array_intersect($onlineUsers, $validOnlineUserIds);
            } else {
                $onlineUsers = [];
            }

            return count($onlineUsers);
        } catch (\Exception $e) {
            // 如果Redis查询失败，回退到数据库查询
            if ($channelId) {
                // 如果userIds为空，说明该渠道没有用户，直接返回0
                if (empty($userIds)) {
                    return 0;
                }
                return Db::name('account')->where('last_login_time', '>=', date('Y-m-d H:i:s'))->whereIn("id", $userIds)->count();
            } else {
                return Db::name('account')->where('last_login_time', '>=', date('Y-m-d H:i:s'))->count();
            }
        }
    }

    /**
     * 获取游戏现金下注金额
     * 
     * 计算方式：
     * - 数据源：game_transactions 表
     * - 条件：reason='bet'（下注）且 wallet_type=1（现金钱包）
     * - 时间范围：req_time 在指定时间范围内
     * - 金额计算：取所有下注记录的 amount 绝对值求和
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return float 下注金额
     */
    private function getGameOrderCash(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): float
    {
        $query = Db::name('game_transactions')
            ->where('reason', 'bet')
            ->where('wallet_type', 1) // 现金钱包
            ->whereBetweenTime('req_time', date('Y-m-d', $startOfDay) . ' 00:00:00', date('Y-m-d', $endOfDay) . ' 23:59:59');
        
        if ($channelId) {
            $query->whereIn("user_id", $userIds);
        }
        
        $transactions = $query->select()->toArray();
        return array_sum(array_map(function($transaction) { 
            return abs($transaction['amount']); 
        }, $transactions));
    }

    /**
     * 获取游戏下注数量
     * 
     * 计算方式：
     * - 数据源：game_transactions 表
     * - 条件：reason='bet'（下注）
     * - 时间范围：req_time 在指定时间范围内
     * - 统计：直接统计符合条件的记录数量
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return int 下注数量
     */
    private function getGameOrderCount(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): int
    {
        $query = Db::name('game_transactions')
            ->where('reason', 'bet')
            ->whereBetweenTime('req_time', date('Y-m-d', $startOfDay) . ' 00:00:00', date('Y-m-d', $endOfDay) . ' 23:59:59');
        
        if ($channelId) {
            $query->whereIn("user_id", $userIds);
        }
        
        return $query->count();
    }

    /**
     * 获取游戏返奖金额
     * 
     * 计算方式：
     * - 数据源：game_transactions 表
     * - 条件：reason='win'（中奖）且 wallet_type=1（现金钱包）
     * - 时间范围：req_time 在指定时间范围内
     * - 金额计算：取所有中奖记录的 amount 绝对值求和
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return float 返奖金额
     */
    private function getGameRewardAmount(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): float
    {
        $query = Db::name('game_transactions')
            ->where('reason', 'win')
            ->where('wallet_type', 1) // 现金钱包
            ->whereBetweenTime('req_time', date('Y-m-d', $startOfDay) . ' 00:00:00', date('Y-m-d', $endOfDay) . ' 23:59:59');
        
        if ($channelId) {
            $query->whereIn("user_id", $userIds);
        }
        
        $transactions = $query->select()->toArray();
        return array_sum(array_map(function($transaction) { 
            return abs($transaction['amount']); 
        }, $transactions));
    }

    /**
     * 获取支付订单数量（用于计算支付成功率）
     * 
     * 计算方式：
     * - 数据源：recharge_orders 表
     * - 条件：created_at 在指定时间范围内
     * - 统计：所有支付订单数量（包括成功和失败）
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return int 支付订单数量
     */
    private function getPaymentOrderCount(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): int
    {
        if ($channelId) {
            return Db::name('recharge_orders')->whereIn("user_id", $userIds)->whereBetweenTime('created_at', $startOfDay, $endOfDay)->count();
        } else {
            return Db::name('recharge_orders')->whereBetweenTime('created_at', $startOfDay, $endOfDay)->count();
        }
    }

    /**
     * 获取成功支付订单数量（用于计算支付成功率）
     * 
     * 计算方式：
     * - 数据源：recharge_orders 表
     * - 条件：created_at 在指定时间范围内且 pay_status=1（支付成功）
     * - 统计：成功支付订单数量
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return int 成功支付订单数量
     */
    private function getSuccessPaymentOrderCount(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): int
{
    if ($channelId) {
        return Db::name('recharge_orders')->whereIn("user_id", $userIds)->whereBetweenTime('created_at', $startOfDay, $endOfDay)->where('pay_status', 1)->count();
    } else {
        return Db::name('recharge_orders')->whereBetweenTime('created_at', $startOfDay, $endOfDay)->where('pay_status', 1)->count();
    }
}


/**
 * 获取付费用户数
 */
private function getPaidUsers(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): int
{
    $query = Db::name('recharge_orders')
        ->where('pay_status', 1)
        ->whereBetweenTime('created_at', $startOfDay, $endOfDay);

    if ($channelId) {
        if (empty($userIds)) {
            return 0;
        }
        $query->whereIn('user_id', $userIds);
    }

    return $query->count('DISTINCT user_id');
}

/**
 * 获取充值总额
 */
private function getTotalRechargeAmount(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): float
{
    $query = Db::name('recharge_orders')
        ->where('pay_status', 1)
        ->whereBetweenTime('created_at', $startOfDay, $endOfDay);

    if ($channelId) {
        if (empty($userIds)) {
            return 0;
        }
        $query->whereIn('user_id', $userIds);
    }

    return (float)$query->sum('amount');
}

    /**
     * 获取总首充用户数（当日所有第一次付费的人数）
     * 
     * 计算方式：
     * - 数据源：recharge_orders 表
     * - 逻辑：统计当日充值的用户中，在此日期之前没有成功充值记录的用户数
     * - 步骤：
     *   1. 查询指定日期之前有成功充值记录的用户ID
     *   2. 查询当日所有成功的充值订单
     *   3. 排除之前有充值记录的用户
     *   4. 统计剩余用户的去重数量
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return int 首充用户数
     */
    private function getFirstChargeUsers(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): int
    {
        // 使用子查询优化：找出当日充值的用户中，在此日期之前没有成功充值记录的用户
        $subQuery = Db::name('recharge_orders')
            ->where('pay_status', 1)
            ->where('created_at', '<', $startOfDay)
            ->field('DISTINCT user_id');
        
        if ($channelId) {
            $subQuery->whereIn("user_id", $userIds);
        }
        
        $existingUsers = $subQuery->select()->toArray();
        $existingUserIds = array_column($existingUsers, 'user_id');
        
        // 查询当日所有成功的充值订单
        $query = Db::name('recharge_orders')
            ->whereBetweenTime('created_at', $startOfDay, $endOfDay)
            ->where('pay_status', 1);
        
        if ($channelId) {
            $query->whereIn("user_id", $userIds);
        }
        
        if (!empty($existingUserIds)) {
            $query->whereNotIn('user_id', $existingUserIds);
        }
        
        $orders = $query->select()->toArray();
        return count(array_unique(array_column($orders, 'user_id')));
    }

    /**
     * 获取总首充金额（当日所有第一次付费的金额）
     * 
     * 计算方式：
     * - 数据源：recharge_orders 表
     * - 逻辑：统计当日首充用户的实际充值金额总和
     * - 步骤：
     *   1. 查询指定日期之前有成功充值记录的用户ID
     *   2. 查询当日所有成功的充值订单
     *   3. 排除之前有充值记录的用户
     *   4. 对每个用户只取第一条记录（首次充值）
     *   5. 计算所有首充金额的总和
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return float 首充金额
     */
    private function getFirstChargeAmount(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): float
    {
        // 使用子查询优化：找出当日充值的用户中，在此日期之前没有成功充值记录的用户
        $subQuery = Db::name('recharge_orders')
            ->where('pay_status', 1)
            ->where('created_at', '<', $startOfDay)
            ->field('DISTINCT user_id');
        
        if ($channelId) {
            $subQuery->whereIn("user_id", $userIds);
        }
        
        $existingUsers = $subQuery->select()->toArray();
        $existingUserIds = array_column($existingUsers, 'user_id');
        
        // 查询当日所有成功的充值订单
        $query = Db::name('recharge_orders')
            ->whereBetweenTime('created_at', $startOfDay, $endOfDay)
            ->where('pay_status', 1);
        
        if ($channelId) {
            $query->whereIn("user_id", $userIds);
        }
        
        if (!empty($existingUserIds)) {
            $query->whereNotIn('user_id', $existingUserIds);
        }
        
        $orders = $query->select()->toArray();
        
        // 对每个用户只取第一条记录（首次充值）
        $userFirstCharges = [];
        foreach ($orders as $order) {
            $userId = $order['user_id'];
            if (!isset($userFirstCharges[$userId])) {
                $userFirstCharges[$userId] = $order['amount'];
            }
        }
        
        return array_sum($userFirstCharges);
    }

    /**
     * 获取新用户首充用户数（当日新增注册的客户付费人数）
     * 
     * 计算方式：
     * - 数据源：account 表 + recharge_orders 表
     * - 逻辑：统计当日注册且当日充值的用户数
     * - 步骤：
     *   1. 查询当日注册的用户ID列表
     *   2. 查询这些新用户中当日有成功充值记录的用户
     *   3. 统计去重后的用户数量
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return int 新用户首充用户数
     */
    private function getNewUserFirstChargeUsers(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): int
    {
        // 第一步：找出当日注册的用户
        $newUserQuery = Db::name('account')
            ->whereBetweenTime('reg_time', $startOfDay, $endOfDay);
        
        if ($channelId) {
            $newUserQuery->whereIn("id", $userIds);
        }
        
        $newUsers = $newUserQuery->field('id')->select()->toArray();
        $newUserIds = array_column($newUsers, 'id');
        
        if (empty($newUserIds)) {
            return 0;
        }
        
        // 第二步：统计这些新用户中当日有充值记录的用户数量
        $query = Db::name('recharge_orders')
            ->whereBetweenTime('created_at', $startOfDay, $endOfDay)
            ->where('pay_status', 1)
            ->whereIn('user_id', $newUserIds);
        
        $orders = $query->select()->toArray();
        
        return count(array_unique(array_column($orders, 'user_id')));
    }

    /**
     * 获取新用户首充金额（当日新增注册的客户付费所有金额）
     * 
     * 计算方式：
     * - 数据源：account 表 + recharge_orders 表
     * - 逻辑：统计当日注册且当日充值的用户金额总和
     * - 步骤：
     *   1. 查询当日注册的用户ID列表
     *   2. 查询这些新用户中当日有成功充值记录的用户
     *   3. 对每个用户只取第一条记录（首次充值）
     *   4. 计算所有首充金额的总和
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return float 新用户首充金额
     */
    private function getNewUserFirstChargeAmount(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): float
    {
        // 第一步：找出当日注册的用户
        $newUserQuery = Db::name('account')
            ->whereBetweenTime('reg_time', $startOfDay, $endOfDay);
        
        if ($channelId) {
            $newUserQuery->whereIn("id", $userIds);
        }
        
        $newUsers = $newUserQuery->field('id')->select()->toArray();
        $newUserIds = array_column($newUsers, 'id');
        
        if (empty($newUserIds)) {
            return 0;
        }
        
        // 第二步：统计这些新用户中当日有充值记录的用户金额
        $query = Db::name('recharge_orders')
            ->whereBetweenTime('created_at', $startOfDay, $endOfDay)
            ->where('pay_status', 1)
            ->whereIn('user_id', $newUserIds);
        
        $orders = $query->select()->toArray();
        
        // 对每个用户只取第一条记录（首次充值）
        $userFirstCharges = [];
        foreach ($orders as $order) {
            $userId = $order['user_id'];
            if (!isset($userFirstCharges[$userId])) {
                $userFirstCharges[$userId] = $order['amount'];
            }
        }
        
        return array_sum($userFirstCharges);
    }

    /**
     * 计算注充率
     * 
     * 计算公式：注册用户数 / 付费用户数
     * 
     * @param int $registeredUsers 注册用户数
     * @param int $paidUsers 付费用户数
     * @return float 注充率
     */
    private function calculateRegistrationChargeRate(int $registeredUsers, int $firstChargeUsers): float
{
    return round($registeredUsers > 0 ? ($firstChargeUsers / $registeredUsers) * 100 : 0, 2);
}

    /**
     * 获取提现成功用户数
     * 
     * 计算方式：
     * - 数据源：withdraw_orders 表
     * - 条件：create_time 在指定时间范围内且 status=2（提现成功）
     * - 统计：去重后的用户数量
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return int 提现成功用户数
     */
    private function getWithdrawalUsers(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): int
    {
        $query = Db::name('withdraw_orders')->whereBetweenTime('create_time', $startOfDay, $endOfDay)->where('status', 2);
        if ($channelId) {
            $query->whereIn("user_id", $userIds);
        }
        $orders = $query->select()->toArray();
        return count(array_unique(array_column($orders, 'user_id')));
    }

    /**
     * 计算提现率
     * 
     * 计算公式：提现金额 / 充值总额
     * 
     * @param float $totalRechargeAmount 充值总额
     * @param float $withdrawalAmount 提现金额
     * @return float 提现率
     */
    private function calculateWithdrawalRate(float $totalRechargeAmount, float $withdrawalAmount): float
    {
        return round($withdrawalAmount / max($totalRechargeAmount, 1), 2);
    }

    /**
     * 获取玩家盈亏
     * 
     * 计算方式：
     * - 数据源：game_transactions 表
     * - 条件：create_time 在指定时间范围内
     * - 计算公式：所有交易的 amount 总和 - real_amount 总和
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return float 玩家盈亏
     */
    private function getPlayerProfitLoss(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): float
    {
        $query = Db::name('game_transactions')->whereBetweenTime('create_time', $startOfDay, $endOfDay);
        if ($channelId) {
            $query->whereIn("user_id", $userIds);
        }
        $transactions = $query->select()->toArray();
        return array_sum(array_column($transactions, 'amount')) - array_sum(array_column($transactions, 'real_amount'));
    }

    /**
     * 获取活动彩金（基于 slot_account_coin_log 统计活动相关的奖励金额）
     * 
     * 计算方式：
     * - 数据源：account_coin_log 表
     * - 条件：
     *   - create_time 在指定时间范围内
     *   - log_type_id 在活动相关的类型列表中
     *   - wallet_type = 1（充值钱包）
     *   - num > 0（只统计正数奖励，不包括扣减）
     * - 统计：所有符合条件的 num 字段求和
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * 活动类型包括：
     * - 注册赠送、站内信活动、签到活动、绑定手机赠送
     * - 弹窗赠送、添加桌面、救援金、红包兑换
     * - VIP游戏返利、系统赠送、会员升级奖励
     * - 宝箱活动奖励、幸运转盘中奖、会员周/月奖励
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return float 活动彩金总额
     */
    private function getActivityCashGift(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): float
    {
        // 活动相关的 log_type_id 列表
        $activityLogTypes = [
            \app\api\enum\CoinLog::RegFree,           // 注册赠送
            \app\api\enum\CoinLog::InternalMessage,   // 站内信活动
            \app\api\enum\CoinLog::DayGold,           // 签到活动
            \app\api\enum\CoinLog::BindMobile,        // 绑定手机赠送
            \app\api\enum\CoinLog::PopUp,             // 弹窗赠送
            \app\api\enum\CoinLog::Pwa,               // 添加桌面
            \app\api\enum\CoinLog::RescueFunds,       // 救援金
            \app\api\enum\CoinLog::RedEnvelope,       // 红包兑换
            \app\api\enum\CoinLog::GameVip375,        // VIP游戏返利
            \app\api\enum\CoinLog::system,            // 系统赠送
            \app\api\enum\CoinLog::MemberUpgrade,     // 会员升级奖励
            \app\api\enum\CoinLog::ChestBox,          // 宝箱活动奖励
            \app\api\enum\CoinLog::LuckyWheel,        // 幸运转盘中奖
            \app\api\enum\CoinLog::MemberWeeklyReward, // 会员周奖励
            \app\api\enum\CoinLog::MemberMonthlyReward, // 会员月奖励
        ];
        
        // 查询活动相关的彩金发放记录，wallet_type=1（充值钱包）
        $query = Db::name('account_coin_log')
            ->whereBetweenTime('create_time', $startOfDay, $endOfDay)
            ->whereIn('log_type_id', $activityLogTypes)
            ->where('wallet_type', 1) // 充值钱包
            ->where('num', '>', 0); // 只统计正数（奖励），不包括扣减
        
        if ($channelId) {
            $query->whereIn("user_id", $userIds);
        }
        
        $logs = $query->select()->toArray();
        return array_sum(array_column($logs, 'num'));
    }

    /**
     * 获取活动提现（基于 slot_account_coin_log 统计专门的提现 log_type_id，wallet_type=1）
     * 
     * 计算方式：
     * - 数据源：account_coin_log 表
     * - 条件：
     *   - create_time 在指定时间范围内
     *   - log_type_id 在专门的提现类型列表中
     *   - wallet_type = 1（充值钱包）
     *   - num > 0（提现记录通常是正数入账）
     * - 统计：所有符合条件的 num 字段求和
     * - 渠道过滤：如果指定渠道，只统计该渠道下的用户
     * 
     * 提现类型包括：
     * - 体验账户提现（体验钱包）
     * - 拼多多邀请转盘提现
     * - Jackpot 提现入账
     * 
     * 注意：排除体验金提现赠送（ExWithdrawGift），因为那不是提现奖励
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @return float 活动提现总额
     */
    private function getActivityWithdrawal(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds): float
    {
        // 专门的提现 log_type_id 列表（排除体验金提现赠送，因为那不是提现奖励）
        $withdrawLogTypes = [
            \app\api\enum\CoinLog::ExWithdraw,        // 体验账户提现（体验钱包）
            \app\api\enum\CoinLog::PDDWithdraw,       // 拼多多邀请转盘提现
            \app\api\enum\CoinLog::JackpotWithdraw,   // Jackpot 提现入账
        ];
        
        // 查询专门的提现记录，wallet_type=1（充值钱包）
        $query = Db::name('account_coin_log')
            ->whereBetweenTime('create_time', $startOfDay, $endOfDay)
            ->whereIn('log_type_id', $withdrawLogTypes)
            ->where('wallet_type', 1) // 充值钱包
            ->where('num', '>', 0); // 提现记录通常是正数（入账）
        
        if ($channelId) {
            $query->whereIn("user_id", $userIds);
        }
        
        $logs = $query->select()->toArray();
        return array_sum(array_column($logs, 'num'));
    }

    /**
     * 获取实时数据（原有逻辑）
     * 
     * 当预计算数据不可用时，使用实时计算逻辑
     * 通过批量查询减少数据库连接次数，提高性能
     * 
     * @param int $startOfDay 开始时间戳
     * @param int $endOfDay 结束时间戳
     * @param int|null $channelId 渠道ID
     * @param array $userIds 用户ID列表（渠道过滤用）
     * @param string $date 日期字符串
     * @return array 实时统计数据
     */
    private function getRealTimeData(int $startOfDay, int $endOfDay, ?int $channelId, array $userIds, string $date): array
    {
        // 确保 $date 变量存在
        if (!isset($date)) {
            $date = date('Y-m-d');
        }
        
        // 批量查询数据，减少重复查询
        $rechargeOrdersQuery = Db::name('recharge_orders')->whereBetweenTime('created_at', $startOfDay, $endOfDay);
        $withdrawOrdersQuery = Db::name('withdraw_orders')->whereBetweenTime('create_time', $startOfDay, $endOfDay);
        $gameTransactionsQuery = Db::name('game_transactions')->whereBetweenTime('create_time', $startOfDay, $endOfDay);
        $accountCoinLogsQuery = Db::name('account_coin_log')->whereBetweenTime('create_time', $startOfDay, $endOfDay);

        if ($channelId) {
            $rechargeOrdersQuery->whereIn("user_id", $userIds);
            $withdrawOrdersQuery->whereIn("user_id", $userIds);
            $gameTransactionsQuery->whereIn("user_id", $userIds);
            $accountCoinLogsQuery->whereIn("user_id", $userIds);
        }

        $rechargeOrders = $rechargeOrdersQuery->select()->toArray();
        $withdrawOrders = $withdrawOrdersQuery->select()->toArray();
        $gameTransactions = $gameTransactionsQuery->select()->toArray();
        $accountCoinLogs = $accountCoinLogsQuery->select()->toArray();

        // 过滤成功的充值订单
        $successRechargeOrders = array_filter($rechargeOrders, function ($order) {
            return (int)$order['pay_status'] === 1; // 只统计成功的充值
        }, ARRAY_FILTER_USE_BOTH);
        
        $paidUsers = $this->getPaidUsers($startOfDay, $endOfDay, $channelId, $userIds);
        
        // 提取amount列
        $amountsColumn = array_column($successRechargeOrders, 'amount');
        $totalRechargeAmount = round(array_sum($amountsColumn), 4);
        
        // 处理提现订单
        $successWithdrawOrders = array_filter($withdrawOrders, function ($order) {
            return (int)$order['status'] === 2;
        }, ARRAY_FILTER_USE_BOTH);
        $withdrawAmountsColumn = array_column($successWithdrawOrders, 'amount');
        $withdrawalAmount = array_sum($withdrawAmountsColumn);

        // 计算 retentionUsers
        $retentionUserIdsColumn = array_column($gameTransactions, 'user_id');
        $retentionUsers = count(array_unique($retentionUserIdsColumn));
        
        // 计算 onlineUsers
        $onlineUsers = $this->isToday($date) ? $this->getOnlineUsers($channelId, $userIds) : 0;
        
        // 计算 firstChargeUsers
        $firstChargeUsers = $this->getFirstChargeUsers($startOfDay, $endOfDay, $channelId, $userIds);
        
        // 计算 firstChargeAmount
        $firstChargeAmount = $this->getFirstChargeAmount($startOfDay, $endOfDay, $channelId, $userIds);
        
        // 计算 newUserFirstChargeUsers
        $newUserFirstChargeUsers = $this->getNewUserFirstChargeUsers($startOfDay, $endOfDay, $channelId, $userIds);
        
        // 计算 newUserFirstChargeAmount
        $newUserFirstChargeAmount = $this->getNewUserFirstChargeAmount($startOfDay, $endOfDay, $channelId, $userIds);
        
        // 计算 registrationChargeRate
        $successOrdersForRate = array_filter($rechargeOrders, function ($order) {
            return (int)$order['pay_status'] === 1;
        }, ARRAY_FILTER_USE_BOTH);
        $userIdsColumnForRate = array_column($successOrdersForRate, 'user_id');
        $paidUsersCount = count(array_unique($userIdsColumnForRate));
        $registrationChargeRate = 0;

$registeredCount = $channelId
    ? Db::name('account')->whereIn("id", $userIds)->whereBetweenTime('reg_time', $startOfDay, $endOfDay)->count()
    : Db::name('account')->whereBetweenTime('reg_time', $startOfDay, $endOfDay)->count();

if ($registeredCount > 0) {
    $registrationChargeRate = round(($firstChargeUsers / $registeredCount) * 100, 2);
}
        
        // 计算 withdrawalUsers
        $successWithdrawOrdersForUsers = array_filter($withdrawOrders, function ($order) {
            return (int)$order['status'] === 2;
        }, ARRAY_FILTER_USE_BOTH);
        $withdrawalUserIdsColumn = array_column($successWithdrawOrdersForUsers, 'user_id');
        $withdrawalUsers = count(array_unique($withdrawalUserIdsColumn));
        
        // 计算 orderAmount
        $orderAmount = $this->getGameOrderCash($startOfDay, $endOfDay, $channelId, $userIds);
        
        // 计算 orderCount
        $orderCount = $this->getGameOrderCount($startOfDay, $endOfDay, $channelId, $userIds);
        
        // 计算 playerProfitLoss
        $rewardAmount = $this->getGameRewardAmount($startOfDay, $endOfDay, $channelId, $userIds);
        $playerProfitLoss = $rewardAmount - $orderAmount;
        
        // 计算 activityCashGift
        $activityLogTypes = [
            \app\api\enum\CoinLog::RegFree, \app\api\enum\CoinLog::InternalMessage, \app\api\enum\CoinLog::DayGold,
            \app\api\enum\CoinLog::BindMobile, \app\api\enum\CoinLog::PopUp, \app\api\enum\CoinLog::Pwa,
            \app\api\enum\CoinLog::RescueFunds, \app\api\enum\CoinLog::RedEnvelope, \app\api\enum\CoinLog::GameVip375,
            \app\api\enum\CoinLog::system, \app\api\enum\CoinLog::MemberUpgrade, \app\api\enum\CoinLog::ChestBox,
            \app\api\enum\CoinLog::LuckyWheel, \app\api\enum\CoinLog::MemberWeeklyReward, \app\api\enum\CoinLog::MemberMonthlyReward
        ];
        $filteredActivityLogs = array_filter($accountCoinLogs, function ($log) use ($activityLogTypes) {
            return in_array($log['log_type_id'], $activityLogTypes) && $log['wallet_type'] == 1 && $log['num'] > 0;
        }, ARRAY_FILTER_USE_BOTH);
        $activityNumsColumn = array_column($filteredActivityLogs, 'num');
        $activityCashGift = array_sum($activityNumsColumn);
        
        // 计算 activityWithdrawal
        $withdrawLogTypes = [
            \app\api\enum\CoinLog::ExWithdraw,        // 体验账户提现
            \app\api\enum\CoinLog::PDDWithdraw,       // 拼多多邀请转盘提现
            \app\api\enum\CoinLog::JackpotWithdraw,   // Jackpot 提现入账
        ];
        $filteredWithdrawLogs = array_filter($accountCoinLogs, function ($log) use ($withdrawLogTypes) {
            return in_array($log['log_type_id'], $withdrawLogTypes) && $log['wallet_type'] == 1 && $log['num'] > 0;
        }, ARRAY_FILTER_USE_BOTH);
        $withdrawNumsColumn = array_column($filteredWithdrawLogs, 'num');
        $activityWithdrawal = array_sum($withdrawNumsColumn);

        $result = [
            'registeredUsers' => ($channelId)
                ? Db::name('account')->whereBetweenTime('reg_time', $startOfDay, $endOfDay)->whereIn("id", $userIds)->count()
                : Db::name('account')->whereBetweenTime('reg_time', $startOfDay, $endOfDay)->count(),

            'activeUsers' => ($channelId)
                ? Db::name('user_login_game_log')->whereBetweenTime('create_time', $startOfDay, $endOfDay)->distinct(true)->whereIn("user_id", $userIds)->count('user_id')
                : Db::name('user_login_game_log')->whereBetweenTime('create_time', $startOfDay, $endOfDay)->distinct(true)->count('user_id'),

           'paidUsers' => $paidUsers,
'retentionUsers' => $retentionUsers,
'onlineUsers' => $onlineUsers,

'firstChargeUsers' => $firstChargeUsers,
'firstChargeAmount' => $firstChargeAmount,

'newUserFirstChargeUsers' => $newUserFirstChargeUsers,
'newUserFirstChargeAmount' => $newUserFirstChargeAmount,

'oldUserFirstChargeUsers' => max($firstChargeUsers - $newUserFirstChargeUsers, 0),
'oldUserFirstChargeAmount' => max($firstChargeAmount - $newUserFirstChargeAmount, 0),

'totalRechargeAmount' => $totalRechargeAmount,
'registrationChargeRate' => $registrationChargeRate,

            'withdrawalUsers' => $withdrawalUsers,

            'withdrawalAmount' => $withdrawalAmount,

            'withdrawalRate' => round($withdrawalAmount / max($totalRechargeAmount, 1), 2),

            'orderAmount' => $orderAmount,

            'orderCount' => $orderCount,

            'playerProfitLoss' => $playerProfitLoss,

            'activityCashGift' => $activityCashGift,

            'activityWithdrawal' => $activityWithdrawal,

            'selectedChannelId' => $channelId,
        ];
        
        return $result;
    }
}
