<?php

namespace app\admin\controller\facebook\events;

use app\common\controller\Backend;

/**
 * Facebook事件日志管理
 */
class Log extends Backend
{
    /**
     * Log模型对象
     * @var object
     * @phpstan-var \app\admin\model\facebook\events\Log
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id', 'user_id', 'channel_id', 'event_type'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\facebook\events\Log();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}