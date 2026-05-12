<?php

namespace app\admin\controller\account;

use app\api\enum\CoinLog;
use app\common\controller\Backend;
use app\admin\model\account\Account as AccountModel;
use app\common\model\AccountCoinLog;
use app\common\traits\ChannelFilter;
use Throwable;
/**
 * 用户管理
 */
class Account extends Backend
{
     use ChannelFilter;
    /**
     * Account模型对象
     * @var object
     * @phpstan-var AccountModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['id', 'name', 'nickname'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AccountModel();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        // 添加渠道权限过滤
        $where = $this->addChannelFilter($where, 'channel_id');

        // 获取在线用户列表，仅用于统计
        $onlineUsers = $this->getOnlineUsers();
        if (empty($onlineUsers)) {
            $onlineUsers = [];
        } elseif (is_array($onlineUsers)) {
            $onlineUsers = array_reduce($onlineUsers, function($carry, $item) {
                return array_merge($carry, is_array($item) ? $item : [$item]);
            }, []);
            if (count($onlineUsers) === 1 && is_string($onlineUsers[0]) && strpos($onlineUsers[0], '[') === 0) {
                $jsonDecoded = json_decode($onlineUsers[0], true);
                if (is_array($jsonDecoded)) {
                    $onlineUsers = $jsonDecoded;
                }
            }
            $onlineUsers = array_filter(array_map('intval', $onlineUsers));
        } else {
            $onlineUsers = [];
        }

        // 应用渠道过滤到在线用户列表
        $channelId = $this->getCurrentAdminChannelId();
        
        // 验证在线用户ID是否在数据库中真实存在，并应用渠道过滤
        if (!empty($onlineUsers)) {
            $query = $this->model->whereIn('id', $onlineUsers);
            if ($channelId !== null) {
                $query->where('channel_id', $channelId);
            }
            // 只保留数据库中真实存在的用户ID
            $validOnlineUserIds = $query->column('id');
            $onlineUsers = array_intersect($onlineUsers, $validOnlineUserIds);
        } else {
            $onlineUsers = [];
        }

        // 确保包含 game_status 字段
        $fields = is_array($this->indexField) ? $this->indexField : explode(',', $this->indexField);
        if (!in_array('game_status', $fields)) {
            $fields[] = 'game_status';
        }
        
        $res = $this->model
            ->field($fields)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);



        // 为每个用户添加在线状态和默认游戏状态
        foreach ($res->items() as $user) {
            $uid = is_array($user->id) ? (isset($user->id[0]) ? $user->id[0] : 0) : $user->id;
            $user->is_online = in_array($uid, $onlineUsers, true);
            // 如果 game_status 为空，默认为1（可玩）
            if (!isset($user->game_status) || $user->game_status === null) {
                $user->game_status = 1;
            }
        }

        $onlineCount = count($onlineUsers);
        
        // 计算总用户数，需要应用渠道过滤
        if ($channelId !== null) {
            $totalCount = $this->model->where('channel_id', $channelId)->count();
        } else {
            $totalCount = $this->model->count();
        }

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
            'online_count' => $onlineCount,
            'total_count' => $totalCount,
        ]);
    }

    /**
     * 获取在线用户列表
     * @return array
     */
    private function getOnlineUsers(): array
    {
        try {
            $onlineUsers = \think\facade\Cache::store('redis')->sMembers('online_users');
            return $onlineUsers ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }


    /**
     * 编辑
     * @throws Throwable
     */
    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            // 后台编辑密码：为空不修改，非空需校验并加密
            $plainPassword = $this->request->post('password', '');
            if ($plainPassword === '' || $plainPassword === null) {
                unset($data['password']);
            } else {
                if (!preg_match('/^[A-Za-z0-9]{6,18}$/', (string)$plainPassword)) {
                    $this->error(__('Password must be 6-18 letters or numbers'));
                }
                $data['password'] = password_hash($plainPassword, PASSWORD_DEFAULT);
            }
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {

                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }

                if ($row["experience_wallet"] != $data["experience_wallet"]) {

                    $isIncrease = $data["experience_wallet"] - $row["experience_wallet"];
                    //加记录
                    AccountCoinLog::create([
                        'user_id'     => $row["id"],
                        'wallet_type' => 0,
                        'old_num'     => $row["experience_wallet"],
                        'num'         => $isIncrease,
                        'new_num'     => $data["experience_wallet"],
                        'log_type_id' => CoinLog::SystemOperation,
                        'note'        => "系统操作",
                        'create_time' => time(),
                        'update_time' => time(),
                    ]);
                }

                if ($row["recharge_wallet"] != $data["recharge_wallet"]) {

                    $isIncrease = $data["recharge_wallet"] - $row["recharge_wallet"];
                    //加记录
                    AccountCoinLog::create([
                        'user_id'     => $row["id"],
                        'wallet_type' => 1,
                        'old_num'     => $row["recharge_wallet"],
                        'num'         => $isIncrease,
                        'new_num'     => $data["recharge_wallet"],
                        'log_type_id' => CoinLog::SystemOperation,
                        'note'        => "系统操作",
                        'create_time' => time(),
                        'update_time' => time(),
                    ]);
                }
                if(  $row['switch_wallet'] == 0 && $data["recharge_wallet"]>0){
                    $data['switch_wallet'] = 1;
                }
                $result = $row->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $row->password = '';
        $this->success('', [
            'row' => $row
        ]);
    }


