<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddWeeklyMonthlyRewardsToMemberLevelConfig extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 修改现有的会员等级配置表，添加周奖励和月奖励字段
        $table = $this->table('member_level_config');
        
        // 检查字段是否已存在，如果不存在才添加
        if (!$table->hasColumn('weekly_reward')) {
            $table->addColumn('weekly_reward', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => '0.00',
                'comment' => '周奖励金额',
                'after' => 'float'
            ]);
        }
        
        if (!$table->hasColumn('monthly_reward')) {
            $table->addColumn('monthly_reward', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => '0.00',
                'comment' => '月奖励金额',
                'after' => 'weekly_reward'
            ]);
        }
        
        $table->update();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        // 移除添加的字段
        $table = $this->table('member_level_config');
        
        if ($table->hasColumn('monthly_reward')) {
            $table->removeColumn('monthly_reward');
        }
        
        if ($table->hasColumn('weekly_reward')) {
            $table->removeColumn('weekly_reward');
        }
        
        $table->update();
    }
}