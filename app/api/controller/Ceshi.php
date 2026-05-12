<?php

namespace app\api\controller;

use app\common\model\Config;
use app\common\model\PddInvitation;
use app\common\model\recharge\Orders;
use app\common\service\MessageService;
use app\common\service\PayGatewayService;
use think\App;
use think\Request;
use think\Response;
use think\facade\Db;

class Ceshi extends Base
{
    protected array $noNeedLogin = ['index','rest_account'];
    public function __construct(App $app)
    {
        parent::__construct($app);

    }
    protected function getPayService(): PayGatewayService
    {
        return $this->payService ??= new PayGatewayService();
    }

    public function closeOrder()
    {
        $orderlist = Orders::where([
            'user_id' => $this->userInfo['id'],
            'pay_status' => 0
        ])->select();  // 修改为select()获取多条记录

        if (!$orderlist->isEmpty()) {  // 修改判断方式
            Db::startTrans();
            try {
                foreach ($orderlist as $order) {
                    $res = $this->getPayService()->closeOrder([
                        'order_no' => $order['order_no'],
                        'pay_type' => "Cashapp"
                    ]);

                    // 更严谨的错误判断
                    if (!$res || (isset($res['success']) && !$res['success'])){
                        throw new \Exception($res['msg'] ?? '关闭支付订单失败');
                    }

                    Orders::where(['order_no' => $order['order_no']])
                        ->update(['pay_status' => 2]);
                }
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                // 保持原有错误处理方式
                $this->error(__('Close order failed'), $e->getMessage());
            }
        }
        $this->success(__('Close order success'), $orderlist);
    }
    public function index(): Response
    {


        event('LevelUp', 442);
//        $user_id = 444;
//        $user_info = Db::name('account')
//            ->where('id', $user_id)
//            ->find();
//        $pddBindMobile       = Config::where('name', 'pdd_bind_mobile')->value('value');
//        list($min, $max) = explode(',', $pddBindMobile);
//        $random = mt_rand($min * 100, $max * 100) / 100;
//
//        $pdd = new PddInvitation();
//        $pdd->insert_invitation($user_info, '2', $random);
        $data = [
            'php_timezone'      => date_default_timezone_get(),
            'thinkphp_timezone' => config('app.default_timezone'),
            'current_time'      => date('Y-m-d H:i:s')
        ];
        $msg = __('Test');
        $this->success($msg,$data);
    }


    public function rest_account()
    {
        $token = $this->request->get('token');
        $account = \app\common\model\Account::where(['token' => $token])->find();
        if (!$account) {
            $this->error(__('未找到用户'));
        }
        $account->token = $token.rand(1000,9999);
        $account->browser_fingerprinting = $account->browser_fingerprinting.rand(1000,9999).rand(1000,9999);
        $account->save();
        if (!$account->save()) {
            $this->error(__('修改失败,再次修改'));
        }
        $this->success(__('修改成功'));
    }

}