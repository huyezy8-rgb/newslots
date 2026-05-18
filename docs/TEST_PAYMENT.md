# TestPay 测试支付说明

## 用途

`TestPay` 是内部测试支付通道，用于在本地或测试环境验证充值、提现和回调后的业务链路。

它不请求第三方支付平台，也不需要真实收银台页面。

## 数据初始化

测试支付方式通过 Seeder 写入数据，不通过 migration 修改表结构。

执行：

```bash
php think seed:run -s TestPaymentMethodSeeder
```

Seeder 文件：

```text
database/seeds/TestPaymentMethodSeeder.php
```

写入的数据：

- `payment_channels.code = Test`
- `payment_methods.unique_tag = testpay`
- `payment_methods.code = testpay`
- `payment_methods.channel_code = Test`

`channel_code = Test` 会映射到支付驱动类：

```text
app/common/library/pay/TestPay.php
```

## 安全约定

Seeder 只负责数据，不负责字段结构变更。

如果库中已经存在 `payment_channels.code = Test`，Seeder 只会在确认它本来就是内部 `TestPay` 通道时更新；否则会抛错，避免覆盖已有真实通道。

如果库中已经存在 `payment_methods.unique_tag = testpay`，Seeder 只会在确认它归属 `TestPay` 时更新；否则会抛错。

## 充值下单

普通第三方支付通常要求返回：

- `payOrderNo`
- `cashierUrl`

但 `TestPay` 不需要 `cashierUrl`。充值下单成功后，接口会返回空字符串：

```json
{
  "cashier_url": ""
}
```

这是预期行为。测试支付的完成动作由命令模拟回调触发。

## 模拟回调

充值成功：

```bash
php think payment:test-callback --order=PAY订单号 --status=success --type=recharge
```

充值失败：

```bash
php think payment:test-callback --order=PAY订单号 --status=fail --type=recharge
```

提现成功：

```bash
php think payment:test-callback --order=提现订单号或ID --status=success --type=withdraw
```

提现失败：

```bash
php think payment:test-callback --order=提现订单号或ID --status=fail --type=withdraw
```

命令入口：

```text
app/command/Payment/TestCallback.php
```

回调处理服务：

```text
app/common/service/TestPaymentCallbackService.php
```

该服务会走统一的 `Notify::processOrder()`，因此测试回调会复用正式的到账、活动、会员升级和事件触发逻辑。

## 注意事项

- `TestPay` 仅用于内部测试环境。
- 不要给 `TestPay` 增加真实三方请求。
- 不要在 migration 中添加测试支付数据，测试数据应放在 seeder 中。
- 如果前端根据 `cashier_url` 自动跳转，需要对空字符串做兼容。
