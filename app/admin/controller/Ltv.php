<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use think\facade\Db;

class Ltv extends Backend
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
     * LTV统计接口
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
                $dateList[] = date('Y-m-d', $cur + 86400 * $i);
            }
        } else {
            while ($cur <= $end) {
                $dateList[] = date('Y-m-d', $cur);
                $cur += 86400;
            }
        }

        $result = [];
        $days = count($dateList); // 实际天数，根据查询范围动态生成
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
                'new_user' => $base_count
            ];

//            if ($base_count == 0) {
//                // 如果没有注册用户，返回空行
//                for ($day = 1; $day <= $days; $day++) {
//                    $row['LTV-' . $day] = '-';
//                }
//                $result[] = $row;
//                continue;
//            }

            // 2. 计算LTV-1到LTV-N（N为实际天数）
            for ($day = 1; $day <= $days; $day++) {
                $target_date = date('Y-m-d', strtotime($base_date) + 86400 * ($day - 1));
                
                // 如果目标日期超出今天，不计算
                if (strtotime($target_date) > strtotime($today)) {
                    $row['LTV-' . $day] = '-';
                    continue;
                }

                // 计算该日期的累计收入
                $startTime = strtotime($base_date . ' 00:00:00');
                $endTime = strtotime($target_date . ' 23:59:59');

                // 获取这些用户在注册日期到目标日期之间的充值总额
                $totalRevenue = Db::name('recharge_orders')
                    ->where('pay_status', 1) // 支付成功
                    ->where('created_at', '>=', $startTime)
                    ->where('created_at', '<=', $endTime)
                    ->whereIn('user_id', $baseUserIds)
                    ->sum('amount');

                // LTV = 累计收入 / 注册用户数
                $ltv = $base_count > 0 ? ($totalRevenue / $base_count) : 0;
                $row['LTV-' . $day] = round($ltv, 2);
            }

            $result[] = $row;
        }

        // 按日期倒序排列
        usort($result, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
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