<?php

namespace app\common\service;

use think\facade\Db;
use app\common\model\OperationData;

/**
 * 运营数据服务类
 */
class OperationDataService
{
    /**
     * 获取指定日期的统计数据（与脚本逻辑保持一致）
     * @param string $date 日期 Y-m-d 格式
     * @param int|null $channelId 渠道ID，null为全局
     * @return array
     */
    public function getDailyStats(string $date, ?int $channelId = null): array
    {
        // ==================== 时间范围设置 ====================
        $startTime = strtotime($date . ' 00:00:00'); // 当日开始时间
        $endTime = strtotime($date . ' 23:59:59');   // 当日结束时间
        
        // ==================== 渠道用户筛选 ====================
        $userIds = [];
        if ($channelId) {
            $userIds = Db::name('account')->where('channel_id', $channelId)->column('id');
            // 如果该渠道没有用户，返回空数据
            if (empty($userIds)) {
                return $this->getEmptyStats($date);
            }
        }

        // ==================== 1. DAU计算 ====================
        // 1.1 当日注册用户数
        $newRegisterCount = Db::name('account')->whereBetweenTime('create_time', $startTime, $endTime);
        if ($channelId && !empty($userIds)) {
            $newRegisterCount = $newRegisterCount->whereIn('id', $userIds);
        }
        $newRegisterCount = $newRegisterCount->count();

        // 1.2 获取往日活跃用户数（在指定日期之前注册且在当日有活动的用户）
        // 1.2.1 游戏交易活跃用户
        $gameActiveUsers = Db::name('game_transactions')
            ->whereBetweenTime('req_time', $date . ' 00:00:00', $date . ' 23:59:59');
        if ($channelId && !empty($userIds)) {
            $gameActiveUsers = $gameActiveUsers->whereIn('user_id', $userIds);
        }
        $gameActiveUsers = $gameActiveUsers->distinct(true)->column('user_id');
        // 1.2.2 充值活跃用户
        $rechargeActiveUsers = Db::name('recharge_orders')
            ->whereBetween('created_at', [$startTime, $endTime]);
        if ($channelId && !empty($userIds)) {
            $rechargeActiveUsers = $rechargeActiveUsers->whereIn('user_id', $userIds);
        }
        $rechargeActiveUsers = $rechargeActiveUsers->distinct(true)->column('user_id');
        // 1.2.3 提现活跃用户
        $withdrawActiveUsers = Db::name('withdraw_orders')
            ->whereBetweenTime('create_time', $startTime, $endTime);
        if ($channelId && !empty($userIds)) {
            $withdrawActiveUsers = $withdrawActiveUsers->whereIn('user_id', $userIds);
        }
        $withdrawActiveUsers = $withdrawActiveUsers->distinct(true)->column('user_id');
        // 1.2.4 合并所有活跃用户（去重）
        $allActiveUsers = array_unique(array_merge($gameActiveUsers, $rechargeActiveUsers, $withdrawActiveUsers));
        // 1.2.5 筛选往日注册的活跃用户（排除当日注册的用户）
        $previousActiveUsers = Db::name('account')
            ->whereIn('id', $allActiveUsers)
            ->where('create_time', '<', $startTime);
        if ($channelId) {
            $previousActiveUsers = $previousActiveUsers->where('channel_id', $channelId);
        }
        $previousActiveUsers = $previousActiveUsers->column('id');
        $previousActiveCount = count($previousActiveUsers);
        // 1.3 DAU = 当日注册用户 + 往日注册但当日活跃的用户
        $dau = $newRegisterCount + $previousActiveCount;

        // ==================== 2. 付费数据统计 ====================
        $rechargeQuery = Db::name('recharge_orders')
            ->where('pay_status', 1)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime);
        if ($channelId && !empty($userIds)) {
            $rechargeQuery = $rechargeQuery->whereIn('user_id', $userIds);
        }
        $rechargeOrders = $rechargeQuery->select()->toArray();
        $paidUsers = count(array_unique(array_column($rechargeOrders, 'user_id')));
        $paidAmount = array_sum(array_column($rechargeOrders, 'amount'));

