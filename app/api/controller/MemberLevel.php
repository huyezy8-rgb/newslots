<?php

namespace app\api\controller;

use app\common\service\MemberLevelService;
use app\Request;

class MemberLevel extends Base
{
    /**
     * 获取所有会员等级相关信息（包含等级列表、用户当前等级、升级进度、奖励信息）
     * GET /api/memberLevel/all
     */
    public function all(Request $request)
    {
        $memberLevelService = new MemberLevelService();
        $allData = [
            'levels' => [],
            'levels_total' => 0,
            'user_level' => null,
            'upgrade_progress' => null,
            'user_rewards' => [],
            'rewards_total' => 0
        ];
        
        try {
            // 使用优化的一次性获取方法，减少数据库查询次数
            $result = $memberLevelService->getAllMemberLevelData($this->userInfo['id']);
            
            if ($result['status']) {
                $allData = $result['data'];
            } else {
                // 业务逻辑错误时，抛出异常
                throw new \Exception($result['msg']);
            }
            
        } catch (\Exception $e) {
            // 发生异常时，应该返回错误状态让前端知道出现了问题
            $this->error(__('memberlevel.get_member_level_info_failed') . ': ' . $e->getMessage(), $allData);
        }

        // $this->success 放在最后，确保正常情况下返回成功
        $this->success(__('memberlevel.get_member_level_info_success'), $allData);
    }


    /**
     * 领取奖励
     * POST /api/memberLevel/claimReward
     */
    public function claimReward(Request $request)
    {
        $rewardType = $request->param('reward_type', '');
        
        if (empty($rewardType)) {
            $this->error(__('memberlevel.reward_type_cannot_be_empty'));
        }

        if (!in_array($rewardType, ['upgrade', 'weekly', 'monthly'])) {
            $this->error(__('memberlevel.invalid_reward_type'));
        }

        $memberLevelService = new MemberLevelService();
        $result = $memberLevelService->claimReward($this->userInfo['id'], $rewardType);
        
        if ($result['status']) {
            $this->success($result['msg'], $result);
        } else {
            $this->error($result['msg']);
        }
    }

    /**
     * 获取用户奖励发放记录
     * GET /api/memberLevel/rewardLogs
     */
    public function rewardLogs(Request $request)
    {
        $page = $request->param('page', 1);
        $limit = $request->param('limit', 20);
        $rewardType = $request->param('reward_type', ''); // 可选：筛选奖励类型
        
        $memberLevelService = new MemberLevelService();
        $result = $memberLevelService->getUserRewardLogs($this->userInfo['id'], $page, $limit, $rewardType);
        
        if ($result['status']) {
            $this->success($result['msg'], $result['data'], $result['total'] ?? 0);
        } else {
            $this->error($result['msg'], $result['data'], $result['error'] ?? '');
        }
    }


} 