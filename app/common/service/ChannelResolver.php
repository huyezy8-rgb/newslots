<?php

namespace app\common\service;

use app\common\model\ChannelList;
use think\Request;

class ChannelResolver
{
    public static function resolve(?string $channelName = null, ?Request $request = null): ?ChannelList
    {
        $channelName = trim((string)$channelName);

        if ($channelName !== '') {
            $channel = ChannelList::withoutField('create_time,update_time')
                ->where(['name' => $channelName])
                ->find();

            if ($channel) {
                return $channel;
            }
        }

        if (!$request) {
            return null;
        }

        return self::resolveByRequestDomain($request);
    }

    public static function resolveByRequestDomain(Request $request): ?ChannelList
    {
        $domains = self::getRequestDomains($request);
        if (!$domains) {
            return null;
        }

        $channels = ChannelList::withoutField('create_time,update_time')
            ->whereNotNull('domain')
            ->where('domain', '<>', '')
            ->select();

        foreach ($channels as $channel) {
            foreach (self::splitConfiguredDomains((string)$channel['domain']) as $configuredDomain) {
                if (in_array($configuredDomain, $domains, true)) {
                    return $channel;
                }
            }
        }

        return null;
    }

    public static function getRequestDomains(Request $request): array
    {
        $values = [
            $request->server('HTTP_REFERER', ''),
            $request->header('referer', ''),
            $request->server('HTTP_ORIGIN', ''),
            $request->header('origin', ''),
            $request->server('HTTP_X_FORWARDED_HOST', ''),
            $request->header('x-forwarded-host', ''),
            $request->server('HTTP_HOST', ''),
            $request->host(true),
            $request->host(false),
        ];

        $domains = [];
        foreach ($values as $value) {
            foreach (explode(',', (string)$value) as $item) {
                $domain = self::normalizeDomain($item);
                if ($domain !== '') {
                    $domains[] = $domain;
                }
            }
        }

        return array_values(array_unique($domains));
    }

    private static function splitConfiguredDomains(string $value): array
    {
        $parts = preg_split('/[\s,;]+/', $value) ?: [];
        $domains = [];

        foreach ($parts as $part) {
            $domain = self::normalizeDomain($part);
            if ($domain !== '') {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    private static function normalizeDomain(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        if (!str_contains($value, '://')) {
            $value = preg_replace('/[\/?#].*$/', '', $value) ?: $value;
            $value = '//' . $value;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (!$host) {
            return '';
        }

        return preg_replace('/^www\./', '', strtolower(trim($host))) ?: '';
    }
}
