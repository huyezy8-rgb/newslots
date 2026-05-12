<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateMemberRewardLogsTable extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 创建奖励发放记录表
        $rewardLogs = $this->table('member_reward_logs', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci',
            'comment' => '会员奖励发放记录表'
        ]);
        
        $rewardLogs
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed' => false,
                'comment' => '主键ID'
            ])
            ->addColumn('user_id', 'integer', [
                'signed' => false,
                'comment' => '用户ID'
            ])
            ->addColumn('reward_id', 'integer', [
                'signed' => false,
                'comment' => '奖励ID（关联member_level_rewards表）'
            ])
            ->addColumn('level', 'integer', [
                'signed' => false,
                'comment' => '当前等级'
            ])
            ->addColumn('previous_level', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '升级前等级'
            ])
            ->addColumn('reward_type', 'enum', [
                'values' => ['upgrade', 'weekly', 'monthly'],
                'comment' => '奖励类型：upgrade-升级奖励，weekly-周奖励，monthly-月奖励'
            ])
            ->addColumn('reward_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'comment' => '奖励金额'
            ])
            ->addColumn('create_time', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => '创建时间'
            ])
            ->addIndex(['user_id'], ['name' => 'idx_user_id'])
            ->addIndex(['reward_id'], ['name' => 'idx_reward_id'])
            ->addIndex(['reward_type'], ['name' => 'idx_reward_type'])
            ->addIndex(['create_time'], ['name' => 'idx_create_time'])
            ->create();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        // 删除奖励发放记录表
        $this->table('member_reward_logs')->drop()->save();
    }
}