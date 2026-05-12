<?php

namespace app\common\service;

class AccountMaskService
{
    /**
     * 脱敏显示账号信息
     */
    public static function maskAccountInfo(array $accountInfo, string $uniqueTag): array
    {
        $masked = $accountInfo;
        
        switch ($uniqueTag) {
            case 'ecashapp':
                if (isset($masked['account_name'])) {
                    $masked['account_name'] = self::maskString($masked['account_name'], 2, 2);
                }
                break;
            case 'fiat_withdrawal':
                if (isset($masked['account_name'])) {
                    $masked['account_name'] = self::maskString($masked['account_name'], 4, 4);
                }
                break;
            case 'paypal':
                if (isset($masked['email'])) {
                    $masked['email'] = self::maskEmail($masked['email']);
                }
                break;
            case 'usdt':
                if (isset($masked['address'])) {
                    $masked['address'] = self::maskString($masked['address'], 6, 4);
                }
                break;
        }
        
        return $masked;
    }
    
    /**
     * 字符串脱敏
     */
    private static function maskString(string $str, int $start, int $end): string
    {
        $length = strlen($str);
        if ($length <= $start + $end) {
            return str_repeat('*', $length);
        }
        
        return substr($str, 0, $start) . str_repeat('*', $length - $start - $end) . substr($str, -$end);
    }
    
    /**
     * 邮箱脱敏
     */
    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        if (strlen($username) <= 2) {
            $maskedUsername = str_repeat('*', strlen($username));
        } else {
            $maskedUsername = substr($username, 0, 1) . str_repeat('*', strlen($username) - 2) . substr($username, -1);
        }
        
        return $maskedUsername . '@' . $domain;
    }
}

