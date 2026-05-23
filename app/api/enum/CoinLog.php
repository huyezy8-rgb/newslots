<?php

namespace app\api\enum;

use Chest;

class CoinLog
{
    const RegFree = 1;        // 注册赠送
    const Recharge = 2;       // 充值
    const Withdraw = 3;       // 余额提现（充值钱包）
    const ExWithdraw = 4;     // 体验账户提现（体验钱包）
    const GameBet = 5;     // 游戏下注
    const GameWin = 6;  //游戏赢
    const GameRefund = 7; //游戏报错返回
    const WithdrawRefund = 8; //余额提现返回
    const ExWithdrawRefund = 9; //体验账户提现返回
    const SystemOperation = 10; //系统操作
    const InternalMessage = 11; //站内信活动
    const DayGold = 12; //签到活动
    const ExWithdrawBc =13; //体验金补充
    const BindMobile = 14; //活动-绑定手机赠送
    const PopUp = 15; //活动-弹窗赠送
    const Pwa = 16; //活动-添加桌面
    const FirstDeposit270 = 17; //活动-限时首充270充值

    const FirstDepositDaily = 18; //活动-每日首充

    const RescueFunds = 19; //救援金
    const DepositVip = 20; //VIP充值

    const RedEnvelope = 21; //红包兑换

    const FirstDeposit25 = 22; //活动-25生涯首充
    const GameVip375 = 23; //活动-375vip游戏活动

    const system   = 24; //系统赠送
    const FirstVip49   = 25; //活动 - 49vip独有充值
    const FirstVip6   = 26; //活动 - 6%vip充值
    const MemberUpgrade = 27; //会员升级奖励

    const ChestBox = 28;    //宝箱活动奖励

    const LeaderboardDaily = 29;    //排行榜日榜奖励
    const LeaderboardWeekly = 30;   //排行榜周榜奖励
    const LeaderboardMonthly = 31;  //排行榜月榜奖励
    const CommissionBet = 32;  //投注返佣
    const PDDWithdraw = 33;  //拼多多邀请转盘提现
    const PDDWithdrawRefund = 34;  //邀请转盘提现返还
    const LuckyWheel = 35;  //幸运转盘中奖
    const MemberWeeklyReward = 36;  //会员周奖励
    const MemberMonthlyReward = 37;  //会员月奖励
    const CommissionWithdraw = 38;  //佣金提取到余额

    // PDD 邀请活动专用
    const PDDInitReward = 39;         // PDD 首次初始化赠送
    const PDDInviteReward = 40;       // PDD 邀请注册奖励（累加）
    const PDDQualifiedFill = 41;      // PDD 达标补齐奖励（补到目标值）

    const PDDYqCz = 45;      // PDD 邀请的用户充值 奖励

    // Jackpot 提现（转入充值钱包）
    const JackpotWithdraw = 42;       // Jackpot 提现入账
    
    // 体验金提现赠送
    const ExWithdrawGift = 43;        // 体验金提现赠送

    // 七天卡活动
    const SevenDayCard = 44;        // 七天卡奖励



    public static function walletType($type): string
    {
        return match (intval($type)) {
            self::RegFree     => "experience_wallet",
            self::Recharge    => "recharge_wallet",
            self::Withdraw    => "recharge_wallet",
            self::ExWithdraw  => "experience_wallet",
            self::PDDWithdraw  => "pdd",
            default           => throw new \UnhandledMatchError("未知的钱包类型: $type"),
        };
    }

