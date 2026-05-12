# 提现账号管理功能

## 功能概述

本功能实现了类似收货地址的提现账号管理，用户可以保存常用的提现账号信息，在提现时直接选择使用，无需重复填写。

## 数据库变更

### 1. 支付方式表扩展
为 `slot_payment_methods` 表添加了字段配置：
- `field_config`: 字段配置JSON
- `validation_rules`: 验证规则JSON

### 2. 新增提现账号表
创建了 `slot_withdraw_accounts` 表存储用户账号信息：
- `user_id`: 用户ID
- `payment_method_id`: 支付方式ID
- `unique_tag`: 支付方式唯一标识
- `account_name`: 用户自定义账号名称
- `is_default`: 是否默认账号
- `account_info`: 账号详细信息JSON
- `status`: 状态

## API接口

### 提现账号管理接口

#### 1. 获取用户提现账号列表
```
GET /api/withdraw_account/index
```

**请求参数：**
- `unique_tag` (可选): 支付方式标识，如 `ecashapp`、`fiat_withdrawal` 等

**返回示例：**
```json
{
    "code": 1,
    "msg": "获取成功",
    "data": {
        "accounts": {
            "ecashapp": [
                {
                    "id": 1,
                    "account_name": "我的ECashApp账号",
                    "is_default": 1,
                    "account_info": {
                        "name": "J*** Doe",
                        "account_name": "$j***123"
                    },
                    "payment_method": {
                        "name": "ECashApp",
                        "icon": "/static/images/ecashapp.png"
                    }
                }
            ],
            "fiat_withdrawal": []
        },
        "payment_methods": {
            "ecashapp": {
                "unique_tag": "ecashapp",
                "name": "ECashApp",
                "field_config": {...}
            }
        }
    }
}
```

#### 2. 创建提现账号
```
POST /api/withdraw_account/create
```

**请求参数：**
```json
{
    "unique_tag": "ecashapp",
    "account_name": "我的ECashApp账号",
    "is_default": 1,
    "account_info": {
        "name": "John Doe",
        "account_name": "$john123"
    }
}
```

**返回示例：**
```json
{
    "code": 1,
    "msg": "账号保存成功",
    "data": {
        "id": 1,
        "account_name": "我的ECashApp账号",
        "is_default": 1
    }
}
```

#### 3. 更新提现账号
```
POST /api/withdraw_account/update
```

**请求参数：**
```json
{
    "id": 1,
    "account_name": "新的备注名",
    "is_default": 1,
    "account_info": {
        "name": "John Doe",
        "account_name": "$john123"
    }
}
```

**返回示例：**
```json
{
    "code": 1,
    "msg": "更新成功"
}
```

#### 4. 删除提现账号
```
POST /api/withdraw_account/delete
```

**请求参数：**
```json
{
    "id": 1
}
```

**返回示例：**
```json
{
    "code": 1,
    "msg": "删除成功"
}
```

#### 5. 设置默认账号
```
POST /api/withdraw_account/set_default
```

**请求参数：**
```json
{
    "id": 1
}
```

**返回示例：**
```json
{
    "code": 1,
    "msg": "设置成功"
}
```

#### 6. 获取支付方式配置
```
GET /api/withdraw_account/payment_methods
```

**返回示例：**
```json
{
    "code": 1,
    "msg": "success",
    "data": {
        "ecashapp": {
            "unique_tag": "ecashapp",
            "name": "ECashApp",
            "icon": "/static/images/ecashapp.png",
            "field_config": {
                "required_fields": ["name", "account_name"],
                "field_labels": {
                    "name": "账户持有人姓名",
                    "account_name": "ECashApp账号"
                },
                "field_placeholder": {
                    "name": "请输入真实姓名",
                    "account_name": "请输入ECashApp账号，如：$username123"
                },
                "field_type": {
                    "name": "text",
                    "account_name": "text"
                }
            },
            "validation_rules": {
                "name": "required|string|max:100",
                "account_name": "required|string|regex:/^\\$[a-zA-Z0-9_]+$/"
            }
        },
        "fiat_withdrawal": {
            "unique_tag": "fiat_withdrawal",
            "name": "银行转账",
            "icon": "/static/images/bank.png",
            "field_config": {
                "required_fields": ["name", "account_number", "bank_name", "bank_code"],
                "field_labels": {
                    "name": "账户持有人姓名",
                    "account_number": "银行账号",
                    "bank_name": "银行名称",
                    "bank_code": "银行代码"
                },
                "field_placeholder": {
                    "name": "请输入真实姓名",
                    "account_number": "请输入银行账号",
                    "bank_name": "请输入银行名称",
                    "bank_code": "请输入银行代码"
                },
                "field_type": {
                    "name": "text",
                    "account_number": "text",
                    "bank_name": "text",
                    "bank_code": "text"
                }
            },
            "validation_rules": {
                "name": "required|string|max:100",
                "account_number": "required|string|max:50",
                "bank_name": "required|string|max:100",
                "bank_code": "required|string|max:20"
            }
        }
    }
}
```

