<?php

namespace app\admin\controller\tg;

use app\common\controller\Backend;
use think\exception\HttpResponseException;
use think\facade\Log;
use Throwable;

class Rule extends Backend
{
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'created_at', 'updated_at'];

    protected string|array $quickSearchField = ['id', 'rule_name'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\tg\Rule();
    }

    public function add(): void
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->normalizePostData($this->request->post());
                $this->savePostData($data);
                $this->success(__('Added successfully'));
            } catch (HttpResponseException $e) {
                throw $e;
            } catch (Throwable $e) {
                $this->fail($e, '[TG] rule add failed');
            }
        }

        $this->error(__('Parameter error'));
    }

    public function edit(): void
    {
        $pk = $this->model->getPk();
        $id = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        if ($this->request->isPost()) {
            try {
                $data = $this->normalizePostData($this->request->post());
                $this->savePostData($data, $row);
                $this->success(__('Update successful'));
            } catch (HttpResponseException $e) {
                throw $e;
            } catch (Throwable $e) {
                $this->fail($e, '[TG] rule edit failed');
            }
        }

        $this->success('', ['row' => $row]);
    }

    private function normalizePostData(array $data): array
    {
        $data['rule_name'] = trim((string)($data['rule_name'] ?? ''));
        if ($data['rule_name'] === '') {
            throw new \InvalidArgumentException('规则名称不能为空');
        }

        foreach (['amount_min', 'amount_max'] as $field) {
            $data[$field] = round((float)($data[$field] ?? 0), 2);
            if ($data[$field] < 0) {
                throw new \InvalidArgumentException($field . ' 不能小于 0');
            }
        }
        if ($data['amount_max'] < $data['amount_min']) {
            throw new \InvalidArgumentException('最大金额不能小于最小金额');
        }

        foreach (['expire_hours', 'per_user_limit', 'max_claim_users', 'is_enabled'] as $field) {
            $data[$field] = max(0, (int)($data[$field] ?? 0));
        }
        $data['is_enabled'] = $data['is_enabled'] ? 1 : 0;

        return $data;
    }

    private function savePostData(array $data, ?\think\Model $row = null): void
    {
        $data = $this->excludeFields($data);
        $this->model->startTrans();
        try {
            $result = $row ? $row->save($data) : $this->model->save($data);
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            throw $e;
        }

        if ($result === false) {
            $this->error($row ? __('No rows updated') : __('No rows were added'));
        }
    }

    private function fail(Throwable $e, string $prefix): void
    {
        Log::error($prefix . ': ' . ($e->getMessage() ?: '操作失败'));
        $this->error($e->getMessage() ?: '操作失败');
    }
}
