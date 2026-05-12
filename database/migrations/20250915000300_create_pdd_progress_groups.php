<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePddProgressGroups extends Migrator
{
    public function change(): void
    {
        // 创建拼多多进度组表
        $table = $this->table('pdd_progress_groups', ['comment' => '拼多多进度组配置表']);
        
        if (!$table->exists()) {
            $table->addColumn('name', 'string', ['limit' => 100, 'comment' => '组名称'])
                ->addColumn('description', 'text', ['null' => true, 'comment' => '组描述'])
                ->addColumn('invite_reward_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.10', 'comment' => '邀请奖励金额'])
                ->addColumn('target_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '30.00', 'comment' => '目标金额'])
                ->addColumn('initial_reward', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '23.00', 'comment' => '首次进入奖励'])
                ->addColumn('status', 'tinyinteger', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
                ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
                ->addTimestamps()
                ->addIndex(['status'], ['name' => 'idx_status'])
                ->addIndex(['sort'], ['name' => 'idx_sort'])
                ->create();
        }
    }
}

