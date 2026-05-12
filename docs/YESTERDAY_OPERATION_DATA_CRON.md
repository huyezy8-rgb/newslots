# 运营数据定时生成任务

## 概述

本命令用于定期生成前一日所有渠道的运营数据，支持自动生成全部渠道汇总数据和各渠道独立数据。

## 命令说明

### 基本命令

```bash
php think generate:yesterday-operation-data
```

### 命令参数

| 参数 | 简写 | 说明 | 默认值 |
|------|------|------|--------|
| `--date` | `-d` | 指定日期 (Y-m-d 格式) | 昨天 |
| `--force` | `-f` | 强制重新生成已存在的数据 | false |

### 使用示例

```bash
# 生成昨天的运营数据（默认）
php think generate:yesterday-operation-data

# 生成指定日期的运营数据
php think generate:yesterday-operation-data --date=2025-07-01

# 强制重新生成昨天的数据
php think generate:yesterday-operation-data --force

# 强制重新生成指定日期的数据
php think generate:yesterday-operation-data --date=2025-07-01 --force
```

## 功能特性

1. **自动生成全部渠道数据**：生成 `channel_id` 为 `null` 的全部渠道汇总数据
2. **自动生成各渠道数据**：遍历所有渠道，为每个渠道生成独立的数据
3. **智能跳过机制**：如果数据已存在且未使用 `--force` 参数，会自动跳过
4. **错误隔离处理**：单个渠道失败不影响其他渠道的数据生成
5. **详细执行日志**：显示每个渠道的处理结果和最终统计信息
6. **日期范围验证**：自动检查日期是否在有效范围内（不能是今天或未来）
7. **用户数据验证**：检查目标日期是否早于用户最早注册时间

## 定时任务配置

### Linux Crontab

```bash
# 编辑 crontab
crontab -e

# 添加定时任务（每天凌晨 2 点执行）
0 2 * * * cd /path/to/your/project && php think generate:yesterday-operation-data >> /var/log/operation_data.log 2>&1

# 或者使用绝对路径（推荐）
0 2 * * * /usr/bin/php /path/to/your/project/think generate:yesterday-operation-data >> /var/log/operation_data.log 2>&1
```

### Windows 计划任务

1. 打开"任务计划程序"（Task Scheduler）
2. 创建基本任务
3. 设置触发器：每天凌晨 2 点
4. 设置操作：启动程序
   - 程序：`php.exe`
   - 参数：`think generate:yesterday-operation-data`
   - 起始位置：项目根目录（如：`D:\PHPwww\slot`）

### 使用批处理文件（Windows）

创建 `generate_yesterday_data.bat`：

```batch
@echo off
cd /d D:\PHPwww\slot
php think generate:yesterday-operation-data
if %errorlevel% neq 0 (
    echo 生成运营数据失败，错误代码：%errorlevel%
    pause
) else (
    echo 生成运营数据成功
)
pause
```

## 数据生成逻辑

### 数据存储结构

1. **全部渠道数据**：`channel_id = null`
   - 统计所有渠道的汇总数据
   - 用于总体运营分析
   - 包含所有用户指标、新老用户指标、下单指标、返奖指标

2. **各渠道数据**：`channel_id = 具体值`
   - 统计每个渠道的独立数据
   - 用于渠道对比分析
   - 数据结构与全部渠道数据相同

### 生成流程

1. **验证阶段**：
   - 检查日期格式和范围
   - 验证用户注册时间
   - 获取渠道列表

2. **数据生成阶段**：
   - 生成全部渠道汇总数据
   - 遍历各渠道生成独立数据
   - 智能跳过已存在的数据

3. **保存阶段**：
   - 保存到 `slot_operation_data` 表
   - 使用 JSON 格式存储详细数据
   - 支持覆盖更新

## 注意事项

### 执行时间
- **推荐时间**：凌晨 2-4 点，避免业务高峰期
- **数据完整性**：确保前一天的数据已经完全写入数据库
- **系统负载**：避免在系统维护时间执行

### 错误处理
- **错误监控**：建议配置错误通知，及时处理生成失败的情况
- **日志记录**：所有错误都会记录到日志文件
- **重试机制**：失败的数据可以通过 `--force` 参数重新生成

### 性能优化
- **存储空间**：定期清理过期的历史数据，避免占用过多存储空间
- **分批处理**：如果渠道数量很多，可能需要分批执行
- **数据库优化**：确保相关表有适当的索引

## 监控建议

### 日志监控
- 监控命令执行日志，确保任务正常完成
- 检查错误日志，及时处理异常情况
- 定期清理日志文件，避免占用过多磁盘空间

### 数据验证
- 定期检查生成的数据是否完整和准确
- 对比实时计算和历史数据的一致性
- 验证各渠道数据的合理性

### 系统监控
- 监控数据库性能，避免影响其他业务
- 监控存储空间使用情况
- 监控系统资源使用情况

### 业务监控
- 监控数据生成的成功率
- 监控数据生成的耗时
- 监控数据质量指标 