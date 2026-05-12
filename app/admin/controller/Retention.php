<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use think\facade\Db;

class Retention extends Backend
{
    /**
     * 获取最早注册日期接口
     */
    public function minDate(): void
    {
        $minCreateTime = Db::name('account')->min('create_time');
        $minDate = $minCreateTime ? date('Y-m-d', is_numeric($minCreateTime) ? $minCreateTime : strtotime($minCreateTime)) : date('Y-m-d');
        $this->success('success', $minDate);
    }

    /**
     * 留存率统计接口
     */
    public function index(): void
    {
        $minCreateTime = Db::name('account')->min('create_time');
        $minDate = $minCreateTime ? date('Y-m-d', is_numeric($minCreateTime) ? $minCreateTime : strtotime($minCreateTime)) : date('Y-m-d');
        $today = date('Y-m-d');

        $end_date = $this->request->get('end_date', $today);
        // 限制end_date不能大于今天
        if (strtotime($end_date) > strtotime($today)) {
            $end_date = $today;
        }
        $default_start = strtotime($end_date) - 86400 * 29;
        $default_start_date = max(strtotime($minDate), $default_start);
        $start_date = $this->request->get('start_date', date('Y-m-d', $default_start_date));
        $channel_id = $this->request->get('channel_id', null);
        //获取当前登录管理员绑定的渠道id
        if ($this->getCurrentAdminChannelId() !== null) {
            $channel_id = $this->getCurrentAdminChannelId();
        }
        if (!$start_date || !$end_date) {
            $this->success('缺少日期参数', []);
            return;
        }

        // 生成日期列表，最多30天
        $dateList = [];
        $cur = strtotime($start_date);
        $end = strtotime($end_date);
        $allDays = 1 + intval(($end - $cur) / 86400);
        // 只保留最新的30天
        if ($allDays > 30) {
            $cur = $end - 86400 * 29;
            $dateList = [];
            for ($i = 0; $i < 30; $i++) {
                $dateList[] = date('Ymd', $cur + 86400 * $i);
            }
        } else {
            while ($cur <= $end) {
                $dateList[] = date('Ymd', $cur);
                $cur += 86400;
            }
        }
        $days = count($dateList);

        $result = [];
        foreach ($dateList as $base_date) {
            // 1. 获取基准日注册用户ID集合
            $baseUserQuery = Db::name('account')
                ->whereTime('create_time', 'between', [$base_date . ' 00:00:00', $base_date . ' 23:59:59']);
            if ($channel_id) {
                $baseUserQuery->where('channel_id', $channel_id);
            }
            $baseUserIds = $baseUserQuery->column('id');
            $base_count = count($baseUserIds);

            $row = [
                'date' => $base_date,
                'D1' => $base_count ?: '-'
            ];

            // 2. 统计D2~Dn
            for ($i = 2; $i <= $days; $i++) {
                $targetDate = date('Y-m-d', strtotime($base_date) + 86400 * ($i - 1));
                // 只计算目标日期不超过end_date的留存，否则为'-'
                if (strtotime($targetDate) > $end) {
                    $row['D' . $i] = '-';
                    continue;
                }
                // 统计第i天活跃的基准用户数
                $activeUserIds = Db::name('game_transactions')->whereIn('user_id', $baseUserIds)
                    ->whereTime('req_time', 'between', [$targetDate . ' 00:00:00', $targetDate . ' 23:59:59'])->column('user_id');
                $activeUserIds = array_merge($activeUserIds, Db::name('recharge_orders')->whereIn('user_id', $baseUserIds)
                    ->whereTime('created_at', 'between', [$targetDate . ' 00:00:00', $targetDate . ' 23:59:59'])->column('user_id'));
                $activeUserIds = array_merge($activeUserIds, Db::name('withdraw_orders')->whereIn('user_id', $baseUserIds)
                    ->whereTime('create_time', 'between', [$targetDate . ' 00:00:00', $targetDate . ' 23:59:59'])->column('user_id'));
                $activeUserIds = array_unique($activeUserIds);
                $active_count = count($activeUserIds);
                $row['D' . $i] = $active_count > 0 ? round($active_count / $base_count * 100, 2) . '%' : '0%';
            }
            $result[] = $row;
        }

        // 按日期倒序排列（最新日期在前）
        usort($result, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $page = $this->request->get('page', 1);
        $limit = $this->request->get('limit', 20);
        $total = count($result);
        $start = ($page - 1) * $limit;
        $pagedResult = array_slice($result, $start, $limit);

        $this->success('success', [
            'list' => $pagedResult,
            'total' => $total,
        ]);
    }

    /**
     * 导出接口（占位，后续完善）
     */
    public function export(): void
    {
        $this->success('导出成功', []);
    }
} 