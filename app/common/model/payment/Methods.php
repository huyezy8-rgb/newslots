<?php

namespace app\common\model\payment;

use think\Model;

/**
 * Methods
 */
class Methods extends Model
{
    // 表名
    protected $name = 'payment_methods';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // JSON字段 - 保留自动处理，但访问器会进一步处理
    protected $json = ['field_config', 'validation_rules'];

    public function getFieldConfigAttr($value, $row)
    {
        // ThinkPHP 的 JSON 字段会自动解析，所以 $value 可能是数组或字符串
        // 如果是数组（已解析），直接返回
        if (is_array($value)) {
            // 空数组返回 null，非空数组返回数组
            return empty($value) ? null : $value;
        }
        
        // 如果是字符串（未自动解析或手动设置），尝试解析
        if (is_string($value)) {
            $trimmed = trim($value);
            if (empty($trimmed)) {
                return null;
            }
            $decoded = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // JSON 解析失败，返回 null（不应该发生，但安全处理）
                return null;
            }
            // 如果是空数组或空对象，返回 null
            if (is_array($decoded) && count($decoded) === 0) {
                return null;
            }
            return $decoded;
        }
        
        // 如果值为 null，直接返回
        return $value;
    }

    public function getValidationRulesAttr($value, $row)
    {
        // ThinkPHP 的 JSON 字段会自动解析，所以 $value 可能是数组或字符串
        // 如果是数组（已解析），直接返回
        if (is_array($value)) {
            // 空数组返回 null，非空数组返回数组
            return empty($value) ? null : $value;
        }
        
        // 如果是字符串（未自动解析或手动设置），尝试解析
        if (is_string($value)) {
            $trimmed = trim($value);
            if (empty($trimmed)) {
                return null;
            }
            $decoded = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // JSON 解析失败，返回 null（不应该发生，但安全处理）
                return null;
            }
            // 如果是空数组或空对象，返回 null
            if (is_array($decoded) && count($decoded) === 0) {
                return null;
            }
            return $decoded;
        }
        
        // 如果值为 null，直接返回
        return $value;
    }

    public function channelCodeTable(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\common\model\payment\Channels::class, 'channel_code', 'code');
    }

    public function getIconAttr($value)
    {
        return full_url('', $value ? true:false, $value ?? '');
    }
}