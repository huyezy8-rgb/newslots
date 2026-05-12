<?php

namespace app\admin\controller\activity;

use app\common\controller\Backend;

/**
 * vip充值配置管理
 */
class FirstDeposit270 extends Backend
{
    /**
     * 270活动
     * @var object
     * @phpstan-var \app\common\model\activity\FirstDeposit270
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\activity\FirstDeposit270();
    }
    /**
     * 编辑
     * @throws \Throwable
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
            $cleanJson = function ($jsonStr) {
                if (!$jsonStr) {
                    return null;
                }
                // 转换HTML实体
                $jsonStr = html_entity_decode($jsonStr);

                // 去除首尾多余双引号
                if (substr($jsonStr, 0, 1) === '"' && substr($jsonStr, -1) === '"') {
                    $jsonStr = substr($jsonStr, 1, -1);
                }

                return $jsonStr;
            };
            $jsonFields = ['amount_list', 'pay_channels', 'reward_value','bet_sum_reward','bet_test_reward'];
            foreach ($jsonFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = $cleanJson($data[$field]);

                    $decoded = json_decode($data[$field], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->error("字段 {$field} JSON 格式错误：" . json_last_error_msg());
                    }

                    // 保存为JSON字符串，防止数据格式异常
                    $data[$field] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                }
            }


            $data = $this->excludeFields($data);

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
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}