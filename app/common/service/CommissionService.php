<?php

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;
use app\api\enum\CoinLog;

/**
 * 返佣结算服务（点位差）
 */
class CommissionService
{
    /**
     * 推送结算任务到队列
     */
    public function dispatchSettleJob(int $sourceUserId, float $betAmount, float $baseRate = 0.5, int $maxLevels = 0, string $queue = 'default'): bool
    {
        $data = [
            'source_user_id' => $sourceUserId,
            'bet_amount' => $betAmount,
            'base_rate' => $baseRate,
            'max_levels' => $maxLevels,
        ];
        try {
            \think\facade\Queue::push('app\\job\\CommissionSettleJob', $data, $queue);
            return true;
        } catch (\Throwable $e) {
            Log::error('dispatchSettleJob 失败: ' . $e->getMessage(), ['data' => $data]);
            return false;
        }
    }

    /**
     * 结算一次投注的团队返佣（点位差）
     *
     * @param int $sourceUserId 下级投注用户ID
     * @param float $betAmount 投注金额
     * @param float $baseRate 百分数，如 0.5 表示 0.5%
     * @param int $channelId 渠道ID
     * @param int $maxLevels 最大层级，0表示不限制
     * @return array [success => bool, total_commission => float, items => []]
     */
    public function settleBetCommission(int $sourceUserId, float $betAmount, float $baseRate = 0.5, int $maxLevels = 0): array
    {
        $result = [
            'success' => false,
            'total_commission' => 0.0,
            'items' => [],
        ];

        if ($betAmount <= 0 || $baseRate <= 0) {
            return $result;
        }

        Log::info('Commission settle start', compact('sourceUserId','betAmount','baseRate','maxLevels'));

        return Db::transaction(function () use ($sourceUserId, $betAmount, $baseRate, $maxLevels, &$result) {
            $source = Db::name('account')->where('id', $sourceUserId)->find();
            if (!$source) {
                Log::warning("返佣失败：找不到用户 {$sourceUserId}");
                return $result;
            }

            // 解析上级链：team_path 为 '/a/b/c/' 或 '0/1/2' 的场景做兼容
            $teamPath = (string)($source['team_path'] ?? '');
            $path = trim($teamPath, '/');
            $ancestorIds = $path === '' ? [] : explode('/', $path);

            if (empty($ancestorIds)) {
                Log::info('Commission settle: no ancestors, no commission', ['source_user_id' => $sourceUserId]);
                $result['success'] = true;
                return $result; // 无上级不返佣
            }

            // 自近到远：反转数组
            $ancestorIds = array_reverse($ancestorIds);

            // 下注者自身点位
            $prevRate = (float)($source['rebate_rate'] ?? 0);
            $baseRateFactor = $baseRate / 100.0; // 百分数转比例

            $accountService = new AccountService();
            $level = 0;
            foreach ($ancestorIds as $ancestorId) {
                $level++;
                if ($maxLevels > 0 && $level > $maxLevels) {
                    break;
                }
                $ancestor = Db::name('account')->where('id', (int)$ancestorId)->find();
                if (!$ancestor) {
                    continue;
                }

                $currRate = (float)($ancestor['rebate_rate'] ?? 0);
                if ($currRate <= $prevRate) {
                    Log::info('Commission settle: no diff', ['ancestor_id' => (int)$ancestorId, 'currRate' => $currRate, 'prevRate' => $prevRate, 'level' => $level]);
                    $prevRate = max($prevRate, $currRate);
                    continue; // 无差值则不返
                }

                $diff = $currRate - $prevRate; // 百分数
                $commission = (float) bcmul((string)$betAmount, (string)$baseRateFactor, 6);
                $commission = (float) bcmul((string)$commission, (string)($diff / 100.0), 6);

                if ($commission <= 0) {
                    $prevRate = $currRate;
                    continue;
                }

                // 写日志（注意使用 name 不含前缀，系统会自动加 slot_ 前缀）
                Db::name('team_commission_log')->insert([
                    'user_id' => (int)$ancestorId,
                    'source_user_id' => $sourceUserId,
                    'channel_id' => (int)($source['channel_id'] ?? 0),
                    'bet_amount' => $betAmount,
                    'base_rate' => $baseRate,
                    'point_diff' => $diff,
                    'commission' => $commission,
                    'level' => $level,
                    'create_time' => time(),
                ]);

                // 累加佣金到账户：钱包类型=2（commission_balance）
                $accountService->increaseBalance((int)$ancestorId, $commission, CoinLog::getWalletType('commission_balance'), CoinLog::CommissionBet, '投注团队返佣(点位差):用户id'.$sourceUserId."返佣");

                Log::info('Commission settle: add', [
                    'ancestor_id' => (int)$ancestorId,
                    'level' => $level,
                    'point_diff' => $diff,
                    'commission' => $commission,
                ]);

                $result['items'][] = [
                    'user_id' => (int)$ancestorId,
                    'level' => $level,
                    'point_diff' => $diff,
                    'commission' => $commission,
                ];
                $result['total_commission'] = (float) bcadd((string)$result['total_commission'], (string)$commission, 6);

                $prevRate = $currRate;
            }

            $result['success'] = true;
            Log::info('Commission settle success', ['source_user_id' => $sourceUserId, 'total_commission' => $result['total_commission'], 'items' => $result['items']]);
            return $result;
        });
    }
}

