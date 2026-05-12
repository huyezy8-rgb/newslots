import createAxios from '/@/utils/axios'

/**
 * 获取时区信息
 */
export const getTimezoneInfo = () => {
    return createAxios({
        url: '/admin/timezone/info',
        method: 'get'
    })
}

/**
 * 测试时区格式化
 */
export const testTimezoneFormat = (params: {
    timestamp: number
    timezone?: string
    format?: string
}) => {
    return createAxios({
        url: '/admin/timezone/test',
        method: 'get',
        params
    })
}

/**
 * 获取时区列表
 */
export const getTimezoneList = () => {
    return createAxios({
        url: '/admin/timezone/list',
        method: 'get'
    })
} 