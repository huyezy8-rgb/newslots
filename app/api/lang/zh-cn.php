<?php
return [
    // 时间格式化-s
    '%d second%s ago'                                                                     => '%d秒前',
    '%d minute%s ago'                                                                     => '%d分钟前',
    '%d hour%s ago'                                                                       => '%d小时前',
    '%d day%s ago'                                                                        => '%d天前',
    '%d week%s ago'                                                                       => '%d周前',
    '%d month%s ago'                                                                      => '%d月前',
    '%d year%s ago'                                                                       => '%d年前',
    '%d second%s after'                                                                   => '%d秒后',
    '%d minute%s after'                                                                   => '%d分钟后',
    '%d hour%s after'                                                                     => '%d小时后',
    '%d day%s after'                                                                      => '%d天后',
    '%d week%s after'                                                                     => '%d周后',
    '%d month%s after'                                                                    => '%d月后',
    '%d year%s after'                                                                     => '%d年后',
    // 时间格式化-e
    // 文件上传-s
    'File uploaded successfully'                                                          => '文件上传成功！',
    'No files were uploaded'                                                              => '没有文件被上传',
    'The uploaded file format is not allowed'                                             => '上传的文件格式未被允许',
    'The uploaded image file is not a valid image'                                        => '上传的图片文件不是有效的图像',
    'The uploaded file is too large (%sMiB), Maximum file size:%sMiB'                     => '上传的文件太大(%sM)，最大文件大小：%sM',
    'No files have been uploaded or the file size exceeds the upload limit of the server' => '没有文件被上传或文件大小超出服务器上传限制！',
    'Topic format error'                                                                  => '上传存储子目录格式错误！',
    'Driver %s not supported'                                                             => '不支持的驱动：%s',
    // 文件上传-e
    'Username'                                                                            => '用户名',
    'Email'                                                                               => '邮箱',
    'Mobile'                                                                              => '手机号',
    'Password'                                                                            => '密码',
    'Login expired, please login again.'                                                  => '登录过期，请重新登录。',
    'Account not exist'                                                                   => '帐户不存在',
    'Account disabled'                                                                    => '帐户已禁用',
    'Token login failed'                                                                  => '令牌登录失败',
    'Please try again after 1 day'                                                        => '登录失败次数超限，请在1天后再试',
    'Password is incorrect'                                                               => '密码不正确',
    'You are not logged in'                                                               => '你没有登录',
    'Unknown operation'                                                                   => '未知操作',
    'No action available, please contact the administrator~'                              => '没有可用操作，请联系管理员~',
    'Please login first'                                                                  => '请先登录！',
    'You have no permission'                                                              => '没有权限操作！',
    'Parameter error'                                                                     => '参数错误!',
    'Token expiration'                                                                    => '登录态过期，请重新登录！',
    'Captcha error'                                                                       => '验证码错误！',
    
    // Leaderboard相关
    'Invalid type'                                                                        => '无效的类型',
    'leaderboard.error'                                                                   => '获取排行榜数据失败',
    'leaderboard.success'                                                                 => '获取排行榜数据成功',
    'Get leaderboard config success'                                                      => '获取排行榜配置成功',
    'Get leaderboard config failed'                                                       => '获取排行榜配置失败',
    '无效的排行榜类型'                                                                        => '无效的排行榜类型',
    '获取奖金池信息失败'                                                                      => '获取奖金池信息失败',
    
    // Service语言包
    // AccountService
    'service.amount_must_be_positive' => '金额必须为正数',
    'service.wallet_type_invalid' => '钱包类型不合法',
    'service.user_not_found' => '用户不存在',
    'service.insufficient_balance' => '余额不足',
    'service.balance_increase' => '余额增加',
    'service.balance_decrease' => '余额扣除',
    
    // PayGatewayService
    'service.incomplete_parameters' => '参数不完整',
    
    // MemberLevelService
    'service.no_member_levels_configured' => '未配置任何会员等级',
    'service.user_upgraded_to_level' => '用户升级为等级：:level',
    'service.no_level_change_needed' => '无需变更等级',
    
    // UserCollectGameService
    'service.game_already_collected' => '游戏已收藏',
    'service.game_does_not_exist' => '游戏不存在',
    'service.collection_successful' => '收藏成功',
    'service.uncollection_successful' => '取消收藏成功',
    'service.uncollection_failed' => '取消收藏失败',
    
    // FacebookService
    'service.event_name_required' => '事件名称(event_name)不能为空',
    'service.invalid_currency_code' => '无效的货币代码: :currency',
    'service.amount_must_be_positive_number' => '金额必须是正数',
    'service.api_request_failed' => 'API请求失败: :error',
    'service.facebook_conversion_event_sent_successfully' => 'Facebook转化事件发送成功',
    'service.facebook_conversion_event_send_failed' => 'Facebook转化事件发送失败',
    'service.conversion_event_request_parameters' => '转化事件请求参数',
    
    // MessageService
    'service.clear_cache' => '清除缓存',
    
    // 佣金提取相关
    'Amount must be greater than 0' => '金额必须大于0',
    'Insufficient commission balance' => '佣金余额不足',
    'Failed to update account balance' => '更新账户余额失败',
    'Failed to record commission withdraw log' => '记录佣金提取日志失败',
    'Commission withdraw failed' => '佣金提取失败',
    'Commission withdraw successful' => '佣金提取成功',
    'Failed to get withdraw log' => '获取提取记录失败',
    'Withdraw log retrieved successfully' => '提取记录获取成功',
    
    // 游戏相关
    'Your account is not allowed to play games' => '您的账户不允许玩游戏',
];