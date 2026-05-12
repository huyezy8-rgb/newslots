<?php
namespace app\admin\controller\account;

use app\common\controller\Backend;
use think\facade\Db;
use app\api\enum\CoinLog;
use Throwable;

/**
 * 用户资金流水记录
 */
class AccountCoinLog extends Backend
{
    /**
     * AccountCoinLog模型对象
     * @var object
     */
    protected object $model;

    protected string|array $defaultSortField = 'id,desc';

    protected string|array $quickSearchField = ['id', 'user_id', 'note'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\common\model\AccountCoinLog();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        $params = $this->request->param();
        $where = [];
        
        if (!empty($params['user_id'])) {
            $where[] = ['user_id', '=', $params['user_id']];
        }
        if (isset($params['wallet_type']) && $params['wallet_type'] !== '') {
            $where[] = ['wallet_type', '=', $params['wallet_type']];
        }
        if (!empty($params['log_type_id'])) {
            $where[] = ['log_type_id', '=', $params['log_type_id']];
        }
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $where[] = ['create_time', 'between', [strtotime($params['start_time']), strtotime($params['end_time'])]];
        }
              // 获取当前登录管理员绑定的渠道id（权限控制）
        if ($this->getCurrentAdminChannelId() !== null) {
            $channelId = $this->getCurrentAdminChannelId();
            if ($channelId !== null) {
                $where[] = ['channel_id', '=', $channelId];
            }
        }
        $list = $this->model
            ->where($where)
            ->order('id desc')
            ->paginate([
                'list_rows' => $this->request->get('limit', 20),
                'page' => $this->request->get('page', 1),
            ]);

        // 增加 log_type_text 字段
        foreach ($list as &$item) {
            $item['log_type_text'] = CoinLog::getTypeText((int)$item['log_type_id']);
            // 完善钱包类型映射：0体验、1充值、2佣金、3拼多多
            $item['wallet_type_text'] = match (intval($item['wallet_type'])) {
                0 => '体验钱包',
                1 => '充值钱包',
                2 => '佣金钱包',
                3 => '拼多多钱包',
                default => '未知钱包',
            };
            $item['create_time_text'] = date('Y-m-d H:i:s', $item['create_time']);
        }
        unset($item);

        $this->success('', [
            'total' => $list->total(),
            'list' => $list->items(),
            'page' => $list->currentPage(),
            'limit' => $list->listRows(),
        ]);
    }

    /**
     * 获取流水类型列表
     */
    public function getLogTypes(): void
    {
        $logTypes = [
            ['id' => CoinLog::RegFree, 'name' => '注册赠送'],
            ['id' => CoinLog::Recharge, 'name' => '用户充值'],
            ['id' => CoinLog::Withdraw, 'name' => '余额提现'],
            ['id' => CoinLog::ExWithdraw, 'name' => '体验钱包提现'],
            ['id' => CoinLog::PDDWithdraw, 'name' => 'PDD提现'],
            ['id' => CoinLog::CommissionWithdraw, 'name' => '佣金提取'],
            ['id' => CoinLog::JackpotWithdraw, 'name' => 'Jackpot提现'],
            ['id' => CoinLog::ExWithdrawGift, 'name' => '体验金提现赠送'],
            ['id' => CoinLog::GameBet, 'name' => '游戏下注'],
            ['id' => CoinLog::GameWin, 'name' => '游戏赢得'],
            ['id' => CoinLog::GameRefund, 'name' => '游戏退款'],
            ['id' => CoinLog::WithdrawRefund, 'name' => '余额提现返回'],
            ['id' => CoinLog::ExWithdrawRefund, 'name' => '体验账户提现返回'],
            ['id' => CoinLog::PDDWithdrawRefund, 'name' => 'PDD提现返回'],
            ['id' => CoinLog::SystemOperation, 'name' => '系统操作'],
            ['id' => CoinLog::InternalMessage, 'name' => '站内信活动'],
            ['id' => CoinLog::DayGold, 'name' => '签到活动'],
            ['id' => CoinLog::ExWithdrawBc, 'name' => '体验金补充'],
            ['id' => CoinLog::BindMobile, 'name' => '绑定手机赠送'],
            ['id' => CoinLog::PopUp, 'name' => '弹窗赠送'],
            ['id' => CoinLog::Pwa, 'name' => '添加桌面'],
            ['id' => CoinLog::FirstDeposit270, 'name' => '限时首充'],
            ['id' => CoinLog::FirstDepositDaily, 'name' => '每日首充'],
            ['id' => CoinLog::RescueFunds, 'name' => '救援金'],
            ['id' => CoinLog::DepositVip, 'name' => 'VIP充值'],
            ['id' => CoinLog::RedEnvelope, 'name' => '红包兑换'],
            ['id' => CoinLog::FirstDeposit25, 'name' => '25生涯首充'],
            ['id' => CoinLog::GameVip375, 'name' => 'VIP游戏返利'],
            ['id' => CoinLog::system, 'name' => '系统赠送'],
            ['id' => CoinLog::FirstVip49, 'name' => 'VIP独有充值'],
            ['id' => CoinLog::FirstVip6, 'name' => 'VIP6%充值'],
            ['id' => CoinLog::MemberUpgrade, 'name' => '会员升级奖励'],
            ['id' => CoinLog::ChestBox, 'name' => '宝箱活动奖励'],
            ['id' => CoinLog::LeaderboardDaily, 'name' => '排行榜日榜奖励'],
            ['id' => CoinLog::LeaderboardWeekly, 'name' => '排行榜周榜奖励'],
            ['id' => CoinLog::LeaderboardMonthly, 'name' => '排行榜月榜奖励'],
            ['id' => CoinLog::CommissionBet, 'name' => '投注返佣'],
            ['id' => CoinLog::PDDWithdraw, 'name' => '邀请转盘提现'],
            ['id' => CoinLog::PDDWithdrawRefund, 'name' => '邀请转盘提现返还'],
            ['id' => CoinLog::LuckyWheel, 'name' => '幸运转盘中奖'],
            ['id' => CoinLog::MemberWeeklyReward, 'name' => '会员周奖励'],
            ['id' => CoinLog::MemberMonthlyReward, 'name' => '会员月奖励'],
            ['id' => CoinLog::SevenDayCard, 'name' => '七天卡'],
        ];

        $this->success('', $logTypes);
    }
} 