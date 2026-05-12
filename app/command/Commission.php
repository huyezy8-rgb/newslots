<?php

namespace app\command;

use app\common\service\CommissionService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Commission extends Command
{
    protected function configure()
    {
        $this->setName('commission:dispatch')
            ->setDescription('派发一次返佣结算任务到队列')
            ->addArgument('source_user_id', \think\console\input\Argument::REQUIRED, '下级投注用户ID')
            ->addArgument('bet_amount', \think\console\input\Argument::REQUIRED, '投注金额')
            ->addOption('base_rate', 'b', \think\console\input\Option::VALUE_OPTIONAL, '基础返佣百分数(默认0.5)', 0.5)
            ->addOption('max_levels', 'm', \think\console\input\Option::VALUE_OPTIONAL, '最大层级(0不限)', 0)
            ->addOption('queue', null, \think\console\input\Option::VALUE_OPTIONAL, '队列名', 'default');
    }

    protected function execute(Input $input, Output $output)
    {
        $sourceUserId = (int)$input->getArgument('source_user_id');
        $betAmount = (float)$input->getArgument('bet_amount');
        $baseRate = (float)$input->getOption('base_rate');
        $maxLevels = (int)$input->getOption('max_levels');
        $queue = (string)$input->getOption('queue');

        $svc = new CommissionService();
        $ok = $svc->dispatchSettleJob($sourceUserId, $betAmount, $baseRate, $maxLevels, $queue);
        if ($ok) {
            $output->writeln('<info>任务已派发到队列</info>');
        } else {
            $output->writeln('<error>任务派发失败</error>');
        }
    }
}

