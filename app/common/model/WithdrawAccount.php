<?php

namespace app\common\model;

use think\Model;
use app\common\service\AccountMaskService;
use app\common\service\PaymentMethodService;

class WithdrawAccount extends Model
{
    protected $name = 'withdraw_accounts';
    
    protected $autoWriteTimestamp = true;
    
    protected $json = ['account_info'];
    
    /**
     * 获取用户的所有提现账号
     */
    public static function getUserAccounts(int $userId, ?string $uniqueTag = null): array
    {
        $query = self::alias('wa')
                    ->join('payment_methods pm', 'wa.payment_method_id = pm.id')
                    ->where('wa.user_id', $userId)
                    ->where('wa.status', 1)
                    ->where('pm.status', 1)
                    ->field('wa.*, pm.name as payment_name, pm.icon as payment_icon, pm.unique_tag')
                    ->order('wa.is_default', 'desc')
                    ->order('wa.create_time', 'desc');
        
        if ($uniqueTag) {
            $query->where('wa.unique_tag', $uniqueTag);
        }
        
        $accounts = $query->select();
        
        foreach ($accounts as $account) {
            // 直接使用JSON数据，无需解密
            $account->account_info = $account->account_info ?: [];
            // 确保 account_info 是数组类型
            if (is_object($account->account_info)) {
                $account->account_info = (array) $account->account_info;
            }
            $account->masked_info = AccountMaskService::maskAccountInfo(
                $account->account_info, 
                $account->unique_tag
            );
        }
        
        return $accounts->toArray();
    }
    
    /**
     * 获取用户的默认账号
     */
    public static function getDefaultAccount(int $userId, string $uniqueTag): ?array
    {
        $account = self::alias('wa')
                      ->join('payment_methods pm', 'wa.payment_method_id = pm.id')
                      ->where('wa.user_id', $userId)
                      ->where('wa.unique_tag', $uniqueTag)
                      ->where('wa.is_default', 1)
                      ->where('wa.status', 1)
                      ->where('pm.status', 1)
                      ->field('wa.*, pm.name as payment_name, pm.icon as payment_icon')
                      ->find();
        
        if (!$account) {
            return null;
        }
        
        $account->account_info = $account->account_info ?: [];
        return $account->toArray();
    }
    
    /**
     * 创建提现账号
     */
    public static function createAccount(int $userId, string $uniqueTag, string $accountName, array $accountInfo, bool $isDefault = false): array
    {
        $paymentMethod = \app\common\model\PaymentMethods::where('unique_tag', $uniqueTag)
                                                       ->where('status', 1)
                                                       ->where('pay_method', 'in', ['0', '2'])
                                                       ->find();
        
        if (!$paymentMethod) {
            throw new \Exception(__('Unsupported payment method'));
        }
        
        $validation = PaymentMethodService::validateAccountInfo($uniqueTag, $accountInfo);
        if (!$validation['valid']) {
            throw new \Exception($validation['message']);
        }
        
        // 检查是否已存在相同账号
        $existing = self::where('user_id', $userId)
                       ->where('unique_tag', $uniqueTag)
                       ->where('account_info', json_encode($accountInfo, JSON_UNESCAPED_UNICODE))
                       ->find();
        
        if ($existing) {
            throw new \Exception(__('Account information already exists'));
        }
        
        // 如果设置为默认，先取消同类型的其他默认账号
        if ($isDefault) {
            self::clearDefaultForUserAndType($userId, $uniqueTag);
        }
        
        $account = new self();
        $account->user_id = $userId;
        $account->payment_method_id = $paymentMethod->id;
        $account->unique_tag = $uniqueTag;
        $account->account_name = $accountName;
        $account->account_info = $accountInfo; // 直接存储JSON，无需加密
        $account->is_default = $isDefault ? 1 : 0;
        $account->status = 1;
        
        if (!$account->save()) {
            throw new \Exception(__('Failed to create account'));
        }
        
        return $account->toArray();
    }
    
    /**
     * 设置默认账号
     */
    public static function setDefault(int $userId, int $accountId): bool
    {
        $account = self::where('user_id', $userId)->where('id', $accountId)->find();
        
        if (!$account) {
            throw new \Exception(__('Account not found'));
        }
        
        // 先取消同类型的其他默认账号
        self::clearDefaultForUserAndType($userId, $account->unique_tag);
        
        return $account->save(['is_default' => 1]);
    }
    
    /**
     * 清除用户指定支付方式的默认账号
     */
    private static function clearDefaultForUserAndType(int $userId, string $uniqueTag): void
    {
        self::where('user_id', $userId)
            ->where('unique_tag', $uniqueTag)
            ->where('is_default', 1)
            ->update(['is_default' => 0]);
    }
    
    /**
     * 删除账号
     */
    public static function deleteAccount(int $userId, int $accountId): bool
    {
        $account = self::where('user_id', $userId)->where('id', $accountId)->find();
        
        if (!$account) {
            throw new \Exception(__('Account not found'));
        }
        
        return $account->delete();
    }
}