        // ==================== 3. 提现数据统计 ====================
        $withdrawQuery = Db::name('withdraw_orders')
            ->where('status', 2) // 与脚本保持一致，2为提现成功
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime);
        if ($channelId && !empty($userIds)) {
            $withdrawQuery = $withdrawQuery->whereIn('user_id', $userIds);
        }
        $withdrawOrders = $withdrawQuery->select()->toArray();
        $withdrawUsers = count(array_unique(array_column($withdrawOrders, 'user_id')));
        $withdrawAmount = array_sum(array_column($withdrawOrders, 'amount'));

        // ==================== 4. 下单数据统计（游戏交易记录） ====================
        $orderQuery = Db::name('game_transactions')
            ->where('reason', 'bet')
            ->where('req_time', '>=', $date . ' 00:00:00')
            ->where('req_time', '<=', $date . ' 23:59:59');
        if ($channelId && !empty($userIds)) {
            $orderQuery = $orderQuery->whereIn('user_id', $userIds);
        }
        $allOrders = $orderQuery->select()->toArray();
        $orderCount = count($allOrders);
        $orderUsers = count(array_unique(array_column($allOrders, 'user_id')));
        $orderAmount = array_sum(array_map(function($order) { return abs($order['amount']); }, $allOrders));
        $orderCash = 0;
        $orderBonus = 0;
        foreach ($allOrders as $order) {
            if ($order['wallet_type'] == 1) {
                $orderCash += abs($order['amount']);
            } else {
                $orderBonus += abs($order['amount']);
            }
        }

        // ==================== 5. 游戏数据统计（投注和返奖） ====================
        $gameQuery = Db::name('game_transactions')
            ->where('req_time', '>=', $date . ' 00:00:00')
            ->where('req_time', '<=', $date . ' 23:59:59');
        if ($channelId && !empty($userIds)) {
            $gameQuery = $gameQuery->whereIn('user_id', $userIds);
        }
        $gameTransactions = $gameQuery->select()->toArray();
        $betAmount = 0;
        foreach ($gameTransactions as $transaction) {
            if ($transaction['reason'] === 'bet') {
                $betAmount += abs($transaction['amount']);
            }
        }
        $winAmount = 0;
        foreach ($gameTransactions as $transaction) {
            if ($transaction['reason'] === 'win' && $transaction['wallet_type'] == 1) {
                $winAmount += $transaction['real_amount'] ?? $transaction['amount'];
            }
        }
        $profit = $orderAmount - $winAmount;

        // ==================== 6. 新老用户数据 ====================
        $newUserData = $this->getNewUserStats($date, $channelId);
        $oldUserData = $this->getOldUserStats($date, $channelId);

        // ==================== 7. 核心指标计算 ====================
        $paidRate = $dau > 0 ? ($paidUsers / $dau) : 0;
        $withdrawRate = $dau > 0 ? ($withdrawUsers / $dau) : 0;
        $arpu = $dau > 0 ? ($paidAmount / $dau) : 0;
        $arppu = $paidUsers > 0 ? ($paidAmount / $paidUsers) : 0;

        // ==================== 8. 返回完整的统计数据 ====================
        $result = [
            'date' => $date, // 统计日期
            // 所有用户数据
            'all_dau' => $dau,
            'all_paid_users' => $paidUsers,
            'all_paid_rate' => $paidRate,
            'all_paid_amount' => round($paidAmount, 2),
            'all_arpu' => round($arpu, 2),
            'all_arppu' => round($arppu, 2),
            'all_withdraw_amount' => round($withdrawAmount, 2),
            'all_withdraw_rate' => $withdrawRate,
            // 新用户数据
            'new_dau' => $newUserData['dau'],
            'new_paid_users' => $newUserData['paid_users'],
            'new_paid_rate' => $newUserData['paid_rate'],
            'new_paid_amount' => $newUserData['paid_amount'],
            'new_arpu' => round($newUserData['arpu'], 2),
            'new_arppu' => round($newUserData['arppu'], 2),
            'new_withdraw_amount' => $newUserData['withdraw_amount'],
            'new_withdraw_rate' => $newUserData['withdraw_rate'],
            'new_order_count' => $newUserData['order_count'],
            'new_order_users' => $newUserData['order_users'],
            'new_order_amount' => $newUserData['order_amount'],
            'new_order_cash' => $newUserData['order_cash'],
            'new_order_bonus' => $newUserData['order_bonus'],
            'new_reward_amount' => $newUserData['reward_amount'],
            'new_profit' => $newUserData['profit'],
            // 老用户数据
            'old_dau' => $oldUserData['dau'],
            'old_paid_users' => $oldUserData['paid_users'],
            'old_paid_rate' => $oldUserData['paid_rate'],
            'old_paid_amount' => $oldUserData['paid_amount'],
            'old_arpu' => round($oldUserData['arpu'], 2),
            'old_arppu' => round($oldUserData['arppu'], 2),
            'old_withdraw_amount' => $oldUserData['withdraw_amount'],
            'old_withdraw_rate' => $oldUserData['withdraw_rate'],
            'old_order_count' => $oldUserData['order_count'],
            'old_order_users' => $oldUserData['order_users'],
            'old_order_amount' => $oldUserData['order_amount'],
            'old_order_cash' => $oldUserData['order_cash'],
            'old_order_bonus' => $oldUserData['order_bonus'],
            'old_reward_amount' => $oldUserData['reward_amount'],
            'old_profit' => $oldUserData['profit'],
            // 下单数据
            'order_count' => $orderCount,
            'order_users' => $orderUsers,
            'order_amount' => round($orderAmount, 2),
            'order_cash' => round($orderCash, 2),
            'order_bonus' => round($orderBonus, 2),
            // 返奖数据
            'reward_amount' => round($winAmount, 2),
            'profit' => round($profit, 2),
        ];
        return $result;
    }

    /**
     * 获取新用户统计数据（当日注册的用户，逻辑与 getDailyStats 保持一致）
     * @param string $date 日期
     * @param int|null $channelId 渠道ID，null为全局
     * @return array
     */
    private function getNewUserStats(string $date, ?int $channelId = null): array
    {
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = strtotime($date . ' 23:59:59');
        // 1. 获取当日注册用户ID
        $newUserQuery = Db::name('account')->whereBetweenTime('create_time', $startTime, $endTime);
        if ($channelId) {
            $newUserQuery = $newUserQuery->where('channel_id', $channelId);
        }
        $newUserIds = $newUserQuery->column('id');
        $newUserCount = count($newUserIds);
        if (empty($newUserIds)) {
            return [
                'dau' => 0,
                'paid_users' => 0,
                'paid_rate' => 0,
                'paid_amount' => 0,
                'arpu' => 0,
                'arppu' => 0,
                'withdraw_amount' => 0,
                'withdraw_rate' => 0,
                'order_count' => 0,
                'order_users' => 0,
                'order_amount' => 0,
                'order_cash' => 0,
                'order_bonus' => 0,
                'reward_amount' => 0,
                'profit' => 0,
            ];
        }
        // 2. 新用户付费数据
        $newUserRechargeData = Db::name('recharge_orders')
            ->where('pay_status', 1)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime)
            ->whereIn('user_id', $newUserIds)
            ->select()->toArray();
        $newUserPaidUsers = array_unique(array_column($newUserRechargeData, 'user_id'));
        $newUserPaidAmount = array_sum(array_column($newUserRechargeData, 'amount'));
        $newUserPaidCount = count($newUserPaidUsers);
        // 3. 新用户提现数据
        $newUserWithdrawData = Db::name('withdraw_orders')
            ->where('status', 2)
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->whereIn('user_id', $newUserIds)
            ->select()->toArray();
        $newUserWithdrawUsers = array_unique(array_column($newUserWithdrawData, 'user_id'));
        $newUserWithdrawAmount = array_sum(array_column($newUserWithdrawData, 'amount'));
        $newUserWithdrawCount = count($newUserWithdrawUsers);
        // 4. 新用户下单数据统计
        $newUserOrderData = Db::name('game_transactions')
            ->where('reason', 'bet')
            ->whereBetweenTime('req_time', $date . ' 00:00:00', $date . ' 23:59:59')
            ->whereIn('user_id', $newUserIds)
            ->select()->toArray();
        $newUserOrderUsers = array_unique(array_column($newUserOrderData, 'user_id'));
        $newUserOrderCount = count($newUserOrderData);
        $newUserOrderAmount = array_sum(array_map(function($order) { return abs($order['amount']); }, $newUserOrderData));
        $newUserOrderCash = 0;
        $newUserOrderBonus = 0;
        foreach ($newUserOrderData as $order) {
            if ($order['wallet_type'] == 1) {
                $newUserOrderCash += abs($order['amount']);
            } else {
                $newUserOrderBonus += abs($order['amount']);
            }
        }
        // 5. 新用户返奖数据
        $newUserRewardData = Db::name('game_transactions')
            ->where('reason', 'win')
            ->where('wallet_type', 1)
            ->whereBetweenTime('req_time', $date . ' 00:00:00', $date . ' 23:59:59')
            ->whereIn('user_id', $newUserIds)
            ->select()->toArray();
        $newUserRewardAmount = 0;
        foreach ($newUserRewardData as $reward) {
            $newUserRewardAmount += ($reward['real_amount'] ?? $reward['amount']);
        }
        // 6. 指标计算
        $paidRate = $newUserCount > 0 ? ($newUserPaidCount / $newUserCount) : 0;
        $withdrawRate = $newUserCount > 0 ? ($newUserWithdrawCount / $newUserCount) : 0;
        $arpu = $newUserCount > 0 ? ($newUserPaidAmount / $newUserCount) : 0;
        $arppu = $newUserPaidCount > 0 ? ($newUserPaidAmount / $newUserPaidCount) : 0;
        $newUserProfit = $newUserOrderAmount - $newUserRewardAmount;
        $result = [
            'dau' => $newUserCount,
            'paid_users' => $newUserPaidCount,
            'paid_rate' => round($paidRate, 4),
            'paid_amount' => round($newUserPaidAmount, 2),
            'arpu' => round($arpu, 2),
            'arppu' => round($arppu, 2),
            'withdraw_amount' => round($newUserWithdrawAmount, 2),
            'withdraw_rate' => round($withdrawRate, 4),
            'order_count' => $newUserOrderCount,
            'order_users' => count($newUserOrderUsers),
            'order_amount' => round($newUserOrderAmount, 2),
            'order_cash' => round($newUserOrderCash, 2),
            'order_bonus' => round($newUserOrderBonus, 2),
            'reward_amount' => round($newUserRewardAmount, 2),
            'profit' => round($newUserProfit, 2),
        ];
        return $result;
    }

    /**
     * 获取老用户统计数据（往日注册的用户，逻辑与 getDailyStats 保持一致）
     * @param string $date 日期
     * @param int|null $channelId 渠道ID，null为全局
     * @return array
     */
    private function getOldUserStats(string $date, ?int $channelId = null): array
    {
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = strtotime($date . ' 23:59:59');
        // 1. 获取往日注册用户ID
        $oldUserQuery = Db::name('account')->where('create_time', '<', $startTime);
        if ($channelId) {
            $oldUserQuery = $oldUserQuery->where('channel_id', $channelId);
        }
        $oldUserIds = $oldUserQuery->column('id');
        if (empty($oldUserIds)) {
            return [
                'dau' => 0,
                'paid_users' => 0,
                'paid_rate' => 0,
                'paid_amount' => 0,
                'arpu' => 0,
                'arppu' => 0,
                'withdraw_amount' => 0,
                'withdraw_rate' => 0,
                'order_count' => 0,
                'order_users' => 0,
                'order_amount' => 0,
                'order_cash' => 0,
                'order_bonus' => 0,
                'reward_amount' => 0,
                'profit' => 0,
            ];
        }
        // 2. 获取老用户当日活跃用户ID
        $gameActiveUsers = Db::name('game_transactions')
            ->whereBetweenTime('req_time', $date . ' 00:00:00', $date . ' 23:59:59')
            ->whereIn('user_id', $oldUserIds)
            ->distinct(true)
            ->column('user_id');
        $rechargeActiveUsers = Db::name('recharge_orders')
            ->whereBetween('created_at', [$startTime, $endTime])
            ->whereIn('user_id', $oldUserIds)
            ->distinct(true)
            ->column('user_id');
        $withdrawActiveUsers = Db::name('withdraw_orders')
            ->whereBetweenTime('create_time', $startTime, $endTime)
            ->whereIn('user_id', $oldUserIds)
            ->distinct(true)
            ->column('user_id');
        $oldActiveUsers = array_unique(array_merge($gameActiveUsers, $rechargeActiveUsers, $withdrawActiveUsers));
        $oldActiveCount = count($oldActiveUsers);
        if (empty($oldActiveUsers)) {
            return [
                'dau' => 0,
                'paid_users' => 0,
                'paid_rate' => 0,
                'paid_amount' => 0,
                'arpu' => 0,
                'arppu' => 0,
                'withdraw_amount' => 0,
                'withdraw_rate' => 0,
                'order_count' => 0,
                'order_users' => 0,
                'order_amount' => 0,
                'order_cash' => 0,
                'order_bonus' => 0,
                'reward_amount' => 0,
                'profit' => 0,
            ];
        }
        // 3. 老用户付费数据
        $oldUserRechargeData = Db::name('recharge_orders')
            ->where('pay_status', 1)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime)
            ->whereIn('user_id', $oldActiveUsers)
            ->select()->toArray();
        $oldUserPaidUsers = array_unique(array_column($oldUserRechargeData, 'user_id'));
        $oldUserPaidAmount = array_sum(array_column($oldUserRechargeData, 'amount'));
        $oldUserPaidCount = count($oldUserPaidUsers);
        // 4. 老用户提现数据
        $oldUserWithdrawData = Db::name('withdraw_orders')
            ->where('status', 2)
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->whereIn('user_id', $oldActiveUsers)
            ->select()->toArray();
        $oldUserWithdrawUsers = array_unique(array_column($oldUserWithdrawData, 'user_id'));
        $oldUserWithdrawAmount = array_sum(array_column($oldUserWithdrawData, 'amount'));
        $oldUserWithdrawCount = count($oldUserWithdrawUsers);
        // 5. 老用户下单数据统计
        $oldUserOrderData = Db::name('game_transactions')
            ->where('reason', 'bet')
            ->whereBetweenTime('req_time', $date . ' 00:00:00', $date . ' 23:59:59')
            ->whereIn('user_id', $oldUserIds)
            ->select()->toArray();
        $oldUserOrderUsers = array_unique(array_column($oldUserOrderData, 'user_id'));
        $oldUserOrderCount = count($oldUserOrderData);
        $oldUserOrderAmount = array_sum(array_map(function($order) { return abs($order['amount']); }, $oldUserOrderData));
        $oldUserOrderCash = 0;
        $oldUserOrderBonus = 0;
        foreach ($oldUserOrderData as $order) {
            if ($order['wallet_type'] == 1) {
                $oldUserOrderCash += abs($order['amount']);
            } else {
                $oldUserOrderBonus += abs($order['amount']);
            }
        }
        // 6. 老用户返奖数据
        $oldUserRewardData = Db::name('game_transactions')
            ->where('reason', 'win')
            ->where('wallet_type', 1)
            ->whereBetweenTime('req_time', $date . ' 00:00:00', $date . ' 23:59:59')
            ->whereIn('user_id', $oldUserIds)
            ->select()->toArray();
        $oldUserRewardAmount = 0;
        foreach ($oldUserRewardData as $reward) {
            $oldUserRewardAmount += ($reward['real_amount'] ?? $reward['amount']);
        }
        // 7. 指标计算
        $paidRate = $oldActiveCount > 0 ? ($oldUserPaidCount / $oldActiveCount) : 0;
        $withdrawRate = $oldActiveCount > 0 ? ($oldUserWithdrawCount / $oldActiveCount) : 0;
        $arpu = $oldActiveCount > 0 ? ($oldUserPaidAmount / $oldActiveCount) : 0;
        $arppu = $oldUserPaidCount > 0 ? ($oldUserPaidAmount / $oldUserPaidCount) : 0;
        $oldUserProfit = $oldUserOrderAmount - $oldUserRewardAmount;
        $result = [
            'dau' => $oldActiveCount,
            'paid_users' => $oldUserPaidCount,
            'paid_rate' => round($paidRate, 4),
            'paid_amount' => round($oldUserPaidAmount, 2),
            'arpu' => round($arpu, 2),
            'arppu' => round($arppu, 2),
            'withdraw_amount' => round($oldUserWithdrawAmount, 2),
            'withdraw_rate' => round($withdrawRate, 4),
            'order_count' => $oldUserOrderCount,
            'order_users' => count($oldUserOrderUsers),
            'order_amount' => round($oldUserOrderAmount, 2),
            'order_cash' => round($oldUserOrderCash, 2),
            'order_bonus' => round($oldUserOrderBonus, 2),
            'reward_amount' => round($oldUserRewardAmount, 2),
            'profit' => round($oldUserProfit, 2),
        ];
        return $result;
    }

    /**
     * 获取空统计数据（当渠道没有用户或无活跃用户时返回，结构与 getDailyStats 保持一致）
     * @param string $date 日期
     * @return array
     */
    private function getEmptyStats(string $date): array
    {
        return [
            'date' => $date, // 统计日期
            // ==================== 所有用户数据 ====================
            'all_dau' => 0, // DAU：当日注册用户 + 往日注册但当日活跃的用户
            'all_paid_users' => 0, // 付费人数：当日有充值成功的用户数
            'all_paid_rate' => 0, // 付费率：付费人数 / DAU（前端formatter会转换为百分比）
            'all_paid_amount' => 0, // 付费额：当日充值成功总金额
            'all_arpu' => 0, // ARPU：付费额 / DAU（平均每用户收入，保留两位小数）
            'all_arppu' => 0, // ARPPU：付费额 / 付费人数（平均每付费用户收入，保留两位小数）
            'all_withdraw_amount' => 0, // 提现金额：当日提现成功总金额
            'all_withdraw_rate' => 0, // 提现率：提现用户数 / DAU（前端formatter会转换为百分比）
            // ==================== 新用户数据 ====================
            'new_dau' => 0, // 新用户DAU：当日注册且当日活跃的用户数
            'new_paid_users' => 0, // 新用户付费人数：当日注册且当日付费的用户数
            'new_paid_rate' => 0, // 新用户付费率：新用户付费人数 / 新用户DAU（前端formatter会转换为百分比）
            'new_paid_amount' => 0, // 新用户付费额：新用户当日充值成功总金额
            'new_arpu' => 0, // 新用户ARPU：新用户付费额 / 新用户DAU（平均每新用户收入，保留两位小数）
            'new_arppu' => 0, // 新用户ARPPU：新用户付费额 / 新用户付费人数（平均每新付费用户收入，保留两位小数）
            'new_withdraw_amount' => 0, // 新用户提现金额：新用户当日提现成功总金额
            'new_withdraw_rate' => 0, // 新用户提现率：新用户提现人数 / 新用户DAU（前端formatter会转换为百分比）
            'new_order_count' => 0, // 新用户下单笔数：当日注册用户的下注交易记录数量
            'new_order_users' => 0, // 新用户下单人数：当日注册且有下注记录的用户数
            'new_order_amount' => 0, // 新用户下单总金额：当日注册用户下注总金额
            'new_order_cash' => 0, // 新用户现金下单：当日注册用户现金钱包下注总金额
            'new_order_bonus' => 0, // 新用户彩金下单：当日注册用户彩金钱包下注总金额
            'new_reward_amount' => 0, // 新用户返奖金额：当日注册用户现金赢取总金额
            'new_profit' => 0, // 新用户运营商盈利：新用户下单金额 - 新用户返奖金额
            // ==================== 老用户数据 ====================
            'old_dau' => 0, // 老用户DAU：往日注册且当日活跃的用户数
            'old_paid_users' => 0, // 老用户付费人数：往日注册且当日付费的用户数
            'old_paid_rate' => 0, // 老用户付费率：老用户付费人数 / 老用户DAU（前端formatter会转换为百分比）
            'old_paid_amount' => 0, // 老用户付费额：老用户当日充值成功总金额
            'old_arpu' => 0, // 老用户ARPU：老用户付费额 / 老用户DAU（平均每老用户收入，保留两位小数）
            'old_arppu' => 0, // 老用户ARPPU：老用户付费额 / 老用户付费人数（平均每老付费用户收入，保留两位小数）
            'old_withdraw_amount' => 0, // 老用户提现金额：老用户当日提现成功总金额
            'old_withdraw_rate' => 0, // 老用户提现率：老用户提现人数 / 老用户DAU（前端formatter会转换为百分比）
            'old_order_count' => 0, // 老用户下单笔数：往日注册用户的下注交易记录数量
            'old_order_users' => 0, // 老用户下单人数：往日注册且有下注记录的用户数
            'old_order_amount' => 0, // 老用户下单总金额：往日注册用户下注总金额
            'old_order_cash' => 0, // 老用户现金下单：往日注册用户现金钱包下注总金额
            'old_order_bonus' => 0, // 老用户彩金下单：往日注册用户彩金钱包下注总金额
            'old_reward_amount' => 0, // 老用户返奖金额：往日注册用户现金赢取总金额
            'old_profit' => 0, // 老用户运营商盈利：老用户下单金额 - 老用户返奖金额
            // ==================== 下单数据（游戏下注） ====================
            'order_count' => 0, // 下单笔数：当日下注交易记录数量
            'order_users' => 0, // 下单人数：当日有下注记录的用户数
            'order_amount' => 0, // 下单总金额：当日下注总金额（取正数）
            'order_cash' => 0, // 现金下单：当日现金钱包下注总金额（wallet_type=1，取正数）
            'order_bonus' => 0, // 彩金下单：当日彩金钱包下注总金额（wallet_type=0，取正数）
            // ==================== 返奖数据 ====================
            'reward_amount' => 0, // 返奖金额：当日现金赢取总金额（reason='win'且wallet_type=1）
            'profit' => 0, // 运营商盈利：下单金额 - 返奖金额
        ];
    }

    /**
     * 获取全部渠道（含全局渠道）
     * @return array
     */
    public static function getAllChannelsWithGlobal(): array
    {
        $channels = [];
        $channels[] = ['id' => null, 'name' => '全部渠道'];
        $channels = array_merge(
            $channels,
            Db::name('channel_list')
                ->field('id, name')
                ->order('id', 'asc')
                ->select()
                ->toArray()
        );
        return $channels;
    }
} 