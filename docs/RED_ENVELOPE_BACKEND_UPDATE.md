# 红包兑换码后台页面更新说明

## 修改内容

将后台兑换码管理页面的状态显示从"已使用/未使用"改为"已兑换数量"，并移除了玩家昵称和兑换用户ID列。

## 修改前后对比

### 修改前
- 状态列显示：未使用/已使用
- 显示兑换用户ID列
- 显示玩家昵称列
- 显示使用时间列

### 修改后
- 状态列显示：已兑换数量（如：0次、5次）
- 移除兑换用户ID列
- 移除玩家昵称列
- 移除使用时间列

## 技术实现

### 后端修改（app/admin/controller/red/envelope/redemption/Code.php）

1. **移除关联查询**
   ```php
   // 修改前：关联用户表查询
   protected array $withJoinTable = ['used'];
   
   // 修改后：不关联任何表
   protected array $withJoinTable = [];
   ```

2. **添加已兑换数量统计**
   ```php
   // 为每个兑换码添加已兑换数量统计
   $res->each(function($item) {
       $item['used_count'] = \app\common\model\RedEnvelopeRedemptionRecord::where('code_id', $item['id'])->count();
   });
   ```

3. **移除用户信息显示**
   ```php
   // 修改前：显示用户信息
   $res->visible(['used' => ['id','nickname']]);
   
   // 修改后：不再显示用户信息
   ```

### 前端修改（web/src/views/backend/red/envelope/redemption/code/index.vue）

1. **替换状态列配置**
   ```javascript
   // 修改前：显示使用状态
   {
       label: t('red.envelope.redemption.code.is_used'),
       prop: 'is_used',
       render: 'tag',
       replaceValue: {
           0: '未使用',
           1: '已使用'
       },
       custom: {
           0: 'success',
           1: 'danger'
       },
   },
   
   // 修改后：显示兑换数量
   {
       label: '已兑换数量',
       prop: 'used_count',
       render: 'tag',
       custom: {
           0: 'info',
       },
       replaceValue: (value: number) => {
           if (value === 0) return '0次';
           return `${value}次`;
       }
   },
   ```

2. **移除用户相关列**
   ```javascript
   // 移除以下列配置：
   // - used_id（兑换用户ID）
   // - used.nickname（玩家昵称）
   // - used_at（使用时间）
   ```

3. **更新样式**
   ```scss
   // 修改前：为使用状态设置颜色
   :deep(.el-tag) {
       &.el-tag--success { /* 未使用 */ }
       &.el-tag--danger { /* 已使用 */ }
   }
   
   // 修改后：为兑换数量设置颜色
   :deep(.el-tag) {
       &.el-tag--info {
           background-color: #f4f4f5;
           border-color: #909399;
           color: #909399;
       }
   }
   ```

## 功能特点

1. **实时统计**：每次加载页面时实时统计每个兑换码的使用次数
2. **简洁显示**：移除冗余的用户信息，专注于兑换码的使用情况
3. **一码多用支持**：适配新的"一码多用"功能，显示实际兑换次数而非简单的已使用状态

## 影响范围

- 后端文件：`app/admin/controller/red/envelope/redemption/Code.php`
- 前端文件：`web/src/views/backend/red/envelope/redemption/code/index.vue`
- 功能：后台兑换码管理页面

## 数据表依赖

- `red_envelope_redemption_code`：兑换码配置表
- `red_envelope_redemption_record`：兑换记录表（用于统计使用次数）

## 显示效果

- 兑换码列表现在显示每个码的兑换次数
- 0次兑换显示为"0次"
- 有兑换记录显示为"X次"（X为实际兑换次数）
- 使用灰色标签样式显示兑换数量
