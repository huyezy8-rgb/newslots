<?php

namespace app\admin\controller\payment;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

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

    protected array $noNeedPermission = ['channels', 'batchEdit'];

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['channelCodeTable'];

    protected string|array $quickSearchField = ['id', 'channel_code_table.name'];

    private const AMOUNT_FIELDS = [
        'min_recharge_amount',
        'max_recharge_amount',
        'min_withdraw_amount',
        'max_withdraw_amount',
    ];

    private const BATCH_EDIT_FIELDS = [
        'show',
        'status',
        'is_clause',
        'pay_method',
        'min_recharge_amount',
        'max_recharge_amount',
        'min_withdraw_amount',
        'max_withdraw_amount',
    ];

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

    public function channels(): void
    {
        $rows = Db::name('payment_methods')
            ->alias('methods')
            ->leftJoin('payment_channels channels', 'channels.code = methods.channel_code')
            ->where('methods.channel_code', '<>', '')
            ->group('methods.channel_code, channels.name')
            ->field('methods.channel_code, channels.name')
            ->select()
            ->toArray();

        $channels = array_values(array_unique(array_filter(array_map(static function (array $row): string {
            return (string)($row['name'] ?: $row['channel_code']);
        }, $rows))));

        $this->success('', $channels);
    }

    public function batchEdit(): void
    {
        $this->checkEditPermission();

        if (!$this->request->isPost()) {
            $this->error('Invalid request method');
        }

        $payload = $this->request->post();
        if (!$payload || (!array_key_exists('ids', $payload) && !array_key_exists('fields', $payload))) {
            $input = $this->request->getContent() ?: $this->request->getInput();
            $jsonPayload = json_decode($input, true);
            $payload = is_array($jsonPayload) ? $jsonPayload : [];
        }

        $payloadIds = $payload['ids'] ?? [];
        $payloadIds = is_array($payloadIds) ? $payloadIds : [$payloadIds];
        $ids = array_values(array_unique(array_filter(array_map(static function ($id): int {
            return (int)$id;
        }, $payloadIds), static function (int $id): bool {
            return $id > 0;
        })));
        if (!$ids) {
            $this->error('Please select payment methods');
        }

        $payloadFields = $payload['fields'] ?? [];
        if (!is_array($payloadFields)) {
            $this->error('Please select fields to update');
        }

        $fields = $this->normalizeBatchFields($payloadFields);
        if (!$fields) {
            $this->error('Please select fields to update');
        }

        $rows = $this->model->whereIn($this->model->getPk(), $ids)->select();
        if ($rows->count() !== count($ids)) {
            $this->error('Selected payment method does not exist');
        }

        $updated = Db::transaction(function () use ($rows, $fields): int {
            $count = 0;
            foreach ($rows as $row) {
                $raw = $row->getData();
                $merged = array_merge($raw, $fields);
                $this->validateAmountRanges($merged);

                $update = $fields;
                if (array_key_exists('pay_method', $fields) && (string)$merged['pay_method'] !== '2') {
                    $update['field_config'] = null;
                    $update['validation_rules'] = null;
                }

                $row->save($update);
                $count++;
            }

            return $count;
        });

        $this->success('Batch update successful', ['updated' => $updated]);
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

            $data = $this->normalizeAmountFields($data);
            $this->validateAmountRanges($data);
            $this->request->withPost($data);
            
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

            $data = $this->normalizeAmountFields($data);
            $this->validateAmountRanges($data);
            $this->request->withPost($data);
            
            // 调用父类方法
            parent::edit();
            return;
        }
        
        parent::edit();
    }

    private function validateAmountRanges(array $data): void
    {
        $minRecharge = $this->getAmountValue($data, 'min_recharge_amount');
        $maxRecharge = $this->getAmountValue($data, 'max_recharge_amount');
        $minWithdraw = $this->getAmountValue($data, 'min_withdraw_amount');
        $maxWithdraw = $this->getAmountValue($data, 'max_withdraw_amount');

        if ($minRecharge !== null && $maxRecharge !== null && $minRecharge > $maxRecharge) {
            $this->error('Minimum recharge amount cannot exceed maximum recharge amount');
        }

        if ($minWithdraw !== null && $maxWithdraw !== null && $minWithdraw > $maxWithdraw) {
            $this->error('Minimum withdraw amount cannot exceed maximum withdraw amount');
        }
    }

    private function checkEditPermission(): void
    {
        $routePath = ($this->app->request->controllerPath ?? '') . '/edit';
        if (!$this->auth->check($routePath)) {
            $this->error(__('You have no permission'), [], 401);
        }
    }

    private function normalizeBatchFields(array $fields): array
    {
        $fields = array_intersect_key($fields, array_flip(self::BATCH_EDIT_FIELDS));

        foreach (['show' => ['all', 'ios', 'android'], 'status' => ['0', '1'], 'is_clause' => ['0', '1'], 'pay_method' => ['0', '1', '2']] as $field => $allowed) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }

            $value = (string)$fields[$field];
            if (!in_array($value, $allowed, true)) {
                $this->error('Invalid ' . $field);
            }
            $fields[$field] = $value;
        }

        foreach (self::AMOUNT_FIELDS as $field) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }

            if ($fields[$field] === '' || $fields[$field] === null) {
                $fields[$field] = null;
                continue;
            }

            if (!is_numeric($fields[$field])) {
                $this->error('Invalid ' . $field);
            }

            $amount = (float)$fields[$field];
            if ($amount < 0) {
                $this->error('Invalid ' . $field);
            }
            $fields[$field] = $amount;
        }

        return $fields;
    }

    private function normalizeAmountFields(array $data): array
    {
        foreach (self::AMOUNT_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === '' || $data[$field] === null) {
                $data[$field] = null;
                continue;
            }

            if (is_numeric($data[$field])) {
                $data[$field] = (float)$data[$field];
            }
        }

        return $data;
    }

    private function getAmountValue(array $data, string $field): ?float
    {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            return null;
        }

        return is_numeric($data[$field]) ? (float)$data[$field] : null;
    }
}
