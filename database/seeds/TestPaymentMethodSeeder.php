<?php

use think\facade\Db;
use think\migration\Seeder;

class TestPaymentMethodSeeder extends Seeder
{
    private const CHANNEL_CODE = 'Test';
    private const METHOD_TAG = 'testpay';
    private const METHOD_CODE = 'testpay';

    public function run(): void
    {
        if (!$this->hasTable('payment_channels') || !$this->hasTable('payment_methods')) {
            return;
        }

        $now = time();

        $channelData = [
            'name' => 'TestPay',
            'code' => self::CHANNEL_CODE,
            'description' => 'Internal test payment channel',
            'config' => json_encode(['mode' => 'local'], JSON_UNESCAPED_UNICODE),
            'status' => '1',
            'remark' => 'Internal test payment channel, no external request',
            'update_time' => $now,
        ];

        $channel = Db::name('payment_channels')->where('code', self::CHANNEL_CODE)->find();
        if ($channel) {
            if (!$this->isTestPayChannel($channel)) {
                throw new RuntimeException('Payment channel code "Test" already exists and is not an internal TestPay channel.');
            }
            Db::name('payment_channels')->where('id', $channel['id'])->update($channelData);
        } else {
            $channelData['create_time'] = $now;
            Db::name('payment_channels')->insert($channelData);
        }

        $methodData = [
            'channel_code' => self::CHANNEL_CODE,
            'name' => 'TestPay',
            'unique_tag' => self::METHOD_TAG,
            'code' => self::METHOD_CODE,
            'description' => 'Internal test payment method',
            'icon' => '',
            'show' => 'all',
            'status' => '1',
            'is_clause' => 0,
            'pay_method' => '0',
            'field_config' => json_encode([
                'required_fields' => [],
                'field_labels' => new stdClass(),
                'field_placeholders' => new stdClass(),
            ], JSON_UNESCAPED_UNICODE),
            'validation_rules' => json_encode(new stdClass(), JSON_UNESCAPED_UNICODE),
            'remark' => 'Internal test payment method, command callback supported',
            'update_time' => $now,
        ];

        $method = Db::name('payment_methods')->where('unique_tag', self::METHOD_TAG)->find();
        if ($method) {
            if (!$this->isTestPayMethod($method)) {
                throw new RuntimeException('Payment method unique_tag "testpay" already exists and is not an internal TestPay method.');
            }
            Db::name('payment_methods')->where('id', $method['id'])->update($methodData);
        } else {
            $methodCodeExists = Db::name('payment_methods')->where('code', self::METHOD_CODE)->find();
            if ($methodCodeExists) {
                throw new RuntimeException('Payment method code "testpay" already exists with another unique_tag.');
            }

            $methodData['create_time'] = $now;
            Db::name('payment_methods')->insert($methodData);
        }
    }

    private function isTestPayChannel(array $channel): bool
    {
        $config = json_decode((string)($channel['config'] ?? ''), true);

        return ($channel['name'] ?? '') === 'TestPay'
            && ($channel['remark'] ?? '') === 'Internal test payment channel, no external request'
            && is_array($config)
            && ($config['mode'] ?? null) === 'local';
    }

    private function isTestPayMethod(array $method): bool
    {
        return ($method['channel_code'] ?? '') === self::CHANNEL_CODE
            && ($method['code'] ?? '') === self::METHOD_CODE
            && ($method['name'] ?? '') === 'TestPay';
    }
}
