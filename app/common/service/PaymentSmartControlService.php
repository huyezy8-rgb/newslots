<?php

namespace app\common\service;

use think\facade\Db;

class PaymentSmartControlService
{
    private const CONFIG_ID = 1;

    public function getConfig(): array
    {
        try {
            $row = Db::name('payment_smart_control_config')
                ->where('id', self::CONFIG_ID)
                ->find();
        } catch (\Throwable $e) {
            $row = null;
        }

        return $this->normalizeConfig($row ?: []);
    }

    public function saveConfig(array $data): void
    {
        $config = $this->normalizeConfig($data);
        $payload = [
            'id' => self::CONFIG_ID,
            'withdraw_amount_enabled' => $config['withdraw_amount_enabled'],
            'withdraw_amount_threshold' => $config['withdraw_amount_threshold'],
            'withdraw_pay_types' => json_encode($config['withdraw_pay_types'], JSON_UNESCAPED_UNICODE),
            'recharge_count_enabled' => $config['recharge_count_enabled'],
            'recharge_count_threshold' => $config['recharge_count_threshold'],
            'recharge_pay_types' => json_encode($config['recharge_pay_types'], JSON_UNESCAPED_UNICODE),
            'update_time' => time(),
        ];

        $exists = Db::name('payment_smart_control_config')->where('id', self::CONFIG_ID)->value('id');
        if ($exists) {
            Db::name('payment_smart_control_config')->where('id', self::CONFIG_ID)->update($payload);
            return;
        }

        $payload['create_time'] = time();
        Db::name('payment_smart_control_config')->insert($payload);
    }

    public function getWithdrawRestrictedPayTypes(?float $amount): array
    {
        $config = $this->getConfig();
        if (
            !$config['withdraw_amount_enabled']
            || $amount === null
            || $amount <= $config['withdraw_amount_threshold']
            || !$config['withdraw_pay_types']
        ) {
            return [];
        }

        return $config['withdraw_pay_types'];
    }

    public function getRechargeAppendPayTypes(int $userId): array
    {
        $config = $this->getConfig();
        if (!$config['recharge_count_enabled'] || !$config['recharge_pay_types']) {
            return [];
        }

        if ($this->getSuccessfulRechargeCount($userId) < $config['recharge_count_threshold']) {
            return [];
        }

        return $config['recharge_pay_types'];
    }

    public function getSuccessfulRechargeCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int)Db::name('recharge_orders')
            ->where(['user_id' => $userId, 'pay_status' => 1])
            ->count();
    }

    public function getWithdrawPayTypeOptions(): array
    {
        return Db::name('payment_methods')
            ->where('unique_tag', '<>', '')
            ->whereIn('pay_method', ['0', '2'])
            ->group('unique_tag')
            ->field('unique_tag,MIN(name) as name,MAX(status) as status')
            ->order('unique_tag', 'asc')
            ->select()
            ->toArray();
    }

    public function getRechargePayTypeOptions(): array
    {
        return Db::name('payment_methods')
            ->where('unique_tag', '<>', '')
            ->whereIn('pay_method', ['0', '1'])
            ->group('unique_tag')
            ->field('unique_tag,MIN(name) as name,MAX(status) as status')
            ->order('unique_tag', 'asc')
            ->select()
            ->toArray();
    }

    private function normalizeConfig(array $data): array
    {
        return [
            'id' => self::CONFIG_ID,
            'withdraw_amount_enabled' => (int)($data['withdraw_amount_enabled'] ?? 0) ? 1 : 0,
            'withdraw_amount_threshold' => max(0, (float)($data['withdraw_amount_threshold'] ?? 0)),
            'withdraw_pay_types' => $this->normalizeStringList($data['withdraw_pay_types'] ?? []),
            'recharge_count_enabled' => (int)($data['recharge_count_enabled'] ?? 0) ? 1 : 0,
            'recharge_count_threshold' => max(0, (int)($data['recharge_count_threshold'] ?? 0)),
            'recharge_pay_types' => $this->normalizeStringList($data['recharge_pay_types'] ?? []),
        ];
    }

    private function normalizeStringList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }
}
