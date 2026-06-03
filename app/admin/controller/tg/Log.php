<?php

namespace app\admin\controller\tg;

use app\common\controller\Backend;

class Log extends Backend
{
    protected object $model;

    protected array|string $preExcludeFields = [];

    protected array $withJoinTable = ['bot'];

    protected string|array $quickSearchField = ['id', 'code', 'chat_id', 'message_id', 'template_name'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\tg\Log();
    }

    public function add(): void
    {
        $this->error(__('You have no permission'));
    }

    public function edit(): void
    {
        $this->error(__('You have no permission'));
    }

    public function del(): void
    {
        $this->error(__('You have no permission'));
    }
}
