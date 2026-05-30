<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\service\OperationDataService;
use think\facade\Db;

class Operation extends Backend
{
    public function index(): void
    {
        // 获取参数
        $dateRange = $this->request->get('dateRange', []); // [start, end]
        $channelId = $this->request->get('channel_id');
        //获取当前登录管理员绑定的渠道id
        if ($this->getCurrentAdminChannelId() !== null) {
            $channelId = $this->getCurrentAdminChannelId();
        }
        $tag = $this->request->get('tag'); // 预留
        $page = max(1, (int)$this->request->get('page', 1));
        $pageSize = max(10, (int)$this->request->get('pageSize', 15));

        // ==================== 时间筛选处理 ====================

        // 日期区间处理
        if (is_array($dateRange) && count($dateRange) === 2 && !empty($dateRange[0]) && !empty($dateRange[1])) {
            $startDate = $dateRange[0];
            $endDate = $dateRange[1];
            // 限制结束时间不能大于今天
            $today = date('Y-m-d');
            if (strtotime($endDate) > strtotime($today)) {
                $endDate = $today;
            }
        } else {
            // 默认日期范围：从用户注册最小时间开始到今天
            $minRegisterTime = Db::name('account')->min('create_time');
            if ($minRegisterTime) {
                $startDate = date('Y-m-d', $minRegisterTime);
            } else {
                // 如果没有用户数据，使用最近14天作为默认
                $startDate = date('Y-m-d', strtotime('-14 days'));
            }
            $endDate = date('Y-m-d'); // 今天，包含当天数据
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

        // ==================== 数据查询 ====================

        // 从数据表中查询历史记录
        $list = $this->getDataFromTable($dateList, $channelId);

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
                ]
            ],
            [
                'label' => '新用户',
                'headerAlign' => 'center',
                'children' => [
                    ['label' => 'DAU', 'prop' => 'new_dau', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '付费人数', 'prop' => 'new_paid_users', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '付费额', 'prop' => 'new_paid_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '付费率', 'prop' => 'new_paid_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => 'ARPU', 'prop' => 'new_arpu', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => 'ARPPU', 'prop' => 'new_arppu', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现金额', 'prop' => 'new_withdraw_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现率', 'prop' => 'new_withdraw_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                    [
                        'label' => '下单',
                        'headerAlign' => 'center',
                        'children' => [
                            ['label' => '笔数', 'prop' => 'new_order_count', 'width' => 80, 'headerAlign' => 'center'],
                            ['label' => '人数', 'prop' => 'new_order_users', 'width' => 80, 'headerAlign' => 'center'],
                            ['label' => '总金额', 'prop' => 'new_order_amount', 'width' => 100, 'headerAlign' => 'center'],
                            ['label' => '现金', 'prop' => 'new_order_cash', 'width' => 80, 'headerAlign' => 'center'],
                            ['label' => '彩金', 'prop' => 'new_order_bonus', 'width' => 80, 'headerAlign' => 'center'],
                        ]
                    ],
                    [
                        'label' => '返奖',
                        'headerAlign' => 'center',
                        'children' => [
                            ['label' => '返奖金额', 'prop' => 'new_reward_amount', 'width' => 100, 'headerAlign' => 'center'],
                            ['label' => '运营商盈利', 'prop' => 'new_profit', 'width' => 100, 'headerAlign' => 'center'],
                        ]
                    ],
                ]
            ],
            [
                'label' => '老用户',
                'headerAlign' => 'center',
                'children' => [
                    ['label' => 'DAU', 'prop' => 'old_dau', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => '付费人数', 'prop' => 'old_paid_users', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '付费额', 'prop' => 'old_paid_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '付费率', 'prop' => 'old_paid_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => 'ARPU', 'prop' => 'old_arpu', 'width' => 80, 'headerAlign' => 'center'],
                    ['label' => 'ARPPU', 'prop' => 'old_arppu', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现金额', 'prop' => 'old_withdraw_amount', 'width' => 100, 'headerAlign' => 'center'],
                    ['label' => '提现率', 'prop' => 'old_withdraw_rate', 'formatter' => 'percent', 'width' => 80, 'headerAlign' => 'center'],
                    [
                        'label' => '下单',
                        'headerAlign' => 'center',
                        'children' => [
                            ['label' => '笔数', 'prop' => 'old_order_count', 'width' => 80, 'headerAlign' => 'center'],
                            ['label' => '人数', 'prop' => 'old_order_users', 'width' => 80, 'headerAlign' => 'center'],
                            ['label' => '总金额', 'prop' => 'old_order_amount', 'width' => 100, 'headerAlign' => 'center'],
                            ['label' => '现金', 'prop' => 'old_order_cash', 'width' => 80, 'headerAlign' => 'center'],
                            ['label' => '彩金', 'prop' => 'old_order_bonus', 'width' => 80, 'headerAlign' => 'center'],
                        ]
                    ],
                    [
                        'label' => '返奖',
                        'headerAlign' => 'center',
                        'children' => [
                            ['label' => '返奖金额', 'prop' => 'old_reward_amount', 'width' => 100, 'headerAlign' => 'center'],
                            ['label' => '运营商盈利', 'prop' => 'old_profit', 'width' => 100, 'headerAlign' => 'center'],
                        ]
                    ],
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
     * 从数据表中批量查询历史记录
     * @param array $dateList 日期列表
     * @param int|null $channelId 渠道ID
     * @return array
     */
    public function getDataFromTable(array $dateList, ?int $channelId = null): array
    {
        $today = date('Y-m-d');

        // 是否计算当天数据（设为 false 可避免查询超时）
        $calculateToday = true;

        // 分离当天日期和历史日期
        $todayDates = [];
        $historyDates = [];
        foreach ($dateList as $date) {
            if ($date === $today) {
                $todayDates[] = $date;
            } else {
                $historyDates[] = $date;
            }
        }

        $list = [];

        // 处理历史数据：优先从数据表查询，没有则实时生成
        if (!empty($historyDates)) {
            $dbDataMap = \app\common\model\OperationData::getByDateListAndChannel($historyDates, $channelId);

            foreach ($historyDates as $date) {
                if (isset($dbDataMap[$date])) {
                    // 数据表中存在数据，直接使用
                    $list[] = $dbDataMap[$date];
                } else {
                    // 数据表中不存在数据，实时计算并保存到数据库
                    $data = $this->getDailyStats($date, $channelId);
                    $list[] = $data;

                    // 异步保存到数据库（避免影响响应速度）
                    try {
                        \app\common\model\OperationData::saveData($date, $data, $channelId);
                    } catch (\Exception $e) {
                        // 保存失败不影响返回数据，只记录日志
                        \think\facade\Log::error("保存运营数据失败: {$date}, channel_id: {$channelId}, error: " . $e->getMessage());
                    }
                }
            }
        }

        // 处理当天数据：根据开关决定是否计算
        if (!empty($todayDates)) {
            if ($calculateToday) {
                // 开关开启：优先从数据库读取，没有则实时计算
                $todayDbDataMap = \app\common\model\OperationData::getByDateListAndChannel($todayDates, $channelId);

                foreach ($todayDates as $date) {
                    if (isset($todayDbDataMap[$date])) {
                        \think\facade\Log::info("读取数据: {$date}, channel_id: {$channelId}");
                        // 数据表中存在当天数据，直接使用
                        $list[] = $todayDbDataMap[$date];
                    } else {
                        \think\facade\Log::info("保存当天运营数据: {$date}, channel_id: {$channelId}");
                        // 数据表中不存在当天数据，实时计算并保存到数据库
                        $data = $this->getDailyStats($date, $channelId);
                        $list[] = $data;

                        // 异步保存到数据库（避免影响响应速度）
                        try {
                            \app\common\model\OperationData::saveData($date, $data, $channelId);
                        } catch (\Exception $e) {
                            // 保存失败不影响返回数据，只记录日志
                            \think\facade\Log::error("保存当天运营数据失败: {$date}, channel_id: {$channelId}, error: " . $e->getMessage());
                        }
                    }
                }
            } else {
                // 开关关闭：直接不展示当天数据（跳过）
                // 什么都不做
            }
        }

        // 按原始日期顺序重新排序（只保留已加入 $list 的日期）
        $result = [];
        foreach ($dateList as $date) {
            foreach ($list as $item) {
                $data = $item['data'] ?? $item;
                if (isset($data['date']) && $data['date'] === $date) {
                    // 合并 update_time、today_update_time 字段
                    if (isset($item['update_time'])) $data['update_time'] = $item['update_time'];
                    if (isset($item['today_update_time'])) $data['today_update_time'] = $item['today_update_time'];
                    $result[] = $data;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * 获取指定日期的统计数据
     * @param string $date 日期 Y-m-d 格式
     * @param int|null $channelId 渠道ID
     * @return array
     */
    public function getDailyStats(string $date, ?int $channelId = null): array
    {
        return (new OperationDataService())->getDailyStats($date, $channelId);

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

        $rechargeStats = OperationDataService::getPaidRechargeStats($startTime, $endTime, $channelId ? $userIds : null);
        $paidUsers = $rechargeStats['paid_users'];
        $paidAmount = $rechargeStats['paid_amount'];

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
            // ==================== 新用户下单数据 ====================
            'new_order_count' => $newUserData['order_count'], // 新用户下单笔数：当日注册用户的下注交易记录数量
            'new_order_users' => $newUserData['order_users'], // 新用户下单人数：当日注册且有下注记录的用户数
            'new_order_amount' => $newUserData['order_amount'], // 新用户下单总金额：当日注册用户下注总金额
            'new_order_cash' => $newUserData['order_cash'], // 新用户现金下单：当日注册用户现金钱包下注总金额
            'new_order_bonus' => $newUserData['order_bonus'], // 新用户彩金下单：当日注册用户彩金钱包下注总金额
            // ==================== 新用户返奖数据 ====================
            'new_reward_amount' => $newUserData['reward_amount'], // 新用户返奖金额：当日注册用户现金赢取总金额
            'new_profit' => $newUserData['profit'], // 新用户运营商盈利：新用户下单金额 - 新用户返奖金额

            // ==================== 老用户数据 ====================
            'old_dau' => $oldUserData['dau'], // 老用户DAU：往日注册且当日活跃的用户数
            'old_paid_users' => $oldUserData['paid_users'], // 老用户付费人数：往日注册且当日付费的用户数
            'old_paid_rate' => $oldUserData['paid_rate'], // 老用户付费率：老用户付费人数 / 老用户DAU（前端formatter会转换为百分比）
            'old_paid_amount' => $oldUserData['paid_amount'], // 老用户付费额：老用户当日充值成功总金额
            'old_arpu' => round($oldUserData['arpu'], 2), // 老用户ARPU：老用户付费额 / 老用户DAU（平均每老用户收入，保留两位小数）
            'old_arppu' => round($oldUserData['arppu'], 2), // 老用户ARPPU：老用户付费额 / 老用户付费人数（平均每老付费用户收入，保留两位小数）
            'old_withdraw_amount' => $oldUserData['withdraw_amount'], // 老用户提现金额：老用户当日提现成功总金额
            'old_withdraw_rate' => $oldUserData['withdraw_rate'], // 老用户提现率：老用户提现人数 / 老用户DAU（前端formatter会转换为百分比）
            // ==================== 老用户下单数据 ====================
            'old_order_count' => $oldUserData['order_count'], // 老用户下单笔数：往日注册用户的下注交易记录数量
            'old_order_users' => $oldUserData['order_users'], // 老用户下单人数：往日注册且有下注记录的用户数
            'old_order_amount' => $oldUserData['order_amount'], // 老用户下单总金额：往日注册用户下注总金额
            'old_order_cash' => $oldUserData['order_cash'], // 老用户现金下单：往日注册用户现金钱包下注总金额
            'old_order_bonus' => $oldUserData['order_bonus'], // 老用户彩金下单：往日注册用户彩金钱包下注总金额
            // ==================== 老用户返奖数据 ====================
            'old_reward_amount' => $oldUserData['reward_amount'], // 老用户返奖金额：往日注册用户现金赢取总金额
            'old_profit' => $oldUserData['profit'], // 老用户运营商盈利：老用户下单金额 - 老用户返奖金额

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
            'new_order_count' => 0,
            'new_order_users' => 0,
            'new_order_amount' => 0,
            'new_order_cash' => 0,
            'new_order_bonus' => 0,
            'new_reward_amount' => 0,
            'new_profit' => 0,
            'old_dau' => 0,
            'old_paid_users' => 0,
            'old_paid_rate' => 0,
            'old_paid_amount' => 0,
            'old_arpu' => 0,
            'old_arppu' => 0,
            'old_withdraw_amount' => 0,
            'old_withdraw_rate' => 0,
            'old_order_count' => 0,
            'old_order_users' => 0,
            'old_order_amount' => 0,
            'old_order_cash' => 0,
            'old_order_bonus' => 0,
            'old_reward_amount' => 0,
            'old_profit' => 0,
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
                'order_count' => 0,
                'order_users' => 0,
                'order_amount' => 0,
                'order_cash' => 0,
                'order_bonus' => 0,
                'reward_amount' => 0,
                'profit' => 0,
            ];
        }

        // 新用户DAU（简化：当日注册的用户数）
        $newDau = count($newUserIds);

        // 新用户付费数据
        $newRechargeStats = OperationDataService::getPaidRechargeStats($startTime, $endTime, $newUserIds);
        $newPaidUsers = $newRechargeStats['paid_users'];
        $newPaidAmount = $newRechargeStats['paid_amount'];

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

        // 新用户下单数据统计
        $newOrderQuery = Db::name('game_transactions')
            ->where('reason', 'bet') // 下注记录
            ->where('req_time', '>=', $date . ' 00:00:00')
            ->where('req_time', '<=', $date . ' 23:59:59')
            ->whereIn('user_id', $newUserIds);
        $newOrders = $newOrderQuery->select()->toArray();

        $newOrderCount = count($newOrders); // 新用户下单笔数
        $newOrderUsers = count(array_unique(array_column($newOrders, 'user_id'))); // 新用户下单人数
        $newOrderAmount = array_sum(array_map(function($order) { return abs($order['amount']); }, $newOrders)); // 新用户下单总金额

        // 新用户按钱包类型统计下单
        $newOrderCash = 0; // 新用户现金下单金额
        $newOrderBonus = 0; // 新用户彩金下单金额
        foreach ($newOrders as $order) {
            if ($order['wallet_type'] == 1) {
                $newOrderCash += abs($order['amount']); // wallet_type=1 为现金
            } else {
                $newOrderBonus += abs($order['amount']); // wallet_type=0 为彩金
            }
        }

        // 新用户返奖数据统计
        $newRewardQuery = Db::name('game_transactions')
            ->where('reason', 'win') // 赢取记录
            ->where('wallet_type', 1) // 现金钱包
            ->where('req_time', '>=', $date . ' 00:00:00')
            ->where('req_time', '<=', $date . ' 23:59:59')
            ->whereIn('user_id', $newUserIds);
        $newRewards = $newRewardQuery->select()->toArray();

        $newRewardAmount = 0; // 新用户返奖金额
        foreach ($newRewards as $reward) {
            $newRewardAmount += $reward['real_amount'] ?? $reward['amount'];
        }

        // 新用户运营商盈利：新用户下单金额 - 新用户返奖金额
        $newProfit = $newOrderAmount - $newRewardAmount;

        return [
            'dau' => $newDau, // 新用户DAU：当日注册的用户数
            'paid_users' => $newPaidUsers, // 新用户付费人数：当日注册且当日付费的用户数
            'paid_rate' => $newPaidRate, // 新用户付费率：新用户付费人数 / 新用户DAU（前端formatter会转换为百分比）
            'paid_amount' => round($newPaidAmount, 2), // 新用户付费额：新用户当日充值成功总金额
            'arpu' => round($newArpu, 2), // 新用户ARPU：新用户付费额 / 新用户DAU（平均每新用户收入，保留两位小数）
            'arppu' => round($newArppu, 2), // 新用户ARPPU：新用户付费额 / 新用户付费人数（平均每新付费用户收入，保留两位小数）
            'withdraw_amount' => round($newWithdrawAmount, 2), // 新用户提现金额：新用户当日提现成功总金额
            'withdraw_rate' => $newWithdrawRate, // 新用户提现率：新用户提现人数 / 新用户DAU（前端formatter会转换为百分比）
            // ==================== 新用户下单数据 ====================
            'order_count' => $newOrderCount, // 新用户下单笔数：当日注册用户的下注交易记录数量
            'order_users' => $newOrderUsers, // 新用户下单人数：当日注册且有下注记录的用户数
            'order_amount' => round($newOrderAmount, 2), // 新用户下单总金额：当日注册用户下注总金额
            'order_cash' => round($newOrderCash, 2), // 新用户现金下单：当日注册用户现金钱包下注总金额
            'order_bonus' => round($newOrderBonus, 2), // 新用户彩金下单：当日注册用户彩金钱包下注总金额
            // ==================== 新用户返奖数据 ====================
            'reward_amount' => round($newRewardAmount, 2), // 新用户返奖金额：当日注册用户现金赢取总金额
            'profit' => round($newProfit, 2), // 新用户运营商盈利：新用户下单金额 - 新用户返奖金额
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
                'order_count' => 0,
                'order_users' => 0,
                'order_amount' => 0,
                'order_cash' => 0,
                'order_bonus' => 0,
                'reward_amount' => 0,
                'profit' => 0,
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
        $oldRechargeStats = OperationDataService::getPaidRechargeStats($startTime, $endTime, $oldUserIds);
        $oldPaidUsers = $oldRechargeStats['paid_users'];
        $oldPaidAmount = $oldRechargeStats['paid_amount'];

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

        // 老用户下单数据统计
        $oldOrderQuery = Db::name('game_transactions')
            ->where('reason', 'bet') // 下注记录
            ->where('req_time', '>=', $date . ' 00:00:00')
            ->where('req_time', '<=', $date . ' 23:59:59')
            ->whereIn('user_id', $oldUserIds);
        $oldOrders = $oldOrderQuery->select()->toArray();

        $oldOrderCount = count($oldOrders); // 老用户下单笔数
        $oldOrderUsers = count(array_unique(array_column($oldOrders, 'user_id'))); // 老用户下单人数
        $oldOrderAmount = array_sum(array_map(function($order) { return abs($order['amount']); }, $oldOrders)); // 老用户下单总金额

        // 老用户按钱包类型统计下单
        $oldOrderCash = 0; // 老用户现金下单金额
        $oldOrderBonus = 0; // 老用户彩金下单金额
        foreach ($oldOrders as $order) {
            if ($order['wallet_type'] == 1) {
                $oldOrderCash += abs($order['amount']); // wallet_type=1 为现金
            } else {
                $oldOrderBonus += abs($order['amount']); // wallet_type=0 为彩金
            }
        }

        // 老用户返奖数据统计
        $oldRewardQuery = Db::name('game_transactions')
            ->where('reason', 'win') // 赢取记录
            ->where('wallet_type', 1) // 现金钱包
            ->where('req_time', '>=', $date . ' 00:00:00')
            ->where('req_time', '<=', $date . ' 23:59:59')
            ->whereIn('user_id', $oldUserIds);
        $oldRewards = $oldRewardQuery->select()->toArray();

        $oldRewardAmount = 0; // 老用户返奖金额
        foreach ($oldRewards as $reward) {
            $oldRewardAmount += $reward['real_amount'] ?? $reward['amount'];
        }

        // 老用户运营商盈利：老用户下单金额 - 老用户返奖金额
        $oldProfit = $oldOrderAmount - $oldRewardAmount;

        return [
            'dau' => $oldDau, // 老用户DAU：往日注册且在当日有活动的用户数
            'paid_users' => $oldPaidUsers, // 老用户付费人数：往日注册且当日付费的用户数
            'paid_rate' => $oldPaidRate, // 老用户付费率：老用户付费人数 / 老用户DAU（前端formatter会转换为百分比）
            'paid_amount' => round($oldPaidAmount, 2), // 老用户付费额：老用户当日充值成功总金额
            'arpu' => round($oldArpu, 2), // 老用户ARPU：老用户付费额 / 老用户DAU（平均每老用户收入，保留两位小数）
            'arppu' => round($oldArppu, 2), // 老用户ARPPU：老用户付费额 / 老用户付费人数（平均每老付费用户收入，保留两位小数）
            'withdraw_amount' => round($oldWithdrawAmount, 2), // 老用户提现金额：老用户当日提现成功总金额
            'withdraw_rate' => $oldWithdrawRate, // 老用户提现率：老用户提现人数 / 老用户DAU（前端formatter会转换为百分比）
            // ==================== 老用户下单数据 ====================
            'order_count' => $oldOrderCount, // 老用户下单笔数：往日注册用户的下注交易记录数量
            'order_users' => $oldOrderUsers, // 老用户下单人数：往日注册且有下注记录的用户数
            'order_amount' => round($oldOrderAmount, 2), // 老用户下单总金额：往日注册用户下注总金额
            'order_cash' => round($oldOrderCash, 2), // 老用户现金下单：往日注册用户现金钱包下注总金额
            'order_bonus' => round($oldOrderBonus, 2), // 老用户彩金下单：往日注册用户彩金钱包下注总金额
            // ==================== 老用户返奖数据 ====================
            'reward_amount' => round($oldRewardAmount, 2), // 老用户返奖金额：往日注册用户现金赢取总金额
            'profit' => round($oldProfit, 2), // 老用户运营商盈利：老用户下单金额 - 老用户返奖金额
        ];
    }


}
