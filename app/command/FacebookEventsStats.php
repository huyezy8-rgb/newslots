<?php

namespace app\command;

use app\common\model\FacebookEventsLog;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class FacebookEventsStats extends Command
{
    protected function configure()
    {
        $this->setName('facebook:stats')
            ->setDescription('查看Facebook事件统计信息')
            ->addOption('days', 'd', \think\console\input\Option::VALUE_OPTIONAL, '统计天数', 7);
    }

    protected function execute(Input $input, Output $output)
    {
        $days = (int)$input->getOption('days');
        
        $output->writeln("Facebook事件统计 (最近 {$days} 天)");
        $output->writeln(str_repeat('-', 50));

        try {
            $stats = FacebookEventsLog::getStatistics($days);
            
            if (empty($stats)) {
                $output->writeln('暂无数据');
                return 0;
            }

            $totalEvents = 0;
            $totalSuccess = 0;
            $totalFailed = 0;
            $totalPending = 0;

            foreach ($stats as $eventType => $statusCounts) {
                $output->writeln("事件类型: {$eventType}");
                
                $pending = $statusCounts['pending'] ?? 0;
                $success = $statusCounts['success'] ?? 0;
                $failed = $statusCounts['failed'] ?? 0;
                $total = $pending + $success + $failed;
                
                $successRate = $total > 0 ? round(($success / $total) * 100, 2) : 0;
                
                $output->writeln("  总数: {$total}");
                $output->writeln("  成功: {$success} ({$successRate}%)");
                $output->writeln("  失败: {$failed}");
                $output->writeln("  待处理: {$pending}");
                $output->writeln("");

                $totalEvents += $total;
                $totalSuccess += $success;
                $totalFailed += $failed;
                $totalPending += $pending;
            }

            $output->writeln(str_repeat('-', 50));
            $output->writeln("总计:");
            $output->writeln("  总事件数: {$totalEvents}");
            $output->writeln("  成功数: {$totalSuccess}");
            $output->writeln("  失败数: {$totalFailed}");
            $output->writeln("  待处理数: {$totalPending}");
            
            if ($totalEvents > 0) {
                $overallSuccessRate = round(($totalSuccess / $totalEvents) * 100, 2);
                $output->writeln("  总体成功率: {$overallSuccessRate}%");
            }

            return 0;

        } catch (\Throwable $e) {
            $output->writeln("统计过程中发生错误: " . $e->getMessage());
            return 1;
        }
    }
} 