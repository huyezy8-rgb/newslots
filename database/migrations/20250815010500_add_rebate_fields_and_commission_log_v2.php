<?php

use think\migration\Migrator;

class AddRebateFieldsAndCommissionLogV2 extends Migrator
{
    public function change()
    {
        // 1) account 表增加返佣相关字段（若不存在）
        $account = $this->table('account');
        if (!$account->hasColumn('rebate_rate')) {
            $account->addColumn('rebate_rate', 'decimal', [
                'precision' => 5,
                'scale' => 2,
                'default' => '0.00',
                'comment' => '返佣点位（百分比，例：50 表示 50%）',
                'after' => 'channel_id',
            ])->save();
        }
        if (!$account->hasColumn('commission_balance')) {
            $account->addColumn('commission_balance', 'decimal', [
                'precision' => 20,
                'scale' => 6,
                'default' => '0.000000',
                'comment' => '返佣账户余额',
                'after' => 'rebate_rate',
            ])->save();
        }

        // 2) 团队返佣日志表：以物理表名 slot_team_commission_log 为准进行修正
        if (!$this->hasTable('slot_team_commission_log')) {
            // 若存在无前缀的旧表名，则重命名为带前缀的规范表名
            if ($this->hasTable('team_commission_log')) {
                $this->table('team_commission_log')->rename('slot_team_commission_log');
            } else {
                $this->table('slot_team_commission_log')
                ->addColumn('user_id', 'biginteger', ['null' => false, 'comment' => '获得佣金的用户ID'])
                ->addColumn('channel_id', 'integer', ['default' => 0, 'comment' => '渠道ID'])
                ->addColumn('source_user_id', 'biginteger', ['null' => false, 'comment' => '触发佣金的下级用户ID'])
                ->addColumn('bet_amount', 'decimal', ['precision' => 20, 'scale' => 6, 'default' => '0.000000', 'comment' => '投注金额'])
                ->addColumn('base_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '基础返佣比例（百分比）'])
                ->addColumn('point_diff', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '点位差（百分比）'])
                ->addColumn('commission', 'decimal', ['precision' => 20, 'scale' => 6, 'default' => '0.000000', 'comment' => '佣金金额'])
                ->addColumn('level', 'integer', ['null' => false, 'comment' => '距投注用户的层级'])
                ->addColumn('create_time', 'integer', ['null' => false, 'comment' => '创建时间（Unix时间戳）'])
                ->addIndex(['user_id'], ['name' => 'idx_user_id'])
                ->addIndex(['source_user_id'], ['name' => 'idx_source_user_id'])
                ->addIndex(['create_time'], ['name' => 'idx_create_time'])
                ->create();
            }
        } else {
            $table = $this->table('slot_team_commission_log');
            // 补充缺失列
            if (!$table->hasColumn('channel_id')) {
                $table->addColumn('channel_id', 'integer', ['default' => 0, 'comment' => '渠道ID']);
            }
            if (!$table->hasColumn('user_id')) {
                $table->addColumn('user_id', 'biginteger', ['null' => false, 'comment' => '获得佣金的用户ID']);
            }
            if (!$table->hasColumn('source_user_id')) {
                $table->addColumn('source_user_id', 'biginteger', ['null' => false, 'comment' => '触发佣金的下级用户ID']);
            }
            if (!$table->hasColumn('bet_amount')) {
                $table->addColumn('bet_amount', 'decimal', ['precision' => 20, 'scale' => 6, 'default' => '0.000000', 'comment' => '投注金额']);
            }
            if (!$table->hasColumn('base_rate')) {
                $table->addColumn('base_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '基础返佣比例（百分比）']);
            }
            if (!$table->hasColumn('point_diff')) {
                $table->addColumn('point_diff', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00', 'comment' => '点位差（百分比）']);
            }
            if (!$table->hasColumn('commission')) {
                $table->addColumn('commission', 'decimal', ['precision' => 20, 'scale' => 6, 'default' => '0.000000', 'comment' => '佣金金额']);
            }
            if (!$table->hasColumn('level')) {
                $table->addColumn('level', 'integer', ['null' => false, 'comment' => '距投注用户的层级']);
            }
            if (!$table->hasColumn('create_time')) {
                $table->addColumn('create_time', 'integer', ['null' => false, 'comment' => '创建时间（Unix时间戳）']);
            } else {
                // 统一 create_time 为 int
                $table->changeColumn('create_time', 'integer', ['null' => false, 'comment' => '创建时间（Unix时间戳）']);
            }
            $table->save();
        }
    }
}

