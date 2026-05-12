<?php
use think\migration\Migrator;
use Phinx\Db\Adapter\MysqlAdapter;

class CreateRankingTables extends Migrator
{
    public function change()
    {
        // 排行榜活动配置表
        $table = $this->table('ranking_activity', [
            'id' => false,
            'primary_key' => 'id',
            'comment' => '排行榜活动配置表',
        ]);
        $table->addColumn('id', 'integer', ['identity' => true])
            ->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '活动名称'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态：0禁用，1启用'])
            ->addColumn('bet_ratio', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 1.00, 'comment' => '打码入池比例'])
            ->addColumn('day_limit', 'integer', ['default' => 100, 'comment' => '日榜人数限制'])
            ->addColumn('week_limit', 'integer', ['default' => 100, 'comment' => '周榜人数限制'])
            ->addColumn('month_limit', 'integer', ['default' => 100, 'comment' => '月榜人数限制'])
            ->addColumn('day_rewards', 'json', ['null' => true, 'comment' => '日榜奖励配置JSON'])
            ->addColumn('week_rewards', 'json', ['null' => true, 'comment' => '周榜奖励配置JSON'])
            ->addColumn('month_rewards', 'json', ['null' => true, 'comment' => '月榜奖励配置JSON'])
            ->addColumn('createtime', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('updatetime', 'biginteger', ['null' => true, 'default' => null])
            ->create();
    }
} 