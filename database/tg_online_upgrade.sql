SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `slot_tg_bot_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '机器人名称',
  `bot_token` varchar(255) NOT NULL DEFAULT '' COMMENT '机器人Token',
  `chat_id` varchar(100) NOT NULL DEFAULT '' COMMENT 'Chat ID',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `daily_send_limit` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '每日发送上限',
  `code_length` int(10) unsigned NOT NULL DEFAULT 4 COMMENT '兑换码位数',
  `template_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '默认文案模板ID',
  `redemption_rule_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '红包兑换码规则ID',
  `send_interval_minutes` int(10) unsigned NOT NULL DEFAULT 120 COMMENT '发送间隔分钟',
  `send_time_start` varchar(10) NOT NULL DEFAULT '00:00' COMMENT '发送开始时间',
  `send_time_end` varchar(10) NOT NULL DEFAULT '23:59' COMMENT '发送结束时间',
  `last_send_time` int(10) unsigned DEFAULT NULL COMMENT '最后发送时间',
  `created_at` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  `updated_at` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_is_enabled` (`is_enabled`),
  KEY `idx_redemption_rule_id` (`redemption_rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='TG机器人配置';

CREATE TABLE IF NOT EXISTS `slot_tg_message_template` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '模板标题',
  `content` text COMMENT '文案内容',
  `media_type` varchar(20) NOT NULL DEFAULT 'none' COMMENT '媒体类型',
  `media_url` varchar(500) NOT NULL DEFAULT '' COMMENT '媒体地址',
  `buttons_json` text COMMENT '按钮JSON',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `created_at` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  `updated_at` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_is_enabled` (`is_enabled`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='TG文案模板';

CREATE TABLE IF NOT EXISTS `slot_tg_send_record` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bot_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '机器人ID',
  `template_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '模板ID',
  `template_name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称快照',
  `redemption_code_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '红包兑换码ID',
  `code` varchar(50) NOT NULL DEFAULT '' COMMENT '兑换码',
  `chat_id` varchar(100) NOT NULL DEFAULT '' COMMENT 'Chat ID',
  `channel_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '渠道ID',
  `message_id` varchar(100) NOT NULL DEFAULT '' COMMENT '消息ID',
  `media_type` varchar(20) NOT NULL DEFAULT 'none' COMMENT '媒体类型',
  `media_url` varchar(500) NOT NULL DEFAULT '' COMMENT '媒体地址',
  `buttons_json` text COMMENT '实际按钮JSON',
  `send_type` varchar(20) NOT NULL DEFAULT 'test' COMMENT '发送类型',
  `send_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '发送状态',
  `claim_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '领取人数',
  `claim_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '领取金额',
  `register_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '注册人数',
  `first_recharge_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '首充人数',
  `first_recharge_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '首充金额',
  `recharge_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '充值人数',
  `recharge_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '充值金额',
  `fail_reason` varchar(1000) NOT NULL DEFAULT '' COMMENT '失败原因',
  `content` text COMMENT '实际发送内容',
  `send_time` int(10) unsigned DEFAULT NULL COMMENT '发送时间',
  `created_at` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_bot_id` (`bot_id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_redemption_code_id` (`redemption_code_id`),
  KEY `idx_send_time` (`send_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='TG发送记录';

CREATE TABLE IF NOT EXISTS `slot_red_envelope_redemption_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(100) NOT NULL DEFAULT '' COMMENT '规则名称',
  `amount_min` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '最小金额',
  `amount_max` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '最大金额',
  `expire_hours` int(10) unsigned NOT NULL DEFAULT 24 COMMENT '有效期小时',
  `per_user_limit` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '每人领取次数',
  `max_claim_users` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '最大领取人数',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `created_at` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
  `updated_at` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rule_name` (`rule_name`),
  KEY `idx_is_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包兑换码规则模板';

DELIMITER $$

DROP PROCEDURE IF EXISTS `slot_tg_add_column_if_missing`$$
CREATE PROCEDURE `slot_tg_add_column_if_missing`(
  IN p_table varchar(64),
  IN p_column varchar(64),
  IN p_definition text
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
  ) THEN
    SET @slot_tg_sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_definition);
    PREPARE slot_tg_stmt FROM @slot_tg_sql;
    EXECUTE slot_tg_stmt;
    DEALLOCATE PREPARE slot_tg_stmt;
  END IF;
END$$

CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'is_enabled', '`is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT ''是否启用''')$$
CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'daily_send_limit', '`daily_send_limit` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''每日发送上限''')$$
CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'code_length', '`code_length` int(10) unsigned NOT NULL DEFAULT 4 COMMENT ''兑换码位数''')$$
CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'template_id', '`template_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''默认文案模板ID''')$$
CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'redemption_rule_id', '`redemption_rule_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''红包兑换码规则ID''')$$
CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'send_interval_minutes', '`send_interval_minutes` int(10) unsigned NOT NULL DEFAULT 120 COMMENT ''发送间隔分钟''')$$
CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'send_time_start', '`send_time_start` varchar(10) NOT NULL DEFAULT ''00:00'' COMMENT ''发送开始时间''')$$
CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'send_time_end', '`send_time_end` varchar(10) NOT NULL DEFAULT ''23:59'' COMMENT ''发送结束时间''')$$
CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'created_at', '`created_at` int(10) unsigned DEFAULT NULL COMMENT ''创建时间''')$$
CALL slot_tg_add_column_if_missing('slot_tg_bot_config', 'updated_at', '`updated_at` int(10) unsigned DEFAULT NULL COMMENT ''更新时间''')$$

