<?php

namespace app\admin\controller\tg;

use app\common\controller\Backend;
use app\common\service\TgBotService;
use think\exception\HttpResponseException;
use think\facade\Db;
use think\facade\Log;
use Throwable;

class Bot extends Backend
{
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'created_at', 'updated_at'];

    protected string|array $quickSearchField = ['id', 'name', 'chat_id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\tg\Bot();
    }

    public function index(): void
    {
        try {
            parent::index();
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot index failed');
        }
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
                $this->fail($e, '[TG] bot add failed');
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
                $this->fail($e, '[TG] bot edit failed');
            }
        }

        $this->success('', ['row' => $row]);
    }

    public function testSend(): void
    {
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) {
            $this->error(__('Parameter error'));
        }

        try {
            $result = (new TgBotService())->sendTest($id);
            !empty($result['ok']) ? $this->success($result['message'] ?: '测试发送成功', $result) : $this->error($result['message'] ?: '测试发送失败', $result);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot testSend failed');
        }
    }

    public function testToken(): void
    {
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) {
            $this->error(__('Parameter error'));
        }

        try {
            $result = (new TgBotService())->testToken($id);
            !empty($result['ok']) ? $this->success($result['message'] ?: 'Token 可用', $result) : $this->error($result['message'] ?: 'Token 测试失败', $result);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot testToken failed');
        }
    }

    public function getChatIds(): void
    {
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) {
            $this->error(__('Parameter error'));
        }

        try {
            $bot = $this->model->find($id);
            if (!$bot) {
                $this->error(__('Record not found'));
            }
            $result = (new TgBotService())->getChatIdsResultByToken((string)$bot['bot_token']);
            if (!$result['list']) {
                $this->success($result['hint'], $result);
            }
            $this->success('获取 Chat ID 成功', $result);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot getChatIds failed');
        }
    }

    public function sendChatTest(): void
    {
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) {
            $this->error(__('Parameter error'));
        }

        try {
            $result = (new TgBotService())->sendChatTest($id);
            !empty($result['ok']) ? $this->success($result['message'] ?: '测试消息发送成功', $result) : $this->error($result['message'] ?: 'Chat test failed', $result);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot sendChatTest failed');
        }
    }

    public function tokenInfo(): void
    {
        try {
            $result = (new TgBotService())->getTokenInfo((string)$this->request->post('bot_token', ''));
            $this->success($result['message'] ?: 'Token 可用', $result);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot tokenInfo failed');
        }
    }

    public function chatIdsByToken(): void
    {
        try {
            $result = (new TgBotService())->getChatIdsResultByToken((string)$this->request->post('bot_token', ''));
            if (!$result['list']) {
                $this->success($result['hint'], $result);
            }
            $this->success('获取 Chat ID 成功', $result);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot chatIdsByToken failed');
        }
    }

    public function chatIdsAfterDeleteWebhook(): void
    {
        try {
            $result = (new TgBotService())->getChatIdsResultByToken((string)$this->request->post('bot_token', ''), true);
            if (!$result['list']) {
                $this->success($result['hint'], $result);
            }
            $this->success('已清除 Webhook，获取 Chat ID 成功', $result);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot chatIdsAfterDeleteWebhook failed');
        }
    }

    public function sendChatTestByConfig(): void
    {
        try {
            $result = (new TgBotService())->sendChatTestByConfig(
                (string)$this->request->post('bot_token', ''),
                (string)$this->request->post('chat_id', '')
            );
            $this->success($result['message'] ?: '测试消息发送成功', $result);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot sendChatTestByConfig failed');
        }
    }

    public function redemptionRules(): void
    {
        try {
            $fields = $this->getTableFields('red_envelope_redemption_rule');
            if (!$fields) {
                throw new \RuntimeException('红包规则表不存在');
            }

            $list = Db::name('red_envelope_redemption_rule')
                ->where('is_enabled', 1)
                ->field('id,rule_name,amount_min,amount_max,expire_hours,per_user_limit,max_claim_users')
                ->order('id desc')
                ->select()
                ->toArray();

            $this->success($list ? '' : '红包规则为空', ['list' => $list]);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] bot redemptionRules failed');
        }
    }

    private function normalizePostData(array $data): array
    {
        unset($data['site_url'], $data['customer_url'], $data['channel_id'], $data['code_amount'], $data['max_claim_users'], $data['expire_hours'], $data['send_times']);

        $defaults = [
            'template_id' => 0,
            'redemption_rule_id' => 0,
            'send_interval_minutes' => 120,
            'daily_send_limit' => 0,
            'code_length' => 4,
            'is_enabled' => 1,
        ];

        foreach ($defaults as $field => $default) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                $data[$field] = $default;
            }
        }

        foreach (['is_enabled', 'code_length', 'daily_send_limit', 'send_interval_minutes', 'template_id', 'redemption_rule_id'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $data[$field] = (int)$data[$field];
            }
        }

        if (!isset($data['is_enabled']) && isset($data['enable'])) {
            $data['is_enabled'] = (int)$data['enable'];
        }
        if (!isset($data['enable']) && isset($data['is_enabled'])) {
            $data['enable'] = (int)$data['is_enabled'];
        }

        if (isset($data['code_length']) && !in_array((int)$data['code_length'], [4, 5, 6, 8], true)) {
            throw new \InvalidArgumentException('兑换码位数不合法');
        }

        foreach (['send_time_start', 'send_time_end'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string)$data[$field])) {
                throw new \InvalidArgumentException('发送时间格式不合法：' . $data[$field]);
            }
        }

        return $data;
    }

    private function savePostData(array $data, ?\think\Model $row = null): void
    {
        $data = $this->excludeFields($data);
        $data = $this->filterTableFields($data, $row === null);
        Db::startTrans();
        try {
            if ($row) {
                $result = Db::name('tg_bot_config')->where('id', (int)$row['id'])->update($data);
            } else {
                $result = Db::name('tg_bot_config')->insert($data);
            }
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            throw $e;
        }

        if ($result === false) {
            $this->error($row ? __('No rows updated') : __('No rows were added'));
        }
    }

    private function filterTableFields(array $data, bool $isAdd): array
    {
        $fields = $this->getTableFields('tg_bot_config');

        foreach (['template_id', 'redemption_rule_id'] as $field) {
            if (in_array($field, $fields, true) && (!isset($data[$field]) || $data[$field] === null || $data[$field] === '')) {
                $data[$field] = 0;
            }
            if (!in_array($field, $fields, true)) {
                unset($data[$field]);
            }
        }

        if (!in_array('is_enabled', $fields, true)) {
            unset($data['is_enabled']);
        }
        if (!in_array('enable', $fields, true)) {
            unset($data['enable']);
        }

        $now = time();
        if ($isAdd && in_array('created_at', $fields, true)) {
            $data['created_at'] = $now;
        }
        if (in_array('updated_at', $fields, true)) {
            $data['updated_at'] = $now;
        }
        if ($isAdd && in_array('create_time', $fields, true)) {
            $data['create_time'] = $now;
        }
        if (in_array('update_time', $fields, true)) {
            $data['update_time'] = $now;
        }

        return array_intersect_key($data, array_flip($fields));
    }

    private function getTableFields(string $table): array
    {
        try {
            $fields = Db::getFields(config('database.connections.mysql.prefix') . $table);
            return array_keys($fields);
        } catch (Throwable $e) {
            Log::error('[TG] get table fields failed for ' . $table . ': ' . $e->getMessage());
            return [];
        }
    }

    private function fail(Throwable $e, string $prefix = '[TG] bot operation failed'): void
    {
        Log::error($prefix . ': ' . ($e->getMessage() ?: '操作失败'));
        $this->error($e->getMessage() ?: '操作失败');
    }
}
