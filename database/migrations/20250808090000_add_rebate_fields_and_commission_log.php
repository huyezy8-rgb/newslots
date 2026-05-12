<?php

use think\migration\Migrator;
use Phinx\Db\Adapter\MysqlAdapter;

class AddRebateFieldsAndCommissionLog extends Migrator
{
    public function change(): void
    {
        $this->updateAccountTable();
        $this->createCommissionLogTable();
    }

    private function updateAccountTable(): void
    {
        if ($this->hasTable('slot_account')) {
            $table = $this->table('slot_account');

            if (!$table->hasColumn('rebate_rate')) {
                $table->addColumn('rebate_rate', 'decimal', [
                    'precision' => 5,
                    'scale' => 2,
                    'default' => '0.00',
                    'null' => false,
                    'comment' => '返佣点位(百分数，50=50%)',
                    'after' => 'team_path',
                ]);
            }

            if (!$table->hasColumn('commission_balance')) {
                $table->addColumn('commission_balance', 'decimal', [
                    'precision' => 20,
                    'scale' => 6,
                    'default' => '0.000000',
                    'null' => false,
                    'comment' => '佣金余额',
                ]);
            }

            if (!$table->hasIndex(['p_id'])) {
                $table->addIndex(['p_id'], [
                    'name' => 'idx_p_id',
                    'type' => MysqlAdapter::INDEX,
                ]);
            }

            if (!$table->hasIndex(['team_path'])) {
                // 直接添加普通索引（如需前缀索引可手写SQL）
                $table->addIndex(['team_path'], [
                    'name' => 'idx_team_path',
                    'type' => MysqlAdapter::INDEX,
                ]);
            }

            $table->update();
        }
    }

    private function createCommissionLogTable(): void
    {
        if (!$this->hasTable('slot_team_commission_log')) {
            $table = $this->table('slot_team_commission_log', [
                'id' => false,
                'primary_key' => 'id',
                'comment' => '团队返佣明细日志',
                'row_format' => 'DYNAMIC',
                'collation' => 'utf8mb4_unicode_ci',
            ]);

            $table->addColumn('id', 'biginteger', [
                    'identity' => true,
                    'signed' => false,
                    'null' => false,
                ])
                ->addColumn('user_id', 'biginteger', [
                    'null' => false,
                    'comment' => '获得佣金的用户ID',
                    'signed' => false,
                ])
                ->addColumn('source_user_id', 'biginteger', [
                    'null' => false,
                    'comment' => '下级投注的用户ID',
                    'signed' => false,
                ])
                ->addColumn('channel_id', 'integer', [
                    'null' => false,
                    'default' => 0,
                    'comment' => '渠道ID',
                    'signed' => false,
                ])
                ->addColumn('bet_amount', 'decimal', [
                    'precision' => 20,
                    'scale' => 6,
                    'null' => false,
                    'comment' => '投注金额',
                ])
                ->addColumn('base_rate', 'decimal', [
                    'precision' => 5,
                    'scale' => 2,
                    'null' => false,
                    'comment' => '基础返佣比例(百分数，如0.5=0.5%)',
                ])
                ->addColumn('point_diff', 'decimal', [
                    'precision' => 5,
                    'scale' => 2,
                    'null' => false,
                    'comment' => '点位差(百分数，如30=30%)',
                ])
                ->addColumn('commission', 'decimal', [
                    'precision' => 20,
                    'scale' => 6,
                    'null' => false,
                    'comment' => '佣金金额',
                ])
                ->addColumn('level', 'integer', [
                    'null' => false,
                    'comment' => '距投注用户的层级(从1开始)',
                    'signed' => false,
                ])
                ->addColumn('create_time', 'datetime', [
                    'null' => false,
                ])
                ->addIndex(['user_id'], ['name' => 'idx_user_id'])
                ->addIndex(['source_user_id'], ['name' => 'idx_source_user_id'])
                ->addIndex(['channel_id'], ['name' => 'idx_channel_id'])
                ->create();
        }
    }
}

