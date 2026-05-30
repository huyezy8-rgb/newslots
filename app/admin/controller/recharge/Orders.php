<?php

namespace app\admin\controller\recharge;

use app\common\controller\Backend;
use app\common\model\recharge\Orders as OrdersModel;
use think\facade\Log;
use think\exception\HttpResponseException;

/**
 * 充值订单管理
 */
class Orders extends Backend
{
    /**
     * Orders模型对象
     * @var object
     * @phpstan-var OrdersModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['id', 'order_no', 'user_id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new OrdersModel();
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

    protected function exportQueryBuilder(): array
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $where = $this->addChannelFilter($where, 'channel_id');

        return [$where, $alias, $limit, $order];
    }



    /**
     * 手动回调订单
     * @throws Throwable
     */
    public function manualCallback(): void
    {
        $id = $this->request->post('id');
        if (!$id) {
            $this->error('参数错误');
        }

        $order = $this->model->find($id);
        if (!$order) {
            $this->error('订单不存在');
        }

        if ($order->pay_status == 1 && !empty($order->paid_at)) {
            $this->error('订单已支付成功，无需回调');
        }

        try {
            Log::info("手动回调开始，订单号: {$order->order_no}, 订单ID: {$id}");

            $notify = new \app\api\controller\Notify();
            $notify->processManualRecharge($order->order_no);

            Log::info("手动回调成功，订单号: {$order->order_no}, 订单ID: {$id}");
            $this->success('手动回调成功');
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage() ?: '未知错误';
            Log::error("手动回调失败，订单ID: {$id}, 错误信息: {$errorMsg}, 错误堆栈: " . $e->getTraceAsString());
            $this->error('手动回调失败: ' . $errorMsg);
        }
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}
