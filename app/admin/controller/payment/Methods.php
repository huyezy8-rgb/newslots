<?php

namespace app\admin\controller\payment;

use Throwable;
use app\common\controller\Backend;

/**
 * 支付方式管理
 */
class Methods extends Backend
{
    /**
     * Methods模型对象
     * @var object
     * @phpstan-var \app\common\model\payment\Methods
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['channelCodeTable'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\payment\Methods();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['channelCodeTable' => ['name']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 添加
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            
            // 如果不是提现方式，清空字段配置和验证规则
            if (!isset($data['pay_method']) || $data['pay_method'] !== '2') {
                $data['field_config'] = null;
                $data['validation_rules'] = null;
            }
            
            // 调用父类方法
            parent::add();
            return;
        }
        
        parent::add();
    }

    /**
     * 编辑
     */
    public function edit(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            
            // 如果不是提现方式，清空字段配置和验证规则
            if (!isset($data['pay_method']) || $data['pay_method'] !== '2') {
                $data['field_config'] = null;
                $data['validation_rules'] = null;
            }
            
            // 调用父类方法
            parent::edit();
            return;
        }
        
        parent::edit();
    }
}