<?php

use think\migration\Migrator;
use think\migration\db\Column;

class RedesignPddProgressGroups extends Migrator
{
    public function change(): void
    {
        // 重新设计拼多多进度组表
        $table = $this->table('pdd_progress_groups', ['comment' => '拼多多进度组表 - 记录本次进度邀请的用户和达标状态']);
        
        if ($table->exists()) {
            $table->drop()->save();
        }
        
        $table->addColumn('user_id', 'integer', ['comment' => '进度组所属用户ID'])
            ->addColumn('progress_id', 'integer', ['comment' => '关联的进度记录ID'])
            ->addColumn('invited_users', 'text', ['comment' => '本次进度邀请的用户ID列表（JSON格式）'])
            ->addColumn('qualified_users', 'text', ['comment' => '已达标（充值50元）的用户ID列表（JSON格式）'])
            ->addColumn('has_qualified_user', 'tinyinteger', ['default' => 0, 'comment' => '是否有达标的被邀请人：0无 1有'])
            ->addColumn('remaining_reward', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '剩余奖励金额（当有达标用户时发放）'])
            ->addColumn('status', 'tinyinteger', ['default' => 0, 'comment' => '状态：0进行中 1已完成 2已领取'])
            ->addTimestamps()
            ->addIndex(['user_id'], ['name' => 'idx_user'])
            ->addIndex(['progress_id'], ['name' => 'idx_progress'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->create();
    }
}

