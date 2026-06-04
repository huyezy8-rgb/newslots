-- Add separate Google Pay and Apple Pay recharge methods for SuccusPay.
-- Safe to run multiple times.

SET @succus_channel_code := COALESCE(
    (SELECT code FROM slot_payment_channels WHERE code = 'Succus' LIMIT 1),
    (SELECT channel_code FROM slot_payment_methods WHERE channel_code = 'Succus' LIMIT 1),
    (SELECT code FROM slot_payment_channels WHERE LOWER(name) = 'succuspay' LIMIT 1),
    (SELECT channel_code FROM slot_payment_methods WHERE LOWER(channel_code) = 'succuspay' LIMIT 1),
    'Succus'
);

INSERT INTO slot_payment_methods (
    channel_code, name, unique_tag, code, description, icon, `show`, status, remark,
    is_clause, pay_method, min_recharge_amount, max_recharge_amount,
    min_withdraw_amount, max_withdraw_amount, create_time, update_time,
    field_config, validation_rules
)
SELECT
    @succus_channel_code,
    'Apple Pay',
    'apple',
    'apple',
    'Apple Pay mobile payment',
    '/static/images/payment/apple_pay.png',
    'all',
    1,
    'SuccusPay Apple Pay recharge',
    0,
    '1',
    NULL,
    NULL,
    NULL,
    NULL,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP(),
    NULL,
    NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM slot_payment_methods
    WHERE channel_code = @succus_channel_code
      AND unique_tag = 'apple'
      AND pay_method = '1'
);

UPDATE slot_payment_methods
SET
    name = 'Apple Pay',
    code = 'apple',
    description = 'Apple Pay mobile payment',
    icon = '/static/images/payment/apple_pay.png',
    `show` = 'all',
    status = 1,
    remark = 'SuccusPay Apple Pay recharge',
    is_clause = 0,
    min_recharge_amount = NULL,
    max_recharge_amount = NULL,
    update_time = UNIX_TIMESTAMP()
WHERE channel_code = @succus_channel_code
  AND unique_tag = 'apple'
  AND pay_method = '1';

INSERT INTO slot_payment_methods (
    channel_code, name, unique_tag, code, description, icon, `show`, status, remark,
    is_clause, pay_method, min_recharge_amount, max_recharge_amount,
    min_withdraw_amount, max_withdraw_amount, create_time, update_time,
    field_config, validation_rules
)
SELECT
    @succus_channel_code,
    'Google Pay',
    'google',
    'google',
    'Google Pay mobile payment',
    '/static/images/payment/google_pay.png',
    'android',
    1,
    'SuccusPay Google Pay recharge',
    0,
    '1',
    NULL,
    NULL,
    NULL,
    NULL,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP(),
    NULL,
    NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM slot_payment_methods
    WHERE channel_code = @succus_channel_code
      AND unique_tag = 'google'
      AND pay_method = '1'
);

UPDATE slot_payment_methods
SET
    name = 'Google Pay',
    code = 'google',
    description = 'Google Pay mobile payment',
    icon = '/static/images/payment/google_pay.png',
    `show` = 'android',
    status = 1,
    remark = 'SuccusPay Google Pay recharge',
    is_clause = 0,
    min_recharge_amount = NULL,
    max_recharge_amount = NULL,
    update_time = UNIX_TIMESTAMP()
WHERE channel_code = @succus_channel_code
  AND unique_tag = 'google'
  AND pay_method = '1';