CALL slot_tg_add_column_if_missing('slot_tg_message_template', 'media_type', '`media_type` varchar(20) NOT NULL DEFAULT ''none'' COMMENT ''媒体类型''')$$
CALL slot_tg_add_column_if_missing('slot_tg_message_template', 'media_url', '`media_url` varchar(500) NOT NULL DEFAULT '''' COMMENT ''媒体地址''')$$
CALL slot_tg_add_column_if_missing('slot_tg_message_template', 'buttons_json', '`buttons_json` text COMMENT ''按钮JSON''')$$

CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'media_type', '`media_type` varchar(20) NOT NULL DEFAULT ''none'' COMMENT ''媒体类型''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'media_url', '`media_url` varchar(500) NOT NULL DEFAULT '''' COMMENT ''媒体地址''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'buttons_json', '`buttons_json` text COMMENT ''实际按钮JSON''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'template_name', '`template_name` varchar(100) NOT NULL DEFAULT '''' COMMENT ''模板名称快照''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'claim_count', '`claim_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''领取人数''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'claim_amount', '`claim_amount` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''领取金额''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'register_count', '`register_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''注册人数''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'first_recharge_count', '`first_recharge_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''首充人数''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'first_recharge_amount', '`first_recharge_amount` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''首充金额''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'recharge_count', '`recharge_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''充值人数''')$$
CALL slot_tg_add_column_if_missing('slot_tg_send_record', 'recharge_amount', '`recharge_amount` decimal(12,2) NOT NULL DEFAULT ''0.00'' COMMENT ''充值金额''')$$

DROP PROCEDURE IF EXISTS `slot_tg_add_column_if_missing`$$

