<?php

namespace app\common\service;

use think\facade\Db;

class RedEnvelopeRedemptionCodeService
{
    private const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function createOrReuseForBot(array $bot, int $ruleId, int $codeLength = 4, ?int $now = null): array
    {
        $now ??= time();
        $rule = $this->getEnabledRule($ruleId);
        $latest = Db::name('tg_send_record')
            ->where('bot_id', (int)($bot['id'] ?? 0))
            ->where('send_status', 1)
            ->where('redemption_code_id', '>', 0)
            ->where('code', '<>', 'TEST')
            ->order('send_time desc')
            ->find();

        if ($latest && !empty($latest['redemption_code_id'])) {
            $code = Db::name('red_envelope_redemption_code')->where('id', (int)$latest['redemption_code_id'])->find();
            if ($code && $this->isReusable($code, $rule, $now)) {
                return $this->mergeRuleFields($code, $rule);
            }
        }

        return $this->createFromRule($ruleId, $codeLength, $now, $rule);
    }

    public function createFromRule(int $ruleId, int $codeLength = 4, ?int $now = null, ?array $rule = null): array
    {
        $now ??= time();
        $rule ??= $this->getEnabledRule($ruleId);

        $fields = $this->getFields();
        $data = ['code' => $this->generateUniqueCode($codeLength)];

        foreach (['amount_min', 'amount_max', 'per_user_limit', 'expire_hours'] as $field) {
            if (in_array($field, $fields, true)) {
                $data[$field] = $rule[$field] ?? 0;
            }
        }

        if (in_array('create_time', $fields, true)) {
            $data['create_time'] = $now;
        }
        if (in_array('update_time', $fields, true)) {
            $data['update_time'] = $now;
        }

        $id = Db::name('red_envelope_redemption_code')->insertGetId(array_intersect_key($data, array_flip($fields)));
        $created = Db::name('red_envelope_redemption_code')->where('id', $id)->find();

        return $this->mergeRuleFields($created ?: array_merge($data, ['id' => $id]), $rule);
    }

    public function createFromLatestRule(int $length = 4, ?int $now = null): array
    {
        $now ??= time();
        $fields = $this->getFields();
        $rule = Db::name('red_envelope_redemption_code')->order('id desc')->find();
        if (!$rule) {
            throw new \RuntimeException('Red envelope code rule not found. Please create one redemption code first.');
        }

        $data = ['code' => $this->generateUniqueCode($length)];
        foreach (['amount_min', 'amount_max', 'per_user_limit', 'expire_hours'] as $field) {
            if (in_array($field, $fields, true) && array_key_exists($field, $rule)) {
                $data[$field] = $rule[$field];
            }
        }

        if (in_array('create_time', $fields, true)) {
            $data['create_time'] = $now;
        }
        if (in_array('update_time', $fields, true)) {
            $data['update_time'] = $now;
        }

        $id = Db::name('red_envelope_redemption_code')->insertGetId($data);
        $created = Db::name('red_envelope_redemption_code')->where('id', $id)->find();

        return $created ?: array_merge($data, ['id' => $id]);
    }

    public function getUsageStats(int $codeId): array
    {
        $claimCount = Db::name('red_envelope_redemption_record')->where('code_id', $codeId)->count();
        $claimAmount = Db::name('red_envelope_redemption_record')->where('code_id', $codeId)->sum('amount');

        return [
            'claim_count' => (int)$claimCount,
            'claim_amount' => (float)$claimAmount,
        ];
    }

    public function buildVars(array $code): array
    {
        $amountMin = isset($code['amount_min']) ? (float)$code['amount_min'] : 0;
        $amountMax = isset($code['amount_max']) ? (float)$code['amount_max'] : $amountMin;
        $amount = $amountMin === $amountMax
            ? number_format($amountMin, 2, '.', '')
            : number_format($amountMin, 2, '.', '') . '-' . number_format($amountMax, 2, '.', '');

        $expireHours = (int)($code['expire_hours'] ?? 0);
        $createTime = (int)($code['create_time'] ?? time());
        $expireTime = $expireHours > 0 ? $createTime + ($expireHours * 3600) : 0;
        $stats = !empty($code['id']) ? $this->getUsageStats((int)$code['id']) : ['claim_count' => 0, 'claim_amount' => 0];
        $claimCount = (int)$stats['claim_count'];
        $maxUsers = (int)($code['max_claim_users'] ?? 0);

        return [
            'code' => (string)($code['code'] ?? ''),
            'amount' => $amount,
            'amount_min' => number_format($amountMin, 2, '.', ''),
            'amount_max' => number_format($amountMax, 2, '.', ''),
            'expire_hours' => (string)$expireHours,
            'expire_time' => $expireTime > 0 ? date('Y-m-d H:i:s', $expireTime) : '',
            'claim_count' => (string)$claimCount,
            'max_users' => (string)$maxUsers,
            'left_count' => $maxUsers > 0 ? (string)max(0, $maxUsers - $claimCount) : 'Unlimited',
        ];
    }

    private function getEnabledRule(int $ruleId): array
    {
        if ($ruleId <= 0) {
            throw new \InvalidArgumentException('请先选择红包兑换码规则。');
        }

        $rule = Db::name('red_envelope_redemption_rule')
            ->where('id', $ruleId)
            ->where('is_enabled', 1)
            ->find();

        if (!$rule) {
            throw new \RuntimeException('红包兑换码规则不存在或未启用。');
        }

        return $rule;
    }

    private function isReusable(array $code, array $rule, int $now): bool
    {
        $expireHours = (int)($code['expire_hours'] ?? $rule['expire_hours'] ?? 0);
        $createTime = (int)($code['create_time'] ?? 0);
        if ($expireHours > 0 && $createTime > 0 && ($createTime + $expireHours * 3600) <= $now) {
            return false;
        }

        $maxClaimUsers = (int)($rule['max_claim_users'] ?? 0);
        if ($maxClaimUsers <= 0) {
            return true;
        }

        $claimCount = (int)Db::name('red_envelope_redemption_record')
            ->where('code_id', (int)$code['id'])
            ->count();

        return $claimCount < $maxClaimUsers;
    }

    private function mergeRuleFields(array $code, array $rule): array
    {
        foreach (['amount_min', 'amount_max', 'per_user_limit', 'expire_hours', 'max_claim_users'] as $field) {
            if (!array_key_exists($field, $code) || $code[$field] === null || $code[$field] === '') {
                $code[$field] = $rule[$field] ?? 0;
            }
        }

        return $code;
    }

    private function generateUniqueCode(int $length): string
    {
        $length = in_array($length, [4, 5, 6, 8], true) ? $length : 4;
        $max = strlen(self::CODE_CHARS) - 1;

        for ($i = 0; $i < 100; $i++) {
            $code = '';
            for ($j = 0; $j < $length; $j++) {
                $code .= self::CODE_CHARS[random_int(0, $max)];
            }

            if (!Db::name('red_envelope_redemption_code')->where('code', $code)->find()) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate unique redemption code');
    }

    private function getFields(): array
    {
        try {
            $fields = Db::getFields(config('database.connections.mysql.prefix') . 'red_envelope_redemption_code');
            return array_keys($fields);
        } catch (\Throwable) {
            return [];
        }
    }
}
