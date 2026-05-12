<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CleanupPddProgressFields extends Migrator
{
    public function change(): void
    {
        $table = $this->table('pdd_progress');
        
        // 删除不需要的字段
        if ($table->hasColumn('valid_invite_count')) {
            $table->removeColumn('valid_invite_count');
        }
        if ($table->hasColumn('draw_times')) {
            $table->removeColumn('draw_times');
        }
        if ($table->hasColumn('first_draw_done')) {
            $table->removeColumn('first_draw_done');
        }
        if ($table->hasColumn('direct_cash_state')) {
            $table->removeColumn('direct_cash_state');
        }
        if ($table->hasColumn('version')) {
            $table->removeColumn('version');
        }
        if ($table->hasColumn('current_progress')) {
            $table->removeColumn('current_progress');
        }
        if ($table->hasColumn('target_progress')) {
            $table->removeColumn('target_progress');
        }
        if ($table->hasColumn('remaining_amount')) {
            $table->removeColumn('remaining_amount');
        }
        if ($table->hasColumn('invite_reward_amount')) {
            $table->removeColumn('invite_reward_amount');
        }
        if ($table->hasColumn('unlock_condition_met')) {
            $table->removeColumn('unlock_condition_met');
        }
        if ($table->hasColumn('total_invite_count')) {
            $table->removeColumn('total_invite_count');
        }
        if ($table->hasColumn('unlock_achieved')) {
            $table->removeColumn('unlock_achieved');
        }
        if ($table->hasColumn('target_amount')) {
            $table->removeColumn('target_amount');
        }
        if ($table->hasColumn('withdrawal_threshold')) {
            $table->removeColumn('withdrawal_threshold');
        }
        if ($table->hasColumn('invite_reward_per_person')) {
            $table->removeColumn('invite_reward_per_person');
        }
        if ($table->hasColumn('recharge_threshold')) {
            $table->removeColumn('recharge_threshold');
        }
        
        $table->update();
    }
}

