<?php

namespace app\common\service;

use think\facade\Db;

class OperationDataService
{
    public static function getPaidRechargeStats(int $startTime, int $endTime, ?array $userIds = null): array
    {
        if ($userIds !== null && empty($userIds)) {
            return ['paid_users' => 0, 'paid_amount' => 0.0];
        }

        $query = Db::name('recharge_orders')
            ->where('pay_status', 1)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime);

        if ($userIds !== null) {
            $query->whereIn('user_id', $userIds);
        }

        $stats = $query
            ->fieldRaw('COUNT(DISTINCT user_id) AS paid_users, COALESCE(SUM(amount), 0) AS paid_amount')
            ->find();

        return [
            'paid_users' => (int)($stats['paid_users'] ?? 0),
            'paid_amount' => (float)($stats['paid_amount'] ?? 0),
        ];
    }

    public function getDailyStats(string $date, ?int $channelId = null): array
    {
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = strtotime($date . ' 23:59:59');

        $channelUserIds = null;
        if ($channelId) {
            $channelUserIds = Db::name('account')->where('channel_id', $channelId)->column('id');
            if (empty($channelUserIds)) {
                return $this->getEmptyStats($date);
            }
        }

        $allActiveUsers = UserActiveService::getActiveUserIds($startTime, $endTime, $channelUserIds);
        $newActiveUsers = $this->filterUsersByRegisterTime($allActiveUsers, $startTime, $endTime);
        $oldActiveUsers = $this->filterUsersBeforeRegisterTime($allActiveUsers, $startTime);
        $dau = count($newActiveUsers) + count($oldActiveUsers);

        $rechargeStats = self::getPaidRechargeStats($startTime, $endTime, $channelUserIds);
        $paidUsers = $rechargeStats['paid_users'];
        $paidAmount = $rechargeStats['paid_amount'];

        $withdrawOrders = $this->getWithdrawOrders($startTime, $endTime, $channelUserIds);
        $withdrawUsers = count(array_unique(array_column($withdrawOrders, 'user_id')));
        $withdrawAmount = array_sum(array_column($withdrawOrders, 'amount'));

        $orders = $this->getGameTransactions($date, $channelUserIds, 'bet');
        $orderStats = $this->summarizeOrders($orders);

        $rewards = $this->getGameTransactions($date, $channelUserIds, 'win', 1);
        $winAmount = $this->sumRewardAmount($rewards);
        $profit = $orderStats['amount'] - $winAmount;

        $newUserData = $this->getNewUserStats($date, $channelId);
        $oldUserData = $this->getOldUserStats($date, $channelId);

        return [
            'date' => $date,
            'all_dau' => $dau,
            'all_paid_users' => $paidUsers,
            'all_paid_rate' => $dau > 0 ? ($paidUsers / $dau) : 0,
            'all_paid_amount' => round($paidAmount, 2),
            'all_arpu' => $dau > 0 ? round($paidAmount / $dau, 2) : 0,
            'all_arppu' => $paidUsers > 0 ? round($paidAmount / $paidUsers, 2) : 0,
            'all_withdraw_amount' => round($withdrawAmount, 2),
            'all_withdraw_rate' => $dau > 0 ? ($withdrawUsers / $dau) : 0,
            'new_dau' => $newUserData['dau'],
            'new_paid_users' => $newUserData['paid_users'],
            'new_paid_rate' => $newUserData['paid_rate'],
            'new_paid_amount' => $newUserData['paid_amount'],
            'new_arpu' => $newUserData['arpu'],
            'new_arppu' => $newUserData['arppu'],
            'new_withdraw_amount' => $newUserData['withdraw_amount'],
            'new_withdraw_rate' => $newUserData['withdraw_rate'],
            'new_order_count' => $newUserData['order_count'],
            'new_order_users' => $newUserData['order_users'],
            'new_order_amount' => $newUserData['order_amount'],
            'new_order_cash' => $newUserData['order_cash'],
            'new_order_bonus' => $newUserData['order_bonus'],
            'new_reward_amount' => $newUserData['reward_amount'],
            'new_profit' => $newUserData['profit'],
            'old_dau' => $oldUserData['dau'],
            'old_paid_users' => $oldUserData['paid_users'],
            'old_paid_rate' => $oldUserData['paid_rate'],
            'old_paid_amount' => $oldUserData['paid_amount'],
            'old_arpu' => $oldUserData['arpu'],
            'old_arppu' => $oldUserData['arppu'],
            'old_withdraw_amount' => $oldUserData['withdraw_amount'],
            'old_withdraw_rate' => $oldUserData['withdraw_rate'],
            'old_order_count' => $oldUserData['order_count'],
            'old_order_users' => $oldUserData['order_users'],
            'old_order_amount' => $oldUserData['order_amount'],
            'old_order_cash' => $oldUserData['order_cash'],
            'old_order_bonus' => $oldUserData['order_bonus'],
            'old_reward_amount' => $oldUserData['reward_amount'],
            'old_profit' => $oldUserData['profit'],
            'order_count' => $orderStats['count'],
            'order_users' => $orderStats['users'],
            'order_amount' => round($orderStats['amount'], 2),
            'order_cash' => round($orderStats['cash'], 2),
            'order_bonus' => round($orderStats['bonus'], 2),
            'reward_amount' => round($winAmount, 2),
            'profit' => round($profit, 2),
        ];
    }

    private function getNewUserStats(string $date, ?int $channelId = null): array
    {
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = strtotime($date . ' 23:59:59');

        $newUserIds = Db::name('account')
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime);
        if ($channelId) {
            $newUserIds->where('channel_id', $channelId);
        }
        $newUserIds = $newUserIds->column('id');
        if (empty($newUserIds)) {
            return $this->getEmptyUserStats();
        }

        $activeUserIds = UserActiveService::getActiveUserIds($startTime, $endTime, $newUserIds);
        return $this->getUserStatsForIds($date, $startTime, $endTime, $newUserIds, count($activeUserIds));
    }

    private function getOldUserStats(string $date, ?int $channelId = null): array
    {
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = strtotime($date . ' 23:59:59');

        $oldUserIds = Db::name('account')->where('create_time', '<', $startTime);
        if ($channelId) {
            $oldUserIds->where('channel_id', $channelId);
        }
        $oldUserIds = $oldUserIds->column('id');
        if (empty($oldUserIds)) {
            return $this->getEmptyUserStats();
        }

        $activeUserIds = UserActiveService::getActiveUserIds($startTime, $endTime, $oldUserIds);
        if (empty($activeUserIds)) {
            return $this->getEmptyUserStats();
        }

        return $this->getUserStatsForIds($date, $startTime, $endTime, $oldUserIds, count($activeUserIds), $activeUserIds);
    }

    private function getUserStatsForIds(
        string $date,
        int $startTime,
        int $endTime,
        array $userIds,
        int $dau,
        ?array $activeRevenueUserIds = null
    ): array {
        $revenueUserIds = $activeRevenueUserIds ?: $userIds;
        $rechargeStats = self::getPaidRechargeStats($startTime, $endTime, $revenueUserIds);
        $paidUsers = $rechargeStats['paid_users'];
        $paidAmount = $rechargeStats['paid_amount'];

        $withdrawOrders = $this->getWithdrawOrders($startTime, $endTime, $revenueUserIds);
        $withdrawUsers = count(array_unique(array_column($withdrawOrders, 'user_id')));
        $withdrawAmount = array_sum(array_column($withdrawOrders, 'amount'));

        $orders = $this->getGameTransactions($date, $userIds, 'bet');
        $orderStats = $this->summarizeOrders($orders);

        $rewards = $this->getGameTransactions($date, $userIds, 'win', 1);
        $rewardAmount = $this->sumRewardAmount($rewards);
        $profit = $orderStats['amount'] - $rewardAmount;

        return [
            'dau' => $dau,
            'paid_users' => $paidUsers,
            'paid_rate' => $dau > 0 ? round($paidUsers / $dau, 4) : 0,
            'paid_amount' => round($paidAmount, 2),
            'arpu' => $dau > 0 ? round($paidAmount / $dau, 2) : 0,
            'arppu' => $paidUsers > 0 ? round($paidAmount / $paidUsers, 2) : 0,
            'withdraw_amount' => round($withdrawAmount, 2),
            'withdraw_rate' => $dau > 0 ? round($withdrawUsers / $dau, 4) : 0,
            'order_count' => $orderStats['count'],
            'order_users' => $orderStats['users'],
            'order_amount' => round($orderStats['amount'], 2),
            'order_cash' => round($orderStats['cash'], 2),
            'order_bonus' => round($orderStats['bonus'], 2),
            'reward_amount' => round($rewardAmount, 2),
            'profit' => round($profit, 2),
        ];
    }

    private function getWithdrawOrders(int $startTime, int $endTime, ?array $userIds): array
    {
        $query = Db::name('withdraw_orders')
            ->where('status', 2)
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime);
        if ($userIds !== null) {
            if (empty($userIds)) {
                return [];
            }
            $query->whereIn('user_id', $userIds);
        }

        return $query->select()->toArray();
    }

    private function getGameTransactions(string $date, ?array $userIds, ?string $reason = null, ?int $walletType = null): array
    {
        $query = Db::name('game_transactions')
            ->where('req_time', '>=', $date . ' 00:00:00')
            ->where('req_time', '<=', $date . ' 23:59:59');
        if ($reason !== null) {
            $query->where('reason', $reason);
        }
        if ($walletType !== null) {
            $query->where('wallet_type', $walletType);
        }
        if ($userIds !== null) {
            if (empty($userIds)) {
                return [];
            }
            $query->whereIn('user_id', $userIds);
        }

        return $query->select()->toArray();
    }

    private function summarizeOrders(array $orders): array
    {
        $amount = 0;
        $cash = 0;
        $bonus = 0;
        foreach ($orders as $order) {
            $orderAmount = abs((float)$order['amount']);
            $amount += $orderAmount;
            if ((int)$order['wallet_type'] === 1) {
                $cash += $orderAmount;
            } else {
                $bonus += $orderAmount;
            }
        }

        return [
            'count' => count($orders),
            'users' => count(array_unique(array_column($orders, 'user_id'))),
            'amount' => $amount,
            'cash' => $cash,
            'bonus' => $bonus,
        ];
    }

    private function sumRewardAmount(array $rewards): float
    {
        $amount = 0;
        foreach ($rewards as $reward) {
            $amount += (float)($reward['real_amount'] ?? $reward['amount']);
        }
        return $amount;
    }

    private function filterUsersByRegisterTime(array $userIds, int $startTime, int $endTime): array
    {
        if (empty($userIds)) {
            return [];
        }

        return Db::name('account')
            ->whereIn('id', $userIds)
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->column('id');
    }

    private function filterUsersBeforeRegisterTime(array $userIds, int $startTime): array
    {
        if (empty($userIds)) {
            return [];
        }

        return Db::name('account')
            ->whereIn('id', $userIds)
            ->where('create_time', '<', $startTime)
            ->column('id');
    }

    private function getEmptyUserStats(): array
    {
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

    private function getEmptyStats(string $date): array
    {
        $empty = $this->getEmptyUserStats();

        return [
            'date' => $date,
            'all_dau' => 0,
            'all_paid_users' => 0,
            'all_paid_rate' => 0,
            'all_paid_amount' => 0,
            'all_arpu' => 0,
            'all_arppu' => 0,
            'all_withdraw_amount' => 0,
            'all_withdraw_rate' => 0,
            'new_dau' => $empty['dau'],
            'new_paid_users' => $empty['paid_users'],
            'new_paid_rate' => $empty['paid_rate'],
            'new_paid_amount' => $empty['paid_amount'],
            'new_arpu' => $empty['arpu'],
            'new_arppu' => $empty['arppu'],
            'new_withdraw_amount' => $empty['withdraw_amount'],
            'new_withdraw_rate' => $empty['withdraw_rate'],
            'new_order_count' => $empty['order_count'],
            'new_order_users' => $empty['order_users'],
            'new_order_amount' => $empty['order_amount'],
            'new_order_cash' => $empty['order_cash'],
            'new_order_bonus' => $empty['order_bonus'],
            'new_reward_amount' => $empty['reward_amount'],
            'new_profit' => $empty['profit'],
            'old_dau' => $empty['dau'],
            'old_paid_users' => $empty['paid_users'],
            'old_paid_rate' => $empty['paid_rate'],
            'old_paid_amount' => $empty['paid_amount'],
            'old_arpu' => $empty['arpu'],
            'old_arppu' => $empty['arppu'],
            'old_withdraw_amount' => $empty['withdraw_amount'],
            'old_withdraw_rate' => $empty['withdraw_rate'],
            'old_order_count' => $empty['order_count'],
            'old_order_users' => $empty['order_users'],
            'old_order_amount' => $empty['order_amount'],
            'old_order_cash' => $empty['order_cash'],
            'old_order_bonus' => $empty['order_bonus'],
            'old_reward_amount' => $empty['reward_amount'],
            'old_profit' => $empty['profit'],
            'order_count' => 0,
            'order_users' => 0,
            'order_amount' => 0,
            'order_cash' => 0,
            'order_bonus' => 0,
            'reward_amount' => 0,
            'profit' => 0,
        ];
    }

    public static function getAllChannelsWithGlobal(): array
    {
        $channels = [['id' => null, 'name' => '全部渠道']];
        return array_merge(
            $channels,
            Db::name('channel_list')
                ->field('id, name')
                ->order('id', 'asc')
                ->select()
                ->toArray()
        );
    }
}
