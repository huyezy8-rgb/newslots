# Banner图接口文档

## 概述

基于 `slot_banner` 表的Banner图管理接口，提供前端展示所需的Banner列表与点击统计功能（详情接口已移除，列表返回完整字段）。

## 数据库表结构

```sql
CREATE TABLE `slot_banner` (
  `id` int(11u) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Banner标题',
  `content` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '内容',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Banner图片路径',
  `link` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '跳转链接',
  `jump_type` int(1) NOT NULL DEFAULT '0' COMMENT '跳转类型：0=活动, 1=外部链接',
  `channel_ids` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '展示渠道',
  `activity` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '活动标识',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序权重',
  `status` int(1) NOT NULL DEFAULT '1' COMMENT '状态：0=禁用，1=启用',
  `start_time` int(11) DEFAULT NULL COMMENT '开始时间',
  `end_time` int(11) DEFAULT NULL COMMENT '结束时间',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Banner图管理表';
```

## API接口

### 1. 获取Banner列表（支持渠道）

**接口地址:** `GET /api/banner/index`  
**权限要求:** 无需登录  
**功能说明:** 获取当前渠道下有效的Banner列表。渠道识别顺序：`channel_name` 参数 > Referer域名 > 渠道表首条记录；过滤依据为 `channel_ids`（空表示全渠道）。

#### 请求参数（可选）
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| channel_name | string | 否 | 渠道名，对应 `slot_channel_list.name` |

#### 响应数据
```json
{
  "code": 1,
  "msg": "Banner list retrieved successfully",
  "data": {
    "list": [
      {
        "id": 1,
        "title": "活动Banner",
        "content": "双十一大促销",
        "image": "https://domain.com/uploads/banner/banner1.jpg",
        "link": "/activity/double11",
        "jump_type": 0,
        "activity": "double11",
        "sort": 100,
        "status": 1,
        "start_time": 0,
        "end_time": 0,
        "remark": "",
        "create_time": 0,
        "update_time": 0
      }
    ],
    "total": 1,
    "channel": {
      "id": 1,
      "name": "default",
      "domain": "example.com"
    }
  }
}
```

#### 字段说明（list项）
- `id`: Banner ID
- `title`: Banner标题
- `content`: Banner内容描述
- `image`: Banner图片完整URL
- `link`: 跳转链接
- `jump_type`: 跳转类型（0=活动, 1=外部链接）
- `activity`: 活动标识（jump_type=0时有效）
- `sort`: 排序权重（数值越大越靠前）
- `status`: 状态（0=禁用, 1=启用）
- `start_time`/`end_time`: 生效时间范围（0或null表示不限制）
- `remark`、`create_time`、`update_time`: 备注与时间戳

响应中还包含：
- `total`: 列表条数
- `channel`: 实际命中的渠道信息（可能为null）

#### 数据过滤规则
- 只返回 `status = 1`（启用）的Banner
- 过滤时间范围：当前时间在 `start_time` 和 `end_time` 之间
- 按 `sort` 降序、`id` 降序排列
- 缓存5分钟
 - 渠道过滤：`channel_ids` 为空=全渠道；否则通过 `FIND_IN_SET(channel_id, channel_ids)` 命中当前渠道

（详情接口已移除，所有必要字段均在列表返回。）

### 2. Banner点击统计

**接口地址:** `POST /api/banner/click`  
**权限要求:** 无需登录  
**功能说明:** 记录Banner点击统计（可选功能）

#### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | Banner ID |

#### 请求示例
```json
POST /api/banner/click
Content-Type: application/json

{
  "id": 1
}
```

#### 响应数据
```json
{
  "code": 1,
  "msg": "Click recorded successfully"
}
```

## 技术实现特性

### 1. 缓存机制
- Banner列表使用Redis缓存，缓存时间5分钟
- 缓存键：`api_banner_list_{channelId}`（无渠道时为 `api_banner_list_all`）
- 减少数据库查询，提高响应速度

### 2. 图片URL处理
- 自动将相对路径转换为完整URL
- 支持已有完整URL的图片
- 统一前端图片显示格式

### 3. 时间过滤
- 智能处理开始时间和结束时间
- 支持null值（表示不限制）
- 确保只返回当前时间有效的Banner

### 4. 数据安全
- 参数类型转换和验证
- 返回前端所需字段

## 前端使用示例

### Vue.js 示例
```javascript
// 获取Banner列表
async getBannerList() {
  try {
    const response = await this.$http.get('/api/banner/index', {
      params: {
        // 可选：指定渠道名
        // channel_name: 'my_channel'
      }
    });
    if (response.data.code === 1) {
      this.banners = response.data.data.list || [];
    }
  } catch (error) {
    console.error('获取Banner列表失败:', error);
  }
}

// 处理Banner点击
async handleBannerClick(banner) {
  try {
    await this.$http.post('/api/banner/click', { id: banner.id });
  } catch (error) {
    console.error('记录点击失败:', error);
  }
  if (banner.jump_type === 0) {
    this.$router.push(`/activity/${banner.activity}`);
  } else {
    window.open(banner.link, '_blank');
  }
}
```

### 轮播图组件示例
```vue
<template>
  <div class="banner-carousel">
    <swiper :options="swiperOptions">
      <swiper-slide v-for="banner in banners" :key="banner.id">
        <div class="banner-item" @click="handleBannerClick(banner)">
          <img :src="banner.image" :alt="banner.title" />
          <div class="banner-content">
            <h3>{{ banner.title }}</h3>
            <p>{{ banner.content }}</p>
          </div>
        </div>
      </swiper-slide>
    </swiper>
  </div>
</template>

<script>
export default {
  data() {
    return {
      banners: [],
      swiperOptions: { autoplay: true, loop: true, pagination: { el: '.swiper-pagination' } }
    }
  },
  mounted() { this.getBannerList(); },
  methods: { getBannerList() {}, handleBannerClick(banner) {} }
}
</script>
```

## 管理后台说明

由于用户要求不需要增加后台页面，Banner的管理可以通过以下方式：

1. 直接数据库操作
2. 现有管理系统的通用内容管理
3. 后续按需补充管理接口

## 部署注意事项

1. 图片存储：确保图片路径正确，建议使用CDN
2. 缓存配置：确保Redis缓存服务正常运行
3. 时间同步：服务器时间与数据库时间保持一致
4. 权限设置：根据需要调整接口的登录权限要求

## 扩展功能建议

1. 点击统计与报表
2. A/B测试
3. 地理位置定向
4. 用户群体定向
5. 实时更新

