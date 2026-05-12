import createAxios from '/@/utils/axios'

export function getLtvData(params: {
  start_date?: string;
  end_date?: string;
  channel_id?: number;
}) {
  return createAxios({
    url: '/admin/ltv/index',
    method: 'get',
    params
  });
} 