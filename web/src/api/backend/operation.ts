import createAxios from '/@/utils/axios'

export const url = '/admin/Operation/'

/**
 * 获取运营数据
 */
export function index(params?: any) {
    return createAxios({
        url: url + 'index',
        method: 'get',
        params,
    })
}

/**
 * 获取历史数据趋势
 * TODO: 实现历史数据趋势图表功能
 */
export function trend(params?: any) {
    return createAxios({
        url: url + 'trend',
        method: 'get',
        params,
    })
}

/**
 * 获取渠道列表
 */
export function getChannels() {
    return createAxios({
        url: url + 'getChannels',
        method: 'get',
    })
}

/**
 * 获取渠道对比数据
 * TODO: 实现渠道对比分析功能
 */
export function channelCompare(params?: any) {
    return createAxios({
        url: url + 'channelCompare',
        method: 'get',
        params,
    })
} 