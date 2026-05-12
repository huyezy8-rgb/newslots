<?php
return [
    // 时间格式化-s
    '%d second%s ago'                                                                     => 'منذ %d ثانية',
    '%d minute%s ago'                                                                     => 'منذ %d دقيقة',
    '%d hour%s ago'                                                                       => 'منذ %d ساعة',
    '%d day%s ago'                                                                        => 'منذ %d يوم',
    '%d week%s ago'                                                                       => 'منذ %d أسبوع',
    '%d month%s ago'                                                                      => 'منذ %d شهر',
    '%d year%s ago'                                                                       => 'منذ %d سنة',
    '%d second%s after'                                                                   => 'بعد %d ثانية',
    '%d minute%s after'                                                                   => 'بعد %d دقيقة',
    '%d hour%s after'                                                                     => 'بعد %d ساعة',
    '%d day%s after'                                                                      => 'بعد %d يوم',
    '%d week%s after'                                                                     => 'بعد %d أسبوع',
    '%d month%s after'                                                                    => 'بعد %d شهر',
    '%d year%s after'                                                                     => 'بعد %d سنة',
    // 时间格式化-e
    // 文件上传-s
    'File uploaded successfully'                                                          => 'تم رفع الملف بنجاح!',
    'No files were uploaded'                                                              => 'لم يتم رفع أي ملفات',
    'The uploaded file format is not allowed'                                             => 'تنسيق الملف المرفوع غير مسموح',
    'The uploaded image file is not a valid image'                                        => 'ملف الصورة المرفوع ليس صورة صالحة',
    'The uploaded file is too large (%sMiB), Maximum file size:%sMiB'                     => 'الملف المرفوع كبير جداً (%s ميجابايت)، الحد الأقصى لحجم الملف: %s ميجابايت',
    'No files have been uploaded or the file size exceeds the upload limit of the server' => 'لم يتم رفع أي ملفات أو حجم الملف يتجاوز حد الرفع على الخادم!',
    'Topic format error'                                                                  => 'خطأ في تنسيق مجلد التخزين الفرعي!',
    'Driver %s not supported'                                                             => 'السائق غير مدعوم: %s',
    // 文件上传-e
    'Username'                                                                            => 'اسم المستخدم',
    'Email'                                                                               => 'البريد الإلكتروني',
    'Mobile'                                                                              => 'رقم الهاتف',
    'Password'                                                                            => 'كلمة المرور',
    'Login expired, please login again.'                                                  => 'انتهت صلاحية تسجيل الدخول، يرجى تسجيل الدخول مرة أخرى.',
    'Account not exist'                                                                   => 'الحساب غير موجود',
    'Account disabled'                                                                    => 'تم تعطيل الحساب',
    'Token login failed'                                                                  => 'فشل تسجيل الدخول بالرمز',
    'Please try again after 1 day'                                                        => 'تم تجاوز عدد محاولات تسجيل الدخول الفاشلة، يرجى المحاولة مرة أخرى بعد 24 ساعة',
    'Password is incorrect'                                                               => 'كلمة المرور غير صحيحة',
    'You are not logged in'                                                               => 'لم تقم بتسجيل الدخول',
    'Unknown operation'                                                                   => 'عملية غير معروفة',
    'No action available, please contact the administrator~'                              => 'لا توجد عملية متاحة، يرجى الاتصال بالمسؤول~',
    'Please login first'                                                                  => 'يرجى تسجيل الدخول أولاً!',
    'You have no permission'                                                              => 'ليس لديك صلاحية للعملية!',
    'Parameter error'                                                                     => 'خطأ في المعاملات!',
    'Token expiration'                                                                    => 'انتهت صلاحية تسجيل الدخول، يرجى تسجيل الدخول مرة أخرى!',
    'Captcha error'                                                                       => 'خطأ في رمز التحقق!',
    
    // Leaderboard相关
    'Invalid type'                                                                        => 'نوع غير صالح',
    'leaderboard.error'                                                                   => 'فشل في الحصول على بيانات لوحة المتصدرين',
    'leaderboard.success'                                                                 => 'تم الحصول على بيانات لوحة المتصدرين بنجاح',
    'Get leaderboard config success'                                                      => 'تم الحصول على إعدادات لوحة المتصدرين بنجاح',
    'Get leaderboard config failed'                                                       => 'فشل في الحصول على إعدادات لوحة المتصدرين',
    '无效的排行榜类型'                                                                        => 'نوع لوحة المتصدرين غير صالح',
    '获取奖金池信息失败'                                                                      => 'فشل في الحصول على معلومات جائزة الجائزة',
    
    // Service语言包
    // AccountService
    'service.amount_must_be_positive' => 'يجب أن يكون المبلغ رقمًا موجبًا',
    'service.wallet_type_invalid' => 'نوع المحفظة غير صالح',
    'service.user_not_found' => 'المستخدم غير موجود',
    'service.insufficient_balance' => 'الرصيد غير كافٍ',
    'service.balance_increase' => 'زيادة الرصيد',
    'service.balance_decrease' => 'خصم الرصيد',
    
    // PayGatewayService
    'service.incomplete_parameters' => 'معاملات غير كاملة',
    
    // MemberLevelService
    'service.no_member_levels_configured' => 'لم يتم تكوين أي مستويات عضوية',
    'service.user_upgraded_to_level' => 'تم ترقية المستخدم إلى المستوى: :level',
    'service.no_level_change_needed' => 'لا حاجة لتغيير المستوى',
    
    // UserCollectGameService
    'service.game_already_collected' => 'اللعبة محفوظة بالفعل',
    'service.game_does_not_exist' => 'اللعبة غير موجودة',
    'service.collection_successful' => 'تم الحفظ بنجاح',
    'service.uncollection_successful' => 'تم إلغاء الحفظ بنجاح',
    'service.uncollection_failed' => 'فشل إلغاء الحفظ',
    
    // FacebookService
    'service.event_name_required' => 'اسم الحدث (event_name) لا يمكن أن يكون فارغًا',
    'service.invalid_currency_code' => 'رمز العملة غير صالح: :currency',
    'service.amount_must_be_positive_number' => 'يجب أن يكون المبلغ رقمًا موجبًا',
    'service.api_request_failed' => 'فشل طلب API: :error',
    'service.facebook_conversion_event_sent_successfully' => 'تم إرسال حدث تحويل Facebook بنجاح',
    'service.facebook_conversion_event_send_failed' => 'فشل إرسال حدث تحويل Facebook',
    'service.conversion_event_request_parameters' => 'معاملات طلب حدث التحويل',
    
    // MessageService
    'service.clear_cache' => 'مسح الذاكرة المؤقتة',
    
    // 佣金提取相关
    'Amount must be greater than 0' => 'يجب أن يكون المبلغ أكبر من 0',
    'Insufficient commission balance' => 'رصيد العمولة غير كافٍ',
    'Failed to update account balance' => 'فشل تحديث رصيد الحساب',
    'Failed to record commission withdraw log' => 'فشل تسجيل سجل سحب العمولة',
    'Commission withdraw failed' => 'فشل سحب العمولة',
    'Commission withdraw successful' => 'تم سحب العمولة بنجاح',
    'Failed to get withdraw log' => 'فشل الحصول على سجل السحب',
    'Withdraw log retrieved successfully' => 'تم الحصول على سجل السحب بنجاح',
    
    // 游戏相关
    'Your account is not allowed to play games' => 'حسابك غير مسموح له بلعب الألعاب',
];

