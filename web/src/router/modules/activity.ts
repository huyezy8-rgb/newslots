import { RouteRecordRaw } from 'vue-router'

const activityRoutes: RouteRecordRaw = {
  path: '/activity',
  name: 'Activity',
  component: () => import('@/layouts/default/index.vue'),
  meta: {
    title: '活动管理',
    icon: 'el-icon-trophy'
  },
  children: [
    {
      path: 'ranking',
      name: 'Ranking',
      component: () => import('@/layouts/default/index.vue'),
      meta: {
        title: '排行榜活动',
        icon: 'el-icon-medal'
      },
      children: [
        {
          path: 'config',
          name: 'RankingConfig',
          component: () => import('@/views/activity/ranking/config.vue'),
          meta: {
            title: '排行榜配置',
            icon: 'el-icon-setting'
          }
        }
      ]
    },
    {
      path: 'lucky-wheel',
      name: 'LuckyWheel',
      component: () => import('@/layouts/default/index.vue'),
      meta: {
        title: '幸运转盘',
        icon: 'el-icon-circle-plus'
      },
      children: [
        {
          path: 'config',
          name: 'LuckyWheelConfig',
          component: () => import('@/views/backend/activity/lucky-wheel/config.vue'),
          meta: {
            title: '配置',
            icon: 'el-icon-setting'
          }
        },
        {
          path: 'turntable1',
          name: 'LuckyWheelTurntable1',
          component: () => import('@/views/backend/activity/lucky-wheel/turntable1.vue'),
          meta: {
            title: '转盘1',
            icon: 'el-icon-circle-plus'
          }
        },
        {
          path: 'turntable2',
          name: 'LuckyWheelTurntable2',
          component: () => import('@/views/backend/activity/lucky-wheel/turntable2.vue'),
          meta: {
            title: '转盘2',
            icon: 'el-icon-circle-plus'
          }
        },
        {
          path: 'turntable3',
          name: 'LuckyWheelTurntable3',
          component: () => import('@/views/backend/activity/lucky-wheel/turntable3.vue'),
          meta: {
            title: '转盘3',
            icon: 'el-icon-circle-plus'
          }
        }
      ]
    }
  ]
}

export default activityRoutes 