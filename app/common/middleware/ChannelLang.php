<?php

namespace app\common\middleware;

use Closure;
use think\Request;
use think\Response;
use think\facade\Lang;
use think\facade\Config;
use app\common\model\Account;
use app\common\model\ChannelList;
use app\common\service\ChannelResolver;

/**
 * 根据渠道设置语言
 * 此中间件应在 LoadLangPack 之后执行
 */
class ChannelLang
{
    /**
     * 处理请求
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $channelLang = $this->getChannelLang($request);
        
        if ($channelLang) {
            $allowLangList = Config::get('lang.allow_lang_list', ['zh-cn', 'en', 'ar']);
            if (in_array($channelLang, $allowLangList)) {
                // 切换语言（这会重新加载语言包）
                Lang::switchLangSet($channelLang);
            }
        }

        return $next($request);
    }

    /**
     * 获取渠道语言
     * @param Request $request
     * @return string|null
     */
    private function getChannelLang(Request $request): ?string
    {
        // 1. 优先从已登录用户获取渠道语言
        $token = $this->getToken($request);
        if ($token) {
            try {
                $userInfo = Account::with(['channel' => function($query) {
                    $query->withoutField('create_time,update_time');
                }])->where('token', $token)->find();
                
                if ($userInfo && isset($userInfo->channel) && !empty($userInfo->channel['lang'])) {
                    return $userInfo->channel['lang'];
                }
            } catch (\Exception $e) {
                // 静默失败，继续尝试其他方式
            }
        }

        // 2. 通过域名获取渠道语言
        try {
            $channelInfo = ChannelResolver::resolveByRequestDomain($request);
            if ($channelInfo && !empty($channelInfo['lang'])) {
                return $channelInfo['lang'];
            }
        } catch (\Exception $e) {
            // Ignore and continue to the legacy lookup below.
        }

        $domain = $this->getDomain($request);
        if ($domain) {
            try {
                $channelInfo = ChannelList::withoutField('create_time,update_time')
                    ->where('domain', $domain)
                    ->find();
                
                if ($channelInfo && !empty($channelInfo['lang'])) {
                    return $channelInfo['lang'];
                }
            } catch (\Exception $e) {
                // 静默失败
            }
        }

        return null;
    }

    /**
     * 获取 Token
     * @param Request $request
     * @return string|null
     */
    private function getToken(Request $request): ?string
    {
        // 使用与 get_auth_token 函数相同的逻辑
        $names = ['token'];
        $separators = [
            'header' => ['', '-'], // token、token
            'param'  => ['', '-', '_'], // token、token、token
            'server' => ['_'], // http_token
        ];

        $tokens = [];
        foreach ($separators as $fun => $sps) {
            foreach ($sps as $sp) {
                $key = ($fun == 'server' ? 'http_' : '') . implode($sp, $names);
                $token = $request->$fun($key);
                if ($token) {
                    $tokens[] = $token;
                }
            }
        }
        
        return !empty($tokens) ? $tokens[0] : null;
    }

    /**
     * 获取域名
     * @param Request $request
     * @return string|null
     */
    private function getDomain(Request $request): ?string
    {
        // 优先从 referer 获取
        $referer = $request->server('HTTP_REFERER');
        if ($referer) {
            $referer_host = parse_url($referer, PHP_URL_HOST);
            if ($referer_host) {
                return $referer_host;
            }
        }
        
        // 其次从 host 获取
        $host = $request->host(true);
        if ($host) {
            return $host;
        }
        
        return null;
    }
}
