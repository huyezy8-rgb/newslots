<?php

namespace app\admin\controller\withdraw;

use app\common\controller\Backend;

/**
 * 用户提现账户管理
 */
class Accounts extends Backend
{
    /**
     * Accounts模型对象
     * @var object
     * @phpstan-var \app\admin\model\withdraw\Accounts
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'payment_method_id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\withdraw\Accounts();
    }


    /**
     * 重写 index 方法，包含用户关联信息
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->with(['user'])
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}