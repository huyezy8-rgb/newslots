<?php

namespace app\api\controller;

use app\common\model\jackpot\JackpotConfig;
use app\common\model\jackpot\JackpotInvestLog;
use app\common\model\jackpot\JackpotLadderConfig;
use app\common\model\jackpot\JackpotWithdrawLog;
use app\common\service\AccountService;
use think\facade\Db;

class Jackpot extends Base
{
    /*
     * 获取摇摇机信息
     */
    public function index()
    {
        //获取jackpot基础信息
        $jackpotInfo = JackpotConfig::find(1);
        //获取提现数据
        $withdrawInfo = JackpotWithdrawLog::where([
            "user_id" => $this->userInfo->id,
            "status"  => 0,
        ])->find();
        if (!$withdrawInfo) {
            //查询上一次提现
            $last_withdrawInfo
                = JackpotWithdrawLog::where([
                "user_id" => $this->userInfo->id,
                "status"  => 1,
            ])->order("id", "desc")->find();

            if ($last_withdrawInfo) {
                $withdraw_amount
                    = $this->getNextLadder($last_withdrawInfo->withdraw_amount);
                if ($withdraw_amount == null) {
                    $withdraw_amount = 30;
                } else {
                    $withdraw_amount = $withdraw_amount["amount"];
                }
            } else {
                $ladderConfigInfo = JackpotLadderConfig::order("id", "asc")
                    ->find();
                $withdraw_amount  = $ladderConfigInfo->amount;
            }

            $withdrawLogModel                  = new JackpotWithdrawLog();
            $withdrawLogModel->user_id         = $this->userInfo->id;
            $withdrawLogModel->current_amount  = 0;
            $withdrawLogModel->withdraw_amount = $withdraw_amount;
            $withdrawLogModel->status          = 0;
            $withdrawLogModel->save();

            $withdraw_current_amount = 0;
        } else {
            $withdraw_amount         = $withdrawInfo->withdraw_amount;
            $withdraw_current_amount = $withdrawInfo->current_amount;
        }

        $jackpotInfo->bonus_amount = $jackpotInfo->bonus_amount + mt_rand(100,
                500);
        $jackpotInfo->save();

        //获取摇摇记录
        $investLogList = JackpotInvestLog::field("slot_jackpot_invest_log.id,slot_account.nickname, slot_jackpot_invest_log.amount, slot_jackpot_invest_log.create_time")
            ->join("slot_account", "slot_account.id = slot_jackpot_invest_log.user_id")
            ->order("id", "Desc")
            ->limit(5)
            ->select();



        $this->success("", [
            "bonus_amount"            => $jackpotInfo->bonus_amount,
            "daily_invest_threshold"  => $jackpotInfo->daily_invest_threshold,
            "today_sum_bet"           => $this->userInfo->today_sum_bet ?? 0,
            "withdraw_current_amount" => $withdraw_current_amount,
            "withdraw_amount"         => $withdraw_amount,
            "invest_list"             => $investLogList,
        ]);
    }

