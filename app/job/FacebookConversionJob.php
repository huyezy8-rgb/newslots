<?php

namespace app\job;

use think\queue\Job;

class FacebookConversionJob
{
    /**
     * Facebook 转化事件队列任务
     * @param Job $job
     * @param array $data
     */
    public function fire(Job $job, $data): void
    {
        try {
            // 如果数据是字符串，尝试解析 JSON
            if (is_string($data)) {
                $data = json_decode($data, true) ?: [];
            }
            
            // 如果是数组且包含 data 字段，说明是 ThinkPHP 队列格式
            if (is_array($data) && isset($data['data'])) {
                $data = $data['data'];
            }
            
            $eventData = $data['event_data'] ?? [];
            
            if (!empty($eventData)) {
                // 触发 Facebook 转化事件
                event('FacebookConversion', $eventData);
                
				\think\facade\Log::info('Facebook conversion event processed successfully: ' . json_encode([
					'user_id' => $eventData['user_id'] ?? 0,
					'event_type' => $eventData['event_type'] ?? '',
					'job_id' => $job->getJobId()
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            
            // 删除任务
            $job->delete();
            
        } catch (\Exception $e) {
			\think\facade\Log::error('Facebook conversion event job failed: ' . json_encode([
				'event_data' => $data['event_data'] ?? [],
				'job_id' => $job->getJobId(),
				'error' => $e->getMessage()
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            
            // 任务失败，删除任务
            $job->delete();
        }
    }
    
    /**
     * 任务失败时的处理
     * @param array $data
     */
    public function failed(array $data): void
    {
		\think\facade\Log::error('Facebook conversion event job failed permanently: ' . json_encode([
			'event_data' => $data['event_data'] ?? [],
			'data' => $data
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
} 