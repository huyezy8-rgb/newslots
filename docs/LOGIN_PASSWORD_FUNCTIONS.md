# 登录密码功能更新

## 新增功能概述

在登录模块中新增了密码登录和忘记密码功能，支持短信验证。

## 新增接口

### 1. 密码登录接口

**接口地址**: `POST /api/login/password`

**请求参数**:
```json
{
    "mobile": "+1234567890",
    "password": "mypassword123"
}
```

**成功响应**:
```json
{
    "code": 1,
    "msg": "登录成功",
    "data": {
        "token": "user_token",
        "name": "username",
        "nickname": "nickname",
        "mobile": "+1234567890",
        "vip": 0,
        "channel_id": 1,
        "channel_name": "main",
        "invite_code": "ABC123",
        "experience_wallet": 100.00,
        "recharge_wallet": 200.00,
        "switch_wallet": 0,
        "account_info": {...},
        "channel_info": {...}
    }
}
```

**错误响应**:
```json
{
    "code": 0,
    "msg": "密码错误",
    "data": null
}
```

### 2. 忘记密码接口

**接口地址**: `POST /api/login/forgot_password`

**请求参数**:
```json
{
    "mobile": "+1234567890",
    "sms_code": "123456",
    "new_password": "newpassword123",
    "confirm_password": "newpassword123"
}
```

**成功响应**:
```json
{
    "code": 1,
    "msg": "密码重置成功",
    "data": null
}
```

## 短信验证码更新

### 发送短信接口更新

**接口地址**: `POST /api/account/send_sms`

**新增event类型**: `forgot_password` (忘记密码)

**请求参数**:
```json
{
    "mobile": "+1234567890",
    "event": "forgot_password"
}
```

**支持的event类型**:
- `bind` - 绑定手机号
- `login` - 短信登录
- `forgot_password` - 忘记密码

## 功能特性

### 密码登录功能
1. ✅ 手机号+密码登录方式
2. ✅ 密码验证使用 `password_verify()` 安全验证
3. ✅ 自动检测密码是否已设置
4. ✅ 未设置密码时提示使用短信登录
5. ✅ 登录成功后更新最后登录时间
6. ✅ 返回完整的用户和渠道信息

### 忘记密码功能
1. ✅ 短信验证码验证
2. ✅ 新密码强度验证（至少6位）
3. ✅ 密码确认验证
4. ✅ 使用事务保证数据一致性
5. ✅ 验证码使用后自动标记为已使用
6. ✅ 密码使用 `password_hash()` 安全加密

## 安全措施

1. **密码加密**: 使用PHP标准 `password_hash()` 和 `password_verify()`
2. **短信验证**: 忘记密码必须通过短信验证
3. **验证码过期**: 验证码5分钟后自动过期
4. **事务处理**: 密码重置使用数据库事务
5. **参数验证**: 严格验证所有输入参数

## 使用流程

### 密码登录流程
1. 用户输入手机号和密码
2. 系统验证手机号是否存在
3. 检查用户是否已设置密码
4. 验证密码是否正确
5. 登录成功，返回用户信息

### 忘记密码流程
1. 用户输入手机号
2. 发送短信验证码 (event: "forgot_password")
3. 用户输入验证码和新密码
4. 系统验证验证码和密码格式
5. 更新密码，重置成功

## 错误处理

| 错误信息 | 说明 |
|---------|------|
| 手机号不能为空 | 未输入手机号 |
| 密码不能为空 | 未输入密码 |
| 手机号不存在 | 手机号未注册 |
| 密码未设置，请使用短信登录 | 用户未设置密码 |
| 密码错误 | 密码验证失败 |
| 密码至少6位 | 新密码长度不足 |
| 两次密码不一致 | 密码确认不匹配 |
| 验证码错误 | 短信验证码错误 |
| 验证码已过期 | 验证码超时 |

## 文件修改列表

1. **`app/api/controller/Login.php`**
   - 新增 `password()` 方法 - 密码登录
   - 新增 `forgot_password()` 方法 - 忘记密码

2. **`app/api/controller/Account.php`**
   - 修改 `send_sms()` 方法 - 支持忘记密码事件

3. **`app/api/lang/zh-cn/account.php`**
   - 添加新的中文错误提示

4. **`app/api/lang/en/account.php`**
   - 添加新的英文错误提示

## 前端集成示例

### 密码登录
```javascript
const loginWithPassword = async (mobile, password) => {
    const response = await fetch('/api/login/password', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            mobile: mobile,
            password: password
        })
    });
    return await response.json();
};
```

### 忘记密码
```javascript
const resetPassword = async (mobile, smsCode, newPassword, confirmPassword) => {
    const response = await fetch('/api/login/forgot_password', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            mobile: mobile,
            sms_code: smsCode,
            new_password: newPassword,
            confirm_password: confirmPassword
        })
    });
    return await response.json();
};
```

## 注意事项

1. 密码登录功能依赖于用户已通过绑定手机号接口设置过密码
2. 忘记密码功能需要先调用发送短信接口获取验证码
3. 建议前端添加密码强度提示和格式验证
4. 所有密码相关操作都使用POST请求
5. 响应数据结构与现有登录接口保持一致
