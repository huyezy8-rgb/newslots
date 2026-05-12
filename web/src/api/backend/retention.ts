import createAxios from '/@/utils/axios'

export function getRetentionData(params: {
  start_date?: string;
  end_date?: string;
  days?: number;
  channel_id?: number;
}) {
  return createAxios({
    url: '/admin/retention/index',
    method: 'get',
    params
  });
} 