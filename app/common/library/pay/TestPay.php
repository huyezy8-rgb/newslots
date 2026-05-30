<?php
declare(strict_types=1);

namespace app\common\library\pay;

use think\Request;

class TestPay extends Driver
{
    public function __construct(?string $channelCode = null)
    {
    }

    public function createOrder(string $orderNo, float $amount, array $extra = []): array
    {
        return [
            'code' => 0,
            'data' => [
                'payOrderNo' => 'TESTPAY' . $orderNo,
                'amount' => $amount,
                'status' => 'created',
            ],
            'message' => 'Test payment order created',
        ];
    }

    public function close(string $orderNo): array
    {
        return [
            'code' => 0,
            'data' => [
                'payOrderNo' => 'TESTPAY' . $orderNo,
                'status' => 'closed',
            ],
            'message' => 'Test payment order closed',
        ];
    }

    public function createTransfer(string $orderNo, float $amount, array $extra = []): array
    {
        return [
            'code' => 0,
            'data' => [
                'transferOrderNo' => 'TESTTRAN' . $orderNo,
                'amount' => $amount,
                'status' => 'created',
            ],
            'message' => 'Test transfer order created',
        ];
    }

    public function notify(Request $request): bool
    {
        return true;
    }
}
