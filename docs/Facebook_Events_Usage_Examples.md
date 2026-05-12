# Facebook事件使用示例

## event_id 自动生成功能

系统已集成自动生成唯一 event_id 的功能，支持以下两种方式：

### 1. 业务唯一ID优先（推荐）

当你的业务数据中有唯一标识时，系统会优先使用：

```php
use app\event\FacebookConversion;

$facebookEvent = new FacebookConversion();

// 购买事件 - 使用订单号作为event_id
$facebookEvent->handle([
    'user_id' => 123,
    'event_type' => 'purchase',
    'custom_data' => [
        'amount' => 99.99,
        'currency' => 'USD',
        'order_id' => 'ORDER20240615123456789' // 系统会自动生成 event_id: purchase_ORDER20240615123456789
    ]
]);

// 充值事件 - 使用充值单号作为event_id
$facebookEvent->handle([
    'user_id' => 123,
    'event_type' => 'purchase', // 充值也使用purchase事件
    'custom_data' => [
        'amount' => 100.00,
        'currency' => 'USD',
        'recharge_id' => 'RECHARGE20240615123456789' // 系统会自动生成 event_id: purchase_RECHARGE20240615123456789
    ]
]);

// 提现事件 - 使用提现单号作为event_id
$facebookEvent->handle([
    'user_id' => 123,
    'event_type' => 'purchase', // 提现也使用purchase事件
    'custom_data' => [
        'amount' => 50.00,
        'currency' => 'USD',
        'withdraw_id' => 'WITHDRAW20240615123456789' // 系统会自动生成 event_id: purchase_WITHDRAW20240615123456789
    ]
]);
```

### 2. 自动生成唯一ID

当没有业务唯一ID时，系统会自动生成：

```php
// 注册事件 - 系统自动生成event_id
$facebookEvent->handle([
    'user_id' => 123,
    'event_type' => 'register',
    'custom_data' => [
        'method' => 'h5',
        'channel_name' => 'facebook'
    ]
]);
// 生成的event_id格式: register_123_1718000000_a1b2c3d4

// 加购物车事件 - 系统自动生成event_id
$facebookEvent->handle([
    'user_id' => 123,
    'event_type' => 'add_to_cart',
    'custom_data' => [
        'amount' => 29.99,
        'currency' => 'USD'
    ]
]);
// 生成的event_id格式: add_to_cart_123_1718000000_e5f6g7h8
```

## 实际业务集成示例

### 用户注册场景

```php
// 在用户注册成功后触发
public function registerSuccess($userId, $registerData) {
    $facebookEvent = new FacebookConversion();
    
    $facebookEvent->handle([
        'user_id' => $userId,
        'event_type' => 'register',
        'custom_data' => [
            'method' => $registerData['method'] ?? 'h5',
            'channel_name' => $registerData['channel_name'] ?? 'unknown',
            'register_time' => date('Y-m-d H:i:s')
        ],
        'client_ip' => request()->ip(),
        'client_user_agent' => request()->header('user-agent'),
        'fbc' => cookie('_fbc'),
        'fbp' => cookie('_fbp')
    ]);
}
```

### 充值成功场景

```php
// 在充值成功后触发
public function rechargeSuccess($userId, $rechargeData) {
    $facebookEvent = new FacebookConversion();
    
    $facebookEvent->handle([
        'user_id' => $userId,
        'event_type' => 'purchase',
        'custom_data' => [
            'amount' => $rechargeData['amount'],
            'currency' => $rechargeData['currency'] ?? 'USD',
            'recharge_id' => $rechargeData['recharge_id'], // 使用充值单号作为event_id
            'payment_method' => $rechargeData['payment_method'] ?? 'unknown'
        ],
        'client_ip' => request()->ip(),
        'client_user_agent' => request()->header('user-agent'),
        'fbc' => cookie('_fbc'),
        'fbp' => cookie('_fbp')
    ]);
}
```

### 购买商品场景

```php
// 在购买成功后触发
public function purchaseSuccess($userId, $orderData) {
    $facebookEvent = new FacebookConversion();
    
    $facebookEvent->handle([
        'user_id' => $userId,
        'event_type' => 'purchase',
        'custom_data' => [
            'amount' => $orderData['total_amount'],
            'currency' => $orderData['currency'] ?? 'USD',
            'order_id' => $orderData['order_id'], // 使用订单号作为event_id
            'product_name' => $orderData['product_name'],
            'quantity' => $orderData['quantity'] ?? 1
        ],
        'client_ip' => request()->ip(),
        'client_user_agent' => request()->header('user-agent'),
        'fbc' => cookie('_fbc'),
        'fbp' => cookie('_fbp')
    ]);
}
```

## event_id 生成规则

### 优先级顺序：

1. **业务唯一ID优先**：
   - `order_id` → `purchase_ORDER123456`
   - `recharge_id` → `purchase_RECHARGE123456`
   - `withdraw_id` → `purchase_WITHDRAW123456`

2. **自动生成**：
   - 格式：`{event_type}_{user_id}_{timestamp}_{random}`
   - 示例：`register_123_1718000000_a1b2c3d4`

### 特点：

- ✅ **唯一性**：每个事件都有唯一的event_id
- ✅ **可追溯**：通过event_id可以追踪具体事件
- ✅ **去重支持**：Facebook会根据event_id进行去重
- ✅ **业务友好**：优先使用业务ID，便于关联

## 数据库字段说明

新增的 `event_id` 字段：

```sql
`event_id` varchar(255) NOT NULL COMMENT '事件唯一ID'
```

- 类型：varchar(255)
- 约束：NOT NULL + UNIQUE KEY
- 用途：存储事件的唯一标识符
- 索引：已创建唯一索引 `uk_event_id`

## 注意事项

1. **业务ID格式**：建议使用有意义的业务ID，如订单号、充值单号等
2. **长度限制**：event_id最长255字符，足够大多数业务场景
3. **唯一性保证**：系统确保每个event_id都是唯一的
4. **去重效果**：Facebook会根据event_id进行事件去重，避免重复计数
5. **日志记录**：所有event_id都会记录在日志表中，便于查询和调试 