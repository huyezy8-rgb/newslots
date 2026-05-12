<?php

use think\migration\Migrator;

class InsertPaymentData extends Migrator
{
    public function up()
    {
        // 插入支付渠道数据
        $channels = [
            [
                'name' => 'AmoPay',
                'code' => 'amopay',
                'description' => 'AmoPay 加密货币支付渠道',
                'config' => '{"client_id":"","secret_key":"","api_url":"https://api.ramp.amopay.io/api"}',
                'status' => '1',
                'remark' => 'AmoPay 加密货币支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'name' => 'SuccusPay',
                'code' => 'succuspay',
                'description' => 'SuccusPay 支付渠道',
                'config' => '{"mch_no":"","key":"","api_url":""}',
                'status' => '1',
                'remark' => 'SuccusPay 支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'name' => '银行转账',
                'code' => 'bank_transfer',
                'description' => '传统银行转账支付',
                'config' => '{"bank_name":"","account_name":"","account_number":"","swift_code":""}',
                'status' => '1',
                'remark' => '银行转账支付',
                'create_time' => time(),
                'update_time' => time(),
            ]
        ];

        $this->table('payment_channels')->insert($channels)->save();

        // 插入支付方式数据
        $methods = [
            // AmoPay 支付方式
            [
                'channel_code' => 'amopay',
                'name' => 'Visa/Master Card',
                'code' => '10001',
                'description' => 'Visa/Master Card 信用卡支付',
                'icon' => '/static/images/payment/visa_master.png',
                'status' => '1',
                'remark' => 'Visa/Master Card 信用卡支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'channel_code' => 'amopay',
                'name' => 'Apple Pay',
                'code' => '501',
                'description' => 'Apple Pay 移动支付',
                'icon' => '/static/images/payment/apple_pay.png',
                'status' => '1',
                'remark' => 'Apple Pay 移动支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'channel_code' => 'amopay',
                'name' => 'Google Pay',
                'code' => '701',
                'description' => 'Google Pay 移动支付',
                'icon' => '/static/images/payment/google_pay.png',
                'status' => '1',
                'remark' => 'Google Pay 移动支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'channel_code' => 'amopay',
                'name' => 'Neteller',
                'code' => '52004',
                'description' => 'Neteller 电子钱包支付',
                'icon' => '/static/images/payment/neteller.png',
                'status' => '1',
                'remark' => 'Neteller 电子钱包支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'channel_code' => 'amopay',
                'name' => 'Skrill',
                'code' => '52005',
                'description' => 'Skrill 电子钱包支付',
                'icon' => '/static/images/payment/skrill.png',
                'status' => '1',
                'remark' => 'Skrill 电子钱包支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            // SuccusPay 支付方式
            [
                'channel_code' => 'succuspay',
                'name' => 'CashApp',
                'code' => 'cashapp',
                'description' => 'CashApp 支付',
                'icon' => '/static/images/payment/cashapp.png',
                'status' => '1',
                'remark' => 'CashApp 支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'channel_code' => 'succuspay',
                'name' => 'Card',
                'code' => 'card',
                'description' => '银行卡支付',
                'icon' => '/static/images/payment/card.png',
                'status' => '1',
                'remark' => '银行卡支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'channel_code' => 'succuspay',
                'name' => 'Zelle',
                'code' => 'zelle',
                'description' => 'Zelle 个码支付',
                'icon' => '/static/images/payment/zelle.png',
                'status' => '1',
                'remark' => 'Zelle 个码支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'channel_code' => 'succuspay',
                'name' => 'PayPal',
                'code' => 'paypal',
                'description' => 'PayPal 支付',
                'icon' => '/static/images/payment/paypal.png',
                'status' => '1',
                'remark' => 'PayPal 支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'channel_code' => 'succuspay',
                'name' => 'Google Pay / Apple Pay',
                'code' => 'googleorapple',
                'description' => 'Google Pay / Apple Pay 支付（不支持 webview）',
                'icon' => '/static/images/payment/google_apple_pay.png',
                'status' => '1',
                'remark' => 'Google Pay / Apple Pay 支付，不支持 webview',
                'create_time' => time(),
                'update_time' => time(),
            ],
            [
                'channel_code' => 'succuspay',
                'name' => 'BTC Lightning',
                'code' => 'btclightning',
                'description' => 'BTC 闪电网络支付',
                'icon' => '/static/images/payment/btc_lightning.png',
                'status' => '1',
                'remark' => 'BTC 闪电网络支付',
                'create_time' => time(),
                'update_time' => time(),
            ],
            // 银行转账支付方式
            [
                'channel_code' => 'bank_transfer',
                'name' => '银行转账',
                'code' => 'bank_transfer',
                'description' => '传统银行转账',
                'icon' => '/static/images/payment/bank.png',
                'status' => '1',
                'remark' => '银行转账支付',
                'create_time' => time(),
                'update_time' => time(),
            ]
        ];

        $this->table('payment_methods')->insert($methods)->save();
    }

    public function down()
    {
        // 删除数据
        $this->execute('DELETE FROM payment_methods');
        $this->execute('DELETE FROM payment_channels');
    }
} 