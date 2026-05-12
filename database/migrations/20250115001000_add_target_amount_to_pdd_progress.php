<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddTargetAmountToPddProgress extends Migrator
{
    public function change(): void
    {
        // 使用不带前缀表名，由框架自动加前缀
        $table = $this->table('pdd_progress');

        if (!$table->hasColumn('target_amount')) {
            $table->addColumn('target_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => '30.00',
                'comment' => '本轮目标金额（从设置读取后写入）',
                'after' => 'invite_reward',
            ])->save();
        }

        // 为已有记录填充默认值
        $this->execute("UPDATE `" . $this->getAdapter()->getOption('table_prefix') . "pdd_progress` SET `target_amount` = IFNULL(`target_amount`, 30.00)");
    }
}
