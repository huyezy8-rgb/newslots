import createAxios from '/@/utils/axios'

export const url = '/admin/Dashboard/'

export function index(params?: any) {
    return createAxios({
        url: url + 'index',
        method: 'get',
        params
    })
}
