-- 七天卡活动配置表结构
-- 创建时间: 2025-01-20

-- 七天卡活动配置表
CREATE TABLE `slot_seven_day_card_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '活动标题',
  `bet_multiple` decimal(10,2) NOT NULL DEFAULT '1.00' COMMENT '打码倍数',
  `original_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '划线价格',
  `current_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '现价',
  `seven_day_rewards` text COMMENT '七天奖励配置(JSON格式)',
  `rescue_rewards` text COMMENT '救援金配置(JSON格式)',
  `daily_rewards` text COMMENT '每日奖励配置(JSON格式)',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态：0=禁用，1=启用',
  `createtime` int(11) unsigned DEFAULT NULL COMMENT '创建时间',
  `updatetime` int(11) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='七天卡活动配置表';

-- 插入默认配置数据
INSERT INTO `slot_seven_day_card_config` (
  `title`, 
  `bet_multiple`, 
  `original_price`, 
  `current_price`, 
  `seven_day_rewards`, 
  `rescue_rewards`, 
  `daily_rewards`, 
  `status`, 
  `createtime`, 
  `updatetime`
) VALUES (
  '七天卡',
  1.00,
  0.00,
  19.99,
  '[22,5,7,4,4,4,8]',
  '[3,3,3,3,3,3,3]',
  '[1,1,3,1,1,1,5]',
  1,
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP()
);

-- 七天卡开通记录表（记录三个奖励的7天进度）
CREATE TABLE IF NOT EXISTS `slot_seven_day_card_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `channel_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '渠道ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '充值订单号',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '购买金额',
  `start_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '开通时间',
  `end_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '结束时间(开通+7天)',
  `reward_main` json NULL COMMENT '七天奖励进度[{"reward":8.00,"status":0},...]',
  `reward_rescue` json NULL COMMENT '救援金进度[...同上]',
  `reward_daily` json NULL COMMENT '每日奖励进度[...同上]',
  `created_at` int(11) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_order_no` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='七天卡开通记录表';