    /**
     * 摇
     */
    public function shake()
    {
        //获取jackpot基础信息
        $jackpotInfo = JackpotConfig::find(1);
        if ($this->userInfo->today_sum_bet
            < $jackpotInfo->daily_invest_threshold) {
            $this->error(__('Insufficient traffic today'));
        }

        $withdrawInfo = JackpotWithdrawLog::where([
            "user_id" => $this->userInfo->id,
            "status"  => 0,
        ])->order("id", "desc")->find();

        if (!$withdrawInfo) {
            $this->error(__('withdrawInfo error'));
        }

        // 若已满额，需先提现后才能继续摇
        if (bccomp($withdrawInfo->current_amount, $withdrawInfo->withdraw_amount, 2) >= 0) {
            $this->error(__('Please withdraw first'));
        }

        //判断是不是幸运用户
        $luckWithdrawInfo
            = JackpotWithdrawLog::where([
            "withdraw_amount" => $withdrawInfo->withdraw_amount,
            "status"          => 1,
            "is_lucky"        => 1,
        ])->order("id", "desc")->find();
        if ($luckWithdrawInfo) {
            $withdrawCount
                = JackpotWithdrawLog::where([
                "withdraw_amount" => $withdrawInfo->withdraw_amount,
                "status"          => 1,
                "is_lucky"        => 1,
            ])->where("id", ">", $luckWithdrawInfo->id)->count();
        } else {
            $withdrawCount
                = JackpotWithdrawLog::where([
                "withdraw_amount" => $withdrawInfo->withdraw_amount,
                "status"          => 1,
                "is_lucky"        => 1,
            ])->count();
        }

        $ladderConfigInfo = JackpotLadderConfig::where("amount",
            $withdrawInfo->withdraw_amount)->find();
        if (!$ladderConfigInfo) {
            $this->error(__('ladderConfigInfo error'));
        }

        //判断是否当过幸运用户
        $is_lucky_user = JackpotWithdrawLog::where([
            "user_id"  => $this->userInfo->id,
            "is_lucky" => 1,
        ])->find();

        $is_lucky     = 0;
        if ($withdrawCount >= $ladderConfigInfo->lucky_times
            && !$is_lucky_user) {
            $is_lucky     = 1;
            $shake_amount = $withdrawInfo->withdraw_amount;
        } else {
            $shake_amount
                = $this->getRandomAmount($jackpotInfo->shake_ratio_config);
        }

        Db::startTrans();
        try {
            $account = \app\admin\model\Account::find($this->userInfo->id);
            $account->dec("today_sum_bet", $jackpotInfo->daily_invest_threshold)
                ->save();

            $jackInvestLog                  = new JackpotInvestLog();
            $jackInvestLog->user_id         = $this->userInfo->id;
            $jackInvestLog->amount          = $shake_amount;
            $jackInvestLog->withdraw_amount = $withdrawInfo->withdraw_amount;
            $jackInvestLog->is_lucky        = $is_lucky;
            $jackInvestLog->save();

            $diff = $withdrawInfo->withdraw_amount
                - $withdrawInfo->current_amount;
            if ($diff > $shake_amount) {
                $withdrawInfo->current_amount = $withdrawInfo->current_amount
                    + $shake_amount;
            } else {
                $withdrawInfo->current_amount = $withdrawInfo->withdraw_amount;
            }
            $withdrawInfo->save();
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success("", $shake_amount);
    }

    /**
     * 提现
     */
    public function withdrawal()
    {
        // 免手续费，直接转入充值钱包，不创建提现订单，不校验三方账户

       $withdrawInfo = JackpotWithdrawLog::where([
            "user_id" => $this->userInfo->id,
            "status"  => 0,
        ])->order("id", "desc")->find();

       if (!$withdrawInfo) {
            $this->error(__('withdrawInfo error'));
       }

       if ($withdrawInfo->current_amount < $withdrawInfo->withdraw_amount) {
            $this->error(__('Insufficient balance'));
       }
        $amount = $withdrawInfo->current_amount;
       Db::startTrans();
       try {
            $withdrawInfo->status = "1";
            $withdrawInfo->save();
            // 充值钱包入账（免手续费）
            $accountService = new AccountService();
            $accountService->increaseBalance($this->userInfo->id, $amount, 1, \app\api\enum\CoinLog::JackpotWithdraw, __('Jackpot withdraw transfer in'));

            //新增下一次提现
           $withdraw_amount
               = $this->getNextLadder($withdrawInfo->current_amount);
           if ($withdraw_amount == null) {
               $withdraw_amount = 30;
           } else {
               $withdraw_amount = $withdraw_amount["amount"];
           }

           $withdrawLogModel                  = new JackpotWithdrawLog();
           $withdrawLogModel->user_id         = $this->userInfo->id;
           $withdrawLogModel->current_amount  = 0;
           $withdrawLogModel->withdraw_amount = $withdraw_amount;
           $withdrawLogModel->status          = 0;
           $withdrawLogModel->save();

            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success(__('Withdrawal successful'), ['transferred_amount' => $amount]);
    }

    public function withdrawal_record()
    {
        $withdrawInfo = JackpotWithdrawLog::field("id,withdraw_amount,create_time")->where([
            "user_id" => $this->userInfo->id,
            "status"  => 1,
        ])->order("id", "desc")->select();

        $this->success("", $withdrawInfo);
    }


    /**
     * 获取阶梯提现金额
     */
    private function getNextLadder($currentAmount)
    {
        // 获取所有记录按金额升序排列
        $list = JackpotLadderConfig::order('amount', 'asc')->select()
            ->toArray();

        $count = count($list);
        if ($count === 0) {
            return null; // 没有记录
        }

        // 遍历找到当前金额对应的位置
        foreach ($list as $index => $item) {
            if (bccomp($item['amount'], $currentAmount, 2) === 0) {
                // 找到当前金额，返回下一条，末尾就返回第一条
                $nextIndex = ($index + 1) % $count;

                return $list[$nextIndex];
            }
        }

        return null; // 没有找到匹配金额
    }


    /**
     * 获取摇一摇金额
     */
    private function getRandomAmount($probabilityJson)
    {
        $ranges = json_decode($probabilityJson, true);

        // 构建权重数组
        $totalWeight = array_sum($ranges);
        $rand        = mt_rand(1, $totalWeight);

        $accumulated = 0;
        foreach ($ranges as $range => $weight) {
            $accumulated += $weight;
            if ($rand <= $accumulated) {
                // 抽中这个区间，开始生成具体金额
                list($min, $max) = explode('-', $range);

                return round(mt_rand($min * 100, $max * 100) / 100,
                    2); // 保留两位小数
            }
        }

        return null; // 正常不会走到这
    }
}