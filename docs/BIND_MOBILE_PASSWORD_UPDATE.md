# 绑定手机号接口密码功能更新

## 修改概述

根据图片需求，在绑定手机号时增加密码设置功能，类似注册界面的密码输入。

⚠️ **重要**: 此更新需要先执行数据库迁移，为 `slot_account` 表添加 `password` 字段。

## 主要修改

### 1. 接口参数修改

`POST /api/account/bind_mobile`

**新增请求参数**：
```json
{
    "mobile": "手机号",
    "sms_code": "短信验证码",
    "password": "密码",
    "confirm_password": "确认密码"
}
```

### 2. 密码验证逻辑

- **非空验证**: 密码不能为空
- **长度验证**: 密码至少6位
- **一致性验证**: 两次密码必须一致
- **加密存储**: 使用 `password_hash()` 加密存储

### 3. 数据库更新

绑定手机号的同时更新用户密码：
```php
\app\common\model\Account::update([
    "mobile" => $data["mobile"],
    "password" => password_hash($data['password'], PASSWORD_DEFAULT),
], [
    "id" => $this->userInfo['id'],
]);
```

### 4. 错误提示

添加了密码相关的错误提示：
- `Password cannot be empty` - 密码不能为空
- `Password must be at least 6 characters` - 密码至少6位
- `Passwords do not match` - 两次密码不一致

## 数据库迁移

### 方法1: 使用ThinkPHP迁移 (推荐)
```bash
php think migrate:run
```

### 方法2: 直接执行SQL
```sql
-- 为 slot_account 表添加 password 字段
ALTER TABLE `slot_account` 
ADD COLUMN `password` VARCHAR(255) NULL COMMENT '用户密码' AFTER `mobile`;
```

或执行提供的SQL文件：
```bash
mysql -u username -p database_name < add_password_field.sql
```

## 文件修改列表

1. **`database/migrations/20250821120000_add_password_to_account.php`**
   - 数据库迁移文件
   - 添加password字段

2. **`app/common/model/Account.php`**
   - 添加密码字段隐藏配置
   - 保护敏感信息

3. **`app/api/controller/Account.php`**
   - 修改 `bind_mobile()` 方法
   - 增加密码验证逻辑
   - 更新数据库操作

4. **`app/api/lang/zh-cn/account.php`**
   - 添加中文错误提示

5. **`app/api/lang/en/account.php`**
   - 添加英文错误提示

6. **`add_password_field.sql`**
   - 直接执行的SQL脚本

## 使用示例

### 前端调用示例
```javascript
// 绑定手机号请求
const response = await fetch('/api/account/bind_mobile', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
    },
    body: JSON.stringify({
        mobile: '+1234567890',
        sms_code: '123456',
        password: 'mypassword123',
        confirm_password: 'mypassword123'
    })
});
```

### 成功响应
```json
{
    "code": 1,
    "msg": "success",
    "data": null
}
```

### 错误响应示例
```json
{
    "code": 0,
    "msg": "密码至少6位",
    "data": null
}
```

## 安全性说明

1. **密码加密**: 使用 PHP 的 `password_hash()` 函数进行加密
2. **验证码校验**: 保持原有的短信验证码验证逻辑
3. **事务处理**: 使用数据库事务确保数据一致性
4. **参数验证**: 严格验证所有输入参数

## 注意事项

1. 前端需要相应修改界面，添加密码和确认密码输入框
2. 密码强度可以根据需要进一步加强（如包含特殊字符等）
3. 建议前端也添加密码强度提示
4. 可以考虑添加密码找回功能

## 兼容性

此修改向后兼容，但前端需要更新以传递新的密码参数。未传递密码参数时会返回"密码不能为空"的错误。
