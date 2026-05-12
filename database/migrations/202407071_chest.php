<?php
use think\migration\Migrator;
use Phinx\Db\Adapter\MysqlAdapter;
class Chest extends Migrator
{
    public function change()
    {
        // 宝箱表
        $table = $this->table('chest', [
            'id' => false,
            'primary_key' => 'id',
            'comment' => '宝箱活动表',
        ]);
        $table->addColumn('id', 'integer', ['identity' => true])
            ->addColumn('name', 'string', ['limit' => 50, 'default' => '', 'comment' => '宝箱名称'])
            ->addColumn('recharge_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '充值条件'])
            ->addColumn('invite_count', 'integer', ['default' => 0, 'comment' => '邀请有效用户数'])
            ->addColumn('reward_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '奖励金额'])
            ->addColumn('default_image', 'string', ['limit' => 255, 'default' => '', 'comment' => '默认图片'])
            ->addColumn('waiting_image', 'string', ['limit' => 255, 'default' => '', 'comment' => '待领取图片'])
            ->addColumn('received_image', 'string', ['limit' => 255, 'default' => '', 'comment' => '已领取图片'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('createtime', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('updatetime', 'biginteger', ['null' => true, 'default' => null])
            ->create();
        // 领取记录表
        $table = $this->table('chest_receive_log', [
            'id' => false,
            'primary_key' => 'id',
            'comment' => '宝箱领取记录表',
        ]);
        $table->addColumn('id', 'integer', ['identity' => true])
            ->addColumn('user_id', 'integer', ['default' => 0, 'comment' => '用户ID'])
            ->addColumn('chest_id', 'integer', ['default' => 0, 'comment' => '宝箱ID'])
            ->addColumn('createtime', 'biginteger', ['null' => true, 'default' => null])
            ->addIndex(['user_id', 'chest_id'], ['unique' => true])
            ->create();
    }
} 