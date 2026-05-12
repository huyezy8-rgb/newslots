<?php

return [
    // AccountService
    'amount_must_be_positive' => '金额必须为正数',
    'wallet_type_invalid' => '钱包类型不合法',
    'user_not_found' => '用户不存在',
    'insufficient_balance' => '余额不足',
    'balance_increase' => '余额增加',
    'balance_decrease' => '余额扣除',
    
    // PayGatewayService
    'incomplete_parameters' => '参数不完整',
    
    // MemberLevelService
    'no_member_levels_configured' => '未配置任何会员等级',
    'user_upgraded_to_level' => '用户升级为等级：:level',
    'user_upgraded_to_levels' => '用户升级为等级：:levels',
    'no_level_change_needed' => '无需变更等级',
    'upgrade_bonus_sent' => '升级奖励已发放',
    'upgrade_notification_sent' => '升级通知已发送',
    'get_levels_success' => '获取等级列表成功',
    'get_levels_failed' => '获取等级列表失败',
    
    // UserCollectGameService
    'game_already_collected' => '游戏已收藏',
    'game_does_not_exist' => '游戏不存在',
    'collection_successful' => '收藏成功',
    'uncollection_successful' => '取消收藏成功',
    'uncollection_failed' => '取消收藏失败',
    
    // FacebookService
    'event_name_required' => '事件名称(event_name)不能为空',
    'invalid_currency_code' => '无效的货币代码: :currency',
    'amount_must_be_positive_number' => '金额必须是正数',
    'api_request_failed' => 'API请求失败: :error',
    'facebook_conversion_event_sent_successfully' => 'Facebook转化事件发送成功',
    'facebook_conversion_event_send_failed' => 'Facebook转化事件发送失败',
    'conversion_event_request_parameters' => '转化事件请求参数',
    
    // MessageService
    'clear_cache' => '清除缓存',
]; 