<?php

use think\migration\Migrator;
use think\migration\db\Column;

class RewritePddModule extends Migrator
{
    public function change()
    {
        // 1) slot_account 扩展（pdd_invite_counted 不需要）
        $account = $this->table('account');
        if (!$account->hasColumn('pdd_reward')) {
            $opts = [
                'precision' => 20,
                'scale' => 2,
                'default' => '0.00',
                'comment' => 'PDD累计奖励（抽奖所得）',
            ];
            // 若存在 commission_balance，则放在其后；否则附加到表末尾，避免依赖顺序
            if ($account->hasColumn('commission_balance')) {
                $opts['after'] = 'commission_balance';
            }
            $account->addColumn('pdd_reward', 'decimal', $opts)->save();
        }

        // 2) slot_pdd_progress（用户唯一）——完全删除原表并重建
        if ($this->hasTable('slot_pdd_progress')) {
            $this->table('slot_pdd_progress')->drop()->save();
        }
        if ($this->hasTable('pdd_progress')) {
            $this->table('pdd_progress')->drop()->save();
        }

        $this->table('pdd_progress')
                ->addColumn('user_id', 'integer', ['null' => false, 'comment' => '用户ID（唯一）'])
                ->addColumn('valid_invite_count', 'integer', ['default' => 0, 'comment' => '有效邀请数'])
                ->addColumn('draw_times', 'integer', ['default' => 0, 'comment' => '可用抽奖次数'])
                ->addColumn('first_draw_done', 'boolean', ['default' => 0, 'comment' => '是否已首抽'])
                ->addColumn('direct_cash_state', 'integer', ['default' => 0, 'comment' => 'direct_cash一次性机会：0无 1待使用 2已消费'])
                ->addColumn('version', 'integer', ['default' => 0, 'comment' => '乐观锁版本号'])
                ->addColumn('create_time', 'integer', ['null' => true])
                ->addColumn('update_time', 'integer', ['null' => true])
                ->addIndex(['user_id'], ['unique' => true, 'name' => 'uk_user'])
                ->addIndex(['draw_times'], ['name' => 'idx_draw_times'])
            ->create();

        // 3) slot_pdd_draw_log
        //删除错误的表
        if ($this->hasTable('slot_pdd_draw_log')) {
            $this->table('slot_pdd_draw_log')->drop()->save();
        }

        if (!$this->hasTable('pdd_draw_log')) {
            $this->table('pdd_draw_log')
                ->addColumn('user_id', 'integer', ['null' => false])
                ->addColumn('pdd_progress_id', 'integer', ['null' => false])
                ->addColumn('draw_type', 'string', ['limit' => 32, 'null' => false, 'comment' => 'first(首抽)|direct_cash(直接提现补齐)'])
                ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 2, 'default' => '0.00', 'comment' => '抽中/补足金额'])
                ->addColumn('meta', 'json', ['null' => true])
                ->addColumn('create_time', 'integer', ['null' => true])
                ->addIndex(['user_id'], ['name' => 'idx_user'])
                ->addIndex(['pdd_progress_id'], ['name' => 'idx_progress'])
                ->addIndex(['draw_type', 'create_time'], ['name' => 'idx_type_time'])
                ->create();
        }
    }
}

