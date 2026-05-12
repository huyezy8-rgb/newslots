<?php

use think\migration\Migrator;

class DropStatusFromPddProgressGroups extends Migrator
{
    public function change(): void
    {
        // 使用不带前缀的表名，由框架自动加表前缀
        $table = $this->table('pdd_progress_groups');
        if ($table->hasColumn('status')) {
            $table->removeColumn('status')->save();
        }
    }
}
