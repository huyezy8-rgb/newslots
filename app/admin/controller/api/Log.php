<?php

namespace app\admin\controller\api;

use app\common\controller\Backend;

/**
 * 接口日志
 */
class Log extends Backend
{
    /**
     * Log模型对象
     * @var object
     * @phpstan-var \app\common\model\api\Log
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\api\Log();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}