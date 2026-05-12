<?php
declare (strict_types = 1);

namespace app\command;

use app\common\model\Account;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class Reset extends Command
{
    protected function configure()
    {
        $this->setName('reset')
            ->setDescription('统一重置命令系统')
            ->addOption(
                'dry-run',
                null,
                Option::VALUE_NONE,
                '试运行，不实际执行'
            );
    }

    protected function execute(Input $input, Output $output)
    {
        $isDryRun = $input->getOption('dry-run');
        $now = time();

        $output->writeln("[" . date('Y-m-d H:i:s', $now) . "] 开始执行重置...");

        $output->writeln("模式: " . ($isDryRun ? "试运行" : "实际执行"));

        $this->resetDepositStatus($output, $isDryRun);

        $this->resetTodaySumBet($output);

        $output->writeln("[" . date('Y-m-d H:i:s') . "] 重置完成");
        return 1;
    }

    /**
     * 重置首充任务状态
     */
    protected function resetDepositStatus(Output $output, $isDryRun)
    {
        $updateData = [
            'task_status' => json_encode([0, 0, 0]),
            'receive_status' => 0,
            'update_time' => time(),
        ];

        $output->writeln("准备重置首充任务状态...");

        if ($isDryRun) {
            $count = Db::name('activity_first_deposit_daily_user')
                ->where('1=1')
                ->count();
            $output->writeln("[试运行] 将重置 {$count} 条记录");
            $output->writeln("更新数据: " . json_encode($updateData));
            return;
        }

        $affectedRows = Db::name('activity_first_deposit_daily_user')
            ->where('1=1')
            ->update($updateData);

        $output->writeln("成功重置 {$affectedRows} 条记录");
    }

    /**
     * 重制每日流水
     */
    public function  resetTodaySumBet(Output $output)
    {

        Account::where('1=1')->update(["today_sum_bet"=>0]);

        $output->writeln("成功重置所有用户每日流水");
    }

}
