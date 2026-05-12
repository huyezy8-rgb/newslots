<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\service\MemberLevelService;
use think\facade\Log;

class MemberMonthlyReward extends Command
{
    protected function configure()
    {
        $this->setName('member:monthly-reward')
            ->setDescription('会员等级月奖励发放任务');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始发放会员月奖励...');
        
        $memberLevelService = new MemberLevelService();
        
        try {
            $startTime = time();
            $output->writeln('执行时间: ' . date('Y-m-d H:i:s', $startTime));
            
            // 发放月奖励
            $monthlyResult = $memberLevelService->createMonthlyRewards();
            
            if ($monthlyResult['status']) {
                $output->writeln("月奖励发放成功，共创建 {$monthlyResult['created_count']} 个奖励");
                Log::info("月奖励发放任务执行成功", [
                    'created_count' => $monthlyResult['created_count'],
                    'execution_time' => time() - $startTime,
                    'start_time' => $startTime
                ]);
            } else {
                $output->writeln("月奖励发放失败: " . $monthlyResult['msg']);
                Log::error("月奖励发放任务执行失败", [
                    'error_msg' => $monthlyResult['msg'],
                    'execution_time' => time() - $startTime,
                    'start_time' => $startTime
                ]);
            }
            
            $output->writeln('会员月奖励发放任务执行完成');
            
        } catch (\Exception $e) {
            $output->writeln('会员月奖励发放任务执行失败: ' . $e->getMessage());
            Log::error('会员月奖励发放任务执行异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time' => time() - $startTime ?? 0
            ]);
            
            // 返回错误码
            return 1;
        }
        
        // 返回成功码
        return 0;
    }
}
