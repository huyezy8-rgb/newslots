ALTER TABLE `slot_payment_methods`
ADD COLUMN `min_recharge_amount` decimal(12,2) NULL DEFAULT NULL COMMENT 'Minimum recharge amount' AFTER `pay_method`,
ADD COLUMN `max_recharge_amount` decimal(12,2) NULL DEFAULT NULL COMMENT 'Maximum recharge amount' AFTER `min_recharge_amount`,
ADD COLUMN `min_withdraw_amount` decimal(12,2) NULL DEFAULT NULL COMMENT 'Minimum withdraw amount' AFTER `max_recharge_amount`,
ADD COLUMN `max_withdraw_amount` decimal(12,2) NULL DEFAULT NULL COMMENT 'Maximum withdraw amount' AFTER `min_withdraw_amount`;
