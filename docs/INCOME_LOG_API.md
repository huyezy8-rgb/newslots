# 用户收益流水接口文档

## 接口概述

用户收益流水接口用于查询用户的收入记录，支持按类型、钱包类型、日期范围等条件筛选。

## 接口列表

### 1. 获取用户收益流水

**接口地址**: `POST /api/account/incomeLog`

**请求参数**:
```json
{
    "page": 1,                    // 页码，默认1
    "page_size": 20,              // 每页数量，默认20，最大100
    "type": "deposit",            // 收益类型筛选（可选）
    "wallet_type": "1",           // 钱包类型筛选（可选）
    "start_date": "2025-01-01",   // 开始日期（可选）
    "end_date": "2025-01-31",     // 结束日期（可选）
    "search_time": 0              // 快捷时间筛选（可选）0-5
}
```

**收益类型 (type)**:
- `deposit`: 充值收益
- `game_win`: 游戏收益
- `activity_reward`: 活动收益
- `leaderboard_reward`: 排行榜奖励
- `commission`: 佣金收益
- `pdd_reward`: 拼多多收益
- `refund`: 退款收益
- `system_operation`: 系统收益

**钱包类型 (wallet_type)**:
- `0`: 体验钱包
- `1`: 充值钱包
- `2`: 佣金钱包
- `3`: 拼多多钱包

**快捷时间筛选 (search_time)**:
- `0`: 今天
- `1`: 昨天
- `2`: 本周
- `3`: 上周
- `4`: 本月
- `5`: 上月

**返回示例**:
```json
{
    "code": 0,
    "error": "",
    "data": {
        "list": [
            {
                "id": 123,
                "log_type_id": 2,
                "wallet_type": 1,
                "amount": 100.00,
                "type": "deposit",
                "type_text": "用户充值",
                "wallet_type_text": "充值钱包",
                "note": "用户充值，金额：100",
                "date": "2025-01-26 10:30:00"
            }
        ],
        "total": 50,
        "page": 1,
        "page_size": 20,
        "pages": 3
    }
}
```

### 2. 获取收益类型列表

**接口地址**: `POST /api/account/incomeTypes`

**请求参数**: 无

**返回示例**:
```json
{
    "code": 0,
    "error": "",
    "data": [
        {
            "key": "deposit",
            "name": "充值收益"
        },
        {
            "key": "game_win",
            "name": "游戏收益"
        },
        {
            "key": "activity_reward",
            "name": "活动收益"
        },
        {
            "key": "leaderboard_reward",
            "name": "排行榜奖励"
        },
        {
            "key": "commission",
            "name": "佣金收益"
        },
        {
            "key": "pdd_reward",
            "name": "拼多多收益"
        },
        {
            "key": "refund",
            "name": "退款收益"
        },
        {
            "key": "system_operation",
            "name": "系统收益"
        }
    ]
}
```

### 3. 获取时间筛选选项列表

**接口地址**: `POST /api/account/timeFilterOptions`

**请求参数**: 无

**返回示例**:
```json
{
    "code": 0,
    "error": "",
    "data": [
        {
            "key": 0,
            "name": "今天"
        },
        {
            "key": 1,
            "name": "昨天"
        },
        {
            "key": 2,
            "name": "本周"
        },
        {
            "key": 3,
            "name": "上周"
        },
        {
            "key": 4,
            "name": "本月"
        },
        {
            "key": 5,
            "name": "上月"
        }
    ]
}
```

## 收益类型详细说明

### 充值收益 (deposit)
- 用户充值
- 限时首充
- 生涯首充
- 每日首充
- VIP充值
- VIP独有充值
- VIP6%充值

### 游戏收益 (game_win)
- 游戏赢得
- 游戏返回

### 活动收益 (activity_reward)
- 注册赠送
- 站内信活动
- 签到活动
- 绑定手机赠送
- 弹窗赠送
- 添加桌面
- 救援金
- 红包兑换
- VIP游戏返利
- 系统赠送
- 会员升级奖励
- 宝箱活动奖励
- 幸运转盘中奖
- 会员周奖励
- 会员月奖励

### 排行榜奖励 (leaderboard_reward)
- 排行榜日榜奖励
- 排行榜周榜奖励
- 排行榜月榜奖励

### 佣金收益 (commission)
- 投注返佣
- 佣金提取到余额

### 拼多多收益 (pdd_reward)
- 邀请转盘提现返还

### 退款收益 (refund)
- 余额提现返回
- 体验账户提现返回
- 邀请转盘提现返还

### 系统收益 (system_operation)
- 系统操作
- 体验金补充

## 注意事项

1. **权限验证**: 接口需要用户登录，只能查询当前用户的收益流水
2. **分页限制**: 每页最大100条记录
3. **日期格式**: 日期参数格式为 `Y-m-d`
4. **时间范围**: 结束日期会自动设置为当天的23:59:59
5. **数据过滤**: 只返回收入记录（num > 0），不包含支出记录
6. **时间筛选优先级**: 如果同时使用 `search_time` 和 `start_date`/`end_date`，`search_time` 优先级更高
7. **返回字段**: 每条记录包含 `type` 字段（英文类型）和 `type_text` 字段（中文描述）
8. **快捷筛选**: `search_time` 参数提供常用的时间范围筛选，方便前端使用
