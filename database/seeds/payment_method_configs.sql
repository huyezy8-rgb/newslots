-- 支付方式字段配置数据
-- ECashApp配置
UPDATE `slot_payment_methods` SET 
`field_config` = '{
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
}',
`validation_rules` = '{
  "name": "required|string|max:100",
  "account_name": "required|string|regex:/^\\$[a-zA-Z0-9_]+$/"
}'
WHERE `unique_tag` = 'ecashapp';

-- 银行转账配置
UPDATE `slot_payment_methods` SET 
`field_config` = '{
  "required_fields": ["name", "account_name", "bank_name", "bank_code"],
  "field_labels": {
    "name": "账户持有人姓名",
    "account_name": "银行账号",
    "bank_name": "银行名称",
    "bank_code": "银行代码"
  },
  "field_placeholder": {
    "name": "请输入真实姓名",
    "account_name": "请输入银行账号",
    "bank_name": "请输入银行名称",
    "bank_code": "请输入银行代码"
  },
  "field_type": {
    "name": "text",
    "account_name": "text",
    "bank_name": "text",
    "bank_code": "text"
  }
}',
`validation_rules` = '{
  "name": "required|string|max:100",
  "account_name": "required|string|max:50",
  "bank_name": "required|string|max:100",
  "bank_code": "required|string|max:20"
}'
WHERE `unique_tag` = 'fiat_withdrawal';

-- PayPal配置
UPDATE `slot_payment_methods` SET 
`field_config` = '{
  "required_fields": ["email"],
  "field_labels": {
    "email": "PayPal邮箱地址"
  },
  "field_placeholder": {
    "email": "请输入PayPal邮箱地址"
  },
  "field_type": {
    "email": "email"
  }
}',
`validation_rules` = '{
  "email": "required|email|max:255"
}'
WHERE `unique_tag` = 'paypal';

-- USDT配置
UPDATE `slot_payment_methods` SET 
`field_config` = '{
  "required_fields": ["address", "network"],
  "field_labels": {
    "address": "钱包地址",
    "network": "网络类型"
  },
  "field_placeholder": {
    "address": "请输入USDT钱包地址",
    "network": "请选择网络类型"
  },
  "field_type": {
    "address": "text",
    "network": "select"
  },
  "field_options": {
    "network": [
      {"value": "TRC20", "label": "TRC20 (推荐)"},
      {"value": "ERC20", "label": "ERC20"},
      {"value": "BEP20", "label": "BEP20"}
    ]
  }
}',
`validation_rules` = '{
  "address": "required|string|min:26|max:62",
  "network": "required|in:TRC20,ERC20,BEP20"
}'
WHERE `unique_tag` = 'usdt';

