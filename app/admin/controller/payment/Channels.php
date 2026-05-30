<?php

namespace app\admin\controller\payment;

use app\common\controller\Backend;

/**
 * 支付渠道管理
 */
class Channels extends Backend
{
    /**
     * Channels模型对象
     * @var object
     * @phpstan-var \app\common\model\payment\Channels
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id', 'code', 'name', 'status'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\payment\Channels();
    }

    /**
     * 添加
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            // 处理 config 字段
            if (isset($data['config'])) {
                $data['config'] = $this->processJsonField($data['config'], 'config');
            }
            $data['weight'] = $this->normalizeWeight($data['weight'] ?? 100);

            $data = $this->excludeFields($data);

            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }
                $result = $this->model->save($data);
                $this->model->commit();
            } catch (\Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Add successful'));
            } else {
                $this->error(__('No rows were inserted'));
            }
        }
        $this->error(__('Parameter error'));
    }

    /**
     * 编辑
     */
    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            // 处理 config 字段
            if (isset($data['config'])) {
                $data['config'] = $this->processJsonField($data['config'], 'config');
            }
            if (array_key_exists('weight', $data)) {
                $data['weight'] = $this->normalizeWeight($data['weight']);
            }

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }
                $result = $row->save($data);
                $this->model->commit();
            } catch (\Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $this->success('', [
            'row' => $row
        ]);
    }

    /**
     * 处理 JSON 字段
     */
    protected function processJsonField($jsonStr, $fieldName)
    {
        if (!$jsonStr) {
            return null;
        }

        // 转换HTML实体
        $jsonStr = html_entity_decode($jsonStr);

        // 去除首尾多余双引号
        if (substr($jsonStr, 0, 1) === '"' && substr($jsonStr, -1) === '"') {
            $jsonStr = substr($jsonStr, 1, -1);
        }

        // 验证 JSON 格式
        $decoded = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("字段 {$fieldName} JSON 格式错误：" . json_last_error_msg());
        }

        // 保存为JSON字符串，防止数据格式异常
        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    private function normalizeWeight($weight): int
    {
        if ($weight === '' || $weight === null) {
            return 100;
        }
        if (!is_numeric($weight) || (int)$weight < 0) {
            $this->error('Invalid weight');
        }
        return (int)$weight;
    }

    /**
     * 若需重写查看、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}
