<?php

namespace app\admin\controller\tg;

use app\common\controller\Backend;
use app\common\service\TgBotService;
use think\exception\HttpResponseException;
use think\facade\Log;
use Throwable;

class Template extends Backend
{
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'created_at', 'updated_at'];

    protected string|array $quickSearchField = ['id', 'title', 'remark'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\tg\Template();
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
                $this->fail($e, '[TG] template add failed');
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
                $this->fail($e, '[TG] template edit failed');
            }
        }

        $this->success('', ['row' => $row]);
    }

    public function preview(): void
    {
        try {
            $data = $this->request->post();
            $content = (new TgBotService())->renderText((string)($data['content'] ?? ''), $this->sampleVars());
            $buttons = (new TgBotService())->renderButtons($data['buttons_json'] ?? [], $this->sampleVars());
            $this->success('', ['content' => $content, 'buttons' => $buttons]);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] template preview failed');
        }
    }

    public function testSend(): void
    {
        $templateId = (int)$this->request->post('id', 0);
        $botId = (int)$this->request->post('bot_id', 0);
        if ($templateId <= 0 || $botId <= 0) {
            $this->error(__('Parameter error'));
        }

        try {
            $result = (new TgBotService())->sendTest($botId, $templateId);
            $result['ok'] ? $this->success('Test sent', $result) : $this->error($result['message'] ?? 'Test send failed', $result);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->fail($e, '[TG] template testSend failed');
        }
    }

    private function normalizePostData(array $data): array
    {
        $data['buttons_json'] = $this->normalizeButtonsJson($data['buttons_json'] ?? null);

        $mediaType = (string)($data['media_type'] ?? 'none');
        if (!in_array($mediaType, ['none', 'image', 'gif', 'video'], true)) {
            throw new \InvalidArgumentException('媒体类型不合法');
        }
        $data['media_type'] = $mediaType;
        if ($mediaType === 'none') {
            $data['media_url'] = '';
        }

        foreach (['is_enabled', 'is_default'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

    private function normalizeButtonsJson(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '[]';
        }

        if (is_array($value)) {
            Log::info('[TG Template buttons_json debug] ' . json_encode([
                'buttons_json_raw' => $value,
                'type' => gettype($value),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $buttons = $value;
        } elseif (is_string($value)) {
            $buttonsJson = $value;
            if ($buttonsJson === '') {
                return '[]';
            }

            Log::info('[TG Template buttons_json debug] ' . json_encode([
                'buttons_json_raw' => $buttonsJson,
                'type' => gettype($buttonsJson),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $buttonsJson = html_entity_decode($buttonsJson, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $buttons = json_decode($buttonsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException(
                    "buttons_json原始值：\n" . $buttonsJson . "\njson_last_error_msg：\n" . json_last_error_msg()
                );
            }

            if (is_string($buttons)) {
                $decodedButtonsJson = $buttons;
                $buttons = json_decode($decodedButtonsJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException(
                        "buttons_json原始值：\n" . $buttonsJson . "\n二次解析值：\n" . $decodedButtonsJson . "\njson_last_error_msg：\n" . json_last_error_msg()
                    );
                }
            }
        } else {
            Log::info('[TG Template buttons_json debug] ' . json_encode([
                'buttons_json_raw' => $value,
                'type' => gettype($value),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            throw new \InvalidArgumentException('buttons_json JSON解析失败：字段类型不支持，当前类型：' . gettype($value));
        }

        if (!is_array($buttons)) {
            throw new \InvalidArgumentException('buttons_json 必须是数组');
        }

        $normalized = [];
        foreach ($buttons as $button) {
            if (!is_array($button)) {
                throw new \InvalidArgumentException('按钮配置格式不正确');
            }

            $text = trim((string)($button['text'] ?? ''));
            $url = trim((string)($button['url'] ?? ''));
            if ($text === '' && $url === '') {
                continue;
            }
            if ($text !== '' && $url === '') {
                throw new \InvalidArgumentException('按钮“' . $text . '”链接不能为空');
            }
            if ($text === '' && $url !== '') {
                throw new \InvalidArgumentException('按钮文字不能为空');
            }
            if (!preg_match('#^(https?://|tg://)#i', $url)) {
                throw new \InvalidArgumentException('按钮“' . $text . '”的链接不是有效URL，请填写 http:// 或 https:// 开头的链接。');
            }

            $normalized[] = ['text' => $text, 'url' => $url];
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    private function sampleVars(): array
    {
        return [
            'code' => 'ABCD',
            'amount' => '5.00',
            'amount_min' => '5.00',
            'amount_max' => '5.00',
            'expire_hours' => '24',
            'expire_time' => date('Y-m-d H:i:s', time() + 86400),
            'claim_count' => '0',
            'max_users' => '100',
            'left_count' => '100',
        ];
    }

    private function fail(Throwable $e, string $prefix): void
    {
        Log::error($prefix . ': ' . ($e->getMessage() ?: '操作失败'));
        $this->error($e->getMessage() ?: '操作失败');
    }
}
