<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateBannerTable extends Migrator
{
    public function change()
    {
        $table = $this->table('banner', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Banner图管理表',
        ]);
        $table
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed' => false,
                'null' => false,
                'comment' => '主键ID',
            ])
            ->addColumn('title', 'string', [
                'limit' => 255,
                'default' => '',
                'null' => false,
                'comment' => 'Banner标题',
            ])
            ->addColumn('content', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => '内容',
            ])
            ->addColumn('image', 'string', [
                'limit' => 255,
                'default' => '',
                'null' => false,
                'comment' => 'Banner图片路径',
            ])
            ->addColumn('link', 'string', [
                'limit' => 255,
                'default' => '',
                'null' => false,
                'comment' => '跳转链接',
            ])
            ->addColumn('jump_type', 'integer', [
                'limit' => 1,
                'default' => 0,
                'null' => false,
                'comment' => '跳转类型：0=活动, 1=外部链接',
            ])
            ->addColumn('activity', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => '活动标识',
            ])
            ->addColumn('sort', 'integer', [
                'default' => 0,
                'null' => false,
                'comment' => '排序权重',
            ])
            ->addColumn('status', 'integer', [
                'limit' => 1,
                'default' => 1,
                'null' => false,
                'comment' => '状态：0=禁用，1=启用',
            ])
            ->addColumn('start_time', 'integer', [
                'null' => true,
                'comment' => '开始时间',
            ])
            ->addColumn('end_time', 'integer', [
                'null' => true,
                'comment' => '结束时间',
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
            ->create();
    }
}
