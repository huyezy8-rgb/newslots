<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSlotApiLogTable extends Migrator
{
    public function change()
    {
        $table = $this->table('api_log', [
            'id'          => false, // 关闭默认的id
            'primary_key' => 'id',
            'comment'     => '接口日志'
        ]);
        $table
            ->addColumn('id', 'integer', [
                'identity' => true, // 自增
                'signed'   => false,
                'null'     => false
            ])
            ->addColumn('uri', 'string', [
                'limit'   => 255,
                'default' => '',
                'comment' => '请求地址'
            ])
            ->addColumn('method', 'string', [
                'limit'   => 10,
                'default' => '',
                'comment' => '请求方法'
            ])
            ->addColumn('ip', 'string', [
                'limit'   => 45,
                'default' => '',
                'comment' => '请求IP'
            ])
            ->addColumn('user_agent', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'comment' => 'User-Agent'
            ])
            ->addColumn('params', 'text', [
                'null'    => true,
                'comment' => '请求参数'
            ])
            ->addColumn('response', 'text', [
                'null'    => true,
                'comment' => '响应内容'
            ])
            ->addColumn('header', 'text', [
                'null'    => true,
                'comment' => '请求Header'
            ])
            ->addColumn('status', 'integer', [
                'default' => 200,
                'comment' => '接口返回状态码'
            ])
            ->addColumn('cost_ms', 'integer', [
                'default' => 0,
                'comment' => '耗时(毫秒)'
            ])
            ->addColumn('create_time', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => '请求时间'
            ])
            ->create();
    }
} 