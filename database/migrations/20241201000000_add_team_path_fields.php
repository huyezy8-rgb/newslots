<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddTeamPathFields extends Migrator
{
    /**
     * 添加团队路径字段
     */
    public function change()
    {
        $table = $this->table('slot_account');
        
        // 添加团队路径字段
        $table->addColumn('team_path', 'string', [
            'limit' => 255,
            'default' => '',
            'comment' => '团队路径，格式如：0/1/3',
            'after' => 'p_id'
        ])
        ->addColumn('team_level', 'integer', [
            'limit' => 11,
            'default' => 0,
            'comment' => '团队层级，根节点为0',
            'after' => 'team_path'
        ])
        ->addIndex(['team_path'], ['name' => 'idx_team_path'])
        ->addIndex(['team_level'], ['name' => 'idx_team_level'])
        ->update();
    }
} 