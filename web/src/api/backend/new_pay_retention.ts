import createAxios from '/@/utils/axios'

export function getNewPayRetentionData(params: {
    start_date?: string
    end_date?: string
    channel_id?: number | string
    page?: number
    limit?: number
}) {
    return createAxios({
        url: '/admin/new_pay_retention/index',
        method: 'get',
        params,
    })
}
