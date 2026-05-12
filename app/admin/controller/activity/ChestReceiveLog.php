<?php

namespace app\admin\controller\activity;

use app\common\controller\Backend;
use app\common\model\ChestReceiveLog as ChestReceiveLogModel;

class ChestReceiveLog extends Backend
{
    /**
     * 模型类实例
     * @var object
     */
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ChestReceiveLogModel();
    }
}
