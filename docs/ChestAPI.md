# 宝箱活动API文档

## 接口列表

### 1. 获取宝箱列表
- **接口地址**: `GET /api/chest/list`
- **功能说明**: 获取所有宝箱及用户领取状态，包含统计信息
- **请求参数**: 无
- **响应示例**:
```json
{
    "code": 1,
    "msg": "获取宝箱列表成功",
    "data": {
        "list": [
            {
                "id": 1,
                "name": "新手宝箱",
                "recharge_amount": 0,
                "invite_count": 0,
                "reward_amount": 10,
                "sort": 100,
                "status": 1,
                "image": "default.png",
                "default_image": "default.png",
                "waiting_image": "waiting.png",
                "received_image": "received.png"
            }
        ],
        "statistics": {
            "unclaimed_amount": 50,
            "total_received_amount": 100,
            "today": {
                "invite_count": 2,
                "valid_user_count": 1,
                "team_recharge_amount": 500
            },
            "total": {
                "invite_count": 15,
                "valid_user_count": 8,
                "team_recharge_amount": 5000
            }
        }
    }
}
```

### 2. 领取单个宝箱
- **接口地址**: `POST /api/chest/receive`
- **功能说明**: 用户领取指定宝箱
- **请求参数**:
  - `chest_id`: 宝箱ID (必填)
- **响应示例**:
```json
{
    "code": 1,
    "msg": "领取成功",
    "data": {
        "reward_amount": 10
    }
}
```

### 3. 一键领取所有可领取宝箱 ⭐ 新功能
- **接口地址**: `POST /api/chest/receiveAll`
- **功能说明**: 一键领取所有满足条件的宝箱
- **请求参数**: 无
- **响应示例**:
```json
{
    "code": 1,
    "msg": "一键领取成功",
    "data": {
        "total_reward": 50,
        "received_count": 3,
        "received_chests": [
            {
                "chest_id": 1,
                "chest_name": "新手宝箱",
                "reward_amount": 10
            },
            {
                "chest_id": 2,
                "chest_name": "充值宝箱",
                "reward_amount": 20
            },
            {
                "chest_id": 3,
                "chest_name": "邀请宝箱",
                "reward_amount": 20
            }
        ]
    }
}
```

### 4. 获取领取记录
- **接口地址**: `GET /api/chest/records`
- **功能说明**: 获取用户宝箱领取记录
- **请求参数**: 无
- **响应示例**:
```json
{
    "code": 1,
    "msg": "获取领取记录成功",
    "data": [
        {
            "id": 1,
            "chest_id": 1,
            "reward_amount": 10,
            "createtime": 1640995200
        }
    ]
}
```

## 状态说明

### 宝箱状态 (status)
- `0`: 未达到领取条件
- `1`: 可领取
- `2`: 已领取

### 领取条件
- 用户充值总额 >= 宝箱要求的充值金额
- 用户有效邀请人数 >= 宝箱要求的邀请人数

### 统计信息字段说明

#### statistics 对象
- `unclaimed_amount`: 未领取金额（一键领取时能领取的总金额）
- `total_received_amount`: 总领取金额（历史累计领取的宝箱奖励）

#### today 对象（今日数据）
- `invite_count`: 今日邀请人数（今日注册的直属下级）
- `valid_user_count`: 今日有效用户人数（今日达到充值门槛的直属下级，基于invite_valid_log表）
- `team_recharge_amount`: 今日下级用户充值总金额（包括所有下级）

#### total 对象（总计数据）
- `invite_count`: 总邀请人数（所有直属下级）
- `valid_user_count`: 总有效用户人数（所有达到充值门槛的直属下级，基于invite_valid_log表）
- `team_recharge_amount`: 总下级用户充值总金额（包括所有下级）

## 错误码说明

| 错误码 | 说明 |
|--------|------|
| 1 | 成功 |
| 2 | 失败 |

## 常见错误信息

- `宝箱不存在`: 指定的宝箱ID不存在
- `未达到领取条件`: 用户的充值金额或邀请人数不满足宝箱要求
- `已领取`: 该宝箱已经领取过
- `没有可领取的宝箱`: 一键领取时没有满足条件的宝箱
- `一键领取失败`: 一键领取过程中发生错误

## 使用示例

### 前端调用示例 (JavaScript)

```javascript
// 获取宝箱列表
const getChestList = async () => {
    const response = await fetch('/api/chest/list', {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token
        }
    });
    return await response.json();
};

// 一键领取所有宝箱
const receiveAllChests = async () => {
    const response = await fetch('/api/chest/receiveAll', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    });
    return await response.json();
};

// 使用示例
try {
    const result = await receiveAllChests();
    if (result.code === 1) {
        console.log(`成功领取 ${result.data.received_count} 个宝箱，总奖励: ${result.data.total_reward}`);
        result.data.received_chests.forEach(chest => {
            console.log(`- ${chest.chest_name}: ${chest.reward_amount}`);
        });
    }
} catch (error) {
    console.error('领取失败:', error);
}
```

## 注意事项

1. 所有接口都需要用户登录认证
2. 一键领取功能会自动检查所有宝箱的领取条件
3. 一键领取使用事务处理，确保数据一致性
4. 奖励金额会直接增加到用户账户余额
5. 每个宝箱只能领取一次 