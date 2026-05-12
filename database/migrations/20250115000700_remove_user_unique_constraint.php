<?php

use think\migration\Migrator;
use think\migration\db\Column;

class RemoveUserUniqueConstraint extends Migrator
{
    public function change(): void
    {
        // 移除 pdd_progress 表中 user_id 的唯一约束
        $table = $this->table('pdd_progress');
        
        if ($table->hasIndex('uk_user')) {
            $table->removeIndex('uk_user')->save();
        }
    }
}
