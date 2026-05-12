import { baTableApi } from '/@/api/common'

const api = new baTableApi('/admin/activity.seven_day_card/')

/**
 * 获取七天卡配置
 */
export const getSevenDayCardConfig = () => {
    return api.edit({})
}

/**
 * 保存七天卡配置
 */
export const saveSevenDayCardConfig = (data: any) => {
    return api.postData('edit', data)
}
