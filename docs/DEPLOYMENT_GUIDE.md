# 排行榜奖金池奖励发放功能部署指南

## 部署步骤

### 1. 数据库迁移

运行数据库迁移创建排行榜奖励日志表：

```bash
php think migrate:run
```

### 2. 验证命令注册

确保命令已正确注册到 `config/console.php`：

```php
'commands' => [
    // ... 其他命令
    'leaderboard:reward'=>'app\command\LeaderboardReward',
    'leaderboard:schedule'=>'app\command\LeaderboardRewardSchedule',
],
```

### 3. 测试命令功能

```bash
# 测试定时任务
php think leaderboard:schedule
```

### 4. 配置定时任务

#### Linux/Unix 系统

编辑 crontab：

```bash
crontab -e
```

添加以下定时任务：

```bash
# 每小时检查一次是否需要发放奖励
0 * * * * cd /path/to/your/project && php think leaderboard:schedule
```

#### Windows 系统

使用任务计划程序：

1. 打开任务计划程序
2. 创建基本任务
3. 设置触发器（每小时）
4. 设置操作：启动程序
5. 程序路径：`php.exe`
6. 参数：`think leaderboard:schedule`

### 5. 配置Redis

确保Redis服务正常运行，并在配置文件中正确配置Redis连接信息。

### 6. 权限设置

确保PHP有执行命令的权限，以及读写数据库和Redis的权限。

## 功能验证

### 1. 检查数据库表

确认以下表已创建：
- `leaderboard_reward_log` - 排行榜奖励发放日志表

### 2. 检查资金流水类型

确认CoinLog枚举中已添加新的类型：
- `LeaderboardDaily` (29)
- `LeaderboardWeekly` (30)
- `LeaderboardMonthly` (31)

### 3. 测试奖励发放

在有排行榜数据的情况下测试奖励发放功能。

## 发放时机说明

### 日榜奖励
- **发放时间**：每日零点
- **发放内容**：昨日的奖金池
- **说明**：每天零点后发放前一天的排行榜奖励

### 周榜奖励
- **发放时间**：每周一零点
- **发放内容**：上周的奖金池
- **说明**：每周一零点后发放上周的排行榜奖励

### 月榜奖励
- **发放时间**：每月1号零点
- **发放内容**：上月的奖金池
- **说明**：每月1号零点后发放上月的排行榜奖励

## 监控和维护

### 1. 日志监控

关注以下日志文件：
- 应用日志：`runtime/log/`
- 系统日志：`/var/log/`（Linux）

### 2. 数据库监控

定期检查：
- `leaderboard_reward_log` 表的记录
- `account_coin_log` 表的奖励发放记录
- 用户余额变化

### 3. Redis监控

监控Redis中的排行榜数据：
- `leaderboard:daily`
- `leaderboard:weekly`
- `leaderboard:monthly`
- `prize_pool:daily`
- `prize_pool:weekly`
- `prize_pool:monthly`

## 故障排除

### 1. 命令无法执行

- 检查命令是否已注册到 `config/console.php`
- 检查PHP环境是否正确
- 检查文件权限

### 2. 数据库连接失败

- 检查数据库配置
- 检查数据库服务状态
- 检查网络连接

### 3. Redis连接失败

- 检查Redis服务状态
- 检查Redis配置
- 检查网络连接

### 4. 奖励发放失败

- 检查排行榜数据是否存在
- 检查奖金池金额
- 检查用户账户状态
- 查看错误日志

### 5. 发放时机问题

- 检查服务器时间是否正确
- 检查定时任务配置
- 检查时区设置

## 安全注意事项

1. **数据备份**：发放奖励前建议备份相关数据
2. **权限控制**：确保只有授权人员可以执行奖励发放
3. **日志记录**：保留完整的操作日志
4. **测试环境**：先在测试环境验证功能
5. **监控告警**：设置异常情况告警

## 性能优化

1. **Redis缓存**：合理使用Redis缓存排行榜数据
2. **批量处理**：大量用户时考虑批量处理
3. **数据库优化**：定期优化数据库查询
4. **定时任务**：合理设置定时任务执行时间

## 扩展功能

如需扩展功能，可以考虑：
1. 添加邮件/短信通知
2. 增加奖励发放统计报表
3. 支持自定义发放时间
4. 添加奖励发放审核流程
5. 支持多种奖励类型（积分、道具等） 