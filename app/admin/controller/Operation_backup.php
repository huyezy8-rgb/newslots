<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\facade\Db;

//备份 实时生成
class Operation_backup extends Backend
{
    public function index(): void
    {
        // 获取参数
        $dateRange = $this->request->get('dateRange', []); // [start, end]
        $channelId = $this->request->get('channel_id');
        $tag = $this->request->get('tag'); // 预留
        $page = max(1, (int)$this->request->get('page', 1));
        $pageSize = max(10, (int)$this->request->get('pageSize', 15));

        // ==================== 时间筛选处理 ====================
        
        // 日期区间处理
        if (is_array($dateRange) && count($dateRange) === 2 && !empty($dateRange[0]) && !empty($dateRange[1])) {
            // 前端传入了有效的日期范围
            $startDate = $dateRange[0];
            $endDate = $dateRange[1];
        } else {
            // 默认日期范围：最近14天
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-14 days'));
        }
        
        // 生成日期列表（倒序）
        $dateList = [];
        $cur = strtotime($startDate);
        $end = strtotime($endDate);
        while ($cur <= $end) {
            $dateList[] = date('Y-m-d', $cur);
            $cur = strtotime('+1 day', $cur);
        }
        // 倒序排列，最新的日期在前
        $dateList = array_reverse($dateList);

        // ==================== 数据统计 ====================
        
        // 统计每一天的数据
        $list = [];
        foreach ($dateList as $date) {
            $row = $this->getDailyStats($date, $channelId);
            $list[] = $row;
        }

        // ==================== 多级表头配置 ====================
        $columns = [
            ['label' => '日期', 'prop' => 'date', 'fixed' => 'left', 'width' => 100, 'headerAlign' => 'center'],
            [
                'label' => '所有用户',
                'headerAlign' => 'center',
                'children' => [
                    ['label' => 'DAU', 'prop' => 'all_dau', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '付费人数', 'prop' => 'all_paid_users', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '付费率', 'prop' => 'all_paid_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '付费额', 'prop' => 'all_paid_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => 'ARPU', 'prop' => 'all_arpu', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => 'ARPPU', 'prop' => 'all_arppu', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现金额', 'prop' => 'all_withdraw_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现率', 'prop' => 'all_withdraw_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                ]
            ],
            [
                'label' => '新用户',
                'headerAlign' => 'center',
                'children' => [
                    ['label' => 'DAU', 'prop' => 'new_dau', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '付费人数', 'prop' => 'new_paid_users', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '付费率', 'prop' => 'new_paid_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '付费额', 'prop' => 'new_paid_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => 'ARPU', 'prop' => 'new_arpu', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => 'ARPPU', 'prop' => 'new_arppu', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现金额', 'prop' => 'new_withdraw_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现率', 'prop' => 'new_withdraw_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                ]
            ],
            [
                'label' => '老用户',
                'headerAlign' => 'center',
                'children' => [
                    ['label' => 'DAU', 'prop' => 'old_dau', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '付费人数', 'prop' => 'old_paid_users', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '付费率', 'prop' => 'old_paid_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '付费额', 'prop' => 'old_paid_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => 'ARPU', 'prop' => 'old_arpu', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => 'ARPPU', 'prop' => 'old_arppu', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现金额', 'prop' => 'old_withdraw_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现率', 'prop' => 'old_withdraw_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                ]
            ],
            [
                'label' => '下单',
                'headerAlign' => 'center',
                'children' => [
                    ['label' => '笔数', 'prop' => 'order_count', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '人数', 'prop' => 'order_users', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '总金额', 'prop' => 'order_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '现金', 'prop' => 'order_cash', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '彩金', 'prop' => 'order_bonus', 'width' => 80, 'headerAlign' => 'center'],
                ]
            ],
            [
                'label' => '返奖',
                'headerAlign' => 'center',
                'children' => [
                    ['label' => '返奖金额', 'prop' => 'reward_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '运营商盈利', 'prop' => 'profit', 'width' => 100, 'headerAlign' => 'center'],
                ]
            ],
        ];

        // 分页
        $total = count($list);
        $list = array_slice($list, ($page - 1) * $pageSize, $pageSize);

        $this->success('OK', [
            'columns' => $columns,
            'list' => $list,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
            ],
        ]);
    }

    /**
     * 获取指定日期的统计数据
     * @param string $date 日期 Y-m-d 格式
     * @param int|null $channelId 渠道ID
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
        $previousActiveUsers = [];
        
        // 1.2.1 游戏交易活跃用户（当日有游戏记录的用户）
        $gameActiveUsers = Db::name('game_transactions')
            ->whereBetweenTime('req_time', $date . ' 00:00:00', $date . ' 23:59:59');
        if ($channelId && !empty($userIds)) {
            $gameActiveUsers = $gameActiveUsers->whereIn('user_id', $userIds);
        }
        $gameActiveUsers = $gameActiveUsers->distinct(true)->column('user_id');
        
        // 1.2.2 充值活跃用户（当日有充值记录的用户）
        $rechargeActiveUsers = Db::name('recharge_orders')
            ->whereBetweenTime('created_at', $startTime, $endTime);
        if ($channelId && !empty($userIds)) {
            $rechargeActiveUsers = $rechargeActiveUsers->whereIn('user_id', $userIds);
        }
        $rechargeActiveUsers = $rechargeActiveUsers->distinct(true)->column('user_id');
        
        // 1.2.3 提现活跃用户（当日有提现记录的用户）
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
            ->where('pay_status', 1) // 支付成功
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
            ->where('status', 2) // 提现成功
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime);
        if ($channelId && !empty($userIds)) {
            $withdrawQuery = $withdrawQuery->whereIn('user_id', $userIds);
        }
        $withdrawOrders = $withdrawQuery->select()->toArray();
        
        $withdrawUsers = count(array_unique(array_column($withdrawOrders, 'user_id')));
        $withdrawAmount = array_sum(array_column($withdrawOrders, 'amount'));

        // ==================== 4. 下单数据统计（游戏交易记录） ====================
        
        // 4.1 获取当日游戏交易数据（reason为'bet'的下注记录）
        $orderQuery = Db::name('game_transactions')
            ->where('reason', 'bet') // 下注记录
            ->where('req_time', '>=', $date . ' 00:00:00')
            ->where('req_time', '<=', $date . ' 23:59:59');
        if ($channelId && !empty($userIds)) {
            $orderQuery = $orderQuery->whereIn('user_id', $userIds);
        }
        $allOrders = $orderQuery->select()->toArray();
        
        $orderCount = count($allOrders); // 下单笔数：当日下注交易记录数量
        $orderUsers = count(array_unique(array_column($allOrders, 'user_id'))); // 下单人数：当日有下注记录的用户数
        $orderAmount = array_sum(array_map(function($order) { return abs($order['amount']); }, $allOrders)); // 下单总金额：当日下注总金额（取正数）
        
        // 4.2 按钱包类型统计现金和彩金下单（取正数）
        $orderCash = 0; // 现金下单金额
        $orderBonus = 0; // 彩金下单金额
        foreach ($allOrders as $order) {
            if ($order['wallet_type'] == 1) {
                $orderCash += abs($order['amount']); // wallet_type=1 为现金，取正数
            } else {
                $orderBonus += abs($order['amount']); // wallet_type=0 为彩金，取正数
            }
        }

        // ==================== 5. 游戏数据统计（投注和返奖） ====================
        
        // 5.1 获取当日游戏交易数据
        $gameQuery = Db::name('game_transactions')
            ->where('req_time', '>=', $date . ' 00:00:00')
            ->where('req_time', '<=', $date . ' 23:59:59');
        if ($channelId && !empty($userIds)) {
            $gameQuery = $gameQuery->whereIn('user_id', $userIds);
        }
        $gameTransactions = $gameQuery->select()->toArray();
        
        // 5.2 计算投注金额（reason为'bet'的交易，取正数）
        $betAmount = 0;
        foreach ($gameTransactions as $transaction) {
            if ($transaction['reason'] === 'bet') {
                $betAmount += abs($transaction['amount']); // 取正数
            }
        }
        
        // 5.3 计算返奖金额（reason为'win'且wallet_type=1的现金赢取记录）
        $winAmount = 0;
        foreach ($gameTransactions as $transaction) {
            if ($transaction['reason'] === 'win' && $transaction['wallet_type'] == 1) {
                $winAmount += $transaction['real_amount'] ?? $transaction['amount'];
            }
        }
        
        // 5.4 计算运营商盈利：下单金额 - 返奖金额
        $profit = $orderAmount - $winAmount;

        // ==================== 6. 新老用户数据 ====================
        
        $newUserData = $this->getNewUserStats($date, $channelId);
        $oldUserData = $this->getOldUserStats($date, $channelId);

        // ==================== 7. 核心指标计算 ====================
        
        // 7.1 付费率：付费用户数 / DAU（不乘以100，前端formatter会处理）
        $paidRate = $dau > 0 ? ($paidUsers / $dau) : 0;
        
        // 7.2 提现率：提现用户数 / DAU（不乘以100，前端formatter会处理）
        $withdrawRate = $dau > 0 ? ($withdrawUsers / $dau) : 0;
        
        // 7.3 ARPU（Average Revenue Per User，平均每用户收入）：付费总金额 / DAU
        $arpu = $dau > 0 ? ($paidAmount / $dau) : 0;
        
        // 7.4 ARPPU（Average Revenue Per Paying User，平均每付费用户收入）：付费总金额 / 付费用户数
        $arppu = $paidUsers > 0 ? ($paidAmount / $paidUsers) : 0;

        // ==================== 8. 返回完整的统计数据 ====================
        $result = [
            'date' => $date, // 统计日期
            
            // ==================== 所有用户数据 ====================
            'all_dau' => $dau, // DAU：当日注册用户 + 往日注册但当日活跃的用户
            'all_paid_users' => $paidUsers, // 付费人数：当日有充值成功的用户数
            'all_paid_rate' => $paidRate, // 付费率：付费人数 / DAU（前端formatter会转换为百分比）
            'all_paid_amount' => round($paidAmount, 2), // 付费额：当日充值成功总金额
            'all_arpu' => round($arpu, 2), // ARPU：付费额 / DAU（平均每用户收入，保留两位小数）
            'all_arppu' => round($arppu, 2), // ARPPU：付费额 / 付费人数（平均每付费用户收入，保留两位小数）
            'all_withdraw_amount' => round($withdrawAmount, 2), // 提现金额：当日提现成功总金额
            'all_withdraw_rate' => $withdrawRate, // 提现率：提现用户数 / DAU（前端formatter会转换为百分比）
            
            // ==================== 新用户数据 ====================
            'new_dau' => $newUserData['dau'], // 新用户DAU：当日注册且当日活跃的用户数
            'new_paid_users' => $newUserData['paid_users'], // 新用户付费人数：当日注册且当日付费的用户数
            'new_paid_rate' => $newUserData['paid_rate'], // 新用户付费率：新用户付费人数 / 新用户DAU（前端formatter会转换为百分比）
            'new_paid_amount' => $newUserData['paid_amount'], // 新用户付费额：新用户当日充值成功总金额
            'new_arpu' => round($newUserData['arpu'], 2), // 新用户ARPU：新用户付费额 / 新用户DAU（平均每新用户收入，保留两位小数）
            'new_arppu' => round($newUserData['arppu'], 2), // 新用户ARPPU：新用户付费额 / 新用户付费人数（平均每新付费用户收入，保留两位小数）
            'new_withdraw_amount' => $newUserData['withdraw_amount'], // 新用户提现金额：新用户当日提现成功总金额
            'new_withdraw_rate' => $newUserData['withdraw_rate'], // 新用户提现率：新用户提现人数 / 新用户DAU（前端formatter会转换为百分比）

            // ==================== 老用户数据 ====================
            'old_dau' => $oldUserData['dau'], // 老用户DAU：往日注册且当日活跃的用户数
            'old_paid_users' => $oldUserData['paid_users'], // 老用户付费人数：往日注册且当日付费的用户数
            'old_paid_rate' => $oldUserData['paid_rate'], // 老用户付费率：老用户付费人数 / 老用户DAU（前端formatter会转换为百分比）
            'old_paid_amount' => $oldUserData['paid_amount'], // 老用户付费额：老用户当日充值成功总金额
            'old_arpu' => round($oldUserData['arpu'], 2), // 老用户ARPU：老用户付费额 / 老用户DAU（平均每老用户收入，保留两位小数）
            'old_arppu' => round($oldUserData['arppu'], 2), // 老用户ARPPU：老用户付费额 / 老用户付费人数（平均每老付费用户收入，保留两位小数）
            'old_withdraw_amount' => $oldUserData['withdraw_amount'], // 老用户提现金额：老用户当日提现成功总金额
            'old_withdraw_rate' => $oldUserData['withdraw_rate'], // 老用户提现率：老用户提现人数 / 老用户DAU（前端formatter会转换为百分比）
            
            // ==================== 下单数据（游戏下注） ====================
            'order_count' => $orderCount, // 下单笔数：当日下注交易记录数量
            'order_users' => $orderUsers, // 下单人数：当日有下注记录的用户数
            'order_amount' => round($orderAmount, 2), // 下单总金额：当日下注总金额（取正数）
            'order_cash' => round($orderCash, 2), // 现金下单：当日现金钱包下注总金额（wallet_type=1，取正数）
            'order_bonus' => round($orderBonus, 2), // 彩金下单：当日彩金钱包下注总金额（wallet_type=0，取正数）
            
            // ==================== 返奖数据 ====================
            'reward_amount' => round($winAmount, 2), // 返奖金额：当日现金赢取总金额（reason='win'且wallet_type=1）
            'profit' => round($profit, 2), // 运营商盈利：下单金额 - 返奖金额
        ];
        
        return $result;
    }

    /**
     * 获取渠道列表
     */
    public function getChannels(): void
    {
        try {
            $channels = Db::name('channel_list')
                ->field("id, name")
                ->order('id', 'asc')
                ->select();
            

        } catch (\Exception $e) {
            $this->error('获取渠道列表失败: ' . $e->getMessage());
        }
        $this->success('获取渠道列表成功', $channels);
    }

    /**
     * 获取空统计数据（当渠道没有用户时返回）
     * @param string $date 日期
     * @return array
     */
    private function getEmptyStats(string $date): array
    {
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
            'new_dau' => 0,
            'new_paid_users' => 0,
            'new_paid_rate' => 0,
            'new_paid_amount' => 0,
            'new_arpu' => 0,
            'new_arppu' => 0,
            'new_withdraw_amount' => 0,
            'new_withdraw_rate' => 0,
            'old_dau' => 0,
            'old_paid_users' => 0,
            'old_paid_rate' => 0,
            'old_paid_amount' => 0,
            'old_arpu' => 0,
            'old_arppu' => 0,
            'old_withdraw_amount' => 0,
            'old_withdraw_rate' => 0,
            'order_count' => 0,
            'order_users' => 0,
            'order_amount' => 0,
            'order_cash' => 0,
            'order_bonus' => 0,
            'reward_amount' => 0,
            'profit' => 0,
        ];
    }

    /**
     * 获取新用户统计数据（当日注册的用户）
     * @param string $date 日期
     * @param int|null $channelId 渠道ID
     * @return array
     */
    private function getNewUserStats(string $date, ?int $channelId = null): array
    {
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = strtotime($date . ' 23:59:59');
        
        // 获取新用户ID列表
        $newUserIds = Db::name('account')
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime);
        if ($channelId) {
            $newUserIds = $newUserIds->where('channel_id', $channelId);
        }
        $newUserIds = $newUserIds->column('id');
        
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
            ];
        }

        // 新用户DAU（简化：当日注册的用户数）
        $newDau = count($newUserIds);

        // 新用户付费数据
        $newRechargeOrders = Db::name('recharge_orders')
            ->where('pay_status', 1)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime)
            ->whereIn('user_id', $newUserIds)
            ->select()
            ->toArray();
        
