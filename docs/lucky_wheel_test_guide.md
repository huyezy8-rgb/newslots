# 幸运转盘功能测试指南

## 测试环境准备

### 1. 数据库准备
- 确保MySQL服务正常运行
- 数据库名：`slot_test`
- 用户名：`root`
- 密码：`root123`

### 2. 表结构创建
执行SQL文件创建表结构：
```sql
-- 执行 database/lucky_wheel_tables.sql
```

### 3. 测试数据导入
运行测试数据脚本：
```bash
php test_lucky_wheel_data.php
```

## 测试数据概览

### 主配置
- **活动标题**：幸运转盘大抽奖
- **Banner图**：https://example.com/banner.jpg
- **打码倍数**：1.5倍
- **活动状态**：启用

### 转盘配置

#### 转盘1（新手转盘）
- **解锁条件**：0元（无需充值）
- **默认赠送**：1次
- **奖项设置**：
  - 谢谢参与：40%
  - 0.5元：25%
  - 1元：15%
  - 2元：10%
  - 5元：5%
  - 10元：3%
  - 20元：1.5%
  - 50元：0.5%
- **规则设置**：
  - 下注达到50元：赠送1次
  - 充值达到100元：赠送2次

#### 转盘2（进阶转盘）
- **解锁条件**：500元
- **默认赠送**：0次
- **奖项设置**：
  - 谢谢参与：30%
  - 1元：20%
  - 2元：15%
  - 5元：12%
  - 10元：10%
  - 20元：8%
  - 50元：4%
  - 100元：1%
- **规则设置**：
  - 下注达到1000元：赠送1次
  - 充值达到2000元：赠送3次
  - 下注达到5000元：赠送5次

#### 转盘3（豪华转盘）
- **解锁条件**：2000元
- **默认赠送**：0次
- **奖项设置**：
  - 谢谢参与：25%
  - 2元：20%
  - 5元：15%
  - 10元：12%
  - 20元：10%
  - 50元：8%
  - 100元：7%
  - 500元：3%
- **规则设置**：
  - 下注达到5000元：赠送2次
  - 充值达到10000元：赠送5次
  - 下注达到20000元：赠送10次

## 功能测试步骤

### 1. 主配置测试
1. 访问：后台管理 → 活动管理 → 幸运转盘 → 配置
2. 验证显示内容：
   - 活动标题：幸运转盘大抽奖
   - Banner图：https://example.com/banner.jpg
   - 打码倍数：1.5
   - 活动状态：启用
3. 测试修改功能：
   - 修改活动标题
   - 修改打码倍数
   - 保存并验证

### 2. 转盘1测试
1. 访问：后台管理 → 活动管理 → 幸运转盘 → 转盘1
2. 验证基础配置：
   - 转盘名称：新手转盘
   - 解锁条件：0
   - 赠送次数：1
   - 转盘状态：启用
3. 验证奖项配置：
   - 检查8个奖项是否正确显示
   - 验证概率总和是否为1
4. 验证规则配置：
   - 检查2条规则是否正确显示
   - 测试添加新规则
   - 测试删除规则

### 3. 转盘2测试
1. 访问：后台管理 → 活动管理 → 幸运转盘 → 转盘2
2. 验证基础配置：
   - 转盘名称：进阶转盘
   - 解锁条件：500
   - 赠送次数：0
   - 转盘状态：启用
3. 验证奖项配置：
   - 检查8个奖项是否正确显示
   - 验证概率总和是否为1
4. 验证规则配置：
   - 检查3条规则是否正确显示

### 4. 转盘3测试
1. 访问：后台管理 → 活动管理 → 幸运转盘 → 转盘3
2. 验证基础配置：
   - 转盘名称：豪华转盘
   - 解锁条件：2000
   - 赠送次数：0
   - 转盘状态：启用
3. 验证奖项配置：
   - 检查8个奖项是否正确显示
   - 验证概率总和是否为1
4. 验证规则配置：
   - 检查3条规则是否正确显示

## API接口测试

