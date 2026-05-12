<?php

use think\migration\Migrator;
use think\migration\db\Column;

class RenameFloatToUpgradeReward extends Migrator
{
    /**
     * 将 slot_member_level_config 表的 float 字段重命名为 upgrade_reward
     */
    public function change()
    {
        $table = $this->table('member_level_config');
        
        // 重命名字段
        $table->renameColumn('float', 'upgrade_reward')
              ->update();
    }
}
