-- 团队与返佣相关 SQL 结构定义与修改建议

-- 1) 用户表 slot_account 新增字段（若不存在）
--   - p_id: 上级ID（统一使用 p_id）
--   - team_path: 物化路径（推荐格式：/1/3/，不含自己）
--   - rebate_rate: 返佣点位，默认0（百分数）
--   - commission_balance: 佣金账户余额，默认0

ALTER TABLE `slot_account`
    ADD COLUMN `p_id` BIGINT NULL COMMENT '上级ID' AFTER `id`,
    ADD COLUMN `team_path` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '团队路径，如：/1/3/' AFTER `p_id`,
    ADD COLUMN `rebate_rate` DECIMAL(5,2) NOT NULL DEFAULT '0.00' COMMENT '当前用户返佣点位(百分数，50=50%)' AFTER `team_path`,
    ADD COLUMN `commission_balance` DECIMAL(20,6) NOT NULL DEFAULT '0.000000' COMMENT '佣金余额' AFTER `rebate_rate`;

-- 索引建议
ALTER TABLE `slot_account`
    ADD INDEX `idx_p_id` (`p_id`),
    ADD INDEX `idx_team_path_prefix` (`team_path`(255));

-- 2) 返佣日志表（含 channel_id）
CREATE TABLE IF NOT EXISTS `slot_team_commission_log` (
  `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL COMMENT '获得佣金的用户ID',
  `source_user_id` BIGINT NOT NULL COMMENT '下级投注的用户ID',
  `channel_id` INT(11) NOT NULL DEFAULT '0' COMMENT '渠道ID',
  `bet_amount` DECIMAL(20,6) NOT NULL COMMENT '投注金额',
  `base_rate` DECIMAL(5,2) NOT NULL COMMENT '基础返佣比例(如0.5表示0.5%)',
  `point_diff` DECIMAL(5,2) NOT NULL COMMENT '点位差(百分数，如30表示30%)',
  `commission` DECIMAL(20,6) NOT NULL COMMENT '佣金金额',
  `level` INT NOT NULL COMMENT '距投注用户的层级(从1开始)',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间(时间戳)',
  KEY `idx_user_id` (`user_id`),
  KEY `idx_source_user_id` (`source_user_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='团队返佣明细日志';

-- 3) 充值记录表（已存在，保留以供引用）
-- CREATE TABLE `slot_recharge_orders` (
--   `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
--   `order_no` varchar(64) NOT NULL COMMENT '充值订单号',
--   `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
--   `channel_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '渠道ID（来源渠道）',
--   `amount` decimal(10,2) NOT NULL COMMENT '充值金额（单位：元）',
--   `reg_amount` decimal(10,2) NOT NULL COMMENT '到账金额',
--   `pay_type` varchar(30) NOT NULL COMMENT '支付方式（如：alipay, wechat, usdt）',
--   `pay_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '支付状态（0待支付 1已支付 2失败）',
--   `callback_data` text COMMENT '第三方回调内容',
--   `remark` varchar(255) DEFAULT NULL COMMENT '备注信息',
--   `paid_at` int(11) DEFAULT NULL COMMENT '支付时间',
--   `expired_time` int(11) DEFAULT NULL COMMENT '过期时间',
--   `created_at` int(11) DEFAULT NULL COMMENT '创建时间',
--   `updated_at` int(11) DEFAULT NULL COMMENT '更新时间',
--   `platform_order_no` varchar(64) DEFAULT NULL COMMENT '商户订单号',
--   `event_name` varchar(255) DEFAULT NULL COMMENT '活动名',
--   PRIMARY KEY (`id`) USING BTREE,
--   UNIQUE KEY `order_no` (`order_no`) USING BTREE,
--   UNIQUE KEY `uk_order_no` (`order_no`) USING BTREE,
--   KEY `idx_user_id` (`user_id`) USING BTREE,
--   KEY `idx_channel_id` (`channel_id`) USING BTREE,
--   KEY `idx_status` (`pay_status`) USING BTREE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户充值记录表';

-- 4) 补充：若此前存在 invite_user_id，可批量同步到 p_id
-- UPDATE slot_account SET p_id = invite_user_id WHERE p_id IS NULL;

