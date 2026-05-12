<?php

namespace app\api\controller;

use app\common\model\WithdrawAccount as WithdrawAccountModel;
use app\common\service\PaymentMethodService;

class WithdrawAccount extends Base
{
    protected array $noNeedLogin = [];

    /**
     * 获取用户的提现账号列表
     */
    public function index()
    {
        $uniqueTag = $this->request->param('unique_tag', '');
        $userId = $this->userInfo['id'];
        
        $accounts = WithdrawAccountModel::getUserAccounts($userId, $uniqueTag);
        
        $groupedAccounts = [];
        foreach ($accounts as $account) {
            $groupedAccounts[$account['unique_tag']][] = $account;
        }
        
        $this->success(__('OK'), [
            'accounts' => $groupedAccounts,
            'payment_methods' => PaymentMethodService::getAvailableWithdrawMethods()
        ]);
    }
    
    /**
     * 创建提现账号
     */
    public function create()
    {
        $data = $this->request->only([
            'unique_tag',
            'account_name',
            'is_default'
        ]);
        
        $accountInfo = $this->request->param('account_info', []);
        $userId = $this->userInfo['id'];
        
        try {
            $account = WithdrawAccountModel::createAccount(
                $userId,
                $data['unique_tag'],
                $data['account_name'],
                $accountInfo,
                (bool)($data['is_default'] ?? false)
            );
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        
        $this->success(__('Account created successfully'), $account);
    }
    
    /**
     * 更新提现账号
     */
    public function update()
    {
        $accountId = $this->request->param('id');
        $data = $this->request->only(['account_name', 'is_default']);
        $accountInfo = $this->request->param('account_info', []);
        $userId = $this->userInfo['id'];
        
        try {
            $account = WithdrawAccountModel::where('user_id', $userId)->where('id', $accountId)->find();
            
            if (!$account) {
                $this->error(__('Account not found'));
            }
            
            if (!empty($accountInfo)) {
                $validation = PaymentMethodService::validateAccountInfo($account->unique_tag, $accountInfo);
                if (!$validation['valid']) {
                    $this->error($validation['message']);
                }
                
                $account->account_info = $accountInfo; // 直接存储JSON
            }
            
            if (isset($data['account_name'])) {
                $account->account_name = $data['account_name'];
            }
            
            if (isset($data['is_default']) && $data['is_default']) {
                WithdrawAccountModel::setDefault($userId, $accountId);
            }
            
            if (!$account->save()) {
                $this->error(__('Update failed'));
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        
        $this->success(__('Update successful'));
    }
    
    /**
     * 删除提现账号
     */
    public function delete()
    {
        $accountId = $this->request->param('id');
        $userId = $this->userInfo['id'];
        
        try {
            $result = WithdrawAccountModel::deleteAccount($userId, $accountId);
            if (!$result) {
                $this->error(__('Delete failed'));
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        
        $this->success(__('Delete successful'));
    }
    
    /**
     * 设置默认账号
     */
    public function setDefault()
    {
        $accountId = $this->request->param('id');
        $userId = $this->userInfo['id'];
        
        try {
            $result = WithdrawAccountModel::setDefault($userId, $accountId);
            if (!$result) {
                $this->error(__('Set default failed'));
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        
        $this->success(__('Set default successful'));
    }
    

    /**
     * 获取支付方式配置
     */
    public function paymentMethods()
    {
        $configs = PaymentMethodService::getAvailableWithdrawMethods();
        $this->success(__('OK'), $configs);
    }
    
    /**
     * 获取支付方式配置 (下划线命名，用于路由兼容)
     */
    public function payment_methods()
    {
        return $this->paymentMethods();
    }
}

