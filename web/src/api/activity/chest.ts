import request from '/@/utils/axios'

// 获取宝箱活动配置
export function getChestConfig() {
  return request({
    url: '/admin/activity.chest/config',
    method: 'get'
  })
}

// 保存宝箱活动配置
export function saveChestConfig(data: any) {
  return request({
    url: '/admin/activity.chest/config',
    method: 'post',
    data
  })
} 