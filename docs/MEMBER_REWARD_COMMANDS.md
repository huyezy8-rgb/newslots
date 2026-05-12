# 会员奖励发放命令说明

## 概述

会员奖励发放功能已分离为两个独立的命令，分别处理周奖励和月奖励的发放。这些命令不包含时间判断逻辑，需要通过外部定时计划（如crontab）来控制执行时间。

## 命令列表

### 1. 周奖励发放命令

**命令名称：** `member:weekly-reward`

**功能描述：** 为所有符合条件的VIP用户发放周奖励

**执行示例：**
```bash
php think member:weekly-reward
```

**执行逻辑：**
- 查找所有配置了周奖励的等级
- 为当前等级有周奖励的用户发放奖励
- 如果用户本周已经发放过奖励，则不重复发放
- 如果用户之前有未领取的周奖励，新奖励会覆盖旧奖励
- 在 `member_reward_logs` 表中记录发放历史

### 2. 月奖励发放命令

**命令名称：** `member:monthly-reward`

**功能描述：** 为所有符合条件的VIP用户发放月奖励

**执行示例：**
```bash
php think member:monthly-reward
```

**执行逻辑：**
- 查找所有配置了月奖励的等级
- 为当前等级有月奖励的用户发放奖励
- 如果用户本月已经发放过奖励，则不重复发放
- 如果用户之前有未领取的月奖励，新奖励会覆盖旧奖励
- 在 `member_reward_logs` 表中记录发放历史

## 定时计划配置

### Linux Crontab 配置示例

```bash
# 编辑crontab
crontab -e

# 每周一凌晨0点发放周奖励
0 0 * * 1 cd /path/to/your/project && php think member:weekly-reward >> /var/log/weekly-reward.log 2>&1

# 每月1号凌晨0点发放月奖励
0 0 1 * * cd /path/to/your/project && php think member:monthly-reward >> /var/log/monthly-reward.log 2>&1
```

### 不同时区的配置

如果需要按美国时区执行，可以使用以下配置：

```bash
# 设置时区为美国东部时间，每周一凌晨0点（美国时间）发放周奖励
TZ=America/New_York
0 0 * * 1 cd /path/to/your/project && php think member:weekly-reward >> /var/log/weekly-reward.log 2>&1

# 每月1号凌晨0点（美国时间）发放月奖励
0 0 1 * * cd /path/to/your/project && php think member:monthly-reward >> /var/log/monthly-reward.log 2>&1
```

### Windows 任务计划程序配置

1. 打开"任务计划程序"
2. 创建基本任务
3. 设置触发器：
   - 周奖励：每周，星期一，00:00
   - 月奖励：每月，1号，00:00
4. 设置操作：
   - 程序：`php.exe`
   - 参数：`think member:weekly-reward`（或 `member:monthly-reward`）
   - 起始于：项目根目录路径

## 命令执行结果

### 成功执行
- 返回码：0
- 控制台输出：显示发放成功的用户数量
- 日志记录：在系统日志中记录成功信息

### 执行失败
- 返回码：1
- 控制台输出：显示错误信息
- 日志记录：在系统日志中记录错误详情

## 执行日志示例

### 周奖励执行日志
```
开始发放会员周奖励...
执行时间: 2024-01-15 00:00:01
周奖励发放成功，共创建 156 个奖励
会员周奖励发放任务执行完成
```

### 月奖励执行日志
```
开始发放会员月奖励...
执行时间: 2024-02-01 00:00:01
月奖励发放成功，共创建 89 个奖励
会员月奖励发放任务执行完成
```

## 手动执行

### 测试执行
在部署前可以手动执行命令进行测试：

```bash
# 测试周奖励发放
php think member:weekly-reward

# 测试月奖励发放
php think member:monthly-reward
```

### 查看可用命令
```bash
# 查看所有可用的think命令
php think

# 查看特定命令的帮助信息
php think member:weekly-reward --help
php think member:monthly-reward --help
```

## 监控和告警

### 日志监控
建议监控以下日志文件：
- 系统日志中的奖励发放记录
- Crontab执行日志
- 错误日志

### 告警配置
可以配置以下告警：
- 命令执行失败时发送邮件/短信通知
- 发放数量异常时的告警
- 命令执行时间过长的告警

### 监控脚本示例
```bash
#!/bin/bash
# 检查周奖励是否执行成功
if grep -q "周奖励发放成功" /var/log/weekly-reward.log; then
    echo "周奖励执行成功"
else
    echo "周奖励执行可能失败，请检查日志" | mail -s "周奖励告警" admin@example.com
fi
```

## 注意事项

1. **幂等性**：两个命令都具有幂等性，重复执行不会产生重复奖励
2. **数据库连接**：确保数据库连接正常，命令执行时会进行数据库操作
3. **权限问题**：确保执行用户有读写项目文件和数据库的权限
4. **时区设置**：注意服务器时区设置，确保按预期时间执行
5. **资源占用**：大量用户时命令执行可能耗时较长，建议在低峰期执行
6. **日志轮转**：定期清理或轮转日志文件，避免占用过多磁盘空间

## 故障排除

### 常见问题

1. **命令找不到**
   ```bash
   # 检查命令是否存在
   php think list | grep member
   ```

2. **权限错误**
   ```bash
   # 检查文件权限
   ls -la app/command/Member*Reward.php
   ```

3. **数据库连接失败**
   ```bash
   # 测试数据库连接
   php think make:command TestDB
   ```

4. **内存不足**
   ```bash
   # 增加PHP内存限制
   php -d memory_limit=512M think member:weekly-reward
   ```

### 调试模式
可以通过修改命令代码临时添加调试信息：
```php
$output->writeln('调试信息: 当前处理用户ID: ' . $user['id']);
```

通过以上配置，您可以灵活地控制奖励发放的时间和频率，同时保证系统的稳定性和可监控性。
