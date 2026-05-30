<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\service\UserActiveService;
use think\facade\Db;

class Retention extends Backend
{
    public function minDate(): void
    {
        $minCreateTime = Db::name('account')->min('create_time');
        $minDate = $minCreateTime ? date('Y-m-d', is_numeric($minCreateTime) ? $minCreateTime : strtotime($minCreateTime)) : date('Y-m-d');
        $this->success('success', $minDate);
    }

    public function index(): void
    {
        $minCreateTime = Db::name('account')->min('create_time');
        $minDate = $minCreateTime ? date('Y-m-d', is_numeric($minCreateTime) ? $minCreateTime : strtotime($minCreateTime)) : date('Y-m-d');
        $today = date('Y-m-d');

        $end_date = $this->request->get('end_date', $today);
        if (strtotime($end_date) > strtotime($today)) {
            $end_date = $today;
        }

        $default_start = strtotime($end_date) - 86400 * 29;
        $default_start_date = max(strtotime($minDate), $default_start);
        $start_date = $this->request->get('start_date', date('Y-m-d', $default_start_date));
        $channel_id = $this->request->get('channel_id', null);
        if ($this->getCurrentAdminChannelId() !== null) {
            $channel_id = $this->getCurrentAdminChannelId();
        }
        if (!$start_date || !$end_date) {
            $this->success('缺少日期参数', []);
            return;
        }

        $dateList = [];
        $cur = strtotime($start_date);
        $end = strtotime($end_date);
        $allDays = 1 + intval(($end - $cur) / 86400);
        if ($allDays > 30) {
            $cur = $end - 86400 * 29;
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
            $baseUserQuery = Db::name('account')
                ->whereTime('create_time', 'between', [$base_date . ' 00:00:00', $base_date . ' 23:59:59']);
            if ($channel_id) {
                $baseUserQuery->where('channel_id', $channel_id);
            }
            $baseUserIds = $baseUserQuery->column('id');
            $base_count = count($baseUserIds);

            $row = [
                'date' => $base_date,
                'D1' => $base_count ?: '-',
            ];

            for ($i = 2; $i <= $days; $i++) {
                $targetDate = date('Y-m-d', strtotime($base_date) + 86400 * ($i - 1));
                if (strtotime($targetDate) > $end) {
                    $row['D' . $i] = '-';
                    continue;
                }

                $active_count = count(UserActiveService::getActiveUserIdsByDate($targetDate, $baseUserIds));
                $row['D' . $i] = $base_count > 0 && $active_count > 0 ? round($active_count / $base_count * 100, 2) . '%' : '0%';
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
