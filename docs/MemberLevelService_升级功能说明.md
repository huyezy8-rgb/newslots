# MemberLevelService 会员升级功能说明

## 功能概述
会员升级功能会在用户满足等级条件时自动升级，并发送站内信通知。**每升一级都会发送一次站内信**，确保用户能及时收到每个等级的升级通知。

## 升级逻辑
1. **逐级升级**：系统会从低等级到高等级逐一检查，每满足一个等级条件就立即升级
2. **多次通知**：每升一级都会发送一次站内信，包含该等级的详细信息
3. **累计奖励**：如果多个等级都有赠送金额，会累计显示总金额

## 站内信内容
- **标题**：`VIP {等级}`
- **内容**：包含等级特权说明和提现限制信息
- **类型**：如果有赠送金额则为 `gift` 类型，否则为 `system` 类型
- **金额**：该等级的赠送金额（如果有）

## 示例场景
假设用户当前是 VIP 1，充值后满足 VIP 2、VIP 3、VIP 4 的条件：
1. 升级到 VIP 2，发送站内信
2. 升级到 VIP 3，发送站内信  
3. 升级到 VIP 4，发送站内信

最终用户会收到 3 条站内信，分别对应每个等级的升级通知。

## 资金记录
- 类型：`member_upgrade`
- 记录：每个等级的赠送金额变动
- 追踪：完整的升级历史记录

## 测试示例
```php
<?php
// 测试会员升级功能
$memberLevelService = new \app\common\service\MemberLevelService();

try {
    $result = $memberLevelService->upgradeByUserId(1);
    echo "升级结果：" . $result['msg'] . "\n";
    echo "升级等级数：" . count($result['upgraded_levels']) . "\n";
    echo "总赠送金额：" . $result['total_bonus'] . "\n";
} catch (Exception $e) {
    echo "升级失败：" . $e->getMessage() . "\n";
}
```

## 注意事项
- 升级过程在事务中进行，确保数据一致性
- 站内信发送失败不会影响升级流程
- 所有升级操作都会记录详细日志 