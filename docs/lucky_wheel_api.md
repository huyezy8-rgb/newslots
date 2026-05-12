# 幸运转盘客户端API接口文档

## 接口概述

幸运转盘客户端API提供转盘信息获取、抽奖和记录查询功能。所有接口都需要用户登录认证。

**系统特性**：
- Redis缓存优化，提升响应速度
- 分布式锁防止并发抽奖
- 自动缓存更新机制

## 基础信息

- **基础URL**: `/api/lucky_wheel`
- **认证方式**: Token认证（在请求头中携带token）
- **数据格式**: JSON
- **字符编码**: UTF-8
- **缓存策略**: Redis缓存 + 分布式锁

## 接口列表

### 1. 获取幸运转盘信息

**接口地址**: `GET /api/lucky_wheel/info`

**接口描述**: 获取用户可用的转盘信息，包括活动配置、转盘列表、用户可用次数等

**缓存策略**: 
- 活动配置缓存1小时
- 转盘配置缓存1小时  
- 用户数据缓存5分钟

**请求参数**: 无

**请求示例**:
```bash
curl -X GET "https://your-domain.com/api/lucky_wheel/info" \
  -H "Authorization: Bearer your-token-here"
```

**响应示例**:
```json
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "config": {
      "title": "幸运转盘大抽奖",
      "banner_image": "https://example.com/banner.jpg",
      "bet_multiple": 1.5
    },
    "wheels": [
      {
        "id": 1,
        "name": "新手转盘",
        "unlock_condition": 0.00,
        "free_times": 1,
        "max_user_times": 10,
        "available_times": 1,
        "prizes": [
          {
            "title": "谢谢参与",
            "amount": 0,
            "probability": 0.3,
            "sort": 1
          },
          {
            "title": "1元",
            "amount": 1,
            "probability": 0.2,
            "sort": 2
          }
        ],
        "is_unlocked": true,
        "total_reward_amount": 188.0
      },
      {
        "id": 2,
        "name": "进阶转盘",
        "unlock_condition": 500.00,
        "free_times": 0,
        "max_user_times": 5,
        "available_times": 2,
        "prizes": [...],
        "is_unlocked": true,
        "total_reward_amount": 885.0
      },
      {
        "id": 3,
        "name": "豪华转盘",
        "unlock_condition": 2000.00,
        "free_times": 0,
        "max_user_times": 3,
        "available_times": 0,
        "prizes": [...],
        "is_unlocked": false,
        "total_reward_amount": 1885.0
      }
    ],
    "user_recharge": 1000.00
  }
}
```

**响应字段说明**:
- `config`: 活动配置信息
  - `title`: 活动标题
  - `banner_image`: 活动Banner图片
  - `bet_multiple`: 打码倍数
- `wheels`: 转盘列表（返回所有转盘，包括未解锁的）
  - `id`: 转盘ID
  - `name`: 转盘名称
  - `unlock_condition`: 解锁条件（充值金额）
  - `free_times`: 基础赠送次数
  - `max_user_times`: 用户最大次数限制（0表示无限制）
  - `available_times`: 用户当前可用次数
  - `prizes`: 奖项配置数组
  - `is_unlocked`: 是否已解锁（true-已解锁，false-未解锁）
  - `total_reward_amount`: 转盘总奖励金额（所有奖项金额之和）
- `user_recharge`: 用户累计充值金额

### 2. 执行转盘抽奖

**接口地址**: `POST /api/lucky_wheel/draw`

**接口描述**: 执行转盘抽奖，返回中奖结果

**并发控制**: 
- 使用Redis分布式锁防止重复抽奖
- 锁超时时间10秒，重试3次
- 同一用户同一转盘同时只能进行一次抽奖

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| wheel_id | int | 是 | 转盘ID |

**请求示例**:
```bash
curl -X POST "https://your-domain.com/api/lucky_wheel/draw" \
  -H "Authorization: Bearer your-token-here" \
  -H "Content-Type: application/json" \
  -d '{
    "wheel_id": 1
  }'
```

**响应示例**:
```json
{
  "code": 1,
  "msg": "抽奖成功",
  "data": {
    "prize": {
      "title": "10元",
      "amount": 10,
      "probability": 0.08,
      "sort": 5
    },
    "log_id": 123,
    "remaining_times": 0
  }
}
```

**响应字段说明**:
- `prize`: 中奖奖项信息
  - `title`: 奖项标题
  - `amount`: 奖项金额
  - `probability`: 中奖概率
  - `sort`: 奖项排序
