<?php
declare (strict_types=1);

namespace app\command\Recharge;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class UpdateExpiredOrders extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('recharge:update_expired')  // 命令名称
        ->setDescription('更新已过期的充值订单状态')  // 命令描述
        ->addOption(
            'dry-run',       // 选项名称
            null,            // 选项简写（无简写）
            Option::VALUE_NONE,  // 选项类型（不需要值）
            '模拟运行，不实际更新数据库'  // 选项描述
        )
            ->addOption(
                'limit',         // 选项名称
                'l',             // 选项简写（-l）
                Option::VALUE_OPTIONAL,  // 选项类型（可选值）
                '限制每次处理的订单数量',  // 选项描述
                1000             // 默认值
            );
    }

    protected function execute(Input $input, Output $output)
    {
        // 获取命令行选项
        $dryRun = $input->getOption('dry-run');  // 是否模拟运行
        $limit = (int)$input->getOption('limit'); // 处理数量限制

        $now = time();  // 当前时间戳

        // 查询过期未支付的订单
        $orders = Db::name('recharge_orders')
            ->where('pay_status', 0)  // 支付状态：0=待支付
            ->where('expired_time', '<', $now)  // 过期时间早于当前时间
            ->limit($limit)
            ->select();

        // 如果没有找到过期订单
        if (empty($orders)) {
            $output->writeln('未找到已过期的充值订单。');
            return;
        }

        $output->writeln(sprintf('共找到 %d 个已过期的充值订单需要更新。', count($orders)));

        // 如果是模拟运行模式
        if ($dryRun) {
            $output->writeln('模拟运行模式 - 不会实际修改数据。');
            $output->writeln('示例订单ID: ' . implode(', ', array_column($orders, 'id')));
            return;
        }

        // 开始数据库事务
        Db::startTrans();
        try {
            // 更新订单状态
            $updated = Db::name('recharge_orders')
                ->where('pay_status', 0)  // 待支付订单
                ->where('expired_time', '<', $now)  // 已过期
                ->limit($limit)
                ->update([
                    'pay_status' => 2,  // 更新为失败状态
                    'updated_at' => $now,  // 更新时间
                    // 在原有备注后追加系统自动标记信息
                    'remark' => Db::raw("CONCAT(IFNULL(remark,''), '系统自动标记为过期')")
                ]);

            // 提交事务
            Db::commit();
            $output->writeln(sprintf('成功更新 %d 个订单状态。', $updated));
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $output->writeln(sprintf('<error>更新订单失败: %s</error>', $e->getMessage()));
        }
    }
}