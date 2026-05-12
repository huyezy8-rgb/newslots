<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

class LeaderboardRewardSchedule extends Command
{
    protected function configure()
    {
        $this->setName('leaderboard:schedule')
            ->setDescription('排行榜奖励定时发放');
    }

    protected function execute(Input $input, Output $output)
    {
        // 确保时区设置正确（命令行脚本需要手动设置时区）
        // 优先使用系统配置的时区，如果没有则使用默认时区
        $systemTimezone = get_sys_config('time_zone');
        $defaultTimezone = \think\facade\Config::get('app.default_timezone', 'America/New_York');
        $timezone = $systemTimezone ?: $defaultTimezone;
        date_default_timezone_set($timezone);
        
        $output->writeln("开始执行排行榜奖励定时发放...");
        $output->writeln("当前时区: " . date_default_timezone_get());

        try {
            $this->checkAndDistributeRewards($output);
            $output->writeln("排行榜奖励定时发放完成");
        } catch (\Exception $e) {
            $output->error("排行榜奖励定时发放失败: " . $e->getMessage());
            Log::error("LeaderboardRewardSchedule: 定时发放失败: " . json_encode([
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 检查并发放奖励
     * @param Output $output
     */
    private function checkAndDistributeRewards(Output $output): void
    {
        $now = time();
        $currentHour = (int)date('H', $now);
        $currentDayOfWeek = (int)date('N', $now); // 1=周一, 7=周日
        $currentDayOfMonth = (int)date('j', $now);

        $output->writeln("当前时间: " . date('Y-m-d H:i:s', $now));
        $output->writeln("当前小时: {$currentHour}, 星期: {$currentDayOfWeek}, 日期: {$currentDayOfMonth}");

        // 日榜奖励：每日发放昨日的奖金池（移除时间限制，适应不同时区）
        $this->checkDailyReward($output);

        // 周榜奖励：每周一发放上周的奖金池（移除时间限制，适应不同时区）
        if ($currentDayOfWeek == 1) {
            $this->checkWeeklyReward($output);
        }

        // 月榜奖励：每月1号发放上月的奖金池（移除时间限制，适应不同时区）
        if ($currentDayOfMonth == 1) {
            $this->checkMonthlyReward($output);
        }
    }

    /**
     * 检查日榜奖励
     * @param Output $output
     */
    private function checkDailyReward(Output $output): void
    {
        $yesterday = date('Y-m-d');
        $output->writeln("检查日榜奖励发放: {$yesterday}");

        // 检查今天是否已经发放过日榜奖励
        $existingLog = \think\facade\Db::name('leaderboard_reward_log')
            ->where('type', 'daily')
            ->where('create_time', '>=', strtotime('today'))
            ->where('create_time', '<', strtotime('today') + 86400)
            ->find();

        if ($existingLog) {
            $output->writeln("日榜奖励今天已发放，跳过");
            $output->writeln("发放时间: " . date('Y-m-d H:i:s', $existingLog['create_time']));
            return;
        }

        // 检查昨天是否有数据需要发放
        $yesterdayStats = \think\facade\Db::name('leaderboard_stats')
            ->where('type', 'daily')
            ->where('period', date('Y-m-d', strtotime('-1 day')))
            ->count();

        if ($yesterdayStats == 0) {
            $output->writeln("昨天没有排行榜数据，跳过发放");
            return;
        }

        $output->writeln("昨天有 {$yesterdayStats} 条排行榜数据，开始发放奖励");

        // 执行日榜奖励发放
        $this->executeRewardCommand('daily', $output);
    }

    /**
     * 检查周榜奖励
     * @param Output $output
     */
    private function checkWeeklyReward(Output $output): void
    {
        $lastWeekStart = date('Y-m-d', strtotime('last monday'));
        $lastWeekEnd = date('Y-m-d', strtotime('last sunday'));
        $output->writeln("检查周榜奖励发放: {$lastWeekStart} 至 {$lastWeekEnd}");

        // 检查是否已经发放过
        $existingLog = \think\facade\Db::name('leaderboard_reward_log')
            ->where('type', 'weekly')
            ->where('create_time', '>=', strtotime('today'))
            ->where('create_time', '<', strtotime('today') + 86400)
            ->find();

        if ($existingLog) {
            $output->writeln("周榜奖励已发放，跳过");
            return;
        }

        // 执行周榜奖励发放
        $this->executeRewardCommand('weekly', $output);
    }

    /**
     * 检查月榜奖励
     * @param Output $output
     */
    private function checkMonthlyReward(Output $output): void
    {
        $lastMonth = date('Y-m', strtotime('-1 month'));
        $output->writeln("检查月榜奖励发放: {$lastMonth}");

        // 检查是否已经发放过
        $existingLog = \think\facade\Db::name('leaderboard_reward_log')
            ->where('type', 'monthly')
            ->where('create_time', '>=', strtotime('today'))
            ->where('create_time', '<', strtotime('today') + 86400)
            ->find();

        if ($existingLog) {
            $output->writeln("月榜奖励已发放，跳过");
            return;
        }

        // 执行月榜奖励发放
        $this->executeRewardCommand('monthly', $output);
    }

    /**
     * 执行奖励发放命令
     * @param string $type 排行榜类型
     * @param Output $output
     */
    private function executeRewardCommand(string $type, Output $output): void
    {
        $output->writeln("执行{$type}排行榜奖励发放...");

        // 使用系统命令执行
        $commandString = "php think leaderboard:reward {$type}";
        $output->writeln("执行命令: {$commandString}");
        
        try {
            $result = system($commandString, $returnCode);
            if ($returnCode === 0) {
                $output->writeln("{$type}排行榜奖励发放执行完成");
            } else {
                throw new \Exception("命令执行失败，返回码: {$returnCode}");
            }
        } catch (\Exception $e) {
            $output->writeln("{$type}排行榜奖励发放执行失败: " . $e->getMessage());
            Log::error("LeaderboardRewardSchedule: {$type}排行榜奖励发放失败: " . json_encode([
                'error' => $e->getMessage(),
                'type' => $type
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
} 