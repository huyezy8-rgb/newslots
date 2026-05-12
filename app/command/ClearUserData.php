<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class ClearUserData extends Command
{
    protected function configure()
    {
        $this->setName('clear:user')
            ->setDescription('清空用户相关数据表');
    }

    protected function execute(Input $input, Output $output)
    {
        // 用户相关表（不含配置表）
        $tables = [
            // 用户主表及资金
            'slot_account',                  // 用户表
            'slot_account_coin_log',         // 用户资金流水记录
            'slot_user',                     // 会员表
            'slot_user_money_log',           // 会员余额变动记录
            'slot_user_score_log',           // 会员积分变动记录
            'slot_user_rule',                // 会员权限规则
            'slot_user_group',               // 会员分组

            // 用户行为与日志
            'slot_user_collect_game',        // 用户收藏游戏
            'slot_user_login_game_log',      // 用户登录游戏日志
            'slot_sms_verify',               // 短信验证码记录

            // 用户活动参与
            'slot_activity_first_deposit_25_user',   // 首充25活动参与记录
            'slot_activity_first_deposit_270_user',  // 首充270活动参与记录
            'slot_activity_first_deposit_daily_user',// 每日首充活动参与记录
            'slot_activity_gamevip_user',            // VIP活动参与记录
            'slot_activity_daygold_user',            // 每日奖励活动参与记录
            'slot_activity_deposit_vip_user',        // VIP充值活动参与记录
            'slot_activity_reward',                  // 活动奖励记录

            // 用户充值、提现、订单
            'slot_recharge_orders',                  // 用户充值订单
            'slot_withdraw_orders',                  // 用户提现订单

            // 红包、积分、日志等
            'slot_red_envelope_redemption_code',     // 红包兑换码
            'slot_red_envelope_redemption_record',   // 红包兑换记录

            // Facebook事件日志
            'slot_facebook_events_log',              // Facebook事件日志

            // jackpot相关
            'slot_jackpot_invest_log',               // jackpot投资记录
            'slot_jackpot_withdraw_log',             // jackpot提现记录

            // 消息、救援金、运营数据、拼多多活动
            'slot_messages',                         // 用户消息表
            'slot_rescue_funds',                     // 救援金记录
            'slot_operation_data',                   // 运营数据
            'slot_pdd_invitation',                   // 拼多多邀请记录
            'slot_pdd_progress',                     // 拼多多进度记录
            //游戏
            'slot_game_transactions'                 //游戏记录
        ];

        foreach ($tables as $table) {
            try {
                Db::execute("TRUNCATE TABLE `{$table}`");
                $output->writeln("已清空表：{$table}");
            } catch (\Exception $e) {
                $output->writeln("清空表 {$table} 失败：" . $e->getMessage());
            }
        }

        $output->writeln('用户相关数据表已全部清空！');
    }
} 