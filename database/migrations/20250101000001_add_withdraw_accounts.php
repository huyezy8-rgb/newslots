<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddWithdrawAccounts extends Migrator
{
    /**
     * 添加提现账号管理相关字段和表
     */
    public function change()
    {
        // 为支付方式表添加字段配置
        $this->table('payment_methods')
            ->addColumn('field_config', 'text', ['null' => true, 'comment' => '字段配置JSON'])
            ->addColumn('validation_rules', 'text', ['null' => true, 'comment' => '验证规则JSON'])
            ->update();

        // 创建提现账号表
        $this->table('withdraw_accounts', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'comment' => '主键ID'])
            ->addColumn('user_id', 'integer', ['signed' => false, 'comment' => '用户ID'])
            ->addColumn('payment_method_id', 'integer', ['signed' => false, 'comment' => '支付方式ID'])
            ->addColumn('unique_tag', 'string', ['limit' => 255, 'comment' => '支付方式唯一标识'])
            ->addColumn('account_name', 'string', ['limit' => 100, 'comment' => '用户自定义账号名称'])
            ->addColumn('is_default', 'boolean', ['default' => 0, 'comment' => '是否默认账号'])
            ->addColumn('account_info', 'text', ['comment' => '账号详细信息JSON'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态：1=启用，0=禁用'])
            ->addColumn('create_time', 'integer', ['comment' => '创建时间戳'])
            ->addColumn('update_time', 'integer', ['comment' => '更新时间戳'])
            ->addIndex(['user_id'], ['name' => 'idx_user_id'])
            ->addIndex(['payment_method_id'], ['name' => 'idx_payment_method_id'])
            ->addIndex(['unique_tag'], ['name' => 'idx_unique_tag'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->addIndex(['user_id', 'unique_tag'], ['name' => 'idx_user_payment'])
            ->addForeignKey('user_id', 'account', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('payment_method_id', 'payment_methods', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}

