<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddChannelToAdmin extends Migrator
{
    /**
     * 添加渠道字段到管理员表
     */
    public function change()
    {
        $table = $this->table('admin');
        
        // 添加渠道ID字段
        $table->addColumn('channel_id', 'integer', [
            'null' => true,
            'default' => null,
            'comment' => '绑定的渠道ID，null表示超级管理员',
            'after' => 'status'
        ])
        ->addIndex(['channel_id'], ['name' => 'idx_channel_id'])
        ->update();
    }
} 