import createAxios from '/@/utils/axios'

export const url = '/admin/account.AccountCoinLog/'

export function index(params: anyObj) {
    return createAxios({
        url: url + 'index',
        method: 'get',
        params: params,
    })
}

export function getLogTypes() {
    return createAxios({
        url: url + 'getLogTypes',
        method: 'get',
    })
} 