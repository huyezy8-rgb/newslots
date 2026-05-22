-- Add Messenger contact link configuration for channel list.
-- If your table prefix is not `slot_`, update the table name before running.

ALTER TABLE `slot_channel_list`
ADD COLUMN `messenger_url` varchar(255) NULL DEFAULT NULL COMMENT 'Messenger跳转链接'
AFTER `kefu_channel_url`;

-- Rollback, if needed:
-- ALTER TABLE `slot_channel_list` DROP COLUMN `messenger_url`;
