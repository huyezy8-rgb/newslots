# 红包兑换码功能更新说明

## 修改内容

将红包兑换码功能从"一码一用"改为"一码多用"模式。

## 修改前后对比

### 修改前（一码一用）
- 兑换码使用后立即标记为 `is_used = 1`
- 每个兑换码只能被使用一次
- 任何用户使用后，其他用户无法再使用该码

### 修改后（一码多用）
- 兑换码不再标记为已使用状态
- 每个兑换码可以被多个用户使用
- 每个用户对每个兑换码只能使用一次
- 每日最大兑换次数限制保持不变

## 技术实现

### 核心逻辑变更

1. **兑换码查找逻辑**
   ```php
   // 修改前：查找未使用的兑换码
   $codeInfo = RedEnvelopeRedemptionCode::where('code', $data["code"])
       ->where('is_used', 0)
       ->find();

   // 修改后：直接查找兑换码，不检查使用状态
   $codeInfo = RedEnvelopeRedemptionCode::where('code', $data["code"])
       ->find();
   ```

2. **用户重复使用检查**
   ```php
   // 新增：检查当前用户是否已经使用过这个兑换码
   $userUsedCode = RedEnvelopeRedemptionRecord::where('user_id', $this->userInfo->id)
       ->where('code_id', $codeInfo['id'])
       ->find();

   if ($userUsedCode) {
       $this->error(__('You have already used this redemption code'));
   }
   ```

3. **移除使用状态标记**
   ```php
   // 修改前：标记兑换码为已使用
   RedEnvelopeRedemptionCode::where('id', $codeInfo['id'])
       ->update([
           'is_used'   => 1,
           'used_id'   => $this->userInfo->id,
           'used_at'   => date('Y-m-d H:i:s'),
       ]);

   // 修改后：不再标记兑换码为已使用
   // 每个用户每个码只能使用一次的限制通过RedEnvelopeRedemptionRecord表来维护
   ```

## 保持不变的功能

1. **每日兑换次数限制**：用户每天最大兑换次数限制保持不变
2. **随机金额生成**：兑换金额仍在配置的最小值和最大值之间随机生成
3. **钱包类型判断**：根据用户充值情况决定入账到体验钱包还是充值钱包
4. **资金流水记录**：兑换记录和资金变动记录保持不变

## 数据表依赖

- `red_envelope_redemption_code`：兑换码配置表
- `red_envelope_redemption_record`：兑换记录表（用于限制用户重复使用同一兑换码）

## 错误提示更新

- `Redemption code invalid or already used` → `Redemption code invalid`
- 新增：`You have already used this redemption code`

## 影响范围

- 文件：`app/api/controller/RedEnvelopeRedemption.php`
- 接口：`POST /api/red_envelope_redemption/receive`
- 功能：红包兑换码兑换功能

## 测试建议

1. 测试同一兑换码被多个用户使用
2. 测试同一用户重复使用同一兑换码（应被拒绝）
3. 测试每日兑换次数限制是否正常工作
4. 测试无效兑换码的处理
5. 测试兑换金额的随机性
