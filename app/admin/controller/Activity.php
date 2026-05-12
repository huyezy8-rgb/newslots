<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 活动控制总管理
 */
class Activity extends Backend
{
    /**
     * Activity模型对象
     * @var object
     * @phpstan-var \app\common\model\Activity
     */
    protected object $model;

    protected string|array $defaultSortField = 'sort,desc';

    protected array|string $preExcludeFields = ['id', 'config', 'create_time', 'update_time'];

    protected string $weighField = 'sort';

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\Activity();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}