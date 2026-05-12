<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdatePddProgressAndLogs extends Migrator
{
    public function change(): void
    {
        // 1) 进度表：精简为最小字段集（若存在则补齐字段，不强制删除旧字段）
        $tableName = 'pdd_progress';
        if ($this->hasTable($tableName)) {
            $table = $this->table($tableName);

            if (!$table->hasColumn('group_id')) {
                $table->addColumn('group_id', 'integer', ['default' => 0, 'comment' => '进度条组ID']);
            }
            if (!$table->hasColumn('status')) {
                $table->addColumn('status', 'integer', ['default' => 0, 'comment' => '状态：0不可领取 1可领取 2已领取']);
            }
            if (!$table->hasColumn('invite_reward')) {
                $table->addColumn('invite_reward', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '邀请奖励金额（累计）']);
            }
            if (!$table->hasColumn('create_time')) {
                $table->addColumn('create_time', 'integer', ['default' => 0]);
            }
            if (!$table->hasColumn('update_time')) {
                $table->addColumn('update_time', 'integer', ['default' => 0]);
            }

            if (!$table->hasIndex(['user_id', 'status'])) {
                $table->addIndex(['user_id', 'status'], ['name' => 'idx_user_status']);
            }
            if (!$table->hasIndex(['user_id', 'group_id'])) {
                $table->addIndex(['user_id', 'group_id'], ['name' => 'idx_user_group']);
            }

            $table->update();
        } else {
            $this->table($tableName, ['comment' => '拼多多进度条表'])
                ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
                ->addColumn('group_id', 'integer', ['default' => 0, 'comment' => '进度条组ID'])
                ->addColumn('status', 'integer', ['default' => 0, 'comment' => '状态：0不可领取 1可领取 2已领取'])
                ->addColumn('invite_reward', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '邀请奖励金额（累计）'])
                ->addColumn('create_time', 'integer', ['default' => 0])
                ->addColumn('update_time', 'integer', ['default' => 0])
                ->addIndex(['user_id', 'status'], ['name' => 'idx_user_status'])
                ->addIndex(['user_id', 'group_id'], ['name' => 'idx_user_group'])
                ->create();
        }

        // 2) 邀请日志表：若不存在则创建
        $logTable = 'pdd_invite_log';
        if (!$this->hasTable($logTable)) {
            $this->table($logTable, ['comment' => '拼多多邀请奖励日志'])
                ->addColumn('inviter_user_id', 'integer', ['comment' => '邀请人'])
                ->addColumn('invite_user_id', 'integer', ['comment' => '被邀请人'])
                ->addColumn('pdd_progress_id', 'integer', ['comment' => '进度ID'])
                ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '奖励金额'])
                ->addColumn('create_time', 'integer', ['default' => 0])
                ->addIndex(['inviter_user_id'], ['name' => 'idx_inviter'])
                ->addIndex(['invite_user_id'], ['name' => 'idx_invitee'])
                ->create();
        }
    }
}


