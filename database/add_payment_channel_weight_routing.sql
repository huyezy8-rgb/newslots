-- Add global weighted payment-channel routing fields.
-- If your table prefix is not `slot_`, update table names before running.

DROP PROCEDURE IF EXISTS `slot_add_payment_channel_weight_routing`;

DELIMITER $$

CREATE PROCEDURE `slot_add_payment_channel_weight_routing`()
BEGIN
    DECLARE v_index_name varchar(128);
    DECLARE done int DEFAULT 0;

    DECLARE unique_tag_indexes CURSOR FOR
        SELECT INDEX_NAME
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'slot_payment_methods'
          AND INDEX_NAME <> 'PRIMARY'
        GROUP BY INDEX_NAME
        HAVING MAX(NON_UNIQUE) = 0
           AND GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) = 'unique_tag';

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'slot_payment_channels'
          AND COLUMN_NAME = 'weight'
    ) THEN
        ALTER TABLE `slot_payment_channels`
            ADD COLUMN `weight` int unsigned NOT NULL DEFAULT 100 COMMENT 'Global routing weight, 0 means excluded from routing' AFTER `status`;
    END IF;

    UPDATE `slot_payment_channels`
    SET `weight` = 100
    WHERE `weight` IS NULL;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'slot_payment_channels'
          AND INDEX_NAME = 'idx_weight'
    ) THEN
        ALTER TABLE `slot_payment_channels`
            ADD KEY `idx_weight` (`weight`);
    END IF;

    OPEN unique_tag_indexes;
    drop_unique_tag_indexes: LOOP
        FETCH unique_tag_indexes INTO v_index_name;
        IF done = 1 THEN
            LEAVE drop_unique_tag_indexes;
        END IF;

        SET @drop_unique_tag_index_sql = CONCAT(
            'ALTER TABLE `slot_payment_methods` DROP INDEX `',
            REPLACE(v_index_name, '`', '``'),
            '`'
        );
        PREPARE drop_unique_tag_index_stmt FROM @drop_unique_tag_index_sql;
        EXECUTE drop_unique_tag_index_stmt;
        DEALLOCATE PREPARE drop_unique_tag_index_stmt;
    END LOOP;
    CLOSE unique_tag_indexes;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'slot_payment_methods'
          AND INDEX_NAME = 'idx_unique_tag'
    ) THEN
        ALTER TABLE `slot_payment_methods`
            ADD KEY `idx_unique_tag` (`unique_tag`);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'slot_payment_methods'
          AND INDEX_NAME = 'uniq_channel_method_scene'
    ) THEN
        ALTER TABLE `slot_payment_methods`
            ADD UNIQUE KEY `uniq_channel_method_scene` (`channel_code`, `unique_tag`, `pay_method`);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'slot_recharge_orders'
          AND COLUMN_NAME = 'payment_channel_code'
    ) THEN
        ALTER TABLE `slot_recharge_orders`
            ADD COLUMN `payment_channel_code` varchar(50) NULL DEFAULT NULL COMMENT 'Actual payment channel code selected by routing',
            ADD COLUMN `payment_method_id` int unsigned NULL DEFAULT NULL COMMENT 'Actual payment method route ID selected by routing',
            ADD COLUMN `payment_channel_weight_snapshot` int unsigned NULL DEFAULT NULL COMMENT 'Channel weight snapshot when route was selected',
            ADD KEY `idx_payment_channel_code` (`payment_channel_code`),
            ADD KEY `idx_payment_method_id` (`payment_method_id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'slot_withdraw_orders'
          AND COLUMN_NAME = 'payment_channel_code'
    ) THEN
        ALTER TABLE `slot_withdraw_orders`
            ADD COLUMN `payment_channel_code` varchar(50) NULL DEFAULT NULL COMMENT 'Actual payment channel code selected by routing',
            ADD COLUMN `payment_method_id` int unsigned NULL DEFAULT NULL COMMENT 'Actual payment method route ID selected by routing',
            ADD COLUMN `payment_channel_weight_snapshot` int unsigned NULL DEFAULT NULL COMMENT 'Channel weight snapshot when route was selected',
            ADD KEY `idx_payment_channel_code` (`payment_channel_code`),
            ADD KEY `idx_payment_method_id` (`payment_method_id`);
    END IF;
END$$

DELIMITER ;

CALL `slot_add_payment_channel_weight_routing`();

DROP PROCEDURE IF EXISTS `slot_add_payment_channel_weight_routing`;

-- Rollback, if needed:
-- ALTER TABLE `slot_withdraw_orders`
--   DROP KEY `idx_payment_method_id`,
--   DROP KEY `idx_payment_channel_code`,
--   DROP COLUMN `payment_channel_weight_snapshot`,
--   DROP COLUMN `payment_method_id`,
--   DROP COLUMN `payment_channel_code`;
-- ALTER TABLE `slot_recharge_orders`
--   DROP KEY `idx_payment_method_id`,
--   DROP KEY `idx_payment_channel_code`,
--   DROP COLUMN `payment_channel_weight_snapshot`,
--   DROP COLUMN `payment_method_id`,
--   DROP COLUMN `payment_channel_code`;
-- ALTER TABLE `slot_payment_methods` DROP KEY `uniq_channel_method_scene`;
-- ALTER TABLE `slot_payment_methods` DROP KEY `idx_unique_tag`;
-- ALTER TABLE `slot_payment_channels` DROP KEY `idx_weight`;
-- ALTER TABLE `slot_payment_channels` DROP COLUMN `weight`;
