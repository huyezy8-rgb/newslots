<?php

namespace app\common\service;



use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class MessageService
{

    /**
     * 发送站内信
     */
    public static function send(array $data): bool
    {
        $insert = [
            'user_id'     => $data['user_id'] ?? 0,
            'channel_id'  => $data['channel_id'] ?? 0,
            'type'        => $data['type'] ?? 'system',
            'title'       => $data['title'],
            'content'     => $data['content'],
            'amount'      => $data['amount'] ?? null,
            'wallet_type' => $data['wallet_type'] ?? null,
            'expire_time' => $data['expire_time'] ?? null,
            'event_name' => $data['event_name'] ?? null,
            'view_more' => $data['view_more'] ?? null,
            'start_time' => $data['start_time'] ?? time(),
            'receive_status' =>0,
            'send_time'   => time(),
            'is_read'     => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
        $result = Db::name('messages')->insert($insert) > 0;

        // ✅ 插入成功后，清除未读缓存
        if ($result && !empty($insert['user_id'])) {
            Log::info("清除缓存");
            Cache::delete("sse:{$insert['user_id']}:unread_message");
        }

        return $result;
    }

    /**
     * 获取用户消息列表（分页）
     */
    public static function getUserMessages(int $uid, int $page = 1, int $limit = 10)
    {
        $query = Db::name('messages')
            ->where(['user_id'=> $uid,'status'=>1]);
            
        // 获取总数
        $total = $query->count();
        
        // 获取分页数据
        $list = $query->order('send_time', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
            
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * 标记为已读
     */
    public static function markAsRead(int $id, int $uid): bool

    {
        $result=Db::name('messages')
            ->where(['id'=> $id, 'user_id'=> $uid])
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_time' => date('Y-m-d H:i:s'),
            ]) > 0;

        // ✅ 插入成功后，清除未读缓存
        if ($result && !empty($uid)) {
            Log::info("清除缓存");
            Cache::delete("sse:{$uid}:unread_message");
        }
        return $result;
    }

    public static function getUnreadMsg(int $uid,int $num)
    {
        $list =Db::name('messages')
            ->where(['user_id'=> $uid,'is_read'=>0])
            ->order('send_time', 'asc')
            ->limit($num)
            ->select()
            ->toArray();
        return $list;
    }
    /**
     * 获取未读消息数量
     */
    public static function getUnreadCount(int $uid): int
    {
        return Db::name('messages')
            ->where(['is_read'=> 0, 'user_id'=> $uid])
            ->count();
    }

    /**
     * 获取用户可领取的奖励消息列表
     */
    public static function getReceivableRewards(int $uid): array
    {
        $currentTime = time();
        
        return Db::name('messages')
            ->where([
                'user_id' => $uid,
                'type' => 'gift',
                'receive_status' => 0,
                'status' => 1
            ])
            ->where('start_time', '<=', $currentTime)
            ->where(function ($query) use ($currentTime) {
                $query->where('expire_time', '>', $currentTime)
                      ->whereOr('expire_time', 'null');
            })
            ->order('send_time', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 批量领取奖励
     */
    public static function batchReceiveRewards(int $uid, array $messageIds): array
    {
        $currentTime = time();
        $results = [
            'success' => [],
            'failed' => []
        ];

        // 获取可领取的消息
        $messages = Db::name('messages')
            ->where('id', 'in', $messageIds)
            ->where([
                'user_id' => $uid,
                'type' => 'gift',
                'receive_status' => 0,
                'status' => 1
            ])
            ->where('start_time', '<=', $currentTime)
            ->where(function ($query) use ($currentTime) {
                $query->where('expire_time', '>', $currentTime)
                      ->whereOr('expire_time', 'null');
            })
            ->select()
            ->toArray();

        foreach ($messages as $message) {
            try {
                // 更新消息状态
                $updated = Db::name('messages')
                    ->where('id', $message['id'])
                    ->update([
                        'receive_status' => 1,
                        'updated_at' => $currentTime,
                    ]);

                if ($updated) {
                    $results['success'][] = $message;
                } else {
                    $results['failed'][] = [
                        'id' => $message['id'],
                        'reason' => '更新失败'
                    ];
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $message['id'],
                    'reason' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
