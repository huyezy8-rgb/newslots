<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateMemberLevelRewardsTable extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 创建用户等级奖励表
        $memberLevelRewards = $this->table('member_level_rewards', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci',
            'comment' => '用户等级奖励表'
        ]);
        
        $memberLevelRewards
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed' => false,
                'comment' => '主键ID'
            ])
            ->addColumn('user_id', 'integer', [
                'signed' => false,
                'comment' => '用户ID'
            ])
            ->addColumn('level', 'integer', [
                'signed' => false,
                'comment' => '用户等级'
            ])
            // 升级奖励相关字段
            ->addColumn('upgrade_reward_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => '0.00',
                'comment' => '升级奖励金额'
            ])
            ->addColumn('upgrade_reward_issued_time', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '升级奖励发放时间'
            ])
            ->addColumn('upgrade_reward_claimed_time', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '升级奖励领取时间'
            ])
            // 周奖励相关字段
            ->addColumn('weekly_reward_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => '0.00',
                'comment' => '周奖励金额'
            ])
            ->addColumn('weekly_reward_issued_time', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '周奖励发放时间'
            ])
            ->addColumn('weekly_reward_claimed_time', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '周奖励领取时间'
            ])
            // 月奖励相关字段
            ->addColumn('monthly_reward_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => '0.00',
                'comment' => '月奖励金额'
            ])
            ->addColumn('monthly_reward_issued_time', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '月奖励发放时间'
            ])
            ->addColumn('monthly_reward_claimed_time', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '月奖励领取时间'
            ])
            ->addColumn('create_time', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '创建时间'
            ])
            ->addColumn('update_time', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '更新时间'
            ])
            ->addIndex(['user_id'], ['name' => 'uk_user_id', 'unique' => true])
            ->addIndex(['level'], ['name' => 'idx_level'])
            ->addIndex(['upgrade_reward_issued_time'], ['name' => 'idx_upgrade_issued'])
            ->addIndex(['weekly_reward_issued_time'], ['name' => 'idx_weekly_issued'])
            ->addIndex(['monthly_reward_issued_time'], ['name' => 'idx_monthly_issued'])
            ->create();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        // 删除用户等级奖励表
        $this->table('member_level_rewards')->drop()->save();
    }
}