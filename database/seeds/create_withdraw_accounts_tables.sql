-- 为支付方式表添加字段配置
ALTER TABLE `slot_payment_methods` 
ADD COLUMN `field_config` text COLLATE utf8mb4_unicode_ci COMMENT '字段配置JSON' AFTER `description`,
ADD COLUMN `validation_rules` text COLLATE utf8mb4_unicode_ci COMMENT '验证规则JSON' AFTER `field_config`;

-- 创建提现账号表
CREATE TABLE `slot_withdraw_accounts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `payment_method_id` int(11) unsigned NOT NULL COMMENT '支付方式ID',
  `unique_tag` varchar(255) NOT NULL COMMENT '支付方式唯一标识',
  `account_name` varchar(100) NOT NULL COMMENT '用户自定义账号名称',
  `is_default` tinyint(1) unsigned DEFAULT 0 COMMENT '是否默认账号',
  `account_info` text NOT NULL COMMENT '账号详细信息JSON',
  `status` tinyint(1) unsigned DEFAULT 1 COMMENT '状态：1=启用，0=禁用',
  `create_time` int(11) NOT NULL COMMENT '创建时间戳',
  `update_time` int(11) NOT NULL COMMENT '更新时间戳',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_payment_default` (`user_id`, `payment_method_id`, `is_default`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_payment_method_id` (`payment_method_id`),
  KEY `idx_unique_tag` (`unique_tag`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_withdraw_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `slot_account` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_withdraw_accounts_payment` FOREIGN KEY (`payment_method_id`) REFERENCES `slot_payment_methods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户提现账号信息表';
