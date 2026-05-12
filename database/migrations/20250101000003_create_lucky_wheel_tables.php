<?php
use think\migration\Migrator;
use Phinx\Db\Adapter\MysqlAdapter;

class CreateLuckyWheelTables extends Migrator
{
    public function change()
    {
        // 创建幸运转盘主配置表
        $table = $this->table('lucky_wheel_config', [
            'id' => false,
            'primary_key' => 'id',
            'comment' => '幸运转盘主配置表',
        ]);
        
        $table->addColumn('id', 'integer', ['identity' => true])
            ->addColumn('title', 'string', ['limit' => 100, 'default' => '', 'comment' => '活动标题'])
            ->addColumn('banner_image', 'string', ['limit' => 255, 'default' => '', 'comment' => 'Banner图'])
            ->addColumn('bet_multiple', 'decimal', ['precision' => 5, 'scale' => 1, 'default' => 1.0, 'comment' => '打码倍数'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '活动状态：0-禁用，1-启用'])
            ->addColumn('createtime', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('updatetime', 'biginteger', ['null' => true, 'default' => null])
            ->create();

        // 创建转盘表（融合配置、奖项、规则）
        $table = $this->table('lucky_wheel_turntable', [
            'id' => false,
            'primary_key' => 'id',
            'comment' => '转盘表',
        ]);
        
        $table->addColumn('id', 'integer', ['identity' => true])
            ->addColumn('wheel_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '转盘名称'])
            ->addColumn('unlock_condition', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '解锁条件（充值金额）'])
            ->addColumn('free_times', 'integer', ['default' => 0, 'comment' => '赠送次数'])
            ->addColumn('prizes', 'text', ['null' => true, 'comment' => '奖项配置JSON'])
            ->addColumn('rules', 'text', ['null' => true, 'comment' => '规则配置JSON'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '转盘状态：0-禁用，1-启用'])
            ->addColumn('createtime', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('updatetime', 'biginteger', ['null' => true, 'default' => null])
            ->create();

        // 创建转盘记录表
        $table = $this->table('lucky_wheel_logs', [
            'id' => false,
            'primary_key' => 'id',
            'comment' => '转盘记录表',
        ]);
        
        $table->addColumn('id', 'integer', ['identity' => true])
            ->addColumn('user_id', 'integer', ['default' => 0, 'comment' => '用户ID'])
            ->addColumn('wheel_id', 'integer', ['default' => 0, 'comment' => '转盘ID'])
            ->addColumn('prize_title', 'string', ['limit' => 100, 'default' => '', 'comment' => '中奖奖项标题'])
            ->addColumn('prize_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '中奖金额'])
            ->addColumn('status', 'integer', ['default' => 0, 'comment' => '状态：0-未发放，1-已发放'])
            ->addColumn('createtime', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('updatetime', 'biginteger', ['null' => true, 'default' => null])
            ->create();
    }
} 