<?php

namespace app\admin\controller\member\level;

use app\common\controller\Backend;

/**
 * 会员等级配置管理
 */
class Config extends Backend
{
    /**
     * Config模型对象
     * @var object
     * @phpstan-var \app\common\model\member\level\Config
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\member\level\Config();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}