<?php
use think\migration\Migrator;
use Phinx\Db\Adapter\MysqlAdapter;

class AddSeparatePoolRatios extends Migrator
{
    public function change()
    {
        // 为排行榜活动配置表添加三个独立的奖池比例字段，并移除bet_ratio字段
        $table = $this->table('ranking_activity');
        
        // 添加三个独立的奖池比例字段
        $table->addColumn('daily_pool_ratio', 'decimal', [
            'precision' => 5, 
            'scale' => 2, 
            'default' => 1.00, 
            'comment' => '日榜奖池比例'
        ])
        ->addColumn('weekly_pool_ratio', 'decimal', [
            'precision' => 5, 
            'scale' => 2, 
            'default' => 1.00, 
            'comment' => '周榜奖池比例'
        ])
        ->addColumn('monthly_pool_ratio', 'decimal', [
            'precision' => 5, 
            'scale' => 2, 
            'default' => 1.00, 
            'comment' => '月榜奖池比例'
        ])
        // 移除原有的bet_ratio字段
        ->removeColumn('bet_ratio')
        ->update();
    }
}