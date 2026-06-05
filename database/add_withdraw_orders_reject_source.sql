-- Add reject source to withdraw orders.
-- Safe to run multiple times.

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'slot_withdraw_orders'
      AND COLUMN_NAME = 'reject_source'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `slot_withdraw_orders` ADD COLUMN `reject_source` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''驳回来源：0=未知/旧数据，1=后台手动驳回，2=第三方打款失败驳回'' AFTER `status`',
    'SELECT ''slot_withdraw_orders.reject_source already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
