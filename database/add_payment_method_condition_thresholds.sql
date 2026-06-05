ALTER TABLE `slot_payment_methods`
ADD COLUMN `condition_recharge_amount` decimal(12,2) NOT NULL DEFAULT 30.00 COMMENT 'Condition display recharge amount' AFTER `is_clause`,
ADD COLUMN `condition_recharge_times` int(10) unsigned NOT NULL DEFAULT 3 COMMENT 'Condition display recharge times' AFTER `condition_recharge_amount`;
