<?php
return [
    \app\common\middleware\AllowCrossDomain::class,
    \think\middleware\LoadLangPack::class,
    \app\common\middleware\ChannelLang::class, // 根据渠道设置语言，需要在 LoadLangPack 之后执行
];
