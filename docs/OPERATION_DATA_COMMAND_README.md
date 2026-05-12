# 运营数据生成命令使用说明

## 功能概述

`GenerateOperationData` 命令用于生成历史运营数据并保存到数据库中，支持按日期范围和渠道筛选，数据以JSON格式存储。

## 数据库表结构

### 表名：`slot_operation_data`

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | int | 主键ID |
| date | date | 统计日期 |
| channel_id | int | 渠道ID，null表示全部渠道 |
| data | json | 运营数据JSON |
| create_time | int | 创建时间 |
| update_time | int | 更新时间 |

### 索引
- `idx_date_channel`: 日期+渠道唯一索引
- `idx_date`: 日期索引
- `idx_channel`: 渠道索引

## 命令使用方法

### 基本语法
```bash
php think generate:operation-data [选项]
```

### 参数说明

| 参数 | 简写 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| --start-date | -s | 是 | 30天前 | 开始日期 (Y-m-d格式) |
| --end-date | -e | 是 | 今天 | 结束日期 (Y-m-d格式) |
| --channel-id | -c | 否 | null | 渠道ID，不指定表示全部渠道 |
| --force | -f | 否 | false | 强制重新生成已存在的数据 |

### 使用示例

#### 1. 生成最近30天的全部渠道数据
```bash
php think generate:operation-data
```

#### 2. 生成指定日期范围的数据
```bash
php think generate:operation-data --start-date 2025-01-01 --end-date 2025-01-31
```

#### 3. 生成指定渠道的数据
```bash
php think generate:operation-data --start-date 2025-01-01 --end-date 2025-01-31 --channel-id 1
```

#### 4. 强制重新生成已存在的数据
```bash
php think generate:operation-data --start-date 2025-01-01 --end-date 2025-01-31 --force
```

#### 5. 生成单日数据
```bash
php think generate:operation-data --start-date 2025-07-01 --end-date 2025-07-01
```

## 数据内容

生成的JSON数据包含以下字段：

### 基础信息
- `date`: 统计日期

### 所有用户指标
- `all_dau`: 日活跃用户数
- `all_paid_users`: 付费用户数
- `all_paid_rate`: 付费率（小数形式，前端自动转换为百分比）
- `all_paid_amount`: 付费金额（保留2位小数）
- `all_arpu`: 平均每用户收入（保留2位小数）
- `all_arppu`: 平均每付费用户收入（保留2位小数）
- `all_withdraw_amount`: 提现金额（保留2位小数）
- `all_withdraw_rate`: 提现率（小数形式，前端自动转换为百分比）

### 新用户指标
- `new_dau`: 新用户DAU（当日注册用户数）
- `new_paid_users`: 新用户付费人数
- `new_paid_rate`: 新用户付费率（小数形式）
- `new_paid_amount`: 新用户付费金额（保留2位小数）
- `new_arpu`: 新用户ARPU（保留2位小数）
- `new_arppu`: 新用户ARPPU（保留2位小数）
- `new_withdraw_amount`: 新用户提现金额（保留2位小数）
- `new_withdraw_rate`: 新用户提现率（小数形式）

### 老用户指标
- `old_dau`: 老用户DAU（往日注册但当日活跃的用户数）
- `old_paid_users`: 老用户付费人数
- `old_paid_rate`: 老用户付费率（小数形式）
- `old_paid_amount`: 老用户付费金额（保留2位小数）
- `old_arpu`: 老用户ARPU（保留2位小数）
- `old_arppu`: 老用户ARPPU（保留2位小数）
- `old_withdraw_amount`: 老用户提现金额（保留2位小数）
- `old_withdraw_rate`: 老用户提现率（小数形式）

### 下单指标（游戏交易）
- `order_count`: 下单笔数（当日游戏下注记录数量）
- `order_users`: 下单人数（当日有下注记录的用户数）
- `order_amount`: 下单总金额（当日下注总金额，取绝对值）
- `order_cash`: 现金下单金额（现金钱包下注金额）
- `order_bonus`: 彩金下单金额（彩金钱包下注金额）

### 返奖指标
- `reward_amount`: 返奖金额（当日现金赢取总金额）
- `profit`: 运营商盈利（下单金额 - 返奖金额）

## 模型方法

### OperationData 模型提供的方法

#### 1. 获取数据
```php
// 根据日期和渠道获取数据
$data = OperationData::getByDateAndChannel('2025-07-01', 1);
```

#### 2. 保存数据
```php
// 保存运营数据
$result = OperationData::saveData('2025-07-01', $data, 1);
```

## 定时任务建议

建议使用专门的定时生成命令 `generate:yesterday-operation-data`，该命令会自动生成前一天所有渠道的数据：

### Linux Crontab
```bash
# 每天凌晨2点生成前一天的运营数据
0 2 * * * cd /path/to/project && php think generate:yesterday-operation-data >> /var/log/operation_data.log 2>&1
```

### Windows 计划任务
创建批处理文件 `generate_yesterday_data.bat`：
```batch
@echo off
cd /d D:\PHPwww\slot
php think generate:yesterday-operation-data
if %errorlevel% neq 0 (
    echo 生成运营数据失败，错误代码：%errorlevel%
) else (
    echo 生成运营数据成功
)
pause
```

### 使用原命令的定时任务
如果需要使用原命令，可以这样设置：
```bash
# Linux
0 2 * * * cd /path/to/project && php think generate:operation-data --start-date $(date -d "yesterday" +%Y-%m-%d) --end-date $(date -d "yesterday" +%Y-%m-%d)

# Windows 批处理
@echo off
cd /d D:\PHPwww\slot
for /f "tokens=1-3 delims=/ " %%a in ('date /t') do set yesterday=%%c-%%a-%%b
php think generate:operation-data --start-date %yesterday% --end-date %yesterday%
```

## 注意事项

1. **数据唯一性**: 同一日期同一渠道的数据只能有一条记录，重复生成会更新现有数据
2. **性能考虑**: 大量历史数据生成时建议分批处理，避免内存溢出
3. **数据准确性**: 生成的数据基于实时统计，确保数据库中的原始数据准确
4. **存储空间**: JSON数据会占用一定存储空间，建议定期清理过期数据
5. **错误处理**: 命令会显示详细的执行进度和错误信息

## 故障排除

### 常见问题

1. **日期格式错误**
   - 确保使用 Y-m-d 格式，如 2025-07-01

2. **渠道ID不存在**
   - 检查渠道ID是否在 channel_list 表中存在

3. **数据库连接失败**
   - 检查数据库配置和连接状态

4. **内存不足**
   - 减少处理日期范围，分批执行

### 日志查看
命令执行时会显示详细的进度信息，包括：
- 处理进度
- 成功/失败统计
- 错误详情

## 扩展功能

### 1. 数据导出
可以基于保存的JSON数据开发导出功能，支持Excel、CSV等格式。

### 2. 数据对比
可以对比不同日期或渠道的数据，生成对比报表。

### 3. 数据可视化
可以基于保存的数据生成图表，展示趋势分析。

### 4. 数据清理
可以添加数据清理命令，定期清理过期或无效数据。 