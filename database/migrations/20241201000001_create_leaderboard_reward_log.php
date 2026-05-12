<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateLeaderboardRewardLog extends Migrator
{
    public function change(): void
    {
        $table = $this->table('leaderboard_reward_log', [
            'id' => false,
            'comment' => '排行榜奖励发放日志表',
            'row_format' => 'DYNAMIC',
            'primary_key' => 'id',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'integer', [
            'comment' => 'ID',
            'signed' => false,
            'identity' => true,
            'null' => false
        ])
        ->addColumn('type', 'enum', [
            'values' => 'daily,weekly,monthly',
            'default' => 'daily',
            'comment' => '排行榜类型: daily=日榜,weekly=周榜,monthly=月榜',
            'null' => false
        ])
        ->addColumn('pool_amount', 'decimal', [
            'precision' => 15,
            'scale' => 2,
            'default' => '0.00',
            'comment' => '奖金池总金额',
            'null' => false
        ])
        ->addColumn('distributed_amount', 'decimal', [
            'precision' => 15,
            'scale' => 2,
            'default' => '0.00',
            'comment' => '实际发放金额',
            'null' => false
        ])
        ->addColumn('success_count', 'integer', [
            'default' => 0,
            'comment' => '成功发放用户数',
            'null' => false
        ])
        ->addColumn('fail_count', 'integer', [
            'default' => 0,
            'comment' => '失败用户数',
            'null' => false
        ])
        ->addColumn('create_time', 'biginteger', [
            'signed' => false,
            'null' => true,
            'default' => null,
            'comment' => '创建时间'
        ])
        ->addColumn('update_time', 'biginteger', [
            'signed' => false,
            'null' => true,
            'default' => null,
            'comment' => '更新时间'
        ])
        ->addIndex(['type'], [
            'type' => 'BTREE',
        ])
        ->addIndex(['create_time'], [
            'type' => 'BTREE',
        ])
        ->create();
    }
} 