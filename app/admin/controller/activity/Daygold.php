<?php

namespace app\admin\controller\activity;

use app\common\controller\Backend;

/**
 * 每日奖励活动配置
 */
class Daygold extends Backend
{
    /**
     * Daygold模型对象
     * @var object
     * @phpstan-var \app\common\model\activity\Daygold
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\activity\Daygold();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}