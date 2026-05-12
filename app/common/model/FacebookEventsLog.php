<?php

namespace app\common\model;

use think\Model;

class FacebookEventsLog extends Model
{
    protected $name = 'facebook_events_log';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    
    // JSON字段
    protected $json = ['event_data', 'custom_data', 'user_data'];
    
    // 状态常量
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    
    // 指定时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    
    /**
     * 创建事件日志
     */
    public static function createLog(array $data): self
    {
        $log = new self();
        $log->user_id = $data['user_id'];
        $log->channel_id = $data['channel_id'];
        $log->event_type = $data['event_type'];
        $log->event_name = $data['event_name'];
        $log->event_data = $data['event_data'] ?? [];
        $log->custom_data = $data['custom_data'] ?? [];
        $log->user_data = $data['user_data'] ?? [];
        $log->fb_pixel_id = $data['fb_pixel_id'] ?? '';
        $log->fb_token = $data['fb_token'] ?? '';
        $log->status = self::STATUS_PENDING;
        $log->event_time = $data['event_time'] ?? time();
        $log->event_id = $data['event_id'] ?? '';
        $log->created_at = time();
        $log->updated_at = time();
        $log->save();
        
        return $log;
    }
    
    /**
     * 更新发送状态
     */
    public function updateStatus(string $status, array $result = []): bool
    {
        $this->status = $status;
        $this->error_message = $result['error'] ?? null;
        $this->fb_event_id = $result['event_id'] ?? null;
        $this->fb_trace_id = $result['fb_trace_id'] ?? null;
        $this->updated_at = time();
        
        return $this->save();
    }
    
    /**
     * 获取统计信息
     */
    public static function getStatistics(int $days = 7): array
    {
        $startTime = time() - ($days * 86400);
        
        $stats = self::where('created_at', '>=', $startTime)
            ->field('event_type, status, COUNT(*) as count')
            ->group('event_type, status')
            ->select()
            ->toArray();
        
        $result = [];
        foreach ($stats as $stat) {
            $eventType = $stat['event_type'];
            $status = $stat['status'];
            if (!isset($result[$eventType])) {
                $result[$eventType] = ['pending' => 0, 'success' => 0, 'failed' => 0];
            }
            $result[$eventType][$status] = $stat['count'];
        }
        
        return $result;
    }
} 