        $newPaidUsers = count(array_unique(array_column($newRechargeOrders, 'user_id')));
        $newPaidAmount = array_sum(array_column($newRechargeOrders, 'amount'));

        // 新用户提现数据
        $newWithdrawOrders = Db::name('withdraw_orders')
            ->where('status', 2)
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->whereIn('user_id', $newUserIds)
            ->select()
            ->toArray();
        
        $newWithdrawAmount = array_sum(array_column($newWithdrawOrders, 'amount'));

        // 计算指标
        // 新用户付费率：新用户付费人数 / 新用户DAU（不乘以100，前端formatter会处理）
        $newPaidRate = $newDau > 0 ? ($newPaidUsers / $newDau) : 0;
        
        // 新用户提现率：新用户提现人数 / 新用户DAU（不乘以100，前端formatter会处理）
        $newWithdrawRate = $newDau > 0 ? (count(array_unique(array_column($newWithdrawOrders, 'user_id'))) / $newDau) : 0;
        
        // 新用户ARPU（Average Revenue Per User，平均每新用户收入）：新用户付费总金额 / 新用户DAU
        $newArpu = $newDau > 0 ? ($newPaidAmount / $newDau) : 0;
        
        // 新用户ARPPU（Average Revenue Per Paying User，平均每新付费用户收入）：新用户付费总金额 / 新用户付费人数
        $newArppu = $newPaidUsers > 0 ? ($newPaidAmount / $newPaidUsers) : 0;

