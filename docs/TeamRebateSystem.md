## 团队系统与返佣（点位差）设计文档

本文档梳理团队层级、路径规则、返佣点位差结算与数据结构，统一实现口径与使用规范。

### 一、核心概念与表结构

- 用户表：`slot_account`
  - `id` BIGINT: 用户ID
  - `p_id` BIGINT: 上级用户ID（已存在字段，统一使用 `p_id`）
  - `team_path` VARCHAR(1000): 团队路径（推荐格式：`/1/3/9/`，从根到父级，不含自己）
  - `rebate_rate` DECIMAL(5,2): 当前用户返佣点位（默认 0，例如 50 表示 50%）
  - `commission_balance` DECIMAL(20,6): 佣金账户余额（新增，所有佣金累计到该字段，不设置冻结字段）

- 建议索引
  - `team_path` 前缀索引：`INDEX idx_team_path_prefix (team_path(255))`
  - `p_id` 普通索引：`INDEX idx_p_id (p_id)`

说明：`team_path` 推荐以 `/` 包裹（如 `/1/3/`），可避免 LIKE 命中歧义（例如 ID 为 `1` 与 `11`）。

### 二、无限级关系结构（物化路径）

- 路径示例：用户链路 `A(1) → B(3) → C(9)`，则 `id=9` 的 `team_path = /1/3/`。
- 层级计算：`level = LENGTH(team_path) - LENGTH(REPLACE(team_path, '/', '')) - 1`
  - 以 `/1/3/` 为例：斜杠数量为 3，则 `level = 3 - 1 = 2`（父为第1代、祖父为第2代... 不含自己）。

查询示例：
- 所有下级：`WHERE team_path LIKE '/{myId}/%'`
- 直属下级：`WHERE p_id = {myId}`

### 三、返佣逻辑（基于点位差）

- 基础返佣比例：`base_rate`（默认 0.5%）。
- 实际返佣：`bet_amount × base_rate × 点位差`。
- 默认点位：
  - 广告渠道注册：默认 50%
  - 邀请链接注册：默认 0%
  - 以上默认值与后续调整可在后台配置。

点位差结算示例：

```
A（100%）
 └── B（80%）
      └── C（50%）
           └── D（50%）投注 10000

返佣基数 = 10000 × 0.5% = 50

从下往上：
 - C 与 D 点位相同(50→50)，差值=0，C 不得佣金
 - B(80) 与 C(50) 差值=30%，B 得 50 × 30% = 15
 - A(100) 与 B(80) 差值=20%，A 得 50 × 20% = 10
```

分发顺序：自下注用户向上依次计算，直到点位不再提升或达到最大层级限制。

### 四、返佣记录表 `slot_team_commission_log`

```sql
CREATE TABLE `slot_team_commission_log` (
  `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL COMMENT '获得佣金的用户ID',
  `source_user_id` BIGINT NOT NULL COMMENT '下级投注的用户ID',
  `channel_id` INT(11) NOT NULL DEFAULT '0' COMMENT '渠道ID',
  `bet_amount` DECIMAL(20,6) NOT NULL COMMENT '投注金额',
  `base_rate` DECIMAL(5,2) NOT NULL COMMENT '基础返佣比例(如0.5表示0.5%)',
  `point_diff` DECIMAL(5,2) NOT NULL COMMENT '点位差(百分数，如30表示30%)',
  `commission` DECIMAL(20,6) NOT NULL COMMENT '佣金金额',
  `level` INT NOT NULL COMMENT '距投注用户的层级(从1开始)',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间(时间戳)',
  KEY `idx_user_id` (`user_id`),
  KEY `idx_source_user_id` (`source_user_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='团队返佣明细日志';
```

说明：增加 `channel_id` 字段，支持按渠道统计与风控。

### 五、推荐返佣计算流程（伪代码）

1) 用户 D 下注（`bet_amount`）。开启事务。
2) 读取 D 的 `team_path`（如 `/A/B/C/`），得到上级链 [C, B, A]（自近至远）。
3) 逐级读取上级 `rebate_rate`，根据点位差计算佣金：

```php
$baseRate = 0.5; // 0.5% 基础返佣
$prevRate = $downUserRate; // 初始为下注用户点位
foreach ($ancestors as $level => $ancestor) {
    $currRate = $ancestor->rebate_rate;
    if ($currRate <= $prevRate) {
        $prevRate = max($prevRate, $currRate);
        continue; // 无差值则跳过
    }
    $diff = $currRate - $prevRate; // 点位差，单位：百分数
    $commission = $betAmount * ($baseRate / 100) * ($diff / 100);

    // 写日志 + 累加到账户
    insert into slot_team_commission_log (...);
    update slot_account set commission_balance = commission_balance + $commission where id = $ancestor->id;

    $prevRate = $currRate;
}
```

4) 提交事务。失败回滚，避免重复发放。

### 六、性能与层级

- `team_path` 长度：若单个ID长度较小，`VARCHAR(1000)` 足以承载极深层级。可按业务量评估并适当加大。
- 索引：对 `team_path` 使用前缀索引（如 255）可有效支撑“查所有下级”。
- 深层团队：极深层或高频统计建议引入“物化路径缓存表”（祖先-后代关系表）以提速。

### 七、统计与查询

- 我所有下级：`WHERE team_path LIKE '/{myId}/%'`
- 我的直属下级：`WHERE invite_user_id = {myId}`
- 我团队总充值：

```sql
SELECT SUM(r.amount)
FROM slot_recharge_orders r
JOIN slot_account u ON u.id = r.user_id
WHERE u.team_path LIKE '/{myId}/%';
```

- 我获得的总佣金：

```sql
SELECT SUM(commission)
FROM slot_team_commission_log
WHERE user_id = {myId};
```

### 八、安全与风控

- 点位只允许“向下设置”，禁止出现下级点位高于上级。
- 点位设置权限仅允许上级设置下级，或由具备权限的后台管理员设置。
- 投注与返佣必须使用事务，并做好幂等控制（防止重复发放）。
- 区分投注来源：试玩或体验币不参与返佣；`channel_id` 记入佣金日志用于渠道风控与统计。

### 九、与现有实现的差异提示

- 当前项目已实现的团队路径可能为另一种格式（如不包含首尾 `/`，或包含根占位符）。本设计推荐统一为以 `/` 包裹的物化路径格式（`/1/3/`）。
- 若准备切换，请统一：生成/更新规则、统计SQL、索引策略与服务逻辑，并执行一次性全量修复脚本重建 `team_path` 与 `team_level`（若保留）。

