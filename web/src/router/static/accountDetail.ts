import type { RouteRecordRaw } from 'vue-router'
import { adminBaseRoutePath } from '/@/router/static/adminBase'

const route: RouteRecordRaw = {
    path: adminBaseRoutePath + '/account/account/detail/:id',
    name: 'account-account-detail',
    component: () => import('/@/views/backend/account/account/detail.vue'),
    meta: {
        title: '用户详情',
        auth: true,
    },
}

export default route


