# 后端 API 架构文档

## 1. 技术栈

- **框架**: ThinkPHP 8.1（PHP >= 8.0.2），多应用模式
- **入口**: `public/index.php`
- **数据库**: MySQL，库名 `newslot`，表前缀 `slot_`
- **认证**: 自研 Token 方案，Token 存储于 MySQL `slot_token` 表，通过 `firebase/php-jwt` 加解密
- **队列**: `topthink/think-queue`，配置于 `config/queue.php`
- **缓存**: Redis（用户在线状态、游戏列表、SSE 等）

---

## 2. 应用分层

| 模块 | 路径 | 用途 |
|---|---|---|
| `api` | `app/api/` | 对外 REST API，Vue 前端与移动端调用 |
| `admin` | `app/admin/` | 管理后台 |
| `common` | `app/common/` | 共享代码：Model、Service、Middleware、Traits |

---

## 3. 路由机制

无显式路由文件。ThinkPHP 默认映射：`/api/控制器/方法` → `app\api\controller\控制器::方法()`。

`config/route.php` 关键配置：
- `url_route_must` = false（不强制路由）
- `default_controller` = Index
- `default_action` = index

---

## 4. 请求处理流程

```
请求 → AllowCrossDomain（CORS 处理）
     → LoadLangPack（加载语言包）
     → ChannelLang（渠道语言切换：zh-cn / en / ar）
     → 控制器构造 → initialize() → 具体方法
```

中间件注册于 `app/api/middleware.php`。

---

## 5. 控制器继承链

```
BaseController (ThinkPHP)
  └─ app\common\controller\Api              基类 API 控制器
       ├─ app\api\controller\Base           ***主要基类（90% 控制器继承此类）***
       │   - 从 header/param/server 读取 token
       │   - 查 slot_account 验证用户身份
       │   - 黑名单检查
       │   - 设置 $this->userInfo
       │   - Redis 在线状态（TTL 5分钟）
       │   - 双钱包系统支持
       │
       └─ app\common\controller\Frontend    会员中心基类（使用 Auth 权限库）
```

### 免登录声明

通过 `protected array $noNeedLogin` 控制：
- `['*']` — 整个控制器无需登录
- `['index', 'list']` — 仅指定方法无需登录

---

## 6. 用户认证流程

### 游客登录（Login::index）

```
前端发送: channel_name + browser_fingerprinting + invite_code
         ↓
后端查找渠道: 域名匹配 → 参数匹配 → 默认第一个渠道
         ↓
查询用户: channel_id + browser_fingerprinting
         ↓
命中 → 老用户登录，返回 token + 账户信息
未命中 → 新用户注册，生成 token，事务写入
         ↓
异步: 游戏注册事件、Facebook Conversion 事件
同步: 站内信、签到、PDD 邀请奖励
```

- Token 生成: `bin2hex(random_bytes(16))`
- 邀请码生成: 6 位大写字母+数字，循环查重

### 手机号+短信登录（Login::mobile）

- 查找 `slot_account.mobile`，验证短信码
- 短信服务商: `api.laaffic.com`

### 密码登录（Login::password）

- 查找 `slot_account.mobile`，`password_verify()` 验证
- 密码未设置时提示使用短信登录

---

## 7. 控制器清单

### 认证相关
| 控制器 | 说明 |
|---|---|
| `Login` | 游客登录、手机号登录、密码登录、忘记密码 |

### 账户管理
| 控制器 | 说明 |
|---|---|
| `Account` | 用户信息、手机绑定、个人资料编辑 |

### 游戏
| 控制器 | 说明 |
|---|---|
| `Game` | 游戏列表（按品牌分组+缓存）、游戏启动 URL（调用第三方 API） |
| `UserCollectGame` | 游戏收藏 |

### 充值 & 提现
| 控制器 | 说明 |
|---|---|
| `Recharge` | 充值订单 |
| `Withdraw` | 余额提现 |
| `WithdrawAccount` | 提现账户管理 |
| `Cash` | 现金/余额相关 |

### 团队 & 代理
| 控制器 | 说明 |
|---|---|
| `Team` | 团队信息、代理列表、返佣比例调整、佣金提取 |

### VIP & 会员
| 控制器 | 说明 |
|---|---|
| `MemberLevel` | 会员等级 |
| `GameVip` | 游戏 VIP |
| `DepositVip` | VIP 充值活动 |

### 活动
| 控制器 | 说明 |
|---|---|
| `LuckyWheel` | 幸运转盘 |
| `Chest` | 宝箱 |
| `RedEnvelopeRedemption` | 红包兑换 |
| `Daygold` | 每日签到 |
| `SevenDayCard` | 七天卡 |
| `RescueFunds` | 救援金 |
| `PopUp` | 弹窗活动 |
| `Pwa` | PWA 安装奖励 |
| `Pdd` | 邀请转盘 |
| `Jackpot` | 奖池投资与提现 |

### 首充活动
| 控制器 | 说明 |
|---|---|
| `FirstDeposit25` | 生涯首充 25 |
| `FirstDeposit270` | 限时首充 270 |
| `FirstDepositDaily` | 每日首充 |

### VIP 专属活动
| 控制器 | 说明 |
|---|---|
| `FirstVip49` | VIP 49 独有充值 |
| `FirstVip6` | VIP 6% 充值 |

