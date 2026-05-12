# 团队管理API接口更新

## 概述

根据前端界面需求，更新了 `app/api/controller/Team.php` 控制器，添加了佣金统计、代理管理等功能。

## 更新的接口

### 1. 主接口 - 获取团队信息
**接口地址:** `POST /api/team/index`

**响应数据结构:**
```json
{
  "code": 1,
  "msg": "Team information retrieved successfully",
  "data": {
    "user_info": {
      "id": 32545645,
      "name": "用户名",
      "nickname": "昵称",
      "rebate_rate": 50.00,
      "commission_balance": 5400.00,
      "team_path": "/1/2/3/",
      "team_level": 3,
      "p_id": 123
    },
    "commission_stats": {
      "total_commission": 5400.00,
      "total_bet_amount": 5400.00,
      "agent_count": 6854
    },
    "team_stats": {
      "team_user_count": 10000,
      "team_recharge": 50000.00
    },
    "direct_agents": [
      {
        "id": 32545645,
        "name": "代理名称",
        "nickname": "代理昵称",
        "rebate_rate": 0.00,
        "commission_balance": 100.00,
        "create_time": 1640995200,
        "bet_amount": 5400.00,
        "commission_from_agent": 27.00,
        "lower_level_commission": 5400.00
      }
    ]
  }
}
```

### 2. 代理列表（合并搜索功能）
**接口地址:** `POST /api/team/agents`

**请求参数:**
- `page`: 页码 (默认: 1)
- `page_size`: 每页数量 (默认: 10)
- `keyword`: 搜索关键词 (可选，支持ID、用户名、昵称搜索)

**响应数据:**
```json
{
  "code": 1,
  "msg": "Agent list retrieved successfully",
  "data": {
    "list": [
      {
        "id": 32545645,
        "name": "代理名称",
        "nickname": "代理昵称",
        "rebate_rate": 0.00,
        "commission_balance": 100.00,
        "create_time": 1640995200,
        "bet_amount": 5400.00,
        "commission_from_agent": 27.00,
        "lower_level_commission": 5400.00
      }
    ],
    "total": 100,
    "page": 1,
    "page_size": 10,
    "pages": 10,
    "keyword": "32545645",
    "has_search": true
  }
}
```

### 3. 调整代理返佣比例
**接口地址:** `POST /api/team/adjustRebateRate`

**请求参数:**
- `agent_id`: 代理ID (必填)
- `rebate_rate`: 新的返佣比例 (0-100, 必填)

**响应数据:**
```json
{
  "code": 1,
  "msg": "Rebate rate adjusted successfully"
}
```

## 关键特性

### 1. 佣金统计
- **总佣金收入**: 从 `slot_team_commission_log` 表统计用户获得的佣金总额
- **总投注额**: 统计下级产生的投注金额总和
- **代理数量**: 直属下级用户数量

### 2. 代理管理
- **返佣比例显示**: 显示每个代理的当前返佣比例
- **投注额统计**: 统计每个代理的投注金额
- **下级佣金**: 显示代理从其下级获得的佣金
- **搜索功能**: 支持按代理ID、用户名、昵称搜索，实时显示搜索结果的佣金统计信息

### 3. 返佣比例调整
- 支持调整直属下级的返佣比例
- 验证权限确保只能调整自己的直属下级
- 范围限制在0-100%之间

## 数据表依赖

### 主要表结构:
1. **slot_account** - 用户账户表
   - `rebate_rate`: 返佣比例
   - `commission_balance`: 佣金余额  
   - `p_id`: 上级ID
   - `team_path`: 团队路径

2. **slot_team_commission_log** - 团队佣金日志表
   - `user_id`: 获得佣金的用户ID
   - `source_user_id`: 产生投注的用户ID
   - `commission`: 佣金金额
   - `bet_amount`: 投注金额

## 私有方法说明

### getCommissionStatistics($userId)
获取用户的佣金统计信息，包括总佣金、总投注额、代理数量。

### getTeamStatistics($userId) 
获取团队统计信息，包括团队总人数和团队总充值。

### getAgentCommissionData($agentId)
获取单个代理的详细佣金数据，包括投注额、上级佣金、下级佣金。

## 前端对接说明

1. **主页面数据**: 调用 `/api/team/index` 获取用户基本信息和概览统计
2. **代理列表**: 调用 `/api/team/agents` 获取分页的代理列表，支持搜索功能
3. **比例调整**: 调用 `/api/team/adjustRebateRate` 修改代理返佣比例

## 注意事项

1. **请求方式**: 所有接口都使用 POST 请求，因为涉及登录验证
2. **参数类型转换**: 所有数值参数使用 `intval()` 或 `floatval()` 确保数据类型正确
3. **分页参数**: 有默认值，前端可选择传递
4. **搜索功能**: 支持模糊匹配用户名和昵称，精确匹配ID，与分页功能完美结合
5. **权限验证**: 返佣比例调整有权限验证，只能调整直属下级
6. **异常处理**: 完善的错误处理，确保接口稳定性