    /**
     * 下拉列表
     * @throws Throwable
     */
    public function select(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        
        // 添加渠道权限过滤
        $where = $this->addChannelFilter($where, 'id');
        
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 详情 - 基础信息
     * @throws Throwable
     */
    public function detail(): void
    {
        $userId = (int)$this->request->param('id');
        if ($userId <= 0) {
            $this->error('参数错误');
        }
        // 权限：用户是否属于当前渠道
        $this->checkUserChannelPermission($userId);

        $base = $this->model->where('id', $userId)->find();
        if (!$base) {
            $this->error('用户不存在');
        }

        // 获取充值统计
        $rechargeStats = \think\facade\Db::name('recharge_orders')
            ->where('user_id', $userId)
            ->field([
                'COUNT(*) as recharge_count',
                'SUM(CASE WHEN pay_status = 1 THEN amount ELSE 0 END) as total_recharge_amount',
                'SUM(CASE WHEN pay_status = 1 THEN 1 ELSE 0 END) as success_recharge_count'
            ])
            ->find();

        // 获取提现统计
        $withdrawStats = \think\facade\Db::name('withdraw_orders')
            ->where('user_id', $userId)
            ->field([
                'COUNT(*) as withdraw_count',
                'SUM(CASE WHEN status = 2 THEN amount ELSE 0 END) as total_withdraw_amount'
            ])
            ->find();

        // 合并统计数据到基础信息
        $base['total_recharge_amount'] = $rechargeStats['total_recharge_amount'] ?? 0;
        $base['recharge_count'] = $rechargeStats['recharge_count'] ?? 0;
        $base['success_recharge_count'] = $rechargeStats['success_recharge_count'] ?? 0;
        $base['total_withdraw_amount'] = $withdrawStats['total_withdraw_amount'] ?? 0;
        $base['withdraw_count'] = $withdrawStats['withdraw_count'] ?? 0;

        $this->success('', [
            'base' => $base,
        ]);
    }

    /**
     * 详情 - 充值记录
     * @throws Throwable
     */
    public function rechargeList(): void
    {
        $userId = (int)$this->request->param('id');
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 10);
        $this->checkUserChannelPermission($userId);

        $query = \think\facade\Db::name('recharge_orders')->where('user_id', $userId)->order('id', 'desc');
        $total = (clone $query)->count();
        $list = $query->page($page, $limit)->select()->toArray();
        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 详情 - 提现记录
     * @throws Throwable
     */
    public function withdrawList(): void
    {
        $userId = (int)$this->request->param('id');
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 10);
        $this->checkUserChannelPermission($userId);

        $query = \think\facade\Db::name('withdraw_orders')->where('user_id', $userId)->order('id', 'desc');
        $total = (clone $query)->count();
        $list = $query->page($page, $limit)->select()->toArray();
        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 详情 - 游戏记录
     * @throws Throwable
     */
    public function gameList(): void
    {
        $userId = (int)$this->request->param('id');
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 10);
        $this->checkUserChannelPermission($userId);

        $query = \think\facade\Db::name('game_transactions')->where('user_id', $userId)->order('id', 'desc');
        $total = (clone $query)->count();
        $list = $query->page($page, $limit)->select()->toArray();
        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 详情 - 资金流水
     * @throws Throwable
     */
    public function coinLogList(): void
    {
        $userId = (int)$this->request->param('id');
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 10);
        $this->checkUserChannelPermission($userId);

        $query = \think\facade\Db::name('account_coin_log')->where('user_id', $userId)->order('id', 'desc');
        $total = (clone $query)->count();
        $list = $query->page($page, $limit)->select()->toArray();
        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 详情 - 提现账户列表
     * @throws Throwable
     */
    public function withdrawAccountList(): void
    {
        $userId = (int)$this->request->param('id');
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 10);
        $this->checkUserChannelPermission($userId);

        $query = \think\facade\Db::name('withdraw_accounts')
            ->alias('wa')
            ->join('payment_methods pm', 'wa.payment_method_id = pm.id')
            ->where('wa.user_id', $userId)
            ->field('wa.*, pm.name as payment_name, pm.icon as payment_icon, pm.unique_tag')
            ->order('wa.is_default', 'desc')
            ->order('wa.create_time', 'desc');
        
        $total = (clone $query)->count();
        $list = $query->page($page, $limit)->select()->toArray();
        
        // 处理账号信息，不做脱敏处理，直接显示完整信息
        foreach ($list as &$item) {
            $item['account_info'] = json_decode($item['account_info'] ?? '{}', true) ?: [];
        }
        
        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 编辑提现账户
     * @throws Throwable
     */
    public function editWithdrawAccount(): void
    {
        $id = (int)$this->request->post('id');
        $accountName = $this->request->post('account_name', '');
        $isDefault = (int)$this->request->post('is_default', 0);
        $status = (int)$this->request->post('status', 1);
        $accountInfo = $this->request->post('account_info', []);

        if ($id <= 0) {
            $this->error('参数错误');
        }

        $account = \think\facade\Db::name('withdraw_accounts')->where('id', $id)->find();
        if (!$account) {
            $this->error('账户不存在');
        }

        $userId = $account['user_id'];
        $this->checkUserChannelPermission($userId);

        // 如果设置为默认，先取消同类型的其他默认账号
        if ($isDefault == 1) {
            \think\facade\Db::name('withdraw_accounts')
                ->where('user_id', $userId)
                ->where('unique_tag', $account['unique_tag'])
                ->where('id', '<>', $id)
                ->where('is_default', 1)
                ->update(['is_default' => 0]);
        }

        $updateData = [
            'account_name' => $accountName,
            'is_default' => $isDefault,
            'status' => $status,
            'update_time' => time(),
        ];

        // 如果提供了账号信息，则更新
        if (!empty($accountInfo) && is_array($accountInfo)) {
            $updateData['account_info'] = json_encode($accountInfo, JSON_UNESCAPED_UNICODE);
        }

        \think\facade\Db::name('withdraw_accounts')
            ->where('id', $id)
            ->update($updateData);

        $this->success('保存成功');
    }

    /**
     * 删除提现账户
     * @throws Throwable
     */
    public function deleteWithdrawAccount(): void
    {
        $id = (int)$this->request->post('id');

        if ($id <= 0) {
            $this->error('参数错误');
        }

        $account = \think\facade\Db::name('withdraw_accounts')->where('id', $id)->find();
        if (!$account) {
            $this->error('账户不存在');
        }

        $userId = $account['user_id'];
        $this->checkUserChannelPermission($userId);

        // 不能删除默认账号
        if ($account['is_default'] == 1) {
            $this->error('不能删除默认账号');
        }

        \think\facade\Db::name('withdraw_accounts')->where('id', $id)->delete();

        $this->success('删除成功');
    }

    /**
     * 用户游戏状态列表
     * @throws Throwable
     */
    public function userGameStatusList(): void
    {
        $userId = (int)$this->request->param('id');
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 50);
        $this->checkUserChannelPermission($userId);

        // 获取用户信息
        $user = $this->model->where('id', $userId)->find();
        if (!$user) {
            $this->error('用户不存在');
        }

        // 获取用户游戏状态（0=不可玩，1=可玩）
        $gameStatus = isset($user['game_status']) ? (int)$user['game_status'] : 1;

        // 获取所有游戏
        $games = \think\facade\Db::name('game_lists')
            ->field('id, game_id, game_name, game_name_en, brand')
            ->where('status', 1)
            ->order('id', 'asc')
            ->select()
            ->toArray();

        // 所有游戏使用相同的状态
        foreach ($games as &$game) {
            $game['status'] = $gameStatus;
            $game['update_time'] = $user['update_time'] ?? null;
        }

        $total = count($games);
        $offset = ($page - 1) * $limit;
        $list = array_slice($games, $offset, $limit);

        $this->success('', [
            'list' => $list, 
            'total' => $total,
            'game_status' => $gameStatus // 返回当前用户游戏状态
        ]);
    }

