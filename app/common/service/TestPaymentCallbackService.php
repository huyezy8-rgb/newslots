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
    public function getRechargePlatformOrderNo(array $order): string
    {
        return $order['platform_order_no'] ?: 'TESTPAY' . $order['order_no'];
    }

    public function buildRechargeCallbackData(array $order, string $status, string $statusField = 'test_callback_status'): array
    {
        return [
            'state' => $status === 'success' ? self::PAY_SUCCESS : 3,
            'mchOrderNo' => $order['order_no'],
            'orderNo' => $this->getRechargePlatformOrderNo($order),
            'amount' => bcmul((string)$order['amount'], '100', 0),
            'successTime' => time() * 1000,
            $statusField => $status,
        ];
    }

    public function processRecharge(array $order, string $status, ThinkRequest $request = null, string $statusField = 'test_callback_status'): int
    {
        $platformOrderNo = $this->getRechargePlatformOrderNo($order);
        $data = $this->buildRechargeCallbackData($order, $status, $statusField);

        $this->process($order['order_no'], $platformOrderNo, $data, $request);

        return $status === 'success' ? 1 : 2;
    }

    public function processPendingRecharge(array $order, string $status, ThinkRequest $request = null, string $statusField = 'testpay_manual_status'): int
    {
        Db::startTrans();
        try {
            $lockedOrder = Db::name('recharge_orders')
                ->where('id', $order['id'])
                ->lock(true)
                ->find();

            if (!$lockedOrder) {
                throw new \Exception('Order not found');
            }

            if (strtolower((string)$lockedOrder['pay_type']) !== 'testpay') {
                throw new \Exception('Payment method param error');
            }

            if ((int)$lockedOrder['pay_status'] !== 0) {
                throw new \Exception('Order already processed');
            }

            $platformOrderNo = $this->getRechargePlatformOrderNo($lockedOrder);
            $data = $this->buildRechargeCallbackData($lockedOrder, $status, $statusField);

            Orders::update(['callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => time()], ['order_no' => $lockedOrder['order_no']]);
            $this->processOrder($lockedOrder['order_no'], $platformOrderNo, $data, $request);

            Db::commit();
            $this->triggerEvents();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::channel('payment')->error("TestPay pending recharge callback failed: {$e->getMessage()}, Trace: {$e->getTraceAsString()}");
            throw $e;
        }

        return $status === 'success' ? 1 : 2;
    }

    public function markRechargeTerminal(array $order, string $status, string $statusField = 'testpay_manual_status'): int
    {
        $platformOrderNo = $this->getRechargePlatformOrderNo($order);
        $data = $this->buildRechargeCallbackData($order, $status, $statusField);
        $remark = $status === 'cancel' ? 'TestPay client canceled' : 'TestPay client failed';

        Db::startTrans();
        try {
            $lockedOrder = Db::name('recharge_orders')
                ->where('id', $order['id'])
                ->lock(true)
                ->find();

            if (!$lockedOrder) {
                throw new \Exception('Order not found');
            }

            if (strtolower((string)$lockedOrder['pay_type']) !== 'testpay') {
                throw new \Exception('Payment method param error');
            }

            if ((int)$lockedOrder['pay_status'] !== 0) {
                throw new \Exception('Order already processed');
            }

            Db::name('recharge_orders')
                ->where('id', $lockedOrder['id'])
                ->update([
                    'pay_status' => 2,
                    'platform_order_no' => $platformOrderNo,
                    'callback_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'remark' => $remark,
                    'updated_at' => time(),
                ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return 2;
    }

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
