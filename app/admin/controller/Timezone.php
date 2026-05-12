<?php
declare (strict_types=1);

namespace app\admin\controller;

use think\facade\Config;
use app\common\controller\Backend;

class Timezone extends Backend
{
    protected array $noNeedLogin = ['info', 'test'];
    protected array $noNeedPermission = ['info', 'test'];

    /**
     * 获取时区信息
     */
    public function info(): void
    {
        $this->success('', [
            'php_timezone' => date_default_timezone_get(),
            'thinkphp_timezone' => Config::get('app.default_timezone'),
            'system_config_timezone' => get_sys_config('time_zone'),
            'current_time' => date('Y-m-d H:i:s'),
            'utc_time' => gmdate('Y-m-d H:i:s'),
            'timestamp' => time(),
            'timezone_offset' => date('Z'),
            'supported_timezones' => [
                'Asia/Shanghai',
                'America/New_York',
                'America/Los_Angeles',
                'UTC',
                'Europe/Berlin',
                'Asia/Tokyo',
                'Asia/Seoul',
                'Asia/Singapore',
                'Australia/Sydney',
                'Europe/London'
            ]
        ]);
    }

    /**
     * 测试时区格式化
     */
    public function test(): void
    {
        $timestamp = $this->request->param('timestamp', time());
        $timezone = $this->request->param('timezone', 'Asia/Shanghai');
        $format = $this->request->param('format', 'Y-m-d H:i:s');

        // 确保 timestamp 是整数类型
        $timestamp = intval($timestamp);
        
        // 处理时间戳：如果大于 9999999999，说明是毫秒级时间戳，需要转换为秒级
        if ($timestamp > 9999999999) {
            $timestamp = intval($timestamp / 1000);
        }

        // 设置时区
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set($timezone);

        // 格式化时间
        $formatted = date($format, $timestamp);

        // 恢复原始时区
        date_default_timezone_set($originalTimezone);

        $this->success('', [
            'timestamp' => $timestamp,
            'timezone' => $timezone,
            'format' => $format,
            'formatted' => $formatted,
            'original_timezone' => $originalTimezone,
            'is_milliseconds' => $timestamp > 9999999999
        ]);
    }

    /**
     * 获取时区列表
     */
    public function list(): void
    {
        $timezones = [
            ['label' => '中国标准时间 (UTC+8)', 'value' => 'Asia/Shanghai'],
            ['label' => '美东时间 (UTC-5)', 'value' => 'America/New_York'],
            ['label' => '美西时间 (UTC-8)', 'value' => 'America/Los_Angeles'],
            ['label' => 'UTC时间 (UTC+0)', 'value' => 'UTC'],
            ['label' => '欧洲中部时间 (UTC+1)', 'value' => 'Europe/Berlin'],
            ['label' => '日本标准时间 (UTC+9)', 'value' => 'Asia/Tokyo'],
            ['label' => '韩国标准时间 (UTC+9)', 'value' => 'Asia/Seoul'],
            ['label' => '新加坡时间 (UTC+8)', 'value' => 'Asia/Singapore'],
            ['label' => '澳大利亚东部时间 (UTC+10)', 'value' => 'Australia/Sydney'],
            ['label' => '英国时间 (UTC+0)', 'value' => 'Europe/London']
        ];

        $this->success('', $timezones);
    }
} 