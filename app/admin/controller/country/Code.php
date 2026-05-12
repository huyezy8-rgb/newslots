<?php

namespace app\admin\controller\country;

use app\common\controller\Backend;

/**
 * 国家号码管理
 */
class Code extends Backend
{
    /**
     * Code模型对象
     * @var object
     * @phpstan-var \app\common\model\country\Code
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'update_time', 'create_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\country\Code();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}