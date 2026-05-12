import createAxios from '/@/utils/axios'

export function getPayRetentionData(params: {
  start_date?: string;
  end_date?: string;
  channel_id?: number;
}) {
  return createAxios({
    url: '/admin/pay_retention/index',
    method: 'get',
    params
  });
}
