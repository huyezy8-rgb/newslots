<?php

namespace app\admin\controller\jackpot\ladder;

use app\common\controller\Backend;

/**
 * jackpot阶梯配置
 */
class Config extends Backend
{
    /**
     * Config模型对象
     * @var object
     * @phpstan-var \app\admin\model\jackpot\ladder\Config
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\jackpot\ladder\Config();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}