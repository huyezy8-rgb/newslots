<?php
return [
    // 会员等级相关字段
    'level' => '等级',
    'level_name' => '等级名称',
    'current_level' => '当前等级',
    'next_level' => '下一等级',
    'user_level' => '用户等级',
    'upgrade_progress' => '升级进度',
    'user_rewards' => '用户奖励',
    'levels' => '等级列表',
    'levels_total' => '等级总数',
    'rewards_total' => '可领取奖励数',
    
    // 等级配置字段
    'recharge_requirement' => '充值要求',
    'withdraw_limit' => '提款限制',
    'daily_withdraw_times' => '每日提款次数',
    'withdraw_fee_percent' => '提款手续费百分比',
    'bonus_percent' => '奖金百分比',
    'upgrade_reward' => '升级奖励',
    'weekly_reward' => '周奖励',
    'monthly_reward' => '月奖励',
    
    // 升级进度相关
    'required_amount' => '所需金额',
    'current_amount' => '当前金额',
    'need_amount' => '还需金额',
    'progress_percentage' => '进度百分比',
    'is_max_level' => '是否最高等级',
    'recharge_amount' => '充值金额',
    
    // 奖励相关
    'reward_type' => '奖励类型',
    'reward_amount' => '奖励金额',
    'issued_time' => '发放时间',
    'claimed_time' => '领取时间',
    'expire_time' => '过期时间',
    'is_claimable' => '是否可领取',
    'next_reward' => '下次奖励',
    'reward_status' => '奖励状态',
    
    // 奖励类型
    'upgrade' => '升级奖励',
    'weekly' => '周奖励',
    'monthly' => '月奖励',
    
    // 奖励状态
    'pending' => '待领取',
    'claimed' => '已领取',
    'expired' => '已过期',
    
    // 操作相关消息
    'get_member_level_info_success' => '获取会员等级信息成功',
    'get_member_level_info_failed' => '获取会员等级信息失败',
    'user_not_found' => '用户不存在',
    'level_config_not_found' => '等级配置不存在',
    'create_reward_record_success' => '创建奖励记录成功',
    'create_reward_record_failed' => '创建奖励记录失败',
    'claim_reward_success' => '领取奖励成功',
    'claim_reward_failed' => '领取奖励失败',
    'reward_not_claimable' => '奖励不可领取',
    'invalid_reward_type' => '无效的奖励类型',
    'no_levels_configured' => '没有配置等级信息',
    'already_max_level' => '已达最高等级',
    
    // 升级相关消息
    'upgrade_success' => '升级成功',
    'upgrade_reward_granted' => '升级奖励已发放',
    'weekly_reward_granted' => '周奖励已发放',
    'monthly_reward_granted' => '月奖励已发放',
    
    // 验证相关
    'invalid_user_id' => '无效的用户ID',
    'invalid_level' => '无效的等级',
    'invalid_amount' => '无效的金额',
    
    // 统计相关
    'total_users' => '总用户数',
    'total_rewards_issued' => '总奖励发放数',
    'total_rewards_claimed' => '总奖励领取数',
    'total_upgrade_rewards' => '升级奖励总计',
    'total_weekly_rewards' => '周奖励总计',
    'total_monthly_rewards' => '月奖励总计',
    
    // Claim reward specific messages
    'reward_type_cannot_be_empty' => '奖励类型不能为空',
    'reward_record_not_exists' => '奖励记录不存在',
    'upgrade_reward_not_claimable' => '升级奖励不可领取',
    'weekly_reward_not_claimable' => '周奖励不可领取',
    'monthly_reward_not_claimable' => '月奖励不可领取',
    'upgrade_reward_note' => '升级奖励',
    'weekly_reward_note' => '周奖励',
    'monthly_reward_note' => '月奖励',
    'member_level_reward_note' => '会员等级{type}奖励',
];
