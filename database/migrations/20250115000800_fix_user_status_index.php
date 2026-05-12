<?php

use think\migration\Migrator;
use think\migration\db\Column;

class FixUserStatusIndex extends Migrator
{
    public function change(): void
    {
        // 使用不带前缀的表名，由框架自动加表前缀
        $table = $this->table('pdd_progress');

        // 若已存在 idx_user_status 索引，则不再重复创建（避免 1061 重复键名错误）
        if (!$table->hasIndex('idx_user_status')) {
            $table->addIndex(['user_id', 'status'], ['name' => 'idx_user_status'])->save();
        }
    }
}
