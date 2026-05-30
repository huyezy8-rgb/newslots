-- Add First Pay LTV report menu and view permission.
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
    '首充LTV',
    'FirstPayLtv',
    'FirstPayLtv',
    'fa fa-circle-o',
    'tab',
    '',
    '/src/views/backend/first_pay_ltv/index.vue',
    0,
    'none',
    '',
    -71,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
WHERE @parent_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM slot_admin_rule WHERE name = 'FirstPayLtv' LIMIT 1
  );

SET @menu_id := (
    SELECT id
    FROM slot_admin_rule
    WHERE name = 'FirstPayLtv'
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
    'firstPayLtv/index',
    '',
    'fa fa-circle-o',
    'tab',
    '',
    '',
    0,
    'none',
    '',
    -71,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
WHERE @menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM slot_admin_rule WHERE name = 'firstPayLtv/index' LIMIT 1
  );

-- The page reuses /admin/ltv/index, and backend permission checks use the
-- controller/action path. Insert this only when an existing LTV permission is
-- not already present.
INSERT INTO slot_admin_rule (
    pid, type, title, name, path, icon, menu_type, url, component, keepalive,
    extend, remark, weigh, status, update_time, create_time
)
SELECT
    @menu_id,
    'button',
    '接口权限',
    'ltv/index',
    '',
    'fa fa-circle-o',
    'tab',
    '',
    '',
    0,
    'none',
    '',
    -72,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
WHERE @menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM slot_admin_rule WHERE name = 'ltv/index' LIMIT 1
  );
