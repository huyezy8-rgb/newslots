import request from '/@/utils/axios'

// 获取排行榜配置
export function getRankingConfig() {
  return request({
    url: '/admin/activity.ranking_activity/config',
    method: 'get'
  })
}

// 保存排行榜配置
export function saveRankingConfig(data: any) {
  return request({
    url: '/admin/activity.ranking_activity/config',
    method: 'post',
    data
  })
} 