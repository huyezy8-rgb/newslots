<?php

namespace app\common\service;

use think\facade\Db;

class PaymentRouteSelector
{
    private const SCENE_PAY_METHOD = [
        'recharge' => '1',
        'withdraw' => '2',
    ];

    public function selectRoute(string $payType, string $scene, float $amount, int $userId = 0, array $options = []): array
    {
        $routes = $this->getCandidateRoutes($payType, $scene, $amount, $userId, $options);
        if (!$routes) {
            throw new \Exception(__('Payment method param error'));
        }

        $totalWeight = array_sum(array_map(static fn(array $route): int => (int)$route['channel_weight'], $routes));
        if ($totalWeight <= 0) {
            throw new \Exception(__('Payment method param error'));
        }

        $hit = random_int(1, $totalWeight);
        $cursor = 0;
        foreach ($routes as $route) {
            $cursor += (int)$route['channel_weight'];
            if ($hit <= $cursor) {
                return $this->formatRoute($route);
            }
        }

        return $this->formatRoute($routes[array_key_last($routes)]);
    }

    public function getAvailableMethods(string $scene, int $userId = 0, array $options = []): array
    {
        $amount = array_key_exists('amount', $options) && $options['amount'] !== null ? (float)$options['amount'] : null;
        $routes = $this->queryRoutes('', $scene, $amount);
        $routes = $this->filterRoutesByChannelCodes($routes, $options['allowed_channel_codes'] ?? []);
        $routes = $this->filterRoutesByPayTypes($routes, $options['allowed_pay_types'] ?? []);
        $userStats = $scene === 'recharge' ? $this->getUserRechargeStats($userId) : ['total_amount' => 0, 'total_times' => 0];
        $appendPayTypes = $scene === 'recharge'
            ? ($options['append_pay_types'] ?? (new PaymentSmartControlService())->getRechargeAppendPayTypes($userId))
            : [];
        $smartControlHit = !empty($options['smart_control_hit']);
        $methods = [];

        foreach ($routes as $route) {
            $payType = strtolower((string)$route['unique_tag']);
            if (!$this->shouldDisplayRoute($route, $userStats, $appendPayTypes)) {
                continue;
            }
            if (isset($methods[$payType])) {
                continue;
            }

            $methods[$payType] = $this->buildClientMethodData($route, $smartControlHit, $scene);
        }

        return array_values($methods);
    }

    public function getCandidateRoutes(string $payType, string $scene, ?float $amount = null, int $userId = 0, array $options = []): array
    {
        $routes = $this->queryRoutes($payType, $scene, $amount);
        $routes = $this->filterRoutesByChannelCodes($routes, $options['allowed_channel_codes'] ?? []);
        $routes = $this->filterRoutesByPayTypes($routes, $options['allowed_pay_types'] ?? []);
        if ($scene !== 'recharge') {
            return $routes;
        }

        $userStats = $this->getUserRechargeStats($userId);
        $appendPayTypes = $options['append_pay_types'] ?? (new PaymentSmartControlService())->getRechargeAppendPayTypes($userId);
        return array_values(array_filter($routes, fn(array $route): bool => $this->shouldDisplayRoute($route, $userStats, $appendPayTypes)));
    }

    private function queryRoutes(string $payType, string $scene, ?float $amount): array
    {
        $payMethod = self::SCENE_PAY_METHOD[$scene] ?? null;
        if ($payMethod === null) {
            throw new \InvalidArgumentException('Invalid payment scene');
        }

        $query = Db::name('payment_methods')
            ->alias('m')
            ->leftJoin('payment_channels c', 'c.code = m.channel_code')
            ->where('m.status', 1)
            ->where('c.status', 1)
            ->where('c.weight', '>', 0)
            ->whereIn('m.pay_method', ['0', $payMethod])
            ->field('m.id,m.unique_tag,m.name,m.icon,m.show,m.is_clause,m.channel_code,m.code,m.pay_method,m.min_recharge_amount,m.max_recharge_amount,m.min_withdraw_amount,m.max_withdraw_amount,c.name as channel_name,c.weight as channel_weight')
            ->order('m.id', 'asc');

        if ($payType !== '') {
            $query->where('m.unique_tag', strtolower($payType));
        }

        if ($amount !== null) {
            $minField = $scene === 'recharge' ? 'm.min_recharge_amount' : 'm.min_withdraw_amount';
            $maxField = $scene === 'recharge' ? 'm.max_recharge_amount' : 'm.max_withdraw_amount';
            $query
                ->where(function ($query) use ($minField, $amount) {
                    $query->whereNull($minField)->whereOr($minField, '<=', $amount);
                })
                ->where(function ($query) use ($maxField, $amount) {
                    $query->whereNull($maxField)->whereOr($maxField, '>=', $amount);
                });
        }

        return $this->dedupeRoutesByChannel($query->select()->toArray(), $payMethod);
    }

