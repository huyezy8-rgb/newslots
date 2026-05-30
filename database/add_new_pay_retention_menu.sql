-- Add New Paid Retention report menu and view permission.
-- Safe to run multiple times.

SET @parent_id := (
    SELECT id
    FROM slot_admin_rule
    WHERE name = 'statistics'
    LIMIT 1
);

INSERT INTO slot_admin_rule (
    pid, type, title, name, path, icon, menu_type, url, component, keepalive,
    extend, remark, weigh, status, update_time, create_time
)
SELECT
    @parent_id,
    'menu',
    '新增付费留存',
    'NewPayRetention',
    'NewPayRetention',
    'fa fa-circle-o',
    'tab',
    '',
    '/src/views/backend/new_pay_retention/index.vue',
    0,
    'none',
    '',
    -70,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
WHERE @parent_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM slot_admin_rule WHERE name = 'NewPayRetention' LIMIT 1
  );

SET @menu_id := (
    SELECT id
    FROM slot_admin_rule
    WHERE name = 'NewPayRetention'
    LIMIT 1
);

INSERT INTO slot_admin_rule (
    pid, type, title, name, path, icon, menu_type, url, component, keepalive,
    extend, remark, weigh, status, update_time, create_time
)
SELECT
    @menu_id,
    'button',
    '查看',
    'newPayRetention/index',
    '',
    'fa fa-circle-o',
    'tab',
    '',
    '',
    0,
    'none',
    '',
    -70,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
WHERE @menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM slot_admin_rule WHERE name = 'newPayRetention/index' LIMIT 1
  );
