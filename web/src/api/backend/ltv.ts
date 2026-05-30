import createAxios from '/@/utils/axios'

export function getLtvData(params: { start_date?: string; end_date?: string; channel_id?: number | string; type?: 'first_pay' }) {
    return createAxios({
        url: '/admin/ltv/index',
        method: 'get',
        params,
    })
}
