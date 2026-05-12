# 宝箱活动配置更新说明

## 更新概述

本次更新将宝箱图片配置从宝箱列表移动到宝箱活动配置页面，实现统一管理。

## 主要变更

### 1. 数据库结构变更

#### 宝箱活动配置表 (`chest_config`)
- **新增字段**：
  - `default_image` varchar(255) - 默认图片
  - `waiting_image` varchar(255) - 待领取图片  
  - `received_image` varchar(255) - 已领取图片

#### 宝箱表 (`chest`)
- **移除字段**：
  - `default_image` - 默认图片
  - `waiting_image` - 待领取图片
  - `received_image` - 已领取图片

### 2. 代码变更

#### 后端变更
- **模型更新**：
  - `app/common/model/activity/ChestConfig.php` - 添加图片字段支持
  - `app/common/model/activity/Chest.php` - 移除图片字段

- **控制器更新**：
  - `app/admin/controller/activity/Chest.php` - 配置接口支持图片字段
  - `app/api/controller/Chest.php` - API从配置表获取图片数据

#### 前端变更
- **配置页面**：
  - `web/src/views/backend/activity/chest/config.vue` - 添加图片配置区域
  - `web/src/views/backend/activity/chest/index.vue` - 移除图片列显示
  - `web/src/views/backend/activity/chest/popupForm.vue` - 移除图片字段

## 执行步骤

### 1. 执行数据库更新
```sql
-- 执行SQL脚本
source database/seeds/chest_config_update.sql;
```

### 2. 验证更新结果
- 检查 `chest_config` 表是否包含新的图片字段
- 检查 `chest` 表是否已移除图片字段
- 测试宝箱活动配置页面功能
- 测试宝箱API接口返回数据格式

## API兼容性

✅ **API返回格式保持不变**
- 宝箱列表接口 (`/api/chest/list`) 返回的数据格式完全一致
- 所有图片字段 (`default_image`, `waiting_image`, `received_image`) 仍然返回
- 图片数据现在从宝箱活动配置表获取，而不是从宝箱表获取

## 配置说明

### 宝箱活动配置页面
1. 进入后台管理 → 活动管理 → 宝箱活动配置
2. 在"宝箱图片配置"区域上传三张图片：
   - 默认图片：宝箱未满足条件时显示
   - 待领取图片：宝箱满足条件但未领取时显示
   - 已领取图片：宝箱已领取时显示

### 宝箱列表管理
- 宝箱列表页面不再显示图片列
- 宝箱编辑弹窗不再包含图片字段
- 所有宝箱将使用统一的图片配置

## 注意事项

1. **数据迁移**：如果现有宝箱数据中有图片，需要手动在配置页面重新上传
2. **缓存清理**：更新后建议清理相关缓存
3. **API测试**：确保所有宝箱相关API正常工作
4. **前端测试**：确保宝箱活动配置页面功能正常

## 回滚方案

如需回滚，可以执行以下SQL：
```sql
-- 回滚：将图片字段移回宝箱表
ALTER TABLE `chest` 
ADD COLUMN `default_image` varchar(255) NOT NULL DEFAULT '' COMMENT '默认图片',
ADD COLUMN `waiting_image` varchar(255) NOT NULL DEFAULT '' COMMENT '待领取图片',
ADD COLUMN `received_image` varchar(255) NOT NULL DEFAULT '' COMMENT '已领取图片';

-- 从配置表移除图片字段
ALTER TABLE `chest_config` 
DROP COLUMN `default_image`,
DROP COLUMN `waiting_image`,
DROP COLUMN `received_image`;
```

## 更新完成检查清单

- [ ] 数据库结构更新完成
- [ ] 宝箱活动配置页面可以正常上传图片
- [ ] 宝箱列表页面不再显示图片列
- [ ] 宝箱API接口返回数据格式正确
- [ ] 宝箱状态图片显示正常
- [ ] 所有相关功能测试通过
