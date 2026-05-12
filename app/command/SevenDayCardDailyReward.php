<?php

namespace app\command;

use app\api\enum\CoinLog;
use app\common\service\AccountService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class SevenDayCardDailyReward extends Command
{
    protected function configure()
    {
        $this->setName('seven-day-card:daily')
            ->setDescription('七天卡每日奖励定时发放（每日执行一次）');
    }

    protected function execute(Input $input, Output $output)
    {
        $now = time();
        $today = strtotime(date('Y-m-d', $now));
        $output->writeln('[' . date('Y-m-d H:i:s', $now) . "] 开始执行七天卡每日奖励发放...");

        // 仅处理活动期内的记录
        $records = Db::name('seven_day_card_user')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->select()
            ->toArray();

        if (!$records) {
            $output->writeln('无待发放记录');
            return 0;
        }

        $accountService = new AccountService();
        $totalAmount = 0.0;
        $successCount = 0;
        $failCount = 0;

        foreach ($records as $record) {
            try {
                $rewardDaily = json_decode($record['reward_daily'] ?? '[]', true) ?: [];

                // 计算今天应发放的天序（1-7）
                $dayIndex = (int)floor(($today - strtotime(date('Y-m-d', (int)$record['start_time']))) / 86400);
                if ($dayIndex < 0 || $dayIndex > 6) {
                    continue;
                }

                if (!isset($rewardDaily[$dayIndex])) {
                    continue;
                }

                // status: 0=未领, 1=已领
                if ((int)($rewardDaily[$dayIndex]['status'] ?? 0) === 1) {
                    continue; // 今日已发
                }

                $amount = (float)($rewardDaily[$dayIndex]['reward'] ?? 0);
                if ($amount <= 0) {
                    // 标记为已处理，避免反复尝试
                    $rewardDaily[$dayIndex]['status'] = 1;
                    Db::name('seven_day_card_user')->where('id', $record['id'])->update([
                        'reward_daily' => json_encode($rewardDaily),
                        'updated_at' => time(),
                    ]);
                    continue;
                }

                Db::startTrans();
                try {
                    // 标记为已发
                    $rewardDaily[$dayIndex]['status'] = 1;
                    Db::name('seven_day_card_user')->where('id', $record['id'])->update([
                        'reward_daily' => json_encode($rewardDaily),
                        'updated_at' => time(),
                    ]);

                    // 发放到充值钱包
                    $accountService->increaseBalance(
                        userId: (int)$record['user_id'],
                        amount: $amount,
                        walletType: 1,
                        logTypeId: CoinLog::SevenDayCard,
                        note: '七天卡每日奖励自动发放'
                    );

                    Db::commit();
                    $totalAmount += $amount;
                    $successCount++;
                } catch (\Throwable $e) {
                    Db::rollback();
                    $failCount++;
                    Log::error('SevenDayCardDailyReward 发放失败: ' . json_encode([
                        'record_id' => $record['id'],
                        'user_id' => $record['user_id'],
                        'error' => $e->getMessage(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            } catch (\Throwable $e) {
                $failCount++;
                Log::error('SevenDayCardDailyReward 处理异常: ' . $e->getMessage());
            }
        }

        $output->writeln("发放完成：成功 {$successCount} 条，失败 {$failCount} 条，合计金额 {$totalAmount}");
        return 0;
    }
}


