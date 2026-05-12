# 渠道语言控制说明文档

## 概述

系统支持通过后台渠道管理设置的语言来控制客户端（API接口）返回的多语言文案。

## 实现原理

### 1. 后端语言切换机制

#### 1.1 已登录用户
在 `app/api/controller/Base.php` 中，当用户登录后：
- 通过关联查询获取用户的渠道信息（包含 `lang` 字段）
- 如果渠道设置了语言，且该语言在允许的语言列表中，则自动切换语言
- 使用 `$this->app->lang->switchLangSet($channelLang)` 切换语言

```php
// 根据渠道语言设置切换语言
if (isset($userInfo->channel) && !empty($userInfo->channel['lang'])) {
    $channelLang = $userInfo->channel['lang'];
    $allowLangList = \think\facade\Config::get('lang.allow_lang_list', ['zh-cn', 'en', 'ar']);
    if (in_array($channelLang, $allowLangList)) {
        $this->app->lang->switchLangSet($channelLang);
    }
}
```

#### 1.2 未登录用户
对于未登录用户，系统会：
- 尝试通过 HTTP_REFERER 或 HTTP_HOST 获取域名
- 根据域名查询对应的渠道信息
- 如果渠道设置了语言，则自动切换语言

```php
private function setLangByDomain(): void
{
    // 从 referer 或 host 获取域名
    // 查询渠道信息
    // 根据渠道的 lang 字段切换语言
}
```

### 2. 语言包加载

在 `app/common/controller/Api.php` 的 `initialize()` 方法中：
- 使用 `$this->app->lang->getLangSet()` 获取当前语言设置
- 加载对应语言包文件：`app/api/lang/{lang}/{controller}.php`

### 3. 前端获取渠道语言

前端可以通过以下接口获取渠道信息（包含语言设置）：

#### 3.1 已登录用户
调用 `/api/channel/info` 接口，返回的渠道信息中包含 `lang` 字段。

#### 3.2 未登录用户
可以通过域名匹配或调用 `/api/channel/getPixelIdByName` 接口获取渠道信息。

## 使用流程

### 后台设置
1. 进入后台 -> 渠道管理
2. 编辑或创建渠道
3. 在"语言"字段中选择语言（zh-cn / en / ar）
4. 保存

### 客户端效果
1. **已登录用户**：
   - 用户登录后，系统自动根据用户所属渠道的语言设置切换语言
   - 所有 API 接口返回的文案都会使用渠道设置的语言

2. **未登录用户**：
   - 系统根据访问域名匹配渠道
   - 如果匹配到渠道，则使用渠道设置的语言
   - 如果未匹配到，则使用系统默认语言

## 语言优先级

语言设置的优先级（从高到低）：
1. **渠道设置的语言**（如果用户已登录或通过域名匹配到渠道）
2. **请求参数中的语言**（通过 `?lang=xx` 参数）
3. **Header 中的语言**（通过 `think-lang` header）
4. **Cookie 中的语言**（通过 `think_lang` cookie）
5. **系统默认语言**（config/lang.php 中的 default_lang）

## 注意事项

1. **语言包文件**：确保对应语言的语言包文件已创建
   - 中文：`app/api/lang/zh-cn/`
   - 英文：`app/api/lang/en/`
   - 阿拉伯语：`app/api/lang/ar/`

2. **允许的语言列表**：在 `config/lang.php` 中配置 `allow_lang_list`，只有列表中的语言才会生效

3. **域名匹配**：未登录用户的语言切换依赖域名匹配，确保渠道的 `domain` 字段正确设置

4. **语言切换时机**：语言切换在 `Base.php` 的构造函数中执行，确保在控制器方法执行前完成

## 测试方法

1. 在后台设置渠道A的语言为 `en`
2. 使用渠道A的用户登录
3. 调用任意 API 接口
4. 检查返回的 `msg` 字段是否为英文

## 相关文件

- `app/api/controller/Base.php` - 语言切换逻辑
- `app/common/controller/Api.php` - 语言包加载
- `app/api/controller/Channel.php` - 渠道信息接口
- `config/lang.php` - 语言配置
- `app/api/lang/{lang}/` - 语言包目录

