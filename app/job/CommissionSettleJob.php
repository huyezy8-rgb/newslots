<?php

namespace app\job;

use app\common\service\CommissionService;
use think\queue\Job;
use think\facade\Log;

class CommissionSettleJob
{
    public function fire(Job $job, array $data): void
    {
        try {
            $sourceUserId = (int)($data['source_user_id'] ?? 0);
            $betAmount = (float)($data['bet_amount'] ?? 0);
            $baseRate = (float)($data['base_rate'] ?? 0.5);
            $maxLevels = (int)($data['max_levels'] ?? 0);

            if ($sourceUserId <= 0 || $betAmount <= 0) {
                Log::warning('CommissionSettleJob 参数无效', $data);
                $job->delete();
                return;
            }

            $svc = new CommissionService();
            $res = $svc->settleBetCommission($sourceUserId, $betAmount, $baseRate, $maxLevels);

            if (!empty($res['success'])) {
                $job->delete();
            } else {
                // 重试策略：可根据业务需求调整
                if ($job->attempts() > 3) {
                    Log::error('CommissionSettleJob 多次失败，丢弃', ['data' => $data]);
                    $job->delete();
                } else {
                    $job->release(5);
                }
            }
        } catch (\Throwable $e) {
            Log::error('CommissionSettleJob 异常: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($job->attempts() > 3) {
                $job->delete();
            } else {
                $job->release(10);
            }
        }
    }
}

