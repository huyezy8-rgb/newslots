<?php
declare(strict_types=1);

namespace app\common\service;

use app\api\controller\Notify;
use app\common\model\recharge\Orders;
use app\common\model\withdraw\Orders as WithdrawOrders;
use think\Request as ThinkRequest;
use think\facade\Db;
use think\facade\Log;

class TestPaymentCallbackService extends Notify
{
    public function process(string $localOrderNo, string $platformOrderNo, array $data, ThinkRequest $request = null): void
    {
        $data = array_merge([
            'mchOrderNo' => $localOrderNo,
            'orderNo' => $platformOrderNo,
            'state' => self::PAY_SUCCESS,
            'successTime' => time() * 1000,
            'test_callback' => true,
        ], $data);

        if (str_starts_with(strtoupper($localOrderNo), 'PAY')) {
            Orders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => time()], ['order_no' => $localOrderNo]);
        } else {
            WithdrawOrders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'update_time' => time()], ['order_no' => $localOrderNo]);
        }

        Db::startTrans();
        try {
            $this->processOrder($localOrderNo, $platformOrderNo, $data, $request);
            Db::commit();
            $this->triggerEvents();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::channel('payment')->error("TestPay callback failed: {$e->getMessage()}, Trace: {$e->getTraceAsString()}, Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            throw $e;
        }
    }
}