DROP PROCEDURE IF EXISTS `slot_tg_seed_admin_rule`$$
CREATE PROCEDURE `slot_tg_seed_admin_rule`()
BEGIN
  DECLARE v_now int unsigned DEFAULT UNIX_TIMESTAMP();
  DECLARE v_parent_id int unsigned DEFAULT 0;
  DECLARE v_bot_id int unsigned DEFAULT 0;
  DECLARE v_template_id int unsigned DEFAULT 0;
  DECLARE v_log_id int unsigned DEFAULT 0;
  DECLARE v_code_stats_id int unsigned DEFAULT 0;

  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'slot_admin_rule'
  ) THEN
    SET v_parent_id = IFNULL((SELECT MAX(`id`) FROM `slot_admin_rule` WHERE `name` = 'tg'), 0);

    IF v_parent_id = 0 THEN
      INSERT INTO `slot_admin_rule`
      (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`)
      VALUES
      (0, 'menu_dir', 'TG机器人', 'tg', 'tg', 'fa fa-telegram', 'tab', '', '', 0, 'none', '', 80, '1', v_now, v_now);
      SET v_parent_id = LAST_INSERT_ID();
    END IF;

    IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot') THEN
      INSERT INTO `slot_admin_rule`
      (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`)
      VALUES
      (v_parent_id, 'menu', 'TG机器人管理', 'tg/bot', 'tg/bot', 'fa fa-circle-o', 'tab', '', '/src/views/backend/tg/bot/index.vue', 1, 'none', '', 100, '1', v_now, v_now);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/template') THEN
      INSERT INTO `slot_admin_rule`
      (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`)
      VALUES
      (v_parent_id, 'menu', 'TG文案模板', 'tg/template', 'tg/template', 'fa fa-circle-o', 'tab', '', '/src/views/backend/tg/template/index.vue', 1, 'none', '', 99, '1', v_now, v_now);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/log') THEN
      INSERT INTO `slot_admin_rule`
      (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`)
      VALUES
      (v_parent_id, 'menu', 'TG发送记录', 'tg/log', 'tg/log', 'fa fa-circle-o', 'tab', '', '/src/views/backend/tg/log/index.vue', 1, 'none', '', 98, '1', v_now, v_now);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/codeStats') THEN
      INSERT INTO `slot_admin_rule`
      (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`)
      VALUES
      (v_parent_id, 'menu', 'TG兑换码统计', 'tg/codeStats', 'tg/codeStats', 'fa fa-bar-chart', 'tab', '', '/src/views/backend/tg/codeStats/index.vue', 1, 'none', '', 97, '1', v_now, v_now);
    END IF;

    SET v_bot_id = IFNULL((SELECT MAX(`id`) FROM `slot_admin_rule` WHERE `name` = 'tg/bot'), 0);
    SET v_template_id = IFNULL((SELECT MAX(`id`) FROM `slot_admin_rule` WHERE `name` = 'tg/template'), 0);
    SET v_log_id = IFNULL((SELECT MAX(`id`) FROM `slot_admin_rule` WHERE `name` = 'tg/log'), 0);
    SET v_code_stats_id = IFNULL((SELECT MAX(`id`) FROM `slot_admin_rule` WHERE `name` = 'tg/codeStats'), 0);

    IF v_bot_id > 0 THEN
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/add') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '添加', 'tg/bot/add', '', '', 'tab', '', '', 0, 'none', '', 100, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/edit') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '编辑', 'tg/bot/edit', '', '', 'tab', '', '', 0, 'none', '', 99, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/del') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '删除', 'tg/bot/del', '', '', 'tab', '', '', 0, 'none', '', 98, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/testSend') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '测试发送', 'tg/bot/testSend', '', '', 'tab', '', '', 0, 'none', '', 97, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/testToken') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '测试Token', 'tg/bot/testToken', '', '', 'tab', '', '', 0, 'none', '', 96, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/getChatIds') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '获取Chat ID', 'tg/bot/getChatIds', '', '', 'tab', '', '', 0, 'none', '', 95, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/sendChatTest') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '发送测试消息', 'tg/bot/sendChatTest', '', '', 'tab', '', '', 0, 'none', '', 94, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/tokenInfo') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '获取机器人信息', 'tg/bot/tokenInfo', '', '', 'tab', '', '', 0, 'none', '', 93, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/chatIdsByToken') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '按Token获取Chat ID', 'tg/bot/chatIdsByToken', '', '', 'tab', '', '', 0, 'none', '', 92, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/sendChatTestByConfig') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '按配置发送测试消息', 'tg/bot/sendChatTestByConfig', '', '', 'tab', '', '', 0, 'none', '', 91, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/bot/redemptionRules') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_bot_id, 'button', '红包规则列表', 'tg/bot/redemptionRules', '', '', 'tab', '', '', 0, 'none', '', 90, '1', v_now, v_now);
      END IF;
    END IF;

    IF v_template_id > 0 THEN
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/template/add') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_template_id, 'button', '添加', 'tg/template/add', '', '', 'tab', '', '', 0, 'none', '', 100, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/template/edit') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_template_id, 'button', '编辑', 'tg/template/edit', '', '', 'tab', '', '', 0, 'none', '', 99, '1', v_now, v_now);
      END IF;
      IF NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/template/del') THEN
        INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_template_id, 'button', '删除', 'tg/template/del', '', '', 'tab', '', '', 0, 'none', '', 98, '1', v_now, v_now);
      END IF;
    END IF;

    IF v_code_stats_id > 0 AND NOT EXISTS (SELECT 1 FROM `slot_admin_rule` WHERE `name` = 'tg/codeStats/index') THEN
      INSERT INTO `slot_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`, `create_time`, `update_time`) VALUES (v_code_stats_id, 'button', '查看', 'tg/codeStats/index', '', '', 'tab', '', '', 0, 'none', '', 100, '1', v_now, v_now);
    END IF;
  END IF;
END$$

CALL slot_tg_seed_admin_rule()$$
DROP PROCEDURE IF EXISTS `slot_tg_seed_admin_rule`$$

DELIMITER ;

INSERT INTO `slot_red_envelope_redemption_rule`
(`rule_name`, `amount_min`, `amount_max`, `expire_hours`, `per_user_limit`, `max_claim_users`, `is_enabled`, `created_at`, `updated_at`)
SELECT 'TG默认红包规则', '1.00', '3.00', 24, 1, 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM `slot_red_envelope_redemption_rule` WHERE `rule_name` = 'TG默认红包规则'
);

INSERT INTO `slot_tg_message_template`
(`title`, `content`, `media_type`, `media_url`, `buttons_json`, `remark`, `is_default`, `is_enabled`, `created_at`, `updated_at`)
SELECT
  '默认每日红包模板',
  '🎁 DAILY BONUS 🎁
CODE: {code}
VALID FOR {expire_hours} HOURS',
  'none',
  '',
  '[]',
  '',
  1,
  1,
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM `slot_tg_message_template` WHERE `title` = '默认每日红包模板'
);

SET FOREIGN_KEY_CHECKS = 1;

-- 代码覆盖后请执行：
-- composer dump-autoload
-- php think list | grep tg
-- php think tg:stats
-- php think tg:send
