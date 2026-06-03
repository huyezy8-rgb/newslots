<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use Throwable;

class TgStats extends Command
{
    protected function configure()
    {
        $this->setName('tg:stats')
            ->setDescription('Backfill Telegram send record redemption and recharge stats');
    }

    protected function execute(Input $input, Output $output)
    {
        $processed = 0;
        $updated = 0;
        $failed = 0;

        try {
            $this->assertRequiredFields();

            $codeIds = Db::name('tg_send_record')
                ->where('redemption_code_id', '>', 0)
                ->group('redemption_code_id')
                ->column('redemption_code_id');

            foreach ($codeIds as $codeId) {
                $processed++;
                $codeId = (int)$codeId;

                try {
                    $stats = $this->buildStats($codeId);
                    $affected = Db::name('tg_send_record')
                        ->where('redemption_code_id', $codeId)
                        ->update($stats);
                    $updated += (int)$affected;
                } catch (Throwable $e) {
                    $failed++;
                    $output->writeln(sprintf(
                        'TG stats failed. redemption_code_id=%d error=%s',
                        $codeId,
                        $e->getMessage()
                    ));
                }
            }
        } catch (Throwable $e) {
            $failed++;
            $output->writeln('TG stats failed. error=' . $e->getMessage());
        }

        $output->writeln(sprintf(
            'TG stats done. processed=%d updated=%d failed=%d',
            $processed,
            $updated,
            $failed
        ));
    }

    private function buildStats(int $codeId): array
    {
        $claimRows = Db::name('red_envelope_redemption_record')
            ->where('code_id', $codeId)
            ->field('user_id, amount')
            ->select()
            ->toArray();

        $userIds = [];
        $claimAmount = 0.0;
        foreach ($claimRows as $row) {
            $userId = (int)($row['user_id'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
            $claimAmount += (float)($row['amount'] ?? 0);
        }

        $claimCount = count($userIds);
        $stats = [
            'claim_count' => $claimCount,
            'claim_amount' => round($claimAmount, 2),
            'register_count' => $claimCount,
            'first_recharge_count' => 0,
            'first_recharge_amount' => 0,
            'recharge_count' => 0,
            'recharge_amount' => 0,
        ];

        if (!$userIds) {
            return $stats;
        }

        $rechargeRows = Db::name('recharge_orders')
            ->whereIn('user_id', array_values($userIds))
            ->where('pay_status', 1)
            ->field('id, user_id, amount, created_at')
            ->order('user_id', 'asc')
            ->order('created_at', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $rechargeUsers = [];
        $rechargeAmount = 0.0;
        $firstRechargeByUser = [];

        foreach ($rechargeRows as $order) {
            $userId = (int)($order['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $rechargeUsers[$userId] = $userId;
            $amount = (float)($order['amount'] ?? 0);
            $rechargeAmount += $amount;

            if (!isset($firstRechargeByUser[$userId])) {
                $firstRechargeByUser[$userId] = $amount;
            }
        }

        $stats['first_recharge_count'] = count($firstRechargeByUser);
        $stats['first_recharge_amount'] = round(array_sum($firstRechargeByUser), 2);
        $stats['recharge_count'] = count($rechargeUsers);
        $stats['recharge_amount'] = round($rechargeAmount, 2);

        return $stats;
    }

    private function assertRequiredFields(): void
    {
        $tgFields = $this->tableFields('tg_send_record');
        $recordFields = $this->tableFields('red_envelope_redemption_record');
        $rechargeFields = $this->tableFields('recharge_orders');

        $this->assertFields('tg_send_record', $tgFields, [
            'redemption_code_id',
            'claim_count',
            'claim_amount',
            'register_count',
            'first_recharge_count',
            'first_recharge_amount',
            'recharge_count',
            'recharge_amount',
        ]);
        $this->assertFields('red_envelope_redemption_record', $recordFields, ['code_id', 'user_id', 'amount']);
        $this->assertFields('recharge_orders', $rechargeFields, ['user_id', 'amount', 'pay_status', 'created_at']);
    }

    private function tableFields(string $table): array
    {
        try {
            return array_keys(Db::getFields(config('database.connections.mysql.prefix') . $table));
        } catch (Throwable $e) {
            throw new \RuntimeException('Table not found or unreadable: ' . $table . '. ' . $e->getMessage());
        }
    }

    private function assertFields(string $table, array $fields, array $required): void
    {
        $missing = array_values(array_diff($required, $fields));
        if ($missing) {
            throw new \RuntimeException(sprintf(
                'Table %s missing required fields: %s',
                $table,
                implode(', ', $missing)
            ));
        }
    }
}
