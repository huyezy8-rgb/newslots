<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateLeaderboardTables extends Migrator
{
    /**
     * 创建排行榜统计表
     */
    public function change()
    {
        // 创建排行榜统计表
        $this->createLeaderboardStatsTable();
        
        // 创建奖金池表
        $this->createLeaderboardPoolTable();
    }

    /**
     * 创建排行榜统计表
     */
    private function createLeaderboardStatsTable()
    {
        $table = $this->table('leaderboard_stats', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '排行榜统计表'
        ]);

        $table->addColumn('id', 'integer', [
            'identity' => true,
            'signed' => false,
            'comment' => '主键ID'
        ])
        ->addColumn('user_id', 'integer', [
            'signed' => false,
            'comment' => '用户ID'
        ])
        ->addColumn('type', 'string', [
            'limit' => 20,
            'comment' => '排行榜类型：daily-日榜，weekly-周榜，monthly-月榜'
        ])
        ->addColumn('period', 'string', [
            'limit' => 20,
            'comment' => '统计周期：日榜格式Y-m-d，周榜格式Y-m-d，月榜格式Y-m'
        ])
        ->addColumn('total_bet', 'decimal', [
            'precision' => 15,
            'scale' => 2,
            'default' => 0,
            'comment' => '总下注金额'
        ])
        ->addColumn('pool_amount', 'decimal', [
            'precision' => 15,
            'scale' => 2,
            'default' => 0,
            'comment' => '入池金额'
        ])
        ->addColumn('username', 'string', [
            'limit' => 50,
            'default' => '',
            'comment' => '用户名'
        ])
        ->addColumn('nickname', 'string', [
            'limit' => 50,
            'default' => '',
            'comment' => '昵称'
        ])
        ->addColumn('avatar', 'string', [
            'limit' => 255,
            'default' => '',
            'comment' => '头像'
        ])
        ->addColumn('channel_id', 'integer', [
            'signed' => false,
            'default' => 0,
            'comment' => '渠道ID'
        ])
        ->addColumn('create_time', 'integer', [
            'signed' => false,
            'comment' => '创建时间'
        ])
        ->addColumn('update_time', 'integer', [
            'signed' => false,
            'comment' => '更新时间'
        ])
        ->addIndex(['user_id', 'type', 'period'], [
            'unique' => true,
            'name' => 'idx_user_type_period'
        ])
        ->addIndex(['type', 'period'], [
            'name' => 'idx_type_period'
        ])
        ->addIndex(['total_bet'], [
            'name' => 'idx_total_bet'
        ])
        ->create();
    }

    /**
     * 创建奖金池表
     */
    private function createLeaderboardPoolTable()
    {
        $table = $this->table('leaderboard_pool', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '排行榜奖金池表'
        ]);

        $table->addColumn('id', 'integer', [
            'identity' => true,
            'signed' => false,
            'comment' => '主键ID'
        ])
        ->addColumn('type', 'string', [
            'limit' => 20,
            'comment' => '排行榜类型：daily-日榜，weekly-周榜，monthly-月榜'
        ])
        ->addColumn('period', 'string', [
            'limit' => 20,
            'comment' => '统计周期：日榜格式Y-m-d，周榜格式Y-m-d，月榜格式Y-m'
        ])
        ->addColumn('total_amount', 'decimal', [
            'precision' => 15,
            'scale' => 2,
            'default' => 0,
            'comment' => '奖金池总金额'
        ])
        ->addColumn('status', 'integer', [
            'limit' => 1,
            'default' => 1,
            'comment' => '状态：1-进行中，2-已结算，3-已发放'
        ])
        ->addColumn('settle_time', 'integer', [
            'signed' => false,
            'default' => 0,
            'comment' => '结算时间'
        ])
        ->addColumn('create_time', 'integer', [
            'signed' => false,
            'comment' => '创建时间'
        ])
        ->addColumn('update_time', 'integer', [
            'signed' => false,
            'comment' => '更新时间'
        ])
        ->addIndex(['type', 'period'], [
            'unique' => true,
            'name' => 'idx_type_period'
        ])
        ->addIndex(['status'], [
            'name' => 'idx_status'
        ])
        ->create();
    }
} 