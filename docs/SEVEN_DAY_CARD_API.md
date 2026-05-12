# 七天卡（Seven Day Card）API 文档

本文档说明“七天卡”活动在客户端的接口调用方式与字段规范。后端已对接充值下单、支付回调开通记录、奖励领取与资金入账。

## 名词说明
- 活动配置表：`slot_seven_day_card_config`
- 用户开通记录表：`slot_seven_day_card_user`
- 资金流水类型：`CoinLog::SevenDayCard`（值：44，文本：七天卡奖励，入账到充值钱包）

---

## 0. 购买入口（复用充值下单）
- 接口地址：`POST /api/recharge/create`
- 说明：沿用系统充值下单接口，七天卡购买仅需将 `event_name` 设为 `seven_day_card`，并保证 `price` 等于配置表中的 `current_price`。
- 请求参数（关键字段）：
  - `price` number 必填，必须等于七天卡现价（单位：USD 或站点币）
  - `pay_type` string 必填，支付方式编码
  - `event_name` string 固定为 `seven_day_card`
  - 其他字段参考充值文档
- 返回：与充值下单一致（包含 `order_no`、`cashier_url` 等）
- 回调：支付成功后，系统自动在 `slot_seven_day_card_user` 写入开通记录，并初始化三类奖励的 7 天进度（[{reward,status}]）。

---

## 1. 获取活动信息与进度
- 接口地址：`GET /api/seven_day_card/info`
- 认证：需要登录
- 描述：返回活动配置、是否已购买、及用户购买后的奖励进度。
- 请求参数：无
- 响应：
```json
{
  "code": 1,
  "msg": "",
  "data": {
    "config": {
      "id": 1,
      "title": "七天卡",
      "bet_multiple": 1.0,
      "original_price": 0.00,
      "current_price": 19.99,
      "seven_day_rewards": [22,5,7,4,4,4,8],
      "rescue_rewards": [3,3,3,3,3,3,3],
      "daily_rewards": [1,1,3,1,1,1,5],
      "status": 1
    },
    "bought": 1,
    "progress": {
      "reward_main": [
        {"reward":22,"status":0},
        {"reward":5,"status":1}
      ],
      "reward_rescue": [
        {"reward":3,"status":0}
      ],
      "reward_daily": [
        {"reward":1,"status":1}
      ],
      "start_time": 1719830400,
      "end_time": 1719830400
    }
  }
}
```
- 字段说明：
  - `bought`：是否已购买（0/1）
  - `progress.reward_*`：长度固定 7 的数组，每项：
    - `reward`：当天金额
    - `status`：领取状态，0 未领，1 已领

---

## 2. 领取奖励
- 接口地址：`POST /api/seven_day_card/claim`
- 认证：需要登录
- 描述：领取三类奖励（七天奖励/救援金/每日奖励）中任意一天的金额，成功后入账充值钱包（资金流水类型为 `SevenDayCard`）。
- 请求参数：
  - `type` string 必填，取值：`main`（七天奖励）| `rescue`（救援金）| `daily`（每日奖励）
  - `day` number 必填，取值 `1..7`
- 响应示例：
```json
{
  "code": 1,
  "msg": "领取成功",
  "data": {"amount": 3.00}
}
```
- 失败错误码（msg 可能值）：
  - `Invalid type` / `Invalid day`：参数错误
  - `Not purchased`：未购买七天卡
  - `Expired`：开通已过期（>7天）
  - `Already claimed`：当天该类型奖励已领取
  - `No reward`：该天配置金额为 0

---

## 3. 服务端行为说明
- 回调开通：支付回调时（`Notify::handleEvent`）读取配置表金额数组，生成三类奖励 7 天进度结构 `[{reward,status}]`，`status` 初始为 `0`。
- 有效周期：开通后 `start_time` 为写入时刻，`end_time = start_time + 7*86400`。
- 资金入账：领取时使用 `AccountService::increaseBalance`，`logTypeId=CoinLog::SevenDayCard`，入账到充值钱包。

---

## 4. 安全与幂等
- 领取接口内部使用数据库原子更新（`status` 从 0->1），若并发重复请求，后续请求会返回 `Already claimed`。
- 金额与配置：购买时强校验 `price` 等于配置现价，避免绕过前端自定义金额下单。

---

## 5. 前端对接建议
- 展示：按 `info` 返回的 `progress.reward_*` 即时渲染 7 个格子（已领/未领/金额）。
- 领取：点击某天按钮时，调用 `claim` 并根据返回刷新 `info`。
- 购买：沿用充值下单面板，`event_name=seven_day_card`，金额取 `config.current_price` 固定填充。

---

## 6. 版本与历史
- 2025-01-20：首版接入，支持购买、回调开通、三类奖励领取、活动信息查询。
