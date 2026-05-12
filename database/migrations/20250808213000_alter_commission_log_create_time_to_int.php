<?php

use think\migration\Migrator;
use Phinx\Db\Adapter\MysqlAdapter;

class AlterCommissionLogCreateTimeToInt extends Migrator
{
    public function change(): void
    {
        // 统一修正目标表为物理表名 slot_team_commission_log；若存在旧名则先重命名
        if (!$this->hasTable('slot_team_commission_log') && $this->hasTable('team_commission_log')) {
            $this->table('team_commission_log')->rename('slot_team_commission_log');
        }

        if ($this->hasTable('slot_team_commission_log')) {
            $table = $this->table('slot_team_commission_log');

            if ($table->hasColumn('create_time')) {
                $table->changeColumn('create_time', 'integer', [
                    'limit' => 11,
                    'null' => false,
                    'default' => 0,
                    'comment' => '创建时间(时间戳)'
                ]);
            }

            if (!$table->hasIndex(['create_time'])) {
                $table->addIndex(['create_time'], ['name' => 'idx_create_time']);
            }

            $table->update();
        }
    }
}

