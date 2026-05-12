<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateWithdrawOrdersStatusComment extends Migrator
{
    /**
     * 更新提现订单表状态字段注释
     */
    public function change()
    {
        $table = $this->table('withdraw_orders');
        $table->changeColumn('status', 'integer', [
            'comment' => '状态：0待审核 1审核通过 2已打款 3已驳回 4打款失败',
            'default' => 0,
            'null' => false,
            'limit' => 3
        ])->update();
    }
}