    /**
     * 更新用户游戏状态
     * @throws Throwable
     */
    public function updateUserGameStatus(): void
    {
        $userId = (int)$this->request->post('user_id');
        $status = (int)$this->request->post('status', 1);

        if ($userId <= 0) {
            $this->error('参数错误');
        }

        if (!in_array($status, [0, 1])) {
            $this->error('状态值错误');
        }

        $this->checkUserChannelPermission($userId);

        // 更新用户游戏状态
        $this->model->where('id', $userId)->update([
            'game_status' => $status,
            'update_time' => time(),
        ]);

        $this->success('状态更新成功');
    }

    /**
     * 批量更新用户游戏状态（已废弃，保留接口兼容性）
     * @throws Throwable
     */
    public function batchUpdateUserGameStatus(): void
    {
        // 由于现在只有一个全局状态，批量更新等同于单个更新
        $userId = (int)$this->request->post('user_id');
        $status = (int)$this->request->post('status', 1);

        if ($userId <= 0) {
            $this->error('参数错误');
        }

        if (!in_array($status, [0, 1])) {
            $this->error('状态值错误');
        }

        $this->checkUserChannelPermission($userId);

        // 更新用户游戏状态
        $this->model->where('id', $userId)->update([
            'game_status' => $status,
            'update_time' => time(),
        ]);

        $this->success('状态更新成功');
    }
}