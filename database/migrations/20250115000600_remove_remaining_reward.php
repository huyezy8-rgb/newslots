<?php

use think\migration\Migrator;
use think\migration\db\Column;

class RemoveRemainingReward extends Migrator
{
    public function change(): void
    {
        // 移除 pdd_progress_groups 表中不必要的 remaining_reward 字段
        $table = $this->table('pdd_progress_groups');
        
        if ($table->hasColumn('remaining_reward')) {
            $table->removeColumn('remaining_reward')->save();
        }
    }
}
