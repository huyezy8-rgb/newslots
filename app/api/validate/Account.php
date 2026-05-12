<?php

namespace app\api\validate;

use think\Validate;

class Account extends Validate
{
    protected $failException = true;

    protected $rule = [
        'channel_id'  => 'require|integer',
        'p_id'        => 'integer',
        'name'        => 'require|regex:^[a-zA-Z][a-zA-Z0-9_]{2,15}$|unique:account',
        'nickname'    => 'require|chsDash',
        'mobile'      => 'require|mobile|unique:account',
        'vip'         => 'integer|egt:0',
        'token'       => 'max:255',
        'invite_code' => 'max:255',
        'browser_fingerprinting' => 'require|max:255',
        'last_login_time' => 'dateFormat:Y-m-d H:i:s',
        'reg_time'        => 'dateFormat:Y-m-d H:i:s',
        'is_black'        => 'in:0,1',
        'experience_wallet' => 'float|egt:0',
        'recharge_wallet'   => 'float|egt:0',
        'switch_wallet'     => 'in:0,1',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'reg' => ['browser_fingerprinting'],
    ];

    public function __construct()
    {
        $this->field = [
            'channel_id'            => __('Channel ID'),
            'p_id'                  => __('Parent ID'),
            'name'                  => __('Username'),
            'nickname'              => __('Nickname'),
            'mobile'                => __('Mobile'),
            'vip'                   => __('VIP Level'),
            'token'                 => __('Token'),
            'invite_code'           => __('Invite Code'),
            'browser_fingerprinting' => __('Browser Fingerprint'),
            'last_login_time'       => __('Last Login Time'),
            'reg_time'              => __('Registration Time'),
            'is_black'              => __('Blacklist Status'),
            'experience_wallet'     => __('Experience Wallet'),
            'recharge_wallet'       => __('Recharge Wallet'),
            'switch_wallet'         => __('Switch Wallet'),
        ];

        $this->message = array_merge($this->message, [
            'name.regex'        => __('Username must start with a letter and be 3-16 characters.'),
            'name.unique'       => __('Username already exists.'),
            'nickname.chsDash'  => __('Nickname can only contain Chinese characters, letters, numbers, underscores, and dashes.'),
            'mobile.unique'     => __('Mobile number already registered.'),
            'mobile.mobile'     => __('Invalid mobile number format.'),
            'browser_fingerprinting.require' => __('Browser fingerprint is required.'),
            'switch_wallet.in'  => __('Switch wallet must be 0 or 1.'),
            'is_black.in'       => __('Blacklist value must be 0 or 1.'),
        ]);

        parent::__construct();
    }
}
