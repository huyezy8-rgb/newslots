<?php

namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\service\AccountService;
use app\common\service\MessageService;
use app\Request;
use think\facade\Cache;
use think\facade\Db;

class Message extends Base
{
    protected function getAccountService(): AccountService
    {
        return $this->accountService ??= new AccountService();
    }
    protected function getMessageService(): MessageService
    {
        return $this->messageService ??= new MessageService();
    }

    /**
     * 获取用户站内信列表（分页）
     * GET /api/messages?uid=xxx&page=1&limit=10
     */
    public function list(Request $request)
    {
        $uid = $this->userInfo['id'];

        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 10);

        // 查询指定用户的消息
        $result = $this->getMessageService()->getUserMessages(
            $uid,
            $page,
            $limit
        );

        $this->success(__('OK'), $result);
    }
    /**
     * 软删除信息
     *
     */
    public function del(Request $request)
    {
        $id = (int)$request->post('id', 0);
        $uid = $this->userInfo['id'];
        if ($id <= 0 || $uid <= 0) {

            $this->error(__('Param error'));
        }
        $updated = Db::name('messages')
            ->where('id', $id)
            ->where('user_id', 'in', [0, $uid])
            ->where('status', 1)
            ->update([
                'status' => 0
            ]);
        $cacheKey = "sse:{$uid}:unread_message";
        Cache::delete($cacheKey);
        if ($updated) {
            $this->success(__('OK'));
        } else {
            $this->error(__('Message not exist'));
        }
    }
    /**
     * 标记消息为已读
     * POST /api/messages/markRead
     * 参数：id=消息ID，uid=用户ID
     */
    public function markRead(Request $request)
    {
        $id = (int)$request->post('id', 0);
        $uid = $this->userInfo['id'];

        if ($id <= 0 || $uid <= 0) {

            $this->error(__('Param error'));
        }

        // 只能标记自己相关的消息已读，支持user_id=0（全体用户）和指定用户
        $msg = Db::name('messages')
            ->where('id', $id)
            ->where('user_id', 'in', [0, $uid])
            ->where('is_read', 0)
            ->find();
        if (!$msg) {
            $this->error(__('Param error'));
        }
        if ($msg['start_time'] > time()) {
            $updateData = [
                'updated_at' => time(),
            ];
        }else{
            $updateData = [
                'is_read' => 1,
                'read_time' => time(),
                'updated_at' => time(),
            ];
        }

        $updated = Db::name('messages')
            ->where('id', $id)
            ->update($updateData);
        $cacheKey = "sse:{$uid}:unread_message";
        Cache::delete($cacheKey);
        if ($updated) {
            $this->success(__('OK'));
        } else {
            $this->error(__('Message not exist or already read'));
        }
    }


    //领取奖励
    public function markReceive(Request $request)
    {
        $id = (int)$request->post('id', 0);
        $uid = $this->userInfo['id'];

        if ($id <= 0 || $uid <= 0) {

            $this->error(__('Param error'));
        }

        $Message = Db::name('messages')
            ->where(['id'=> $id, 'type'=>'gift', 'user_id'=>$uid,'receive_status'=>0])
            ->find();
        if (empty($Message)) {
            $this->error(__('Message not exist or cannot receive'));
        }

        //未到领取时间
        if (isset($Message['start_time']) && $Message['start_time'] > time()) {
            $this->error("Not yet time to claim the reward. Start time: " . date('Y-m-d H:i:s', $Message['start_time']));
        }

        if (isset($Message['expire_time']) && $Message['expire_time'] < time()){
            $this->error(__('Reward expired'));
        }
        Db::startTrans();
        try {
        // 只能标记自己相关的消息已读，支持user_id=0（全体用户）和指定用户
        $updated = Db::name('messages')
            ->where(['id'=> $id, 'type'=>'gift', 'user_id'=>$uid,'receive_status'=>0])
            ->update([
                'receive_status' => 1,
                'updated_at' => time(),
            ]);
            if (!$updated) {
                throw new \Exception(__('Message not exist or already received'));
            }
            $log_type_id = CoinLog::getIdByEvent($Message['event_name']);
            $note = CoinLog::getTypeText($log_type_id) ."赠送";
            $walletType = CoinLog::getWalletType( $Message['wallet_type']);
            $this->getAccountService()->increaseBalance(
                userId: $uid,
                amount: $Message['amount'],
                walletType:$walletType,
                logTypeId: $log_type_id,
                note:$note
            );
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Receive failed'), $e->getMessage());

        }
        $this->success(__('Receive success'));
    }

    /**
     * 获取可领取的奖励列表
     * GET /api/messages/receivable
     */
    public function receivable(Request $request)
    {
        $uid = $this->userInfo['id'];
        
        $rewards = $this->getMessageService()::getReceivableRewards($uid);
        
        $this->success(__('OK'), [
            'rewards' => $rewards,
            'count' => count($rewards)
        ]);
    }

    /**
     * 一键领取所有奖励
     * POST /api/messages/batchReceive
     */
    public function batchReceive(Request $request)
    {
        $uid = $this->userInfo['id'];
        
        // 获取所有可领取的奖励
        $receivableRewards = $this->getMessageService()::getReceivableRewards($uid);
        if (empty($receivableRewards)) {
            $this->error(__('No rewards to receive'));
        }
        
        $messageIds = array_column($receivableRewards, 'id');
        
        Db::startTrans();
        try {
            $totalAmount = 0;
            $successCount = 0;
            $failedCount = 0;
            
            foreach ($receivableRewards as $reward) {
                try {
                    // 检查时间限制
                    if (isset($reward['start_time']) && $reward['start_time'] > time()) {
                        $failedCount++;
                        continue;
                    }
                    
                    if (isset($reward['expire_time']) && $reward['expire_time'] < time()) {
                        $failedCount++;
                        continue;
                    }
                    
                    // 更新消息状态
                    $updated = Db::name('messages')
                        ->where(['id' => $reward['id'], 'type' => 'gift', 'user_id' => $uid, 'receive_status' => 0])
                        ->update([
                            'receive_status' => 1,
                            'is_read' => 1,
                            'read_time' => time(),
                            'updated_at' => time(),
                        ]);
                    
                    if ($updated) {
                        // 增加用户余额
                        $log_type_id = CoinLog::getIdByEvent($reward['event_name']);
                        $note = CoinLog::getTypeText($log_type_id) . "赠送";
                        $walletType = CoinLog::getWalletType($reward['wallet_type']);
                        
                        $this->getAccountService()->increaseBalance(
                            userId: $uid,
                            amount: $reward['amount'],
                            walletType: $walletType,
                            logTypeId: $log_type_id,
                            note: $note
                        );
                        
                        $totalAmount = $totalAmount +$reward['amount'];
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                    // 记录错误但不中断整个流程
                    \think\facade\Log::error("批量领取奖励失败，消息ID: {$reward['id']}, 错误: " . $e->getMessage());
                }
            }
            
            // 清除缓存
            $cacheKey = "sse:{$uid}:unread_message";
            Cache::delete($cacheKey);
            
            Db::commit();

            
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(__('Batch receive failed'), $e->getMessage());
        }

        $this->success(__('Batch receive success'), [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'total_amount' => $totalAmount,
            'total_rewards' => count($receivableRewards)
        ]);
    }
}