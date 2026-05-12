<?php

namespace app\admin\controller\activity;

use app\admin\model\Account;
use app\api\enum\CoinLog;
use app\common\controller\Backend;

/**
 * 站内信活动配置管理
 */
class InternalMessage extends Backend
{
    /**
     * InternalMessage模型对象
     * @var object
     * @phpstan-var \app\common\model\activity\InternalMessage
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\activity\InternalMessage();
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

    public function send()
    {
        $params = $this->request->post();
        $title = trim($params['title'] ?? '');
        $content = trim($params['content'] ?? '');
        $type = $params['type'] ?? 'system';
        $amount = $params['amount'] ?? 0;
        $wallet_type = $params['wallet_type'] ?? '';
        $valid_hours = $params['valid_hours'] ?? 0;
        $sender_ids = trim($params['sender_ids'] ?? '');
        $send_to_all = !empty($params['send_to_all']);

        if ($title === '' || $content === '') {
            return json(['code' => 0, 'msg' => '标题和内容不能为空']);
        }

        // 计算过期时间
        if ($type == 'gift') {
            $expire_time = $valid_hours > 0 ? time() + $valid_hours * 3600 : null;
        } else {
            $expire_time = null;
        }

        // 组装公共数据
        $baseData = [
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'amount' => $amount,
            'wallet_type' => $wallet_type,
            'start_time' => time(),
            'expire_time' => $expire_time,
            'event_name' => "system",
        ];

        $success = 0;
        $fail = 0;
        $failIds = [];

        if ($send_to_all) {
            // 发送给所有用户
            $userIds = \think\facade\Db::name('account')->where('is_black', 0)->column('id');
        } else {
            // 发送给指定用户
            $userIds = array_filter(array_map('trim', explode(',', $sender_ids)), function($id) {
                return $id !== '' && is_numeric($id);
            });
        }

        foreach ($userIds as $uid) {
            $data = $baseData;
            $data['user_id'] = $uid;
            $data["channel_id"] = Account::find($uid)->channel_id;
            $result = \app\common\service\MessageService::send($data);
            if ($result) {
                $success++;
            } else {
                $fail++;
                $failIds[] = $uid;
            }
        }

        if ($success > 0) {
            $this->success('', "发送成功：{$success}条" . ($fail > 0 ? ", 失败：{$fail}条（" . implode(',', $failIds) . "）" : ''));
        } else {
            $this->error("发送失败");
        }
    }
} 