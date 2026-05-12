<?php

use think\migration\Migrator;
use think\migration\db\Column;

class ChangeRewardStatusToTinyint extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 修改 member_level_rewards 表的状态字段为 tinyint 类型
        $table = $this->table('member_level_rewards');
        
        // 修改现有的状态字段类型
        $table->changeColumn('upgrade_reward_status', 'integer', [
            'limit' => 1,
            'signed' => false,
            'default' => 0,
            'comment' => '升级奖励状态：0-待领取，1-已领取，2-已过期'
        ])
        ->changeColumn('weekly_reward_status', 'integer', [
            'limit' => 1,
            'signed' => false,
            'default' => 0,
            'comment' => '周奖励状态：0-待领取，1-已领取，2-已过期'
        ])
        ->changeColumn('monthly_reward_status', 'integer', [
            'limit' => 1,
            'signed' => false,
            'default' => 0,
            'comment' => '月奖励状态：0-待领取，1-已领取，2-已过期'
        ])
        ->update();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        // 回滚：删除 tinyint 状态字段，恢复枚举字段
        $table = $this->table('member_level_rewards');
        
        // 删除 tinyint 状态字段
        if ($table->hasColumn('monthly_reward_status')) {
            $table->removeColumn('monthly_reward_status');
        }
        if ($table->hasColumn('weekly_reward_status')) {
            $table->removeColumn('weekly_reward_status');
        }
        if ($table->hasColumn('upgrade_reward_status')) {
            $table->removeColumn('upgrade_reward_status');
        }
        
        // 恢复枚举状态字段
        $table->addColumn('upgrade_reward_status', 'enum', [
            'values' => ['pending', 'claimed', 'expired'],
            'default' => 'pending',
            'comment' => '升级奖励状态：pending-待领取，claimed-已领取，expired-已过期',
            'after' => 'upgrade_reward_claimed_time'
        ])
        ->addColumn('weekly_reward_status', 'enum', [
            'values' => ['pending', 'claimed', 'expired'],
            'default' => 'pending',
            'comment' => '周奖励状态：pending-待领取，claimed-已领取，expired-已过期',
            'after' => 'weekly_reward_claimed_time'
        ])
        ->addColumn('monthly_reward_status', 'enum', [
            'values' => ['pending', 'claimed', 'expired'],
            'default' => 'pending',
            'comment' => '月奖励状态：pending-待领取，claimed-已领取，expired-已过期',
            'after' => 'monthly_reward_claimed_time'
        ])
        ->update();
    }
}