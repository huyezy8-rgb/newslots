<?php
use think\migration\Migrator;
use Phinx\Db\Adapter\MysqlAdapter;

class CreateChestConfigTable extends Migrator
{
    public function change()
    {
        // 创建宝箱活动配置表
        $table = $this->table('chest_config', [
            'id' => false,
            'primary_key' => 'id',
            'comment' => '宝箱活动配置表',
        ]);
        
        $table->addColumn('id', 'integer', ['identity' => true])
            ->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '活动名称'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '活动状态：0-禁用，1-启用'])
            ->addColumn('bet_multiple', 'decimal', ['precision' => 5, 'scale' => 1, 'default' => 0, 'comment' => '打码倍数'])
            ->addColumn('banner_image', 'string', ['limit' => 255, 'default' => '', 'comment' => 'Banner图'])
            ->addColumn('createtime', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('updatetime', 'biginteger', ['null' => true, 'default' => null])
            ->create();
    }
} 