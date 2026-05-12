-- 幸运转盘数据库表结构
-- 创建时间：2025-01-01

-- 1. 幸运转盘主配置表
CREATE TABLE IF NOT EXISTS `slot_lucky_wheel_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '活动标题',
  `banner_image` varchar(255) NOT NULL DEFAULT '' COMMENT 'Banner图片',
  `bet_multiple` decimal(3,1) NOT NULL DEFAULT '1.0' COMMENT '打码倍数',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '活动状态：0-禁用，1-启用',
  `createtime` bigint(20) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(20) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='幸运转盘主配置表';

-- 2. 转盘表
CREATE TABLE IF NOT EXISTS `slot_lucky_wheel_turntable` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `wheel_name` varchar(50) NOT NULL DEFAULT '' COMMENT '转盘名称',
  `unlock_condition` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '解锁条件（充值金额）',
  `free_times` int(11) NOT NULL DEFAULT '0' COMMENT '赠送次数',
  `max_user_times` int(11) NOT NULL DEFAULT '0' COMMENT '用户最大次数限制（0表示无限制）',
  `prizes` text COMMENT '奖项配置JSON',
  `rules` text COMMENT '规则配置JSON',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '转盘状态：0-禁用，1-启用',
  `createtime` bigint(20) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(20) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='转盘表';

-- 3. 转盘记录表
CREATE TABLE IF NOT EXISTS `slot_lucky_wheel_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `wheel_id` int(11) NOT NULL DEFAULT '0' COMMENT '转盘ID',
  `prize_title` varchar(100) NOT NULL DEFAULT '' COMMENT '中奖奖项标题',
  `prize_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '中奖金额',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0-未发放，1-已发放',
  `createtime` bigint(20) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(20) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_wheel` (`user_id`, `wheel_id`),
  KEY `idx_wheel_id` (`wheel_id`),
  KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='转盘记录表';

-- 插入默认数据
INSERT INTO `slot_lucky_wheel_config` (`id`, `title`, `banner_image`, `bet_multiple`, `status`, `createtime`, `updatetime`) VALUES
(1, '幸运转盘大抽奖', 'https://example.com/banner.jpg', 1.5, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT INTO `slot_lucky_wheel_turntable` (`id`, `wheel_name`, `unlock_condition`, `free_times`, `max_user_times`, `prizes`, `rules`, `status`, `createtime`, `updatetime`) VALUES
(1, '新手转盘', 0.00, 1, 10, '[]', '[]', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '进阶转盘', 500.00, 0, 5, '[]', '[]', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, '豪华转盘', 2000.00, 0, 3, '[]', '[]', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()); 