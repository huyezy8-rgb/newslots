-- Add per-user redemption limit and expiration window to red envelope redemption codes.
-- Safe to run multiple times on MySQL 5.7.

SET @table_name := 'slot_red_envelope_redemption_code';

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = 'per_user_limit'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `slot_red_envelope_redemption_code` ADD COLUMN `per_user_limit` int unsigned NOT NULL DEFAULT 1 COMMENT ''Per-user redemption limit, 0=unlimited'' AFTER `amount_max`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = 'expire_hours'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `slot_red_envelope_redemption_code` ADD COLUMN `expire_hours` int unsigned NOT NULL DEFAULT 0 COMMENT ''Expiration hours, 0=never expires'' AFTER `per_user_limit`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `slot_red_envelope_redemption_code`
    MODIFY COLUMN `per_user_limit` int unsigned NOT NULL DEFAULT 1 COMMENT 'Per-user redemption limit, 0=unlimited' AFTER `amount_max`,
    MODIFY COLUMN `expire_hours` int unsigned NOT NULL DEFAULT 0 COMMENT 'Expiration hours, 0=never expires' AFTER `per_user_limit`;

UPDATE `slot_red_envelope_redemption_code`
SET `create_time` = UNIX_TIMESTAMP()
WHERE `create_time` IS NULL;

SET @record_table_name := 'slot_red_envelope_redemption_record';

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @record_table_name
      AND INDEX_NAME = 'idx_red_envelope_record_user_code'
);

SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE `slot_red_envelope_redemption_record` ADD INDEX `idx_red_envelope_record_user_code` (`user_id`, `code_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @record_table_name
      AND INDEX_NAME = 'idx_red_envelope_record_user_create_time'
);

SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE `slot_red_envelope_redemption_record` ADD INDEX `idx_red_envelope_record_user_create_time` (`user_id`, `create_time`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
