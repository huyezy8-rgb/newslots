<?php
declare(strict_types=1);

namespace app\command\Payment;

use app\api\controller\Notify;
use app\common\service\TestPaymentCallbackService;
use ba\PaymentHelper;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class TestCallback extends Command
{
    protected const TYPE_AUTO = 'auto';
    protected const TYPE_RECHARGE = 'recharge';
    protected const TYPE_WITHDRAW = 'withdraw';

    protected function configure()
    {
        $this->setName('payment:test-callback')
            ->setDescription('Simulate a successful or failed payment callback for recharge and withdraw orders')
            ->addOption('order', null, Option::VALUE_REQUIRED, 'Order number or numeric order ID')
            ->addOption('status', null, Option::VALUE_REQUIRED, 'Callback status: success or fail')
            ->addOption('type', null, Option::VALUE_OPTIONAL, 'Order type: recharge, withdraw, or auto', self::TYPE_AUTO)
            ->addOption('dry-run', null, Option::VALUE_NONE, 'Show the planned changes without writing data');
    }

    protected function execute(Input $input, Output $output)
    {
        $orderValue = trim((string)$input->getOption('order'));
        $status = strtolower(trim((string)$input->getOption('status')));
        $type = strtolower(trim((string)$input->getOption('type') ?: self::TYPE_AUTO));
        $dryRun = (bool)$input->getOption('dry-run');

        if ($orderValue === '') {
            $output->writeln('<error>Missing required option: --order</error>');
            return 1;
        }

        if (!in_array($status, ['success', 'fail'], true)) {
            $output->writeln('<error>Invalid --status. Use success or fail.</error>');
            return 1;
        }

        if (!in_array($type, [self::TYPE_AUTO, self::TYPE_RECHARGE, self::TYPE_WITHDRAW], true)) {
            $output->writeln('<error>Invalid --type. Use recharge, withdraw, or auto.</error>');
            return 1;
        }

        try {
            [$resolvedType, $order] = $this->resolveOrder($orderValue, $type);

            if ($resolvedType === self::TYPE_RECHARGE) {
                $this->handleRecharge($order, $status, $dryRun, $output);
            } else {
                $this->handleWithdraw($order, $status, $dryRun, $output);
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }

        return 0;
    }

    private function resolveOrder(string $orderValue, string $type): array
    {
        if ($type === self::TYPE_RECHARGE) {
            $order = $this->findRechargeOrder($orderValue);
            if (!$order) {
                throw new \Exception("Recharge order not found: {$orderValue}");
            }
            return [self::TYPE_RECHARGE, $order];
        }

        if ($type === self::TYPE_WITHDRAW) {
            $order = $this->findWithdrawOrder($orderValue);
            if (!$order) {
                throw new \Exception("Withdraw order not found: {$orderValue}");
            }
            return [self::TYPE_WITHDRAW, $order];
        }

        if (str_starts_with(strtoupper($orderValue), 'PAY')) {
            $order = $this->findRechargeOrder($orderValue);
            if (!$order) {
                throw new \Exception("Recharge order not found: {$orderValue}");
            }
            return [self::TYPE_RECHARGE, $order];
        }

        if (!is_numeric($orderValue)) {
            $order = $this->findWithdrawOrder($orderValue);
            if (!$order) {
                throw new \Exception("Withdraw order not found: {$orderValue}");
            }
            return [self::TYPE_WITHDRAW, $order];
        }

        $recharge = $this->findRechargeOrder($orderValue);
        $withdraw = $this->findWithdrawOrder($orderValue);

        if ($recharge && $withdraw) {
            throw new \Exception('Numeric order ID exists in both recharge and withdraw tables. Please pass --type.');
        }

        if ($recharge) {
            return [self::TYPE_RECHARGE, $recharge];
        }

        if ($withdraw) {
            return [self::TYPE_WITHDRAW, $withdraw];
        }

        throw new \Exception("Order not found: {$orderValue}");
    }

    private function findRechargeOrder(string $orderValue): ?array
    {
        $query = Db::name('recharge_orders');

        if (is_numeric($orderValue)) {
            $query->where('id', (int)$orderValue);
        } else {
            $query->where('order_no', $orderValue);
        }

        $order = $query->find();
        return $order ?: null;
    }

    private function findWithdrawOrder(string $orderValue): ?array
    {
        $query = Db::name('withdraw_orders');

        if (is_numeric($orderValue)) {
            $query->where('id', (int)$orderValue);
        } else {
            $query->where('order_no', $orderValue);
        }

        $order = $query->find();
        return $order ?: null;
    }

    private function handleRecharge(array $order, string $status, bool $dryRun, Output $output): void
    {
        $output->writeln(sprintf(
            'Recharge order %s: pay_status %s -> %s',
            $order['order_no'],
            $order['pay_status'],
            $status === 'success' ? '1' : '2'
        ));

        if ($dryRun) {
            $output->writeln('[dry-run] No data was changed.');
            return;
        }

        (new TestPaymentCallbackService())->processRecharge($order, $status);
        $output->writeln('Callback processed.');
    }

    private function handleWithdraw(array $order, string $status, bool $dryRun, Output $output): void
    {
        if (in_array((int)$order['status'], [2, 3, 4], true)) {
            throw new \Exception("Withdraw order is already terminal: status={$order['status']}");
        }

        $orderNo = $order['order_no'] ?: PaymentHelper::generateOrderNo('Tran');
        $platformOrderNo = $order['platform_order_no'] ?: 'TESTTRAN' . $orderNo;
        $finalStatus = $status === 'success' ? 2 : 4;

        $output->writeln(sprintf(
            'Withdraw order ID %s: status %s -> %s, order_no=%s',
            $order['id'],
            $order['status'],
            $finalStatus,
            $orderNo
        ));

        if ($dryRun) {
            $output->writeln('[dry-run] No data was changed.');
            return;
        }

        if (empty($order['order_no']) || (int)$order['status'] === 0) {
            Db::name('withdraw_orders')
                ->where('id', $order['id'])
                ->update([
                    'order_no' => $orderNo,
                    'platform_order_no' => $platformOrderNo,
                    'status' => 1,
                    'update_time' => time(),
                ]);
        }

        $data = [
            'state' => $status === 'success' ? Notify::PAY_SUCCESS : 3,
            'mchOrderNo' => $orderNo,
            'orderNo' => $platformOrderNo,
            'amount' => bcmul((string)($order['real_amount'] ?? $order['amount']), '100', 0),
            'successTime' => time() * 1000,
            'test_callback_status' => $status,
        ];

        (new TestPaymentCallbackService())->process($orderNo, $platformOrderNo, $data);
        $output->writeln('Callback processed.');
    }
}
