<?php
namespace app\middleware;

use think\Request;
use think\facade\Db;
use Closure;

class ApiLogToDb
{
    public function handle(Request $request, Closure $next)
    {
        // 路由过滤：不记录包含以下关键字的接口
        $ignoreKeywords = [
            'sse/message',
            'sse/userinfo',
            'Cash/Get',
            'Cash/TransferInOut',
        ];
        foreach ($ignoreKeywords as $keyword) {
            if (strpos($request->pathinfo(), $keyword) !== false) {
                return $next($request);
            }
        }
        // 过滤 method 为 OPTIONS 和 GET 的请求
        if (in_array(strtoupper($request->method()), ['OPTIONS'])) {
            return $next($request);
        }
        // 只记录api模块（ThinkPHP8无module方法，判断pathinfo前缀）
        if (strpos($request->pathinfo(), 'api/') !== 0) {
            return $next($request);
        }
        $startTime = microtime(true);
        $response = $next($request);
        $endTime = microtime(true);
        $cost = intval(($endTime - $startTime) * 1000);
        try {
            Db::name('api_log')->insert([
                'route'      => $request->pathinfo(),
                'uri'        => $request->url(true),
                'method'     => $request->method(),
                'ip'         => $request->ip(),
                'user_agent' => $request->header('User-Agent', ''),
                'params'     => json_encode($request->param(), JSON_UNESCAPED_UNICODE),
                'response'   => $response->getContent(),
                'header'     => json_encode($request->header(), JSON_UNESCAPED_UNICODE),
                'status'     => $response->getCode(),
                'cost_ms'    => $cost,
                'create_time'=> date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            \think\facade\Log::error('API日志写入失败：' . $e->getMessage());
        }
        return $response;
    }
} 