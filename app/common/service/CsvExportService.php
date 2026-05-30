<?php

namespace app\common\service;

use think\Response;
use think\exception\HttpResponseException;

class CsvExportService
{
    public static function download(string $filename, array $headers, array $rows): void
    {
        if (empty($rows)) {
            throw new \InvalidArgumentException('无数据');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_values($headers));

        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($headers) as $field) {
                $line[] = self::formatValue(self::getValue($row, (string)$field));
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $encodedFilename = rawurlencode($filename);
        $response = Response::create($content, 'html', 200)
            ->header([
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"; filename*=UTF-8''{$encodedFilename}",
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);

        throw new HttpResponseException($response);
    }

    private static function getValue(array|object $row, string $field): mixed
    {
        $data = is_object($row) ? $row->toArray() : $row;

        if (array_key_exists($field, $data)) {
            return $data[$field];
        }

        if (str_contains($field, '.')) {
            $value = $data;
            foreach (explode('.', $field) as $part) {
                if (is_array($value) && array_key_exists($part, $value)) {
                    $value = $value[$part];
                } else {
                    return '';
                }
            }
            return $value;
        }

        return '';
    }

    private static function formatValue(mixed $value): string|int|float
    {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
