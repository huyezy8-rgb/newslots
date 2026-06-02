<?php

namespace app\api\controller;

use app\admin\model\Config;
use app\api\enum\CoinLog;
use app\common\model\Account;
use app\common\model\AccountCoinLog;
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

            Db::startTrans();
            try {
                $account = Account::where('id', $this->userInfo->id)
                    ->lock(true)
                    ->find();

                if (!$account) {
                    Db::rollback();
                    $this->error(__('Token error'));
                }

                $codeInfo = RedEnvelopeRedemptionCode::where('code', $data["code"])
                    ->find();

                if (!$codeInfo) {
                    Db::rollback();
                    $this->error(__('Redemption code invalid')); // 兑换码无效
                }

                $createTime = intval($codeInfo['create_time'] ?? 0);
                $expireHours = intval($codeInfo['expire_hours'] ?? 0);
                if ($expireHours > 0 && $createTime > 0 && time() > $createTime + ($expireHours * 3600)) {
                    Db::rollback();
                    $this->error(__('Redemption code expired')); // 兑换码已过期
                }

                // 查询最大兑换次数
                $maxTimesValue = Config::where('name', 'maximum_daily_redemption_times')
                    ->value('value');
                $maxTimes = ($maxTimesValue === null || $maxTimesValue === '') ? 10 : intval($maxTimesValue);

                if ($maxTimes > 0) {
                    $startOfToday = strtotime(date('Y-m-d'));

                    $count = RedEnvelopeRedemptionRecord::where('user_id', $account->id)
                        ->where('create_time', '>=', $startOfToday)
                        ->count();

                    if ($count >= $maxTimes) {
                        Db::rollback();
                        $this->error(__('Today redemption times reached limit')); // 今日兑换次数已达上限
                    }
                }

                $perUserLimit = intval($codeInfo['per_user_limit'] ?? 1);
                $userUsedCount = RedEnvelopeRedemptionRecord::where('user_id', $account->id)
                    ->where('code_id', $codeInfo['id'])
                    ->count();

                if ($perUserLimit > 0 && $userUsedCount >= $perUserLimit) {
                    Db::rollback();
                    $this->error(__('Redemption code user limit reached')); // 兑换码领取次数已达上限
                }

                $amount = mt_rand(
                        $codeInfo['amount_min'] * 100,
                        $codeInfo['amount_max'] * 100
                    ) / 100;

                // 不再标记兑换码为已使用，因为改为一码多用
                // 每个用户每个码只能使用一次的限制通过RedEnvelopeRedemptionRecord表来维护

                // 添加兑换记录
                $wallet_type = $account->sum_recharge == 0 ? 0 : 1;
                RedEnvelopeRedemptionRecord::create([
                        'user_id'     => $account->id,
                        'code_id'     => $codeInfo['id'],
                        'amount'      => $amount,
                        'wallet_type' => $wallet_type,
                    ]);

                $coinLog = new AccountCoinLog();
                $coinLog->user_id = $account->id;
                if ($wallet_type == 0) {
                    $oldNum = (float)$account->experience_wallet;
                    $newNum = $oldNum + $amount;
                    $account->experience_wallet = $newNum;
                } else {
                    $oldNum = (float)$account->recharge_wallet;
                    $newNum = $oldNum + $amount;
                    $account->recharge_wallet = $newNum;
                }
                $coinLog->old_num = $oldNum;
                $coinLog->new_num = $newNum;
                $coinLog->num = $amount;
                $coinLog->log_type_id = CoinLog::RedEnvelope;
                $coinLog->note = CoinLog::getTypeText(CoinLog::RedEnvelope);

                if (!$account->save() || !$coinLog->save()) {
                    throw new \Exception(__('Redemption failed'));
                }

                Db::commit();
            } catch (\think\exception\HttpResponseException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Db::rollback();
                $this->error(__('Redemption failed: %s', [$e->getMessage()])); // 兑换失败
            }

            $this->success();

        }
    }
}
