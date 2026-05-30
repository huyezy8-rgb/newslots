# 支付智能控制客户端变动文档

本文只记录本次客户端需要对接的变动点。

## 1. 充值页

充值方式列表接口不变：

```http
GET /index.php/api/recharge/index?server=1&token={token}
```

客户端继续使用：

```js
data.pay_channels
```

变动点：

- 客户端不需要判断充值次数。
- 后端会根据后台“充值次数控制”自动追加或隐藏支付方式。
- 充值列表不返回实际支付渠道。
- 提交充值时，`pay_type` 只能使用当前列表返回项的 `channel`。

提交接口不变：

```http
POST /index.php/api/recharge/create?server=1&token={token}
```

参数示例：

```json
{
  "price": 300,
  "pay_type": "saxpay",
  "event_name": "normal"
}
```

如果提交了当前不可用的 `pay_type`，后端会返回：

```json
{
  "code": 0,
  "msg": "Payment method param error",
  "data": null
}
```

## 2. 提现页

提现方式列表接口新增可选参数 `amount`：

```http
GET /index.php/api/withdraw/index?server=1&token={token}&typeid=3&amount={amount}
```

客户端需要在用户输入提现金额变化时重新请求该接口。

示例：

```http
GET /index.php/api/withdraw/index?server=1&token={token}&typeid=3&amount=101
```

变动点：

- 后台“提现金额控制”命中后，后端只返回配置的指定支付方式。
- 返回项仍按支付方式展示，客户端继续展示返回项的 `name`。
- `smart_control_hit` 表示本次列表是否命中提现金额控制规则。
- 提现列表不返回实际支付渠道。

返回项示例：

```json
{
  "channel": "ecashapp",
  "name": "ecashapp",
  "smart_control_hit": true
}
```

提现提交接口不变：

```http
POST /index.php/api/withdraw/submit?server=1&token={token}
```

参数示例：

```json
{
  "typeid": 3,
  "amount": 101,
  "pay_type": "ecashapp",
  "name": "User Name",
  "account_name": "$cashapp_account"
}
```

提交规则：

- `amount` 必须是用户当前输入金额。
- `pay_type` 只能使用当前金额下 `withdraw/index` 返回项的 `channel`。
- 后端提交时会再次校验智能控制规则，前端绕过会被拒绝。

## 3. 客服按钮

“支付失败次数达到指定次数显示联系客服”本次后端无新增接口。

客户端按现有前端逻辑自行处理。