### 其他
| 控制器 | 说明 |
|---|---|
| `Leaderboard` | 日/周/月排行榜 |
| `Message` | 站内信 |
| `Banner` | 广告横幅 |
| `Channel` | 渠道信息 |
| `Common` | 公共接口（短信发送等） |
| `Index` | 首页初始化（会员中心） |
| `Notify` | 支付/外部回调 |
| `Sse` | SSE 推送 |
| `Ceshi` | 测试控制器 |

---

## 8. 双钱包系统

- `experience_wallet` (switch_wallet=0) — 体验钱包，存放活动奖励、签到等免费金
- `recharge_wallet` (switch_wallet=1) — 充值钱包，用户真实充值
- `commission_balance` (类型2) — 佣金余额
- `pdd_reward` (类型3) — PDD 活动奖励

可通过渠道配置 `double_wallet_enabled` 关闭双钱包（强制使用充值钱包）。

Base 控制器提供 `getWalletTypeForReward()` 方法判断奖励应入哪个钱包。

---

## 9. 余额变动体系

### 服务层

`app/common/service/AccountService.php`
- `increaseBalance(userId, amount, walletType, logTypeId, note)` — 增加余额
- `decreaseBalance(userId, amount, walletType, logTypeId, note)` — 扣除余额
- 核心: 数据库事务 + `Account::lock(true)` 悲观锁
- 自动写入 `slot_account_coin_log` 流水

### 操作类型枚举

`app/api/enum/CoinLog.php` 定义 44 种类型，主要：

| ID | 常量 | 说明 |
|---|---|---|
| 1 | RegFree | 注册赠送 |
| 2 | Recharge | 充值 |
| 3 | Withdraw | 余额提现 |
| 5 | GameBet | 游戏下注 |
| 6 | GameWin | 游戏赢得 |
| 7 | GameRefund | 游戏返回 |
| 10 | SystemOperation | 系统操作 |
| 11 | InternalMessage | 站内信 |
| 12 | DayGold | 签到 |
| 17 | FirstDeposit270 | 限时首充 |
| 28 | ChestBox | 宝箱奖励 |
| 29-31 | Leaderboard* | 排行榜奖励 |
| 32 | CommissionBet | 投注返佣 |
| 35 | LuckyWheel | 幸运转盘 |
| 38 | CommissionWithdraw | 佣金提取 |
| 42 | JackpotWithdraw | Jackpot 提现 |

---

## 10. 游戏集成

`extend/ba/GameHelper.php` 负责与第三方游戏 API 通信。

流程：
1. 用户请求 `Game::get_url(game_id=xxx)`
2. 检查用户游戏状态（`game_status != 0`）
3. 确保 `player_id` 已注册（异步事件触发）
4. 调用游戏 API `/api/v1/game/launch`，传入 UserID + GameID + Language + switch_wallet
5. 返回第三方游戏 URL，缓存 600 秒

游戏列表：
- 按品牌（brand）分组，热门游戏单独分组
- 缓存 3600 秒（tag: `game_lists`）
- 用户收藏标记合并

---

## 11. 团队代理系统

### 树形结构

`slot_account` 表字段：
- `p_id` — 上级 ID
- `team_path` — 路径，如 `/1/2/`（根路径为 `/`）
- `team_level` — 层级（根为 0）

模型事件：
- `onBeforeInsert`: 自动计算 team_path 和 team_level
- `onAfterUpdate`: p_id 变更时递归重建下级路径

### 返佣

- 下级投注产生佣金 → `slot_team_commission_log`
- 上级 `rebate_rate` 决定返佣比例（0-100%）
- 调整规则：只能调高不能调低，不能超过上级的 rebate_rate
- 提现：佣金 → `commission_balance` 扣除 → `recharge_wallet` 增加（事务）

---

## 12. 通用响应格式

```json
{
  "code": 1,
  "msg": "Success",
  "time": 1715850000,
  "data": {}
}
```

- `code=1` 成功，`code=0` 失败
- `code=2` Token 错误（触发前端重新登录）
- `code=409` Token 过期

通过 `Api::success(msg, data, code)` 和 `Api::error(msg, data, code)` 返回。

---

## 13. 语言与国际化

- 支持语言: `zh-cn`, `en`, `ar`
- 语言决定链: 登录用户的渠道语言 > 访问域名的渠道语言 > 系统默认
- 中间件 `ChannelLang` 在 `LoadLangPack` 之后执行
- 加载控制器专属语言包: `app/api/lang/{langSet}/{controllerPath}.php`

---

## 14. 关键路径速查

```
app/api/
  controller/    39 个控制器
  enum/          枚举（CoinLog）
  validate/      验证器
  lang/          语言包
  common.php     辅助函数（邀请码生成、短信发送）
  middleware.php 中间件注册

app/common/
  controller/    基类（Api, Frontend）
  model/         核心 Model + 子目录（activity/, jackpot/, recharge/, withdraw/ 等）
  service/       22 个服务类
  middleware/    中间件（AllowCrossDomain, ChannelLang）
  event/         事件监听

config/
  buildadmin.php   核心配置（CORS, Token, 登录限制）
  database.php     数据库连接
  route.php        路由配置
  queue.php        队列配置

extend/ba/
  GameHelper.php   游戏 API 调用
  Auth.php         权限库
  Filesystem.php   文件系统
  Module.php       模块管理
```
