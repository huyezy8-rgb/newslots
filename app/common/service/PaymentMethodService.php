<?php

namespace app\common\service;

use app\common\model\PaymentMethods;

class PaymentMethodService
{
    /**
     * 获取可用的提现支付方式
     */
    public static function getAvailableWithdrawMethods(): array
    {
        $methods = \think\facade\Db::name('payment_methods')
            ->where('status', 1)
            ->where('pay_method', 'in', ['0', '2'])
            ->field('id,unique_tag,name,description,icon,show,field_config,validation_rules')
            ->select();
        
        $result = [];
        foreach ($methods as $method) {
            $data = $method;
            $data['field_config'] = is_string($method['field_config']) ? json_decode($method['field_config'], true) : ($method['field_config'] ?: []);
            $data['validation_rules'] = is_string($method['validation_rules']) ? json_decode($method['validation_rules'], true) : ($method['validation_rules'] ?: []);
            $result[] = $data;
        }
        
        return $result;
    }
    
    /**
     * 获取支付方式配置
     */
    public static function getPaymentMethodConfig(string $uniqueTag): ?array
    {
        $method = \think\facade\Db::name('payment_methods')
                               ->where('unique_tag', $uniqueTag)
                               ->where('status', 1)
                               ->where('pay_method', 'in', ['0', '2'])
                               ->field('id,unique_tag,name,description,icon,show,field_config,validation_rules')
                               ->find();
        
        if (!$method) {
            return null;
        }
        
        $config = $method;
        
        // 处理JSON字段
        $config['field_config'] = is_string($method['field_config']) ? json_decode($method['field_config'], true) : ($method['field_config'] ?: []);
        $config['validation_rules'] = is_string($method['validation_rules']) ? json_decode($method['validation_rules'], true) : ($method['validation_rules'] ?: []);
        
        return $config;
    }
    
    /**
     * 验证账号信息
     */
    public static function validateAccountInfo(string $uniqueTag, array $accountInfo): array
    {
        $config = self::getPaymentMethodConfig($uniqueTag);
        if (!$config) {
            return ['valid' => false, 'message' => '不支持的支付方式'];
        }
        
        $fieldConfig = $config['field_config'] ?? [];
        $requiredFields = $fieldConfig['required_fields'] ?? [];
        $fieldLabels = $fieldConfig['field_labels'] ?? [];
        
        $errors = [];
        foreach ($requiredFields as $field) {
            if (!isset($accountInfo[$field]) || empty($accountInfo[$field])) {
                $label = $fieldLabels[$field] ?? $field;
                $errors[] = $label . '不能为空';
            }
        }
        
        return [
            'valid' => empty($errors),
            'message' => implode('，', $errors)
        ];
    }
    
    /**
     * 获取所有支付方式的完整配置
     */
    public static function getAllPaymentMethodConfigs(): array
    {
        $methods = self::getAvailableWithdrawMethods();
        $configs = [];
        
        foreach ($methods as $method) {
            $config = self::getPaymentMethodConfig($method['unique_tag']);
            if ($config) {
                $configs[$method['unique_tag']] = $config;
            }
        }
        
        return $configs;
    }
}

