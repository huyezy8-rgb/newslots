-- Add payment smart-control configuration.
-- Run this SQL manually. Do not use migration for this change.
-- If your table prefix is not `slot_`, update table names before running.

CREATE TABLE IF NOT EXISTS `slot_payment_smart_control_config` (
  `id` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Singleton config ID',
  `withdraw_amount_enabled` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Enable withdraw amount payment-method rule: 0=no, 1=yes',
  `withdraw_amount_threshold` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Withdraw amount must be greater than this value',
  `withdraw_pay_types` text NULL COMMENT 'JSON array of payment_methods.unique_tag',
  `recharge_count_enabled` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Enable recharge count payment-method rule: 0=no, 1=yes',
  `recharge_count_threshold` int unsigned NOT NULL DEFAULT 0 COMMENT 'Successful recharge count threshold',
  `recharge_pay_types` text NULL COMMENT 'JSON array of payment_methods.unique_tag',
  `create_time` bigint unsigned NULL DEFAULT NULL,
  `update_time` bigint unsigned NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment smart-control singleton config';

SET @database_name := DATABASE();
SELECT COUNT(*) INTO @has_withdraw_pay_types
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME = 'slot_payment_smart_control_config'
  AND COLUMN_NAME = 'withdraw_pay_types';

SET @add_withdraw_pay_types_sql := IF(
  @has_withdraw_pay_types = 0,
  'ALTER TABLE `slot_payment_smart_control_config` ADD COLUMN `withdraw_pay_types` text NULL COMMENT ''JSON array of payment_methods.unique_tag'' AFTER `withdraw_amount_threshold`',
  'SELECT 1'
);
PREPARE add_withdraw_pay_types_stmt FROM @add_withdraw_pay_types_sql;
EXECUTE add_withdraw_pay_types_stmt;
DEALLOCATE PREPARE add_withdraw_pay_types_stmt;

INSERT INTO `slot_payment_smart_control_config` (
  `id`,
  `withdraw_amount_enabled`,
  `withdraw_amount_threshold`,
  `withdraw_pay_types`,
  `recharge_count_enabled`,
  `recharge_count_threshold`,
  `recharge_pay_types`,
  `create_time`,
  `update_time`
) VALUES (
  1,
  0,
  0.00,
  '[]',
  0,
  0,
  '[]',
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP()
) ON DUPLICATE KEY UPDATE
  `update_time` = `update_time`;

SET @now := UNIX_TIMESTAMP();

INSERT INTO `slot_admin_rule` (
  `pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`,
  `keepalive`, `extend`, `remark`, `weigh`, `status`, `update_time`, `create_time`
)
SELECT
  `id`, 'menu', '支付智能控制', 'payment/smart-control', 'payment/smart-control', 'fa fa-random', 'tab', '',
  '/src/views/backend/payment/smart-control/index.vue', 0, 'none', '', -36, 1, @now, @now
FROM `slot_admin_rule`
WHERE `name` = 'payment'
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT `id` FROM `slot_admin_rule` WHERE `name` = 'payment/smart-control') AS existing_rule
  )
LIMIT 1;

INSERT INTO `slot_admin_rule` (
  `pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`,
  `keepalive`, `extend`, `remark`, `weigh`, `status`, `update_time`, `create_time`
)
SELECT
  parent.`id`, button.`type`, button.`title`, button.`name`, '', '', NULL, '', '',
  0, 'none', '', button.`weigh`, 1, @now, @now
FROM `slot_admin_rule` parent
JOIN (
  SELECT 'button' AS `type`, '查看' AS `title`, 'payment/smart-control/detail' AS `name`, -35 AS `weigh`
  UNION ALL SELECT 'button', '编辑', 'payment/smart-control/edit', -34
  UNION ALL SELECT 'button', '选项', 'payment/smart-control/options', -33
) button
WHERE parent.`name` = 'payment/smart-control'
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT `id`, `name` FROM `slot_admin_rule`) AS existing_button
    WHERE existing_button.`name` = button.`name`
  );

-- Optional rollback:
-- DROP TABLE `slot_payment_smart_control_config`;
-- DELETE FROM `slot_admin_rule` WHERE `name` IN (
--   'payment/smart-control',
--   'payment/smart-control/detail',
--   'payment/smart-control/edit',
--   'payment/smart-control/options'
-- );
