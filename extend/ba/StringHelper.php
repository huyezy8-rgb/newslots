<?php

namespace ba;

use DateTime;
use Throwable;
use DateTimeZone;
use DateTimeInterface;

/**
 * 字符串处理
 * @form https://gitee.com/karson/fastadmin/blob/develop/extend/fast/Date.php
 */
class StringHelper
{
    function cleanJsonString($jsonStr) {
        // 先将 HTML 实体转回符号
        $jsonStr = html_entity_decode($jsonStr);

        // 去掉字符串前后的多余双引号（如果有）
        if (substr($jsonStr, 0, 1) === '"' && substr($jsonStr, -1) === '"') {
            $jsonStr = substr($jsonStr, 1, -1);
        }

        return $jsonStr;
    }
}