### 修改后的提现接口

#### 1. 获取提现页面数据
```
GET /api/withdraw/index
```

**请求参数：**
- `typeid`: 提现类型ID

**返回示例：**
```json
{
    "code": 1,
    "msg": "success",
    "data": {
        "withdraw_limit": {
            "min": 10,
            "max": 10000
        },
        "withdraw_channels": [
            {
                "unique_tag": "ecashapp",
                "name": "ECashApp",
                "icon": "/static/images/ecashapp.png"
            }
        ],
        "saved_accounts": {
            "ecashapp": [
                {
                    "id": 1,
                    "account_name": "我的ECashApp账号",
                    "is_default": 1,
                    "account_info": {
                        "name": "J*** Doe",
                        "account_name": "$j***123"
                    }
                }
            ]
        }
    }
}
```

#### 2. 提交提现
```
POST /api/withdraw/submit
```

**请求参数（使用已保存账号）：**
```json
{
    "typeid": 3,
    "amount": 100,
    "pay_type": "ecashapp",
    "account_id": 1
}
```

**请求参数（使用临时账号）：**
```json
{
    "typeid": 3,
    "amount": 100,
    "pay_type": "ecashapp",
    "account_info": {
        "name": "John Doe",
        "account_name": "$john123"
    }
}
```

**返回示例：**
```json
{
    "code": 1,
    "msg": "提现申请提交成功",
    "data": {
        "order_id": "WD202501010001"
    }
}
```

## 接口说明

由于项目使用 ThinkPHP 的自动路由机制，接口路径会自动映射到控制器方法：

- `GET /api/withdraw_account/index` - 获取用户提现账号列表
- `POST /api/withdraw_account/create` - 创建提现账号
- `POST /api/withdraw_account/update` - 更新提现账号
- `POST /api/withdraw_account/delete` - 删除提现账号
- `POST /api/withdraw_account/set_default` - 设置默认账号
- `GET /api/withdraw_account/payment_methods` - 获取支付方式配置

## 部署步骤

### 1. 运行数据库迁移
```bash
php think migrate:run
```

### 2. 导入支付方式配置
```bash
mysql -u username -p database_name < database/seeds/payment_method_configs.sql
```

### 3. 清除缓存
```bash
php think clear
```

## 使用方式

### 前端集成示例

```javascript
// 获取提现页面数据
const response = await fetch('/api/withdraw/index?typeid=3');
const data = await response.json();

// 获取用户提现账号列表
const getAccountList = async (uniqueTag = '') => {
    const response = await fetch(`/api/withdraw_account/index?unique_tag=${uniqueTag}`);
    return response.json();
};

// 获取支付方式配置
const methodsResponse = await fetch('/api/withdraw_account/payment_methods');
const methods = await methodsResponse.json();

// 创建提现账号
const createAccount = async (accountData) => {
    const response = await fetch('/api/withdraw_account/create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(accountData)
    });
    return response.json();
};

// 更新提现账号
const updateAccount = async (accountData) => {
    const response = await fetch('/api/withdraw_account/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(accountData)
    });
    return response.json();
};

// 删除提现账号
const deleteAccount = async (id) => {
    const response = await fetch('/api/withdraw_account/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id })
    });
    return response.json();
};

// 设置默认账号
const setDefaultAccount = async (id) => {
    const response = await fetch('/api/withdraw_account/set_default', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id })
    });
    return response.json();
};

// 提交提现（使用已保存账号）
const withdrawWithSavedAccount = async (withdrawData) => {
    const response = await fetch('/api/withdraw/submit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(withdrawData)
    });
    return response.json();
};
```

## 功能特点

1. **配置化字段管理**: 支付方式的字段要求通过数据库配置，无需修改代码
2. **账号信息脱敏**: 显示时自动脱敏保护用户隐私
3. **默认账号设置**: 支持为每种支付方式设置默认账号
4. **数据安全**: 账号信息直接存储JSON格式，无需加密
5. **向后兼容**: 支持临时账号和保存账号两种模式
6. **灵活验证**: 支持动态字段验证规则
7. **RESTful API**: 遵循RESTful设计原则

## 支持的支付方式

- **ECashApp**: 需要姓名和账号
- **银行转账**: 需要姓名、账号、银行名称、银行代码
- **PayPal**: 需要邮箱地址
- **USDT**: 需要钱包地址和网络类型

## 注意事项

1. 用户必须绑定手机号才能进行提现
2. 每个用户每种支付方式只能有一个默认账号
3. 删除账号为物理删除，会从数据库中完全删除数据
4. 账号信息会进行脱敏显示，保护用户隐私
5. 所有接口都需要用户登录认证
6. 字段验证规则由支付方式配置决定，支持动态调整
