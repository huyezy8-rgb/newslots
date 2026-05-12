import { defineStore } from 'pinia'
import type { RouteRecordRaw } from 'vue-router'
import type { SiteConfig } from '/@/stores/interface'

export const useSiteConfig = defineStore('siteConfig', {
    state: (): SiteConfig => {
        return {
            siteName: '',
            version: '',
            cdnUrl: '',
            apiUrl: '',
            upload: {
                mode: 'local',
            },
            headNav: [],
            recordNumber: '',
            cdnUrlParams: '',
            timezone: {
                default: 'Asia/Shanghai',
                system: 'Asia/Shanghai',
                current: 'Asia/Shanghai',
            },
            initialize: false,
            userInitialize: false,
        }
    },
    actions: {
        dataFill(state: SiteConfig) {
            // 使用 this.$patch(state) 时 headNav 的类型异常，直接赋值
            this.$state = state
            
            // 将时区配置存储到全局变量，供 dayjs 使用
            if (typeof window !== 'undefined') {
                (window as any).__SITE_CONFIG__ = state
                
                // 站点配置加载完成后，重新设置时区
                this.updateTimezoneAfterConfig()
            }
        },
        
        updateTimezoneAfterConfig() {
            // 动态导入时区相关函数，避免循环依赖
            import('/@/utils/dayjs').then(({ setGlobalTimezone, getSystemTimezone }) => {
                const newTimezone = getSystemTimezone()
                
                // 重新设置全局时区
                setGlobalTimezone(newTimezone)
                
                // 重新配置Element Plus
                import('/@/utils/element-plus-config').then(({ updateElementPlusTimezone }) => {
                    updateElementPlusTimezone(newTimezone)
                })
            })
        },
        setHeadNav(headNav: RouteRecordRaw[]) {
            this.headNav = headNav
        },
        setInitialize(initialize: boolean) {
            this.initialize = initialize
        },
        setUserInitialize(userInitialize: boolean) {
            this.userInitialize = userInitialize
        },
    },
})
