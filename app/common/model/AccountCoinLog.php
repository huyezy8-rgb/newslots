<?php
declare (strict_types=1);

namespace app\common\model;


use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class AccountCoinLog extends Model
{

    protected $name = 'account_coin_log';

    protected $autoWriteTimestamp = true;


    /**
     * 设置余额
     *
     * @param $num
     */
    public function UpdateBalance($user_id, $source_type, $wallet_type, $num, $remark)
    {
        Db::startTrans();
        try {
            //获取用户信息
            $account = Account::where('id', $user_id)->find();
            if (!$account) {
                throw new \Exception("用户不存在");
            }

            $this->user_id     = $user_id;
            if ($wallet_type == 0) {
                $this->old_num              = $account->experience_wallet;
                $account->experience_wallet += $num;
            } else {
                $this->old_num            = $account->recharge_wallet;
                $account->recharge_wallet += $num;
            }
            $this->new_num     = $this->old_num + $num;
            $this->num         = $num;
            $this->log_type_id = $source_type;
            $this->note        = $remark;;

            if ($this->save() && $account->save()) {
                Db::commit();
            } else {
                Db::rollback();
                throw new \Exception("更新失败");
            }

        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
    }
}
