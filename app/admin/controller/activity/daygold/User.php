<?php

namespace app\admin\controller\activity\daygold;

use app\common\controller\Backend;

/**
 * 每日奖励用户领取记录
 */
class User extends Backend
{
    /**
     * User模型对象
     * @var object
     * @phpstan-var \app\common\model\activity\daygold\User
     */
    protected object $model;

    protected array|string $preExcludeFields = ['create_time', 'update_time'];

    protected string|array $quickSearchField = ['uid'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\activity\daygold\User();
    }
    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        // 添加渠道权限过滤
        $where = $this->addChannelFilter($where, 'channel_id');
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
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