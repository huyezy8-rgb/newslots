<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddPasswordToAccount extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        $table = $this->table('account');
        
        // 添加密码字段
        $table->addColumn('password', 'string', [
            'limit' => 255,
            'null' => true,
            'comment' => '用户密码',
            'after' => 'mobile'
        ])
        ->update();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        $table = $this->table('account');
        
        // 删除密码字段
        $table->removeColumn('password')
              ->update();
    }
}
