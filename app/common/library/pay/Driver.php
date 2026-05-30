<?php
declare(strict_types=1);

namespace app\common\library\pay;

use ba\PaymentHelper;
use think\Request;

abstract class Driver
{
    public static function instance(string $type, ?string $channelCode = null): Driver
    {
        $class = __NAMESPACE__ . '\\' . ucfirst($type) . 'Pay';
        if (!class_exists($class)) {
            throw new \Exception("支付方式 {$type} 不存在");
        }
        return new $class($channelCode);
    }

    abstract public function createOrder(string $orderNo, float $amount, array $extra = []): array;
    abstract public function close(string $orderNo): array;
    abstract public function createTransfer(string $orderNo, float $amount, array $extra = []): array;


    protected function verifySign(array $data, string $key): bool
    {
        if (!isset($data['sign'])) return false;
        $sign = $data['sign'];
        unset($data['sign']);
        return PaymentHelper::sign($data, $key) === $sign;
    }
}
