<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * Banner图管理管理
 */
class Banner extends Backend
{
    /**
     * Banner模型对象
     * @var object
     * @phpstan-var \app\common\model\Banner
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\Banner();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}