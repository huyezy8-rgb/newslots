<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddRewardStatusToMemberLevelRewards extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 为 member_level_rewards 表添加每个奖励的状态字段
        $table = $this->table('member_level_rewards');
        
        // 升级奖励状态
        if (!$table->hasColumn('upgrade_reward_status')) {
            $table->addColumn('upgrade_reward_status', 'enum', [
                'values' => ['pending', 'claimed', 'expired'],
                'default' => 'pending',
                'comment' => '升级奖励状态：pending-待领取，claimed-已领取，expired-已过期',
                'after' => 'upgrade_reward_claimed_time'
            ]);
        }
        
        // 周奖励状态
        if (!$table->hasColumn('weekly_reward_status')) {
            $table->addColumn('weekly_reward_status', 'enum', [
                'values' => ['pending', 'claimed', 'expired'],
                'default' => 'pending',
                'comment' => '周奖励状态：pending-待领取，claimed-已领取，expired-已过期',
                'after' => 'weekly_reward_claimed_time'
            ]);
        }
        
        // 月奖励状态
        if (!$table->hasColumn('monthly_reward_status')) {
            $table->addColumn('monthly_reward_status', 'enum', [
                'values' => ['pending', 'claimed', 'expired'],
                'default' => 'pending',
                'comment' => '月奖励状态：pending-待领取，claimed-已领取，expired-已过期',
                'after' => 'monthly_reward_claimed_time'
            ]);
        }
        
        // 添加状态字段的索引
        $table->addIndex(['upgrade_reward_status'], ['name' => 'idx_upgrade_status'])
              ->addIndex(['weekly_reward_status'], ['name' => 'idx_weekly_status'])
              ->addIndex(['monthly_reward_status'], ['name' => 'idx_monthly_status'])
              ->update();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        // 移除添加的状态字段
        $table = $this->table('member_level_rewards');
        
        if ($table->hasColumn('monthly_reward_status')) {
            $table->removeColumn('monthly_reward_status');
        }
        
        if ($table->hasColumn('weekly_reward_status')) {
            $table->removeColumn('weekly_reward_status');
        }
        
        if ($table->hasColumn('upgrade_reward_status')) {
            $table->removeColumn('upgrade_reward_status');
        }
        
        $table->update();
    }
}