    private function dedupeRoutesByChannel(array $routes, string $payMethod): array
    {
        $deduped = [];
        foreach ($routes as $route) {
            $key = strtolower((string)$route['channel_code']) . '|' . strtolower((string)$route['unique_tag']);
            if (!isset($deduped[$key])) {
                $deduped[$key] = $route;
                continue;
            }

            if ((string)$route['pay_method'] === $payMethod && (string)$deduped[$key]['pay_method'] !== $payMethod) {
                $deduped[$key] = $route;
            }
        }

        return array_values($deduped);
    }

    private function formatRoute(array $route): array
    {
        return [
            'payment_method_id' => (int)$route['id'],
            'payment_channel_code' => (string)$route['channel_code'],
            'payment_channel_name' => (string)($route['channel_name'] ?? $route['channel_code']),
            'driver_name' => (string)$route['channel_code'],
            'way_code' => (string)$route['code'],
            'pay_type' => (string)$route['unique_tag'],
            'weight_snapshot' => (int)$route['channel_weight'],
            'method' => $route,
        ];
    }

    private function buildClientMethodData(array $route, bool $smartControlHit = false, string $scene = 'recharge'): array
    {
        $data = [
            'channel' => $route['unique_tag'],
            'reward_percent' => 0,
            'icon' => $route['icon'] ?? '',
            'name' => $route['name'] ?? '',
            'show' => $route['show'] ?? 'all',
            'min_recharge_amount' => $this->formatLimitValue($route['min_recharge_amount'] ?? null),
            'max_recharge_amount' => $this->formatLimitValue($route['max_recharge_amount'] ?? null),
            'min_withdraw_amount' => $this->formatLimitValue($route['min_withdraw_amount'] ?? null),
            'max_withdraw_amount' => $this->formatLimitValue($route['max_withdraw_amount'] ?? null),
            'bank_list' => [],
            'bank_count' => 0,
            'has_banks' => false,
        ];

        if ($scene === 'withdraw') {
            $data['smart_control_hit'] = $smartControlHit;
        }

        return $data;
    }

    private function shouldDisplayRoute(array $route, array $userStats, array $appendPayTypes = []): bool
    {
        if (empty($route['is_clause']) || (int)$route['is_clause'] !== 1) {
            return true;
        }

        $payType = strtolower((string)($route['unique_tag'] ?? ''));
        return in_array($payType, $this->normalizeStringList($appendPayTypes), true);
    }

    private function getUserRechargeStats(int $userId): array
    {
        if ($userId <= 0) {
            return ['total_amount' => 0, 'total_times' => 0];
        }

        return [
            'total_amount' => Db::name('recharge_orders')
                ->where(['user_id' => $userId, 'pay_status' => 1])
                ->sum('amount') ?: 0,
            'total_times' => Db::name('recharge_orders')
                ->where(['user_id' => $userId, 'pay_status' => 1])
                ->count() ?: 0,
        ];
    }

    private function formatLimitValue($amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return (float)$amount;
    }

    private function filterRoutesByChannelCodes(array $routes, array $channelCodes): array
    {
        $channelCodes = $this->normalizeStringList($channelCodes);
        if (!$channelCodes) {
            return $routes;
        }

        return array_values(array_filter($routes, static function (array $route) use ($channelCodes): bool {
            return in_array(strtolower((string)($route['channel_code'] ?? '')), $channelCodes, true);
        }));
    }

    private function filterRoutesByPayTypes(array $routes, array $payTypes): array
    {
        $payTypes = $this->normalizeStringList($payTypes);
        if (!$payTypes) {
            return $routes;
        }

        return array_values(array_filter($routes, static function (array $route) use ($payTypes): bool {
            return in_array(strtolower((string)($route['unique_tag'] ?? '')), $payTypes, true);
        }));
    }

    private function normalizeStringList(array $items): array
    {
        return array_values(array_unique(array_filter(array_map(static function ($item): string {
            return strtolower(trim((string)$item));
        }, $items), static fn(string $item): bool => $item !== '')));
    }
}
