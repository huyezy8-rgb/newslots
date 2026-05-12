# 排行榜系统使用说明

## 系统概述

排行榜系统基于Redis有序集合+MySQL实现，支持日榜、周榜、月榜三种类型，自动统计用户下注金额并计算奖金池。Redis只保留当前最新的排行榜数据，MySQL保留所有历史数据。

## 功能特性

- **多类型排行榜**：支持日榜、周榜、月榜
- **实时统计**：用户下注时自动更新排行榜数据
- **奖金池计算**：根据配置比例自动计算入池金额
- **Redis+MySQL**：Redis提供高性能排序，MySQL提供数据持久化
- **历史数据保留**：MySQL中保留所有历史数据，Redis只保留当前数据

## 系统架构

### 数据存储
- **Redis**：存储当前排行榜有序集合和奖金池金额
- **MySQL**：存储详细的排行榜统计数据和奖金池记录（保留历史数据）

### 核心组件
- `LeaderboardService`：排行榜核心服务类
- `LeaderboardStats`：排行榜统计事件处理器
- `Leaderboard`：排行榜API控制器

## 数据库表结构

### leaderboard_stats（排行榜统计表）
```sql
CREATE TABLE `leaderboard_stats` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(20) NOT NULL COMMENT '排行榜类型',
  `period` varchar(20) NOT NULL COMMENT '统计周期',
  `total_bet` decimal(15,2) DEFAULT '0.00' COMMENT '总下注金额',
  `pool_amount` decimal(15,2) DEFAULT '0.00' COMMENT '入池金额',
  `username` varchar(50) DEFAULT '' COMMENT '用户名',
  `nickname` varchar(50) DEFAULT '' COMMENT '昵称',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `channel_id` int(11) unsigned DEFAULT '0' COMMENT '渠道ID',
  `create_time` int(11) unsigned NOT NULL COMMENT '创建时间',
  `update_time` int(11) unsigned NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_type_period` (`user_id`,`type`,`period`),
  KEY `idx_type_period` (`type`,`period`),
  KEY `idx_total_bet` (`total_bet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='排行榜统计表';
```

### leaderboard_pool（奖金池表）
```sql
CREATE TABLE `leaderboard_pool` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL COMMENT '排行榜类型',
  `period` varchar(20) NOT NULL COMMENT '统计周期',
  `total_amount` decimal(15,2) DEFAULT '0.00' COMMENT '奖金池总金额',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：1-进行中，2-已结算，3-已发放',
  `settle_time` int(11) unsigned DEFAULT '0' COMMENT '结算时间',
  `create_time` int(11) unsigned NOT NULL COMMENT '创建时间',
  `update_time` int(11) unsigned NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_type_period` (`type`,`period`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='排行榜奖金池表';
```

## 配置说明

### 入池比例配置
在 `LeaderboardService` 类中配置各类型排行榜的入池比例：

```php
private array $config = [
    'daily_pool_rate' => 0.01,    // 日榜入池比例 1%
    'weekly_pool_rate' => 0.02,   // 周榜入池比例 2%
    'monthly_pool_rate' => 0.05,  // 月榜入池比例 5%
];
```

### Redis键格式
- 排行榜：`leaderboard:{type}`（如：leaderboard:daily）
- 奖金池：`prize_pool:{type}`（如：prize_pool:daily）

### 周期格式（MySQL存储）
- 日榜：`Y-m-d`（如：2025-01-01）
- 周榜：`Y-m-d`（如：2025-01-01，表示该周开始日期）
- 月榜：`Y-m`（如：2025-01）

## API接口

### 1. 获取排行榜数据
```
GET /api/leaderboard/getRanking
```

参数：
- `type`：排行榜类型（daily/weekly/monthly）
- `limit`：返回数量限制（可选，默认100）

返回示例：
```json
{
    "code": 0,
    "error": "",
    "data": {
        "type": "daily",
        "prize_pool": 1000.50,
        "leaderboard": [
            {
                "rank": 1,
                "user_id": 123,
                "score": 5000.00,
                "username": "user123",
                "nickname": "玩家123",
                "avatar": "avatar.jpg"
            }
        ]
    }
}
```

### 2. 获取奖金池信息
```
GET /api/leaderboard/getPrizePool
```

参数：
- `type`：排行榜类型（daily/weekly/monthly）

返回示例：
```json
{
    "code": 0,
    "error": "",
    "data": {
        "type": "daily",
        "prize_pool": 1000.50
    }
}
```

### 3. 获取用户排名
```
GET /api/leaderboard/getUserRank
```

参数：
- `user_id`：用户ID
- `type`：排行榜类型（daily/weekly/monthly）

返回示例：
```json
{
    "code": 0,
    "error": "",
    "data": {
        "user_id": 123,
        "type": "daily",
        "rank": {
            "rank": 5,
            "score": 3000.00,
            "total_users": 100
        }
    }
}
```

### 4. 重置排行榜（管理员功能）
```
POST /api/leaderboard/resetLeaderboard
```

参数：
- `type`：排行榜类型（daily/weekly/monthly）

返回示例：
```json
{
    "code": 0,
    "error": "",
    "data": {
        "message": "排行榜重置成功",
        "type": "daily"
    }
}
```

## 事件触发

系统在下注时自动触发排行榜统计事件：

```php
// 在 Cash@transferInOut 方法中
if ($params['Reason'] == "bet") {
    // 触发排行榜统计事件
    event('LeaderboardStats', [
        'amount' => abs(floatval($params['Amount'])), 
        'user_id' => $userId
    ]);
}
```

## 数据管理

### 重置排行榜
当需要切换排行榜周期时（如日榜切换到新的一天），可以调用重置接口：

```bash
# 重置日榜
curl -X POST "http://your-domain/api/leaderboard/resetLeaderboard" \
     -d "type=daily"

# 重置周榜
curl -X POST "http://your-domain/api/leaderboard/resetLeaderboard" \
     -d "type=weekly"

# 重置月榜
curl -X POST "http://your-domain/api/leaderboard/resetLeaderboard" \
     -d "type=monthly"
```

### 历史数据
所有历史数据都保留在MySQL中，可以通过以下方式查询：

```sql
-- 查询某日排行榜数据
SELECT * FROM slot_leaderboard_stats 
WHERE type = 'daily' AND period = '2025-01-01' 
ORDER BY total_bet DESC;

-- 查询某周排行榜数据
SELECT * FROM slot_leaderboard_stats 
WHERE type = 'weekly' AND period = '2025-01-01' 
ORDER BY total_bet DESC;

-- 查询某月排行榜数据
SELECT * FROM slot_leaderboard_stats 
WHERE type = 'monthly' AND period = '2025-01' 
ORDER BY total_bet DESC;
```

## 部署步骤

1. **运行数据库迁移**
```bash
php think migrate:run
```

2. **确保Redis配置正确**
在 `config/cache.php` 中配置Redis连接信息

3. **测试事件触发**
可以通过下注操作测试排行榜统计是否正常工作

## 注意事项

1. **Redis连接**：确保Redis服务正常运行，排行榜功能依赖Redis
2. **数据一致性**：Redis和MySQL数据可能存在短暂不一致，建议定期同步
3. **性能优化**：大量用户时建议调整Redis配置和MySQL索引
4. **监控告警**：建议监控Redis内存使用和排行榜数据更新情况
5. **周期切换**：需要手动调用重置接口来切换排行榜周期

## 扩展功能

### 自定义排行榜类型
可以通过修改 `LeaderboardService` 类添加新的排行榜类型

### 奖励发放
可以基于排行榜数据实现自动奖励发放功能

### 数据导出
可以添加数据导出功能，支持Excel等格式

### 管理后台
可以开发管理后台，支持排行榜配置和奖励管理 