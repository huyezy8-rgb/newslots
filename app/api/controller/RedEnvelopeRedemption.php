<?php

namespace app\api\controller;

use app\admin\model\Config;
use app\api\enum\CoinLog;
use app\common\model\RedEnvelopeRedemptionCode;
use app\common\model\RedEnvelopeRedemptionRecord;
use think\facade\Db;

class RedEnvelopeRedemption extends Base
{
    /**
     * 红包兑换
     */
    public function receive()
    {
        if ($this->request->isPost()) {
            $data = $this->request->only([
                'code',
            ]);

            if (!isset($data["code"])) {
                $this->error(__('Code cannot be empty')); // code 不能为空
            }

// 查询最大兑换次数
            $maxTimesValue = Config::where('name', 'maximum_daily_redemption_times')
                ->value('value');
            $maxTimes = ($maxTimesValue === null || $maxTimesValue === '') ? 10 : intval($maxTimesValue);

            if ($maxTimes > 0) {
                // 今天起始时间戳
                $startOfToday = strtotime(date('Y-m-d'));

                // 查询今天兑换次数
                $count = RedEnvelopeRedemptionRecord::where('user_id', $this->userInfo->id)
                    ->where('create_time', '>=', $startOfToday)
                    ->count();

                if ($count >= $maxTimes) {
                   $this->error(__('Today redemption times reached limit')); // 今日兑换次数已达上限
                }
            }

            // 查找兑换码（不再检查is_used，因为改为一码多用）
            $codeInfo = RedEnvelopeRedemptionCode::where('code', $data["code"])
                ->find();

            if (!$codeInfo) {
                $this->error(__('Redemption code invalid')); // 兑换码无效
            }

            // 检查当前用户是否已经使用过这个兑换码
            $userUsedCode = RedEnvelopeRedemptionRecord::where('user_id', $this->userInfo->id)
                ->where('code_id', $codeInfo['id'])
                ->find();

            if ($userUsedCode) {
                $this->error(__('You have already used this redemption code')); // 您已经使用过此兑换码
            }

            Db::startTrans();
            try {
                // 生成随机金额
                $amount = mt_rand(
                        $codeInfo['amount_min'] * 100,
                        $codeInfo['amount_max'] * 100
                    ) / 100;

                // 不再标记兑换码为已使用，因为改为一码多用
                // 每个用户每个码只能使用一次的限制通过RedEnvelopeRedemptionRecord表来维护

                // 添加兑换记录
                $wallet_type = $this->userInfo->sum_recharge == 0 ? 0 : 1;
                RedEnvelopeRedemptionRecord::create([
                        'user_id'     => $this->userInfo->id,
                        'code_id'     => $codeInfo['id'],
                        'amount'      => $amount,
                        'wallet_type' => $wallet_type,
                    ]);

                $coinLog = new \app\common\model\AccountCoinLog();
                $coinLog->UpdateBalance($this->userInfo->id, CoinLog::RedEnvelope, $wallet_type,
                    $amount, CoinLog::getTypeText(CoinLog::RedEnvelope));

                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                $this->error(__('Redemption failed: %s', [$e->getMessage()])); // 兑换失败
            }

            $this->success();

        }
    }
}