- `log_id`: 转盘记录ID
- `remaining_times`: 剩余可用次数

**错误响应示例**:
```json
{
  "code": 0,
  "msg": "转盘次数已用完"
}
```

**并发错误响应**:
```json
{
  "code": 0,
  "msg": "获取锁失败，请稍后重试"
}
```

### 3. 获取用户转盘记录

**接口地址**: `GET /api/lucky_wheel/logs`

**接口描述**: 获取用户的转盘抽奖记录

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| wheel_id | int | 否 | 转盘ID，不传则获取所有转盘记录 |
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页数量，默认10 |

**请求示例**:
```bash
curl -X GET "https://your-domain.com/api/lucky_wheel/logs?wheel_id=1&page=1&limit=10" \
  -H "Authorization: Bearer your-token-here"
```

**响应示例**:
```json
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "list": [
      {
        "id": 123,
        "user_id": 1001,
        "wheel_id": 1,
        "prize_title": "10元",
        "prize_amount": 10.00,
        "status": 1,
        "createtime": 1704067200
      },
      {
        "id": 122,
        "user_id": 1001,
        "wheel_id": 1,
        "prize_title": "谢谢参与",
        "prize_amount": 0.00,
        "status": 0,
        "createtime": 1703980800
      }
    ],
    "total": 25,
    "page": 1,
    "limit": 10
  }
}
```

**响应字段说明**:
- `list`: 记录列表
  - `id`: 记录ID
  - `user_id`: 用户ID
  - `wheel_id`: 转盘ID
  - `prize_title`: 中奖奖项标题
  - `prize_amount`: 中奖金额
  - `status`: 发放状态（0-未发放，1-已发放）
  - `createtime`: 创建时间戳
- `total`: 总记录数
- `page`: 当前页码
- `limit`: 每页数量

## 错误码说明

| 错误码 | 说明 |
|--------|------|
| 0 | 操作失败 |
| 1 | 操作成功 |
| 2 | Token错误或为空 |
| 409 | Token过期 |

## 业务规则说明

### 转盘解锁规则
1. 新手转盘：无解锁条件，所有用户可用
2. 进阶转盘：需要累计充值≥500元
3. 豪华转盘：需要累计充值≥2000元

### 转盘次数计算规则
1. **基础次数**: 转盘配置的免费次数
2. **规则次数**: 根据用户充值情况，按规则配置赠送次数
3. **已用次数**: 用户已使用的转盘次数
4. **最大限制**: 转盘配置的用户最大次数限制
5. **可用次数** = min(基础次数 + 规则次数 - 已用次数, 最大限制 - 已用次数)

### 抽奖规则
1. 用户必须有可用次数才能抽奖
2. 抽奖结果根据奖项概率随机生成：
   - 系统会计算所有奖项概率的总和
   - 如果总概率为0，则随机返回一个奖项
   - 如果总概率大于0，则按概率比例进行抽奖
   - 概率总和不再限制为1，可以灵活设置
3. 中奖金额大于0时，自动发放到用户账户
4. 每次抽奖消耗1次可用次数
5. **并发控制**: 使用Redis锁确保同一用户同一转盘同时只能抽奖一次

### 奖励发放规则
1. 中奖金额自动添加到用户充值钱包
2. 使用AccountService的increaseBalance方法确保数据一致性
3. 记录详细的资金变动日志（CoinLog::LuckyWheel）
4. 更新转盘记录状态为已发放
5. 支持打码倍数限制（在提现时检查）

### 缓存更新规则
1. **抽奖后**: 自动清除用户相关缓存
2. **配置修改**: 后台修改配置时清除对应缓存
3. **过期机制**: 缓存自动过期，确保数据新鲜度

## 使用流程

### 1. 获取转盘信息
```javascript
// 前端调用示例
const response = await fetch('/api/lucky_wheel/info', {
  headers: {
    'Authorization': 'Bearer ' + token
  }
});
const data = await response.json();
```

### 2. 执行抽奖
```javascript
// 前端调用示例
const response = await fetch('/api/lucky_wheel/draw', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    wheel_id: 1
  })
});
const result = await response.json();

// 处理并发错误
if (result.code === 0 && result.msg.includes('获取锁失败')) {
  // 提示用户稍后重试
  alert('系统繁忙，请稍后重试');
}
```

### 3. 查看记录
```javascript
// 前端调用示例
const response = await fetch('/api/lucky_wheel/logs?page=1&limit=10', {
  headers: {
    'Authorization': 'Bearer ' + token
  }
});
const logs = await response.json();
```
