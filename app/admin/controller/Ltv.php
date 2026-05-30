<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\service\OperationDataService;
use think\facade\Db;

class Ltv extends Backend
{
    public function minDate(): void
    {
        $this->success('success', $this->getMinRegisterDate());
    }

    public function index(): void
    {
        $minDate = $this->getMinRegisterDate();
        $today = date('Y-m-d');

        $end_date = (string)$this->request->get('end_date', $today);
        if (strtotime($end_date) > strtotime($today)) {
            $end_date = $today;
        }

        $default_start = strtotime($end_date) - 86400 * 29;
        $default_start_date = max(strtotime($minDate), $default_start);
        $start_date = (string)$this->request->get('start_date', date('Y-m-d', $default_start_date));
        $channel_id = $this->request->get('channel_id', null);
        $type = (string)$this->request->get('type', '');

        if ($this->getCurrentAdminChannelId() !== null) {
            $channel_id = $this->getCurrentAdminChannelId();
        }

        if (!$start_date || !$end_date) {
            $this->success('缺少日期参数', []);
            return;
        }

        $dateList = $this->buildDateList($start_date, $end_date);
        $days = count($dateList);
        $result = $type === 'first_pay'
            ? $this->buildFirstPayLtvRows($dateList, $days, $today, $channel_id)
            : $this->buildDefaultLtvRows($dateList, $days, $today, $channel_id);

        usort($result, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        [$pagedResult, $total] = $this->paginateRows($result);

        $this->success('success', [
            'list' => $pagedResult,
            'total' => $total,
        ]);
    }

    public function export(): void
    {
        $this->success('导出成功', []);
    }

    private function buildDefaultLtvRows(array $dateList, int $days, string $today, $channel_id): array
    {
        $result = [];

        foreach ($dateList as $base_date) {
            $baseUserIds = $this->getRegisterUserIds($base_date, $channel_id);
            $base_count = count($baseUserIds);

            $row = [
                'date' => $base_date,
                'new_user' => $base_count,
            ];

            for ($day = 1; $day <= $days; $day++) {
                $target_date = date('Y-m-d', strtotime($base_date) + 86400 * ($day - 1));

                if (strtotime($target_date) > strtotime($today)) {
                    $row['LTV-' . $day] = '-';
                    continue;
                }

                $startTime = strtotime($base_date . ' 00:00:00');
                $endTime = strtotime($target_date . ' 23:59:59');
                $rechargeStats = OperationDataService::getPaidRechargeStats($startTime, $endTime, $baseUserIds);
                $totalRevenue = $rechargeStats['paid_amount'];
                $row['LTV-' . $day] = round($base_count > 0 ? ($totalRevenue / $base_count) : 0, 2);
            }

            $result[] = $row;
        }

        return $result;
    }

    private function buildFirstPayLtvRows(array $dateList, int $days, string $today, $channel_id): array
    {
        $result = [];

        foreach ($dateList as $base_date) {
            $baseUserIds = $this->getRegisterUserIds($base_date, $channel_id);
            $baseStartTime = strtotime($base_date . ' 00:00:00');
            $baseEndTime = strtotime($base_date . ' 23:59:59');
            $firstPayUserIds = $this->getFirstPayUserIds($baseUserIds, $baseStartTime, $baseEndTime);
            $base_count = count($firstPayUserIds);

            $row = [
                'date' => $base_date,
                'new_user' => $base_count,
            ];

            for ($day = 1; $day <= $days; $day++) {
                $target_date = date('Y-m-d', strtotime($base_date) + 86400 * ($day - 1));

                if (strtotime($target_date) > strtotime($today)) {
                    $row['LTV-' . $day] = '-';
                    continue;
                }

                $endTime = strtotime($target_date . ' 23:59:59');
                $rechargeStats = OperationDataService::getPaidRechargeStats($baseStartTime, $endTime, $firstPayUserIds);
                $totalRevenue = $rechargeStats['paid_amount'];
                $row['LTV-' . $day] = round($base_count > 0 ? ($totalRevenue / $base_count) : 0, 2);
            }

            $result[] = $row;
        }

        return $result;
    }

    private function getMinRegisterDate(): string
    {
        $minCreateTime = Db::name('account')->min('create_time');
        return $minCreateTime ? date('Y-m-d', is_numeric($minCreateTime) ? $minCreateTime : strtotime($minCreateTime)) : date('Y-m-d');
    }

    private function buildDateList(string $start_date, string $end_date): array
    {
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

        return $dateList;
    }

    private function getRegisterUserIds(string $date, $channel_id): array
    {
        $baseUserQuery = Db::name('account')
            ->whereTime('create_time', 'between', [$date . ' 00:00:00', $date . ' 23:59:59']);

        if ($channel_id) {
            $baseUserQuery->where('channel_id', $channel_id);
        }

        return $baseUserQuery->column('id');
    }

    private function getFirstPayUserIds(array $baseUserIds, int $startTime, int $endTime): array
    {
        if (empty($baseUserIds)) {
            return [];
        }

        return Db::name('recharge_orders')
            ->where('pay_status', 1)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime)
            ->whereIn('user_id', $baseUserIds)
            ->distinct(true)
            ->column('user_id');
    }

    private function paginateRows(array $result): array
    {
        $page = (int)$this->request->get('page', 1);
        $limit = (int)$this->request->get('limit', 20);
        $total = count($result);
        $start = ($page - 1) * $limit;

        return [array_slice($result, $start, $limit), $total];
    }
}
