<?php


namespace app\api\controller;

use app\api\enum\CoinLog;
use app\common\service\LeaderboardService;
use think\Request;
use think\Response;
use think\facade\Log;
use think\App;
use think\facade\Db;

class Leaderboard extends Base
{
    private LeaderboardService $leaderboardService;

    public function __construct(App $app)
    {
        parent::__construct($app);

        $this->leaderboardService = new LeaderboardService();
    }

    //获取排行榜
    public function getRanking()
    {

        $type = $this->request->param('type', 'daily'); // 默认类型为 daily
        $limit = $this->request->param('limit', 0);  // 0表示使用配置限制
        $userId = $this->userInfo['id'];
        
        // 获取用户渠道ID
        $channelId = $this->userInfo['channel_id'] ?? null;

        // 验证类型
        if (!in_array($type, ['daily', 'weekly', 'monthly'])) {
            $this->error(__('Invalid type'));
        }

        // 限制查询数量
        if ($limit > 0) {
            $limit = min($limit, 300);
        }

        $leaderboard = $this->leaderboardService->getLeaderboard($type, $limit, $channelId);
        $prizePool = $this->leaderboardService->getPrizePool($type, $channelId);

        $userRank = $this->leaderboardService->getUserRanking($userId, $type, $channelId);

        // 从服务层获取当前排行榜类型的入池比例
        $betRatio = $this->leaderboardService->getBetRatio($type);

        $this->success(
            __('leaderboard.success'),
            [
                'type' => $type,
                'prize_pool' => number_format($prizePool, 2, '.', ''),
                'leaderboard' => $leaderboard,
                'bet_ratio' => $betRatio,
                'user_rank' => $userRank
            ]
        );
    }

    /**
     * 获取奖金池信息
     * @param Request $request
     * @return Response
     */
    public function getPrizePool(Request $request): Response
    {
        try {
            $params = $request->get();

            $type = $params['type'] ?? 'daily';

            if (!in_array($type, ['daily', 'weekly', 'monthly'])) {
                return json(['code' => 1, 'error' => __('无效的排行榜类型')]);
            }

            $prizePool = $this->leaderboardService->getPrizePool($type);

            return json([
                'code' => 0,
                'error' => '',
                'data' => [
                    'type' => $type,
                    'prize_pool' => number_format($prizePool, 2, '.', '')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Leaderboard@getPrizePool 获取奖金池信息失败: ' . json_encode([
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return json(['code' => 1, 'error' => __('获取奖金池信息失败')]);
        }
    }

    /**
     * 获取排行榜奖励发放记录
     */
    public function getRewardLogs()
    {

            $page = $this->request->param('page', 1);
            $limit = $this->request->param('limit', 10);
            
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 50) $limit = 10;
            
            // 获取奖励记录
            $query = Db::name('account_coin_log')->whereIn('log_type_id',[29,30,31]);
            $query->where('user_id', $this->userInfo['id']);
            
            $total = $query->count();
            $logs = $query->order('create_time', 'desc')->page($page, $limit)->select()->toArray();
            
            if($logs){
                foreach($logs as $key => $value){
                    $logs[$key]['log_type_text'] = CoinLog::getTypeText($value['log_type_id']);
                    $logs[$key]['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
                }
            }

            $this->success('success', [
                'list' => $logs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]);

    }
    

    public function getConfig(Request $request)
    {
        $config = $this->leaderboardService->getLeaderboardConfig();

        // 返回配置信息，包含奖励详情
        $publicConfig = [
            'bet_ratio' => $config['bet_ratio'] ?? 0,
            'daily_limit' => $config['daily_limit'] ?? 100,
            'weekly_limit' => $config['weekly_limit'] ?? 100,
            'monthly_limit' => $config['monthly_limit'] ?? 100,
            'rewards_list' => $config['rewards_list'] ?? []
        ];

        $this->success(__('Get leaderboard config success'), $publicConfig);
    }
}