        return [
            'dau' => $newDau, // 新用户DAU：当日注册的用户数
            'paid_users' => $newPaidUsers, // 新用户付费人数：当日注册且当日付费的用户数
            'paid_rate' => $newPaidRate, // 新用户付费率：新用户付费人数 / 新用户DAU（前端formatter会转换为百分比）
            'paid_amount' => round($newPaidAmount, 2), // 新用户付费额：新用户当日充值成功总金额
            'arpu' => round($newArpu, 2), // 新用户ARPU：新用户付费额 / 新用户DAU（平均每新用户收入，保留两位小数）
            'arppu' => round($newArppu, 2), // 新用户ARPPU：新用户付费额 / 新用户付费人数（平均每新付费用户收入，保留两位小数）
            'withdraw_amount' => round($newWithdrawAmount, 2), // 新用户提现金额：新用户当日提现成功总金额
            'withdraw_rate' => $newWithdrawRate, // 新用户提现率：新用户提现人数 / 新用户DAU（前端formatter会转换为百分比）
        ];
    }

    /**
     * 获取老用户统计数据（往日注册的用户）
     * @param string $date 日期
     * @param int|null $channelId 渠道ID
     * @return array
     */
    private function getOldUserStats(string $date, ?int $channelId = null): array
    {
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = strtotime($date . ' 23:59:59');
        
        // 获取老用户ID列表（往日注册的用户）
        $oldUserIds = Db::name('account')
            ->where('create_time', '<', $startTime);
        if ($channelId) {
            $oldUserIds = $oldUserIds->where('channel_id', $channelId);
        }
        $oldUserIds = $oldUserIds->column('id');
        
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
            ];
        }

        // 老用户DAU（往日注册且在当日有活动的用户）
        $oldActiveUsers = [];
        
        // 获取老用户当日活跃记录
        $oldGameActiveUsers = Db::name('game_transactions')
            ->whereIn('user_id', $oldUserIds)
            ->whereBetweenTime('req_time', $date . ' 00:00:00', $date . ' 23:59:59')
            ->distinct(true)
            ->column('user_id');
        
        $oldRechargeActiveUsers = Db::name('recharge_orders')
            ->whereIn('user_id', $oldUserIds)
            ->whereBetweenTime('created_at', $startTime, $endTime)
            ->distinct(true)
            ->column('user_id');
        
        $oldWithdrawActiveUsers = Db::name('withdraw_orders')
            ->whereIn('user_id', $oldUserIds)
            ->whereBetweenTime('create_time', $startTime, $endTime)
            ->distinct(true)
            ->column('user_id');
        
        // 合并所有老用户活跃用户（去重）
        $oldActiveUsers = array_unique(array_merge($oldGameActiveUsers, $oldRechargeActiveUsers, $oldWithdrawActiveUsers));

        $oldDau = count($oldActiveUsers);

        // 老用户付费数据
        $oldRechargeOrders = Db::name('recharge_orders')
            ->where('pay_status', 1)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime)
            ->whereIn('user_id', $oldUserIds)
            ->select()
            ->toArray();
        
        $oldPaidUsers = count(array_unique(array_column($oldRechargeOrders, 'user_id')));
        $oldPaidAmount = array_sum(array_column($oldRechargeOrders, 'amount'));

        // 老用户提现数据
        $oldWithdrawOrders = Db::name('withdraw_orders')
            ->where('status', 2)
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->whereIn('user_id', $oldUserIds)
            ->select()
            ->toArray();
        
        $oldWithdrawAmount = array_sum(array_column($oldWithdrawOrders, 'amount'));

        // 计算指标
        // 老用户付费率：老用户付费人数 / 老用户DAU（不乘以100，前端formatter会处理）
        $oldPaidRate = $oldDau > 0 ? ($oldPaidUsers / $oldDau) : 0;
        
        // 老用户提现率：老用户提现人数 / 老用户DAU（不乘以100，前端formatter会处理）
        $oldWithdrawRate = $oldDau > 0 ? (count(array_unique(array_column($oldWithdrawOrders, 'user_id'))) / $oldDau) : 0;
        
        // 老用户ARPU（Average Revenue Per User，平均每老用户收入）：老用户付费总金额 / 老用户DAU
        $oldArpu = $oldDau > 0 ? ($oldPaidAmount / $oldDau) : 0;
        
        // 老用户ARPPU（Average Revenue Per Paying User，平均每老付费用户收入）：老用户付费总金额 / 老用户付费人数
        $oldArppu = $oldPaidUsers > 0 ? ($oldPaidAmount / $oldPaidUsers) : 0;

        return [
            'dau' => $oldDau, // 老用户DAU：往日注册且在当日有活动的用户数
            'paid_users' => $oldPaidUsers, // 老用户付费人数：往日注册且当日付费的用户数
            'paid_rate' => $oldPaidRate, // 老用户付费率：老用户付费人数 / 老用户DAU（前端formatter会转换为百分比）
            'paid_amount' => round($oldPaidAmount, 2), // 老用户付费额：老用户当日充值成功总金额
            'arpu' => round($oldArpu, 2), // 老用户ARPU：老用户付费额 / 老用户DAU（平均每老用户收入，保留两位小数）
            'arppu' => round($oldArppu, 2), // 老用户ARPPU：老用户付费额 / 老用户付费人数（平均每老付费用户收入，保留两位小数）
            'withdraw_amount' => round($oldWithdrawAmount, 2), // 老用户提现金额：老用户当日提现成功总金额
            'withdraw_rate' => $oldWithdrawRate, // 老用户提现率：老用户提现人数 / 老用户DAU（前端formatter会转换为百分比）
        ];
    }


} 