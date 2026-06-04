<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\service\UserActiveService;
use think\facade\Db;

class PayRetention extends Backend
{
    public function index(): void
    {
        $today = date('Y-m-d');
        $end_date = $this->request->get('end_date', $today);
        if (strtotime($end_date) > strtotime($today)) {
            $end_date = $today;
        }
        $default_start = strtotime($end_date) - 86400 * 29;
        $start_date = $this->request->get('start_date', date('Y-m-d', $default_start));
        $channel_id = $this->request->get('channel_id', null);
        if ($this->getCurrentAdminChannelId() !== null) {
            $channel_id = $this->getCurrentAdminChannelId();
        }
        if (!$start_date || !$end_date) {
            $this->success('缺少日期参数', []);
            return;
        }

        $minCreateTime = Db::name('account')->min('create_time');
        $minDate = $minCreateTime ? date('Y-m-d', is_numeric($minCreateTime) ? $minCreateTime : strtotime($minCreateTime)) : date('Y-m-d');
        if (strtotime($start_date) < strtotime($minDate)) {
            $start_date = $minDate;
        }

        $dateList = [];
        $cur = strtotime($start_date);
        $end = strtotime($end_date);
        $allDays = 1 + intval(($end - $cur) / 86400);
        if ($allDays > 30) {
            $cur = $end - 86400 * 29;
            for ($i = 0; $i < 30; $i++) {
                $dateList[] = date('Y-m-d', $cur + 86400 * $i);
            }
        } else {
            while ($cur <= $end) {
               $dateList[] = date('Y-m-d', $cur);
                $cur += 86400;
            }
        }
        $days = count($dateList);

        $result = [];
        foreach ($dateList as $base_date) {
            $payUserQuery = Db::name('recharge_orders')
                ->where('pay_status', 1)
                ->whereTime('created_at', 'between', [$base_date . ' 00:00:00', $base_date . ' 23:59:59']);
            if ($channel_id) {
                $payUserQuery->where('channel_id', $channel_id);
            }
            $payUserIds = $payUserQuery->distinct(true)->column('user_id');
            $pay_count = count($payUserIds);

            $row = [
                'date' => $base_date,
                'D1' => $pay_count,
            ];

            for ($i = 2; $i <= $days; $i++) {
                $targetDate = date('Y-m-d', strtotime($base_date) + 86400 * ($i - 1));
                if (strtotime($targetDate) > $end) {
                    $row['D' . $i] = '-';
                    continue;
                }

                $active_count = count(UserActiveService::getActiveUserIdsByDate($targetDate, $payUserIds));
                $row['D' . $i] = $pay_count > 0 && $active_count > 0 ? round($active_count / $pay_count * 100, 2) . '%' : '0%';
            }
            $result[] = $row;
        }

        usort($result, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $page = (int)$this->request->get('page', 1);
        $limit = (int)$this->request->get('limit', 20);
        $total = count($result);
        $start = ($page - 1) * $limit;
        $pagedResult = array_slice($result, $start, $limit);

        $this->success('success', [
            'list' => $pagedResult,
            'total' => $total,
        ]);
    }

    public function export(): void
    {
        $this->success('导出成功', []);
    }
}
