<?php

use think\migration\Migrator;
use think\migration\db\Column;
use Phinx\Db\Adapter\MysqlAdapter;

class CreatePaymentTables extends Migrator
{
    public function change()
    {
        $this->createPaymentChannels();
        $this->createPaymentMethods();
    }

    /**
     * 创建支付渠道表
     */
    protected function createPaymentChannels()
    {
        $table = $this->table('payment_channels', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '支付渠道表',
        ]);
        
        $table
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed' => false,
                'null' => false,
                'comment' => '主键ID',
            ])
            ->addColumn('name', 'string', [
                'limit' => 100,
                'default' => '',
                'null' => false,
                'comment' => '渠道名称',
            ])
            ->addColumn('code', 'string', [
                'limit' => 50,
                'default' => '',
                'null' => false,
                'comment' => '渠道代码',
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'comment' => '渠道描述',
            ])
            ->addColumn('config', 'text', [
                'null' => true,
                'comment' => '渠道配置(JSON格式)',
            ])
            ->addColumn('status', 'enum', [
                'values' => '0,1',
                'default' => '1',
                'null' => false,
                'comment' => '状态：0=禁用，1=启用',
            ])
            ->addColumn('remark', 'text', [
                'null' => true,
                'comment' => '备注',
            ])
            ->addColumn('create_time', 'integer', [
                'default' => 0,
                'null' => false,
                'comment' => '创建时间',
            ])
            ->addColumn('update_time', 'integer', [
                'default' => 0,
                'null' => false,
                'comment' => '更新时间',
            ])
            ->addIndex(['code'], [
                'unique' => true,
                'name' => 'idx_code'
            ])
            ->addIndex(['status'], [
                'name' => 'idx_status'
            ])
            ->create();
    }

    /**
     * 创建支付方式表
     */
    protected function createPaymentMethods()
    {
        $table = $this->table('payment_methods', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '支付方式表',
        ]);
        
        $table
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed' => false,
                'null' => false,
                'comment' => '主键ID',
            ])
            ->addColumn('channel_code', 'string', [
                'limit' => 50,
                'default' => '',
                'null' => false,
                'comment' => '支付渠道代码',
            ])
            ->addColumn('name', 'string', [
                'limit' => 100,
                'default' => '',
                'null' => false,
                'comment' => '支付方式名称',
            ])
            ->addColumn('code', 'string', [
                'limit' => 50,
                'default' => '',
                'null' => false,
                'comment' => '支付方式代码',
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'comment' => '支付方式描述',
            ])
            ->addColumn('icon', 'string', [
                'limit' => 255,
                'default' => '',
                'null' => false,
                'comment' => '支付方式图标',
            ])
            ->addColumn('status', 'enum', [
                'values' => '0,1',
                'default' => '1',
                'null' => false,
                'comment' => '状态：0=禁用，1=启用',
            ])
            ->addColumn('remark', 'text', [
                'null' => true,
                'comment' => '备注',
            ])
            ->addColumn('create_time', 'integer', [
                'default' => 0,
                'null' => false,
                'comment' => '创建时间',
            ])
            ->addColumn('update_time', 'integer', [
                'default' => 0,
                'null' => false,
                'comment' => '更新时间',
            ])
            ->addIndex(['channel_code'], [
                'name' => 'idx_channel_code'
            ])
            ->addIndex(['code'], [
                'unique' => true,
                'name' => 'idx_code'
            ])
            ->addIndex(['status'], [
                'name' => 'idx_status'
            ])
            ->create();
    }
} 