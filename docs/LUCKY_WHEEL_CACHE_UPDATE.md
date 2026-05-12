# 幸运转盘配置缓存更新功能说明

## 功能概述

实现了后台修改幸运转盘配置后自动清除配置相关Redis缓存的功能，确保配置变更能够立即生效，避免用户看到过期的配置信息。**只清除配置缓存，保留用户相关缓存。**

## 实现原理

### 1. 缓存机制

幸运转盘系统使用Redis缓存来存储以下数据：
- **活动配置缓存**：存储主配置信息（标题、Banner图、打码倍数、状态等）
- **转盘配置缓存**：存储转盘列表和奖项配置
- **用户相关缓存**：存储用户充值金额、使用次数等个人数据

### 2. 缓存更新策略

当后台管理员修改配置时，系统会自动：
1. 保存配置到数据库
2. 只清除配置相关的Redis缓存（活动配置、转盘配置）
3. 保留用户相关缓存（用户充值、使用次数等）
4. 下次用户访问时重新从数据库加载最新配置

## 代码实现

### 1. 主配置控制器更新

**文件**：`app/admin/controller/activity/LuckyWheel.php`

```php
public function edit(): void
{
    if ($this->request->isPost()) {
        $params = $this->request->post();
        
        $config = $this->model->find(1);
        if (!$config) {
            $config = $this->model->create($params);
        } else {
            $config->save($params);
        }
        
        // 清除幸运转盘配置缓存
        $this->clearLuckyWheelCache();
        
        $this->success('保存成功');
    }
    
    $config = $this->model->find(1);
    $this->success('', $config);
}

private function clearLuckyWheelCache(): void
{
    try {
        // 只清除配置缓存
        LuckyWheelCacheService::clearConfigCache();
        
    } catch (\Exception $e) {
        // 记录错误日志，但不影响主流程
        \think\facade\Log::error('清除幸运转盘配置缓存失败: ' . $e->getMessage());
    }
}
```

### 2. 转盘配置控制器更新

**文件**：`app/admin/controller/activity/LuckyWheelTurntable.php`

```php
public function updatePrizes(): void
{
    if ($this->request->isPost()) {
        // ... 验证和保存逻辑 ...
        
        if ($result) {
            // 清除转盘配置缓存
            $this->clearTurntableCache($params['id']);
            $this->success('奖项配置保存成功');
        }
    }
}

public function updateRules(): void
{
    if ($this->request->isPost()) {
        // ... 验证和保存逻辑 ...
        
        if ($result) {
            // 清除转盘配置缓存
            $this->clearTurntableCache($params['id']);
            $this->success('规则配置保存成功');
        }
    }
}

private function clearTurntableCache($turntableId): void
{
    try {
        // 只清除转盘配置缓存
        LuckyWheelCacheService::clearWheelsCache();
        
    } catch (\Exception $e) {
        \think\facade\Log::error('清除转盘配置缓存失败: ' . $e->getMessage());
    }
}
```

### 3. 缓存服务增强

**文件**：`app/common/service/LuckyWheelCacheService.php`

新增了更精确的缓存清除方法：

```php
/**
 * 清除所有用户相关缓存
 */
public static function clearAllUserCache()
{
    // 获取所有用户相关的缓存键
    $patterns = [
        self::CACHE_PREFIX . self::USER_WHEELS_CACHE_KEY . '*',
        self::CACHE_PREFIX . self::USER_RECHARGE_CACHE_KEY . '*',
        self::CACHE_PREFIX . self::USER_BET_CACHE_KEY . '*',
        self::CACHE_PREFIX . self::USER_USAGE_CACHE_KEY . '*'
    ];
    
    $allKeys = [];
    foreach ($patterns as $pattern) {
        $keys = Cache::store('redis')->keys($pattern);
        if (!empty($keys)) {
            $allKeys = array_merge($allKeys, $keys);
        }
    }
    
    if (!empty($allKeys)) {
        return Cache::store('redis')->del($allKeys);
    }
    
    return true;
}
```

## 缓存键结构

### 缓存键前缀
- 主前缀：`lucky_wheel:`
- 配置缓存：`lucky_wheel:config`
- 转盘缓存：`lucky_wheel:wheels`
- 用户转盘：`lucky_wheel:user_wheels:{userId}`
- 用户充值：`lucky_wheel:user_recharge:{userId}`
- 用户下注：`lucky_wheel:user_bet:{userId}`
- 用户使用：`lucky_wheel:user_usage:{userId}:{wheelId}`

### 缓存过期时间
- 配置缓存：1小时
- 转盘缓存：1小时
- 用户缓存：5分钟
- 使用缓存：1分钟

## 使用场景

### 1. 主配置更新
当管理员修改以下配置时，会清除配置缓存：
- 活动标题
- Banner图片
- 打码倍数
- 活动状态

### 2. 转盘配置更新
当管理员修改以下配置时，会清除转盘配置缓存：
- 奖项配置（奖品、概率、金额）
- 解锁条件
- 使用规则
- 最大使用次数

### 3. 缓存预热
系统支持缓存预热功能，可以在系统启动时预加载常用数据：

```php
// 预热缓存
LuckyWheelCacheService::warmUpCache();
```

## 测试验证

### 1. 功能测试
使用提供的测试脚本验证缓存更新功能：

```bash
php test_lucky_wheel_cache_update.php
```

### 2. 手动测试步骤
1. 登录后台管理系统
2. 修改幸运转盘主配置
3. 保存配置
4. 检查Redis缓存是否被清除
5. 访问前端页面验证配置是否更新

### 3. 缓存统计
可以通过以下方法查看缓存统计信息：

```php
$stats = LuckyWheelCacheService::getCacheStats();
print_r($stats);
```

## 注意事项

### 1. 错误处理
- 缓存清除失败不会影响配置保存
- 错误信息会记录到日志中
- 系统会继续正常运行

### 2. 性能考虑
- 只清除配置缓存，不影响用户相关缓存
- 配置缓存会在下次访问时自动重建
- 用户缓存保持稳定，提升用户体验

### 3. 数据一致性
- 配置保存成功后立即清除缓存
- 确保用户看到的是最新配置
- 避免缓存与数据库不一致的问题

## 相关文件

- **主配置控制器**：`app/admin/controller/activity/LuckyWheel.php`
- **转盘配置控制器**：`app/admin/controller/activity/LuckyWheelTurntable.php`
- **缓存服务**：`app/common/service/LuckyWheelCacheService.php`
- **测试脚本**：`test_lucky_wheel_cache_update.php`
- **使用文档**：`docs/LUCKY_WHEEL_CACHE_UPDATE.md`

## 总结

通过实现自动配置缓存更新功能，确保了幸运转盘配置的实时性，同时保持了用户相关缓存的稳定性。系统在配置变更后只会清除配置相关缓存，保证用户始终看到最新的配置信息，同时避免频繁清除用户缓存对性能的影响。