### 1. 主配置接口
```bash
# 获取主配置
GET /admin/activity.lucky_wheel/edit

# 保存主配置
POST /admin/activity.lucky_wheel/edit
Content-Type: application/json
{
  "title": "测试标题",
  "banner_image": "https://test.com/banner.jpg",
  "bet_multiple": 2.0,
  "status": 1
}
```

### 2. 转盘配置接口
```bash
# 获取转盘配置
GET /admin/activity.lucky_wheel_turntable/edit?id=1

# 保存转盘基础配置
POST /admin/activity.lucky_wheel_turntable/edit
Content-Type: application/json
{
  "id": 1,
  "wheel_name": "测试转盘",
  "unlock_condition": 100,
  "free_times": 2,
  "status": 1
}

# 更新奖项配置
POST /admin/activity.lucky_wheel_turntable/updatePrizes
Content-Type: application/json
{
  "id": 1,
  "prizes": [
    {"title": "谢谢参与", "amount": 0, "probability": 0.3, "sort": 1},
    {"title": "1元", "amount": 1, "probability": 0.2, "sort": 2}
    // ... 其他奖项
  ]
}

# 更新规则配置
POST /admin/activity.lucky_wheel_turntable/updateRules
Content-Type: application/json
{
  "id": 1,
  "rules": [
    {"rule_type": 1, "condition_value": 100, "reward_times": 1, "status": 1}
  ]
}
```

## 数据库验证

### 1. 检查主配置表
```sql
SELECT * FROM slot_lucky_wheel_config WHERE id = 1;
```

### 2. 检查转盘表
```sql
SELECT id, wheel_name, unlock_condition, free_times, status FROM slot_lucky_wheel_turntable;
```

### 3. 检查奖项配置
```sql
SELECT id, wheel_name, prizes FROM slot_lucky_wheel_turntable WHERE id = 1;
```

### 4. 检查规则配置
```sql
SELECT id, wheel_name, rules FROM slot_lucky_wheel_turntable WHERE id = 1;
```

### 5. 检查记录表
```sql
SELECT * FROM slot_lucky_wheel_logs ORDER BY createtime DESC LIMIT 10;
```

## 常见问题排查

### 1. 页面无法访问
- 检查路由配置是否正确
- 验证Vue组件文件是否存在
- 检查浏览器控制台错误

### 2. 数据无法加载
- 检查数据库连接
- 验证表结构是否正确
- 检查API接口返回数据

### 3. 保存失败
- 检查表单验证规则
- 验证必填字段是否完整
- 检查数据库权限

### 4. JSON解析错误
- 验证JSON格式是否正确
- 检查中文字符编码
- 确认数据类型匹配

## 性能测试

### 1. 页面加载时间
- 主配置页面：< 2秒
- 转盘配置页面：< 3秒

### 2. 数据保存时间
- 基础配置保存：< 1秒
- 奖项配置保存：< 2秒
- 规则配置保存：< 1秒

### 3. 并发测试
- 支持多用户同时配置
- 数据一致性验证

## 测试报告模板

### 功能测试结果
- [ ] 主配置功能正常
- [ ] 转盘1配置功能正常
- [ ] 转盘2配置功能正常
- [ ] 转盘3配置功能正常
- [ ] 奖项配置功能正常
- [ ] 规则配置功能正常
- [ ] 数据保存功能正常
- [ ] 数据加载功能正常

### 性能测试结果
- [ ] 页面加载时间符合要求
- [ ] 数据保存时间符合要求
- [ ] 并发访问正常

### 问题记录
1. 问题描述：
2. 复现步骤：
3. 期望结果：
4. 实际结果：
5. 解决方案：

## 测试完成标准

1. **功能完整性**：所有配置功能正常工作
2. **数据准确性**：配置数据正确保存和加载
3. **用户体验**：界面友好，操作流畅
4. **性能要求**：响应时间符合预期
5. **稳定性**：长时间运行无异常

测试完成后，可以删除测试脚本：
```bash
rm test_lucky_wheel_data.php
``` 