    public static function getTypeText(int $type): string
    {
        return match ($type) {
            self::RegFree => '注册赠送',
            self::Recharge => '用户充值',
            self::Withdraw => '余额提现',
            self::ExWithdraw => '体验钱包提现',
            self::GameBet => '游戏下注',
            self::GameWin => '游戏赢得',
            self::GameRefund => '游戏返回',
            self::WithdrawRefund => '余额提现返回',
            self::ExWithdrawRefund => '体验账户提现返回',
            self::SystemOperation => '系统操作',
            self::InternalMessage => '站内信活动',
            self::DayGold => '签到活动',
            self::BindMobile => '绑定手机赠送',
            self::PopUp => '弹窗赠送',
            self::Pwa => '添加桌面',
            self::FirstDeposit270 => '限时首充',
            self::FirstDeposit25 => '生涯首充',
            self::FirstDepositDaily => '每日首充',
            self::RescueFunds => '救援金',
            self::DepositVip => 'VIP充值',
            self::RedEnvelope => '红包兑换',
            self::GameVip375 => 'VIP游戏返利',
            self::system => '系统赠送',
            self::FirstVip49 => 'VIP独有充值',
            self::FirstVip6 => 'VIP6%充值',
            self::MemberUpgrade => '会员升级奖励',
            self::ExWithdrawBc => '体验金补充',
            self::ChestBox => '宝箱活动奖励',
            self::LeaderboardDaily => '排行榜日榜奖励',
            self::LeaderboardWeekly => '排行榜周榜奖励',
            self::LeaderboardMonthly => '排行榜月榜奖励',
            self::CommissionBet => '投注返佣',
            self::PDDWithdraw => '邀请转盘提现',
            self::PDDWithdrawRefund => '邀请转盘提现返还',
            self::LuckyWheel => '幸运转盘中奖',
            self::MemberWeeklyReward => '会员周奖励',
            self::MemberMonthlyReward => '会员月奖励',
            self::CommissionWithdraw => '佣金提取到余额',
            self::PDDInitReward => 'PDD首次初始化赠送',
            self::PDDInviteReward => 'PDD邀请奖励',
            self::PDDQualifiedFill => 'PDD达标补齐奖励',
            self::JackpotWithdraw => 'Jackpot提现',
            self::ExWithdrawGift => '体验金提现赠送',
            self::SevenDayCard => '七天卡奖励',
            default => '未知操作',
        };
    }

    public static function getIdByEvent(string $event): int
    {
        return match ($event) {
            // 基础活动/系统
            'internal_message' =>  self::InternalMessage,
            'day_gold' =>  self::DayGold,
            'system' =>  self::system,

            // 充值相关
            'normal' =>  self::Recharge,
            'first_deposit_270' =>  self::FirstDeposit270,
            'first_deposit_25' =>  self::FirstDeposit25,
            'first_deposit_daily' =>  self::FirstDepositDaily,
            'deposit_vip1' =>  self::DepositVip,
            'deposit_vip2' =>  self::DepositVip,
            'deposit_vip3' =>  self::DepositVip,
            'first_vip_49' =>  self::FirstVip49,
            'first_vip_6' =>  self::FirstVip6,
            'seven_day_card' => self::SevenDayCard,

            // 会员/排行榜
            'member_upgrade' =>  self::MemberUpgrade,
            'member_weekly_reward' =>  self::MemberWeeklyReward,
            'member_monthly_reward' =>  self::MemberMonthlyReward,
            'leaderboard_daily' => self::LeaderboardDaily,
            'leaderboard_weekly' => self::LeaderboardWeekly,
            'leaderboard_monthly' => self::LeaderboardMonthly,

            // 游戏/返利
            'game_vip_375' =>  self::GameVip375,
            'commission_bet' => self::CommissionBet,

            // 提现/退款（通用）
            'withdraw' => self::Withdraw,
            'withdraw_refund' => self::WithdrawRefund,
            'ex_withdraw' => self::ExWithdraw,
            'ex_withdraw_refund' => self::ExWithdrawRefund,
            'commission_withdraw' => self::CommissionWithdraw,

            // PDD 专用
            'pdd_withdraw' => self::PDDWithdraw,
            'pdd_init_reward' => self::PDDInitReward,
            'pdd_invite_reward' => self::PDDInviteReward,
            'pdd_qualified_fill' => self::PDDQualifiedFill,

            // Jackpot 提现（入账）
            'jackpot_withdraw' => self::JackpotWithdraw,

            // 体验金提现赠送
            'ex_withdraw_gift' => self::ExWithdrawGift,

            default => self::system,
        };
    }

    public static function getWalletType(string $wallet): int
    {
        return match ($wallet) {
            'experience_wallet'=>0,
            'recharge_wallet'=>1,
            'commission_balance'=>2,
            'pdd_reward'=>3,
            default => 0,
        };
    }
}
