<?php

namespace app\common\service;

use app\common\model\TgBotConfig;
use app\common\model\TgMessageTemplate;
use app\common\model\TgSendRecord;
use think\facade\Db;
use think\facade\Log;

class TgBotService
{
    public static function normalizeButtons(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('buttons_json 必须是合法 JSON');
            }
            $value = $decoded;
        }

        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('buttons_json 必须是数组');
        }

        $buttons = [];
        foreach ($value as $button) {
            if (!is_array($button)) {
                throw new \InvalidArgumentException('每个按钮必须是对象');
            }
            $text = trim((string)($button['text'] ?? ''));
            $url = trim((string)($button['url'] ?? ''));
            if ($text === '' && $url === '') {
                continue;
            }
            if ($text === '' || $url === '') {
                throw new \InvalidArgumentException('按钮文字和链接必须同时填写');
            }
            self::assertButtonUrl($text, $url);
            $buttons[] = ['text' => $text, 'url' => $url];
        }

        return $buttons;
    }

    public static function encodeButtonsForStorage(mixed $value): string
    {
        return json_encode(self::normalizeButtons($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function assertButtonUrl(string $text, string $url): void
    {
        if (!preg_match('#^(https?://|tg://)#i', trim($url))) {
            throw new \InvalidArgumentException('按钮“' . $text . '”的链接不是有效URL，请填写 http:// 或 https:// 开头的链接。');
        }
    }

    public function processDueBots(?int $now = null): array
    {
        $now ??= time();
        $processed = 0;
        $sent = 0;
        $failed = 0;

        $query = TgBotConfig::query();
        $botFields = $this->getTableFields('tg_bot_config');
        if (in_array('is_enabled', $botFields, true)) {
            $query->where('is_enabled', 1);
        } elseif (in_array('enable', $botFields, true)) {
            $query->where('enable', 1);
        } elseif (in_array('status', $botFields, true)) {
            $query->where('status', 1);
        }

        foreach ($query->select() as $botModel) {
            $bot = $botModel->toArray();
            if (!$this->isInSendWindow($bot, $now) || $this->hasSentInMinute((int)$bot['id'], $now) || $this->exceedsDailyLimit($bot, $now) || !$this->intervalReached($bot, $now)) {
                continue;
            }

            $processed++;
            $result = $this->sendAuto($bot, $now);
            $result['ok'] ? $sent++ : $failed++;
        }

        return compact('processed', 'sent', 'failed');
    }

    public function sendTest(int $botId, ?int $templateId = null): array
    {
        $bot = TgBotConfig::find($botId);
        if (!$bot) {
            throw new \RuntimeException('机器人不存在');
        }

        $template = $this->resolveTemplate($bot->toArray(), $templateId);
        $vars = [
            'code' => 'TEST',
            'amount' => '0.00',
            'amount_min' => '0.00',
            'amount_max' => '0.00',
            'expire_hours' => '24',
            'expire_time' => date('Y-m-d H:i:s', time() + 86400),
            'claim_count' => '0',
            'max_users' => '0',
            'left_count' => 'Unlimited',
        ];
        $content = $this->renderText((string)$template['content'], $vars);
        $buttons = $this->renderButtons($template['buttons_json'] ?? [], $vars);

        return $this->sendAndRecord($bot->toArray(), $template, 0, 'TEST', $content, $buttons, 'test');
    }

    public function testToken(int $botId): array
    {
        $bot = $this->getBot($botId);
        return $this->getTokenInfo((string)($bot['bot_token'] ?? ''));
    }

    public function getChatIds(int $botId): array
    {
        $bot = $this->getBot($botId);
        return $this->getChatIdsByToken((string)($bot['bot_token'] ?? ''));
    }

    public function getTokenInfo(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('请先填写 Bot Token');
        }

        $response = $this->telegramRequest($token, 'getMe', []);
        $result = $response['result'] ?? [];
        $username = (string)($result['username'] ?? '');
        $firstName = (string)($result['first_name'] ?? '');
        $name = $username !== '' ? $username : $firstName;

        return [
            'ok' => true,
            'message' => $name !== '' ? 'Token 可用，机器人名称 ' . $name : 'Token 可用',
            'name' => $name,
            'username' => $username,
            'first_name' => $firstName,
            'raw' => $response,
        ];
    }

    public function getChatIdsByToken(string $token): array
    {
        return $this->getChatIdsResultByToken($token)['list'];
    }

    public function getChatIdsResultByToken(string $token, bool $deleteWebhook = false): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('请先填写 Bot Token');
        }

        $deleteWebhookResponse = null;
        if ($deleteWebhook) {
            $deleteWebhookResponse = $this->telegramRequest($token, 'deleteWebhook', ['drop_pending_updates' => false]);
        }

        $response = $this->telegramRequest($token, 'getUpdates', []);
        $ids = [];
        foreach (($response['result'] ?? []) as $update) {
            foreach (['message', 'channel_post', 'my_chat_member'] as $key) {
                $chat = $update[$key]['chat'] ?? null;
                if (is_array($chat) && isset($chat['id'])) {
                    $ids[(string)$chat['id']] = [
                        'chat_id' => (string)$chat['id'],
                        'title' => (string)($chat['title'] ?? $chat['username'] ?? $chat['first_name'] ?? ''),
                        'type' => (string)($chat['type'] ?? ''),
                    ];
                }
            }
        }

        return [
            'list' => array_values($ids),
            'raw' => $response,
            'delete_webhook_raw' => $deleteWebhookResponse,
            'hint' => $this->chatIdHint($response, $deleteWebhook),
        ];
    }

    public function sendChatTestByConfig(string $token, string $chatId): array
    {
        $token = trim($token);
        $chatId = trim($chatId);
        if ($token === '') {
            throw new \InvalidArgumentException('请先填写 Bot Token');
        }
        if ($chatId === '') {
            throw new \InvalidArgumentException('请先填写 Chat ID 或频道用户名');
        }

        $response = $this->telegramRequest($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => 'TG bot chat test ok.',
            'disable_web_page_preview' => true,
        ]);

        return [
            'ok' => true,
            'message' => '测试消息发送成功',
            'message_id' => (string)($response['result']['message_id'] ?? ''),
            'raw' => $response,
        ];
    }

    public function sendChatTest(int $botId): array
    {
        $bot = $this->getBot($botId);
        if (trim((string)($bot['bot_token'] ?? '')) === '') {
            throw new \InvalidArgumentException('请先填写 Bot Token');
        }
        if (trim((string)($bot['chat_id'] ?? '')) === '') {
            throw new \InvalidArgumentException('请先填写 Chat ID 或频道用户名');
        }

        $content = 'TG bot chat test ok.';
        $ok = false;
        $messageId = null;
        $failReason = null;

        try {
            $response = $this->telegramRequest((string)$bot['bot_token'], 'sendMessage', [
                'chat_id' => (string)$bot['chat_id'],
                'text' => $content,
                'disable_web_page_preview' => true,
            ]);
            $ok = (bool)($response['ok'] ?? false);
            $messageId = $ok ? (string)($response['result']['message_id'] ?? '') : null;
            $failReason = $ok ? null : $this->telegramError($response, 'Chat test failed');
        } catch (\Throwable $e) {
            Log::error('[TG] sendChatTest telegram failed: ' . $e->getMessage());
            $failReason = $e->getMessage();
        }

        try {
            $this->createRecord($bot, null, 0, 'TEST', $messageId, $content, [], $ok, $failReason, 'test');
        } catch (\Throwable $e) {
            Log::error('[TG] sendChatTest record failed: ' . $e->getMessage());
            return ['ok' => false, 'message_id' => $messageId, 'message' => $e->getMessage() ?: '发送记录写入失败'];
        }

        return ['ok' => $ok, 'message_id' => $messageId, 'message' => $ok ? '测试消息发送成功' : $failReason];
    }

    public function renderText(string $text, array $vars): string
    {
        return strtr($text, $this->wrapVars($vars));
    }

    public function renderButtons(mixed $buttons, array $vars): array
    {
        $rendered = [];
        foreach (self::normalizeButtons($buttons) as $button) {
            $item = [
                'text' => $this->renderText($button['text'], $vars),
                'url' => $this->renderText($button['url'], $vars),
            ];
            self::assertButtonUrl($item['text'], $item['url']);
            $rendered[] = $item;
        }

        return $rendered;
    }

    private function sendAuto(array $bot, int $now): array
    {
        $template = $this->resolveTemplate($bot);
        $codeId = 0;
        $codeValue = '';
        try {
            $ruleId = (int)($bot['redemption_rule_id'] ?? 0);
            if ($ruleId <= 0) {
                throw new \RuntimeException('请先选择红包兑换码规则。');
            }

            $codeService = new RedEnvelopeRedemptionCodeService();
            $code = $codeService->createOrReuseForBot($bot, $ruleId, (int)($bot['code_length'] ?? 4), $now);
            $codeId = (int)($code['id'] ?? 0);
            $codeValue = (string)($code['code'] ?? '');
            $vars = $codeService->buildVars($code);
            $content = $this->renderText((string)$template['content'], $vars);
            $buttons = $this->renderButtons($template['buttons_json'] ?? [], $vars);

            return $this->sendAndRecord($bot, $template, $codeId, $codeValue, $content, $buttons, 'auto');
        } catch (\Throwable $e) {
            Log::error('[TG] sendAuto failed: ' . $e->getMessage());
            return $this->recordFailure($bot, $template, $codeId, $codeValue, $e->getMessage(), 'auto');
        }
    }

    private function sendAndRecord(array $bot, ?array $template, ?int $codeId, ?string $code, string $content, array $buttons, string $sendType): array
    {
        $messageId = null;
        $failReason = null;
        $ok = false;

        try {
            $response = $this->sendTelegramMessage($bot, $template, $content, $buttons);
            $ok = (bool)($response['ok'] ?? false);
            $messageId = $ok ? (string)($response['result']['message_id'] ?? '') : null;
            $failReason = $ok ? null : $this->telegramError($response, 'Telegram send failed');
        } catch (\Throwable $e) {
            Log::error('[TG] send telegram failed: ' . $e->getMessage());
            $failReason = $e->getMessage();
        }

        try {
            $this->createRecord($bot, $template, $codeId, $code, $messageId, $content, $buttons, $ok, $failReason, $sendType);
        } catch (\Throwable $e) {
            Log::error('[TG] send record failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage() ?: '发送记录写入失败', 'message_id' => $messageId];
        }

        return ['ok' => $ok, 'message' => $ok ? '发送成功' : $failReason, 'message_id' => $messageId];
    }

    private function recordFailure(array $bot, ?array $template, ?int $codeId, ?string $code, string $reason, string $sendType): array
    {
        try {
            $this->createRecord($bot, $template, $codeId, $code, null, '', [], false, $reason, $sendType);
        } catch (\Throwable $e) {
            Log::error('[TG] failure record failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage() ?: $reason];
        }

        return ['ok' => false, 'message' => $reason];
    }

    private function createRecord(array $bot, ?array $template, ?int $codeId, ?string $code, ?string $messageId, string $content, array $buttons, bool $ok, ?string $failReason, string $sendType): void
    {
        $data = [
            'bot_id' => (int)($bot['id'] ?? 0),
            'template_id' => (int)($template['id'] ?? 0),
            'template_name' => (string)($template['title'] ?? ''),
            'redemption_code_id' => (int)($codeId ?? 0),
            'code' => (string)($code ?? ''),
            'chat_id' => (string)($bot['chat_id'] ?? ''),
            'channel_id' => (int)($bot['channel_id'] ?? 0),
            'message_id' => (string)($messageId ?? ''),
            'media_type' => $template['media_type'] ?? 'none',
            'media_url' => (string)($template['media_url'] ?? ''),
            'send_type' => $sendType ?: 'test',
            'send_status' => $ok ? 1 : 0,
            'fail_reason' => $ok ? '' : mb_substr((string)($failReason ?? ''), 0, 1000),
            'content' => (string)($content ?? ''),
            'buttons_json' => $buttons ? json_encode($buttons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            'send_time' => time(),
            'created_at' => time(),
        ];

        if ($data['bot_id'] <= 0) {
            throw new \RuntimeException('Bot id is required');
        }

        $fields = $this->getTableFields('tg_send_record');
        if (!$fields) {
            throw new \RuntimeException('TG发送记录表不存在或字段读取失败');
        }

        $defaults = [
            'template_id' => 0,
            'redemption_code_id' => 0,
            'channel_id' => 0,
            'code' => '',
            'chat_id' => '',
            'message_id' => '',
            'send_type' => $sendType ?: 'test',
            'send_status' => 0,
            'fail_reason' => '',
            'content' => '',
            'buttons_json' => '',
            'media_type' => 'none',
            'media_url' => '',
        ];
        foreach ($defaults as $field => $default) {
            if (in_array($field, $fields, true) && (!isset($data[$field]) || $data[$field] === null || $data[$field] === '')) {
                $data[$field] = $default;
            }
        }

        if (!in_array('created_at', $fields, true)) {
            unset($data['created_at']);
        }
        if (in_array('create_time', $fields, true) && !isset($data['create_time'])) {
            $data['create_time'] = time();
        }

        Db::name('tg_send_record')->insert(array_intersect_key($data, array_flip($fields)));
    }

    private function sendTelegramMessage(array $bot, ?array $template, string $content, array $buttons): array
    {
        foreach ($buttons as $button) {
            self::assertButtonUrl((string)($button['text'] ?? ''), (string)($button['url'] ?? ''));
        }

        $mediaType = (string)($template['media_type'] ?? 'none');
        $mediaUrl = trim((string)($template['media_url'] ?? ''));
        $payload = ['chat_id' => (string)$bot['chat_id']];

        if ($buttons) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => array_map(static fn($button) => [$button], $buttons)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $method = 'sendMessage';
        if ($mediaType === 'image') {
            $method = 'sendPhoto';
            $payload['photo'] = $mediaUrl;
            $payload['caption'] = $content;
        } elseif ($mediaType === 'gif') {
            $method = 'sendAnimation';
            $payload['animation'] = $mediaUrl;
            $payload['caption'] = $content;
        } elseif ($mediaType === 'video') {
            $method = 'sendVideo';
            $payload['video'] = $mediaUrl;
            $payload['caption'] = $content;
        } else {
            $payload['text'] = $content;
            $payload['disable_web_page_preview'] = true;
        }

        if ($method !== 'sendMessage' && $mediaUrl === '') {
            throw new \InvalidArgumentException('媒体地址不能为空');
        }

        return $this->telegramRequest((string)$bot['bot_token'], $method, $payload);
    }

    private function telegramRequest(string $token, string $method, array $payload): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('请先填写 Bot Token');
        }

        $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($error ?: 'Telegram 请求失败');
        }
        curl_close($ch);

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Telegram 返回不是合法 JSON：' . mb_substr((string)$body, 0, 500));
        }
        if (empty($decoded['ok'])) {
            throw new \RuntimeException($this->telegramError($decoded, 'Telegram 请求失败'));
        }

        return $decoded;
    }

    private function resolveTemplate(array $bot, ?int $templateId = null): array
    {
        $id = $templateId ?: (int)($bot['template_id'] ?? 0);
        if ($id > 0) {
            $template = TgMessageTemplate::where('id', $id)->where('is_enabled', 1)->find();
            if ($template) {
                return $template->toArray();
            }
        }

        $template = TgMessageTemplate::where('is_enabled', 1)->where('is_default', 1)->order('id desc')->find();
        if ($template) {
            return $template->toArray();
        }

        $template = TgMessageTemplate::where('is_enabled', 1)->order('id asc')->find();
        if ($template) {
            return $template->toArray();
        }

        throw new \RuntimeException('请先创建TG文案模板');
    }

    private function getBot(int $botId): array
    {
        $bot = TgBotConfig::find($botId);
        if (!$bot) {
            throw new \RuntimeException('机器人不存在');
        }

        return $bot->toArray();
    }

    private function wrapVars(array $vars): array
    {
        $wrapped = [];
        foreach ($vars as $key => $value) {
            $wrapped['{' . $key . '}'] = (string)$value;
        }

        return $wrapped;
    }

    private function isInSendWindow(array $bot, int $now): bool
    {
        $current = date('H:i', $now);
        $start = (string)($bot['send_time_start'] ?? '00:00');
        $end = (string)($bot['send_time_end'] ?? '23:59');

        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)) {
            return false;
        }

        return $start <= $end ? ($current >= $start && $current <= $end) : ($current >= $start || $current <= $end);
    }

    private function intervalReached(array $bot, int $now): bool
    {
        $interval = max(1, (int)($bot['send_interval_minutes'] ?? 120));
        $lastTime = TgSendRecord::where('bot_id', (int)$bot['id'])
            ->where('send_type', 'auto')
            ->where('send_status', 1)
            ->order('send_time desc')
            ->value('send_time');

        return !$lastTime || ((int)$lastTime + $interval * 60) <= $now;
    }

    private function hasSentInMinute(int $botId, int $now): bool
    {
        $minuteStart = $now - ((int)date('s', $now));
        return TgSendRecord::where('bot_id', $botId)
            ->where('send_type', 'auto')
            ->where('send_time', '>=', $minuteStart)
            ->where('send_time', '<', $minuteStart + 60)
            ->count() > 0;
    }

    private function exceedsDailyLimit(array $bot, int $now): bool
    {
        $limit = (int)($bot['daily_send_limit'] ?? 0);
        if ($limit <= 0) {
            return false;
        }

        return TgSendRecord::where('bot_id', (int)$bot['id'])
            ->where('send_type', 'auto')
            ->where('send_time', '>=', strtotime(date('Y-m-d 00:00:00', $now)))
            ->where('send_time', '<=', strtotime(date('Y-m-d 23:59:59', $now)))
            ->count() >= $limit;
    }

    private function telegramError(array $response, string $fallback): string
    {
        $description = (string)($response['description'] ?? '');
        $code = $response['error_code'] ?? null;
        if ($description !== '' && $code !== null) {
            return 'Telegram error ' . $code . ': ' . $description;
        }
        if ($description !== '') {
            return $description;
        }

        return $fallback . '，原始返回：' . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function chatIdHint(array $response, bool $deleteWebhook): string
    {
        $tips = [
            '未获取到 Chat ID。',
            '请把机器人设为管理员。',
            '请在群里 @机器人用户名 发送一条消息。',
            '频道/群需要最近有消息，机器人也需要有读取权限。',
        ];
        if (!$deleteWebhook) {
            $tips[] = '如果设置了 webhook，getUpdates 可能拿不到，请使用“清除 Webhook 并获取 Chat ID”。';
        }
        $tips[] = 'Telegram 原始返回：' . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return implode("\n", $tips);
    }

    private function getTableFields(string $table): array
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            try {
                $fields = Db::getFields(config('database.connections.mysql.prefix') . $table);
                $cache[$table] = array_keys($fields);
            } catch (\Throwable $e) {
                Log::error('[TG] get table fields failed for ' . $table . ': ' . $e->getMessage());
                $cache[$table] = [];
            }
        }

        return $cache[$table];
    }
}
