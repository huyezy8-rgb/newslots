# 无限级团队设计使用文档

## 概述

本系统实现了基于路径分隔符 `/` 的无限级团队设计，支持团队层级管理、奖金分发、统计查询等功能。

## 数据库结构

### 新增字段

```sql
ALTER TABLE `slot_account`
ADD COLUMN `team_path` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '团队路径，格式如：/1/3/（从根到父级，不含自身，使用/包裹以防歧义）',
ADD COLUMN `team_level` INT NOT NULL DEFAULT 0 COMMENT '团队层级，根节点为0';
```

### 索引优化

```sql
ALTER TABLE `slot_account` 
ADD INDEX `idx_team_path` (`team_path`),
ADD INDEX `idx_team_level` (`team_level`);
```

## 核心概念

### 团队路径生成规则（使用 p_id）

- **根节点用户**: `team_path = '/'`, `team_level = 0`
- **普通用户**: `team_path = 上级 team_path + 上级ID + '/'`, `team_level = 上级 team_level + 1`

### 示例结构

```
用户ID: 1 (根节点)
team_path: '/'
team_level: 0

用户ID: 2 (1的直属下级)
team_path: '/1/'
team_level: 1

用户ID: 3 (2的直属下级)
team_path: '/1/2/'
team_level: 2

用户ID: 4 (3的直属下级)
team_path: '/1/2/3/'
team_level: 3
```

## 服务类使用

### 1. 团队路径服务 (TeamPathService)

```php
use app\common\service\TeamPathService;

$teamPathService = new TeamPathService();

// 更新用户团队路径
$teamPathService->updateTeamPath($userId);

// 获取所有下级
$allChildren = $teamPathService->getAllChildren($userId);

// 获取直属下级
$directChildren = $teamPathService->getDirectChildren($userId);

// 获取所有上级
$allParents = $teamPathService->getAllParents($userId);

// 计算团队奖金
$bonusResults = $teamPathService->calculateTeamBonus($userId, $amount, $ratios);

// 统计团队充值
$teamRecharge = $teamPathService->getTeamRechargeAmount($userId);

// 统计团队用户数
$teamUserCount = $teamPathService->getTeamUserCount($userId);
```

### 2. 奖金分发示例

```php
// 默认分成比例
$ratios = [
    0 => 0.10, // 一级上级 10%
    1 => 0.05, // 二级上级 5%
    2 => 0.03, // 三级上级 3%
];

$bonusResults = $teamPathService->calculateTeamBonus($userId, 100, $ratios);

// 结果示例
[
    [
        'leader_id' => 2,
        'level' => 1,
        'ratio' => 0.10,
        'bonus' => 10.0,
        'user_id' => 4
    ],
    [
        'leader_id' => 1,
        'level' => 2,
        'ratio' => 0.05,
        'bonus' => 5.0,
        'user_id' => 4
    ]
]
```

## 命令行工具

### 初始化团队路径

```bash
# 初始化所有用户的团队路径
php think team:path init

# 更新指定用户的团队路径
php think team:path update --user_id=123

# 验证团队路径完整性
php think team:path validate

# 查看统计信息
php think team:path statistics --user_id=123
```

## API接口

### 1. 获取团队信息

```
GET /api/team/index
```

**响应示例:**
```json
{
    "code": 1,
    "msg": "团队信息获取成功",
    "data": {
        "user_info": {
            "id": 1,
            "name": "张三",
            "team_path": "0",
            "team_level": 0,
            "p_id": 0
        },
        "statistics": {
            "direct_children_count": 5,
            "all_children_count": 25,
            "parents_count": 0,
            "team_recharge": 10000.00,
            "team_user_count": 25
        },
        "direct_children": [...],
        "all_parents": [...]
    }
}
```

### 2. 获取团队下级

```
GET /api/team/children?type=direct&page=1&size=20
```

**参数说明:**
- `type`: `direct` (直属下级) 或 `all` (所有下级)
- `page`: 页码
- `size`: 每页数量

### 3. 获取团队上级

```
GET /api/team/parents
```

### 4. 获取团队统计

```
GET /api/team/statistics?start_time=2024-01-01&end_time=2024-12-31
```

### 5. 计算团队奖金

```
POST /api/team/calculateBonus
```

**请求参数:**
```json
{
    "amount": 100,
    "ratios": {
        "0": 0.10,
        "1": 0.05,
        "2": 0.03
    }
}
```

### 6. 搜索团队成员

```
GET /api/team/search?keyword=张三&type=all
```

## SQL查询示例

### 1. 查询所有下级（推荐以/包裹防歧义）

```sql
SELECT * FROM slot_account
WHERE team_path LIKE '/1/2/3/%';
```

### 2. 查询直属下级

```sql
SELECT * FROM slot_account WHERE p_id = 3;
```

### 3. 统计团队充值

```sql
SELECT SUM(r.amount) FROM slot_recharge_orders r
JOIN slot_account u ON u.id = r.user_id
WHERE u.team_path LIKE '/1/2/3/%';
```

### 4. 查询各层级用户分布

```sql
SELECT team_level, COUNT(*) as count 
FROM slot_account 
GROUP BY team_level 
ORDER BY team_level;
```

## 性能优化建议

### 1. 索引优化

确保以下索引存在：
- `idx_team_path_prefix` (team_path(255))
- `idx_team_level` (team_level)
- `idx_p_id` (p_id)

### 2. 查询优化

- 使用 `LIKE 'path/%'` 查询下级时，确保 `team_path` 字段有索引
- 对于大量数据的团队统计，考虑使用缓存
- 分页查询时使用 `LIMIT` 限制结果集大小

### 3. 缓存策略

```php
// 缓存团队统计信息
$cacheKey = "team_stats_{$userId}";
$teamStats = cache($cacheKey);
if (!$teamStats) {
    $teamStats = $teamPathService->getTeamStatistics($userId);
    cache($cacheKey, $teamStats, 300); // 缓存5分钟
}
```

## 注意事项

### 1. 数据一致性

- 修改用户上级时，需要递归更新所有下级的团队路径
- 建议在事务中执行团队路径更新操作
- 定期验证团队路径的完整性

### 2. 性能考虑

- 团队路径长度不宜过长（建议不超过10级）
- 大量用户时，考虑分批处理团队路径更新
- 使用异步任务处理复杂的团队统计计算

### 3. 安全考虑

- 验证用户权限，确保只能查看自己的团队信息
- 防止循环引用（用户不能成为自己的下级）
- 记录团队路径变更日志

## 故障排除

### 1. 团队路径不完整

```bash
# 验证所有用户团队路径
php think team:path validate

# 重新初始化团队路径
php think team:path init
```

### 2. 性能问题

- 检查数据库索引是否正确创建
- 使用 `EXPLAIN` 分析慢查询
- 考虑增加缓存层

### 3. 数据异常

- 检查是否有循环引用
- 验证上级用户是否存在
- 查看错误日志获取详细信息

## 联系支持

如果在使用过程中遇到问题，请：
1. 查看系统日志获取详细错误信息
2. 使用命令行工具验证数据完整性
3. 检查数据库连接和权限
4. 联系技术支持团队 