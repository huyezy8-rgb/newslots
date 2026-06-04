-- Add channel order number to withdraw orders.
-- Safe to run multiple times.

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'slot_withdraw_orders'
      AND COLUMN_NAME = 'channel_order_no'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `slot_withdraw_orders` ADD COLUMN `channel_order_no` varchar(100) NOT NULL DEFAULT '''' COMMENT ''渠道订单号'' AFTER `platform_order_no`',
    'SELECT ''slot_withdraw_orders.channel_order_no already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
