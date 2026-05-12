import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { loadLang } from '/@/lang/index'
import { registerIcons } from '/@/utils/common'
import ElementPlus from 'element-plus'
import mitt from 'mitt'
import pinia from '/@/stores/index'
import { directives } from '/@/utils/directives'
import { initGlobalTimezone, configureElementPlusDatePicker } from '/@/utils/dayjs'
import { configureElementPlus } from '/@/utils/element-plus-config'
import 'element-plus/dist/index.css'
import 'element-plus/theme-chalk/display.css'
import 'font-awesome/css/font-awesome.min.css'
import '/@/styles/index.scss'
// modules import mark, Please do not remove.

async function start() {
    const app = createApp(App)
    app.use(pinia)

    // 全局语言包加载
    await loadLang(app)

    app.use(router)
    app.use(ElementPlus)

    // 全局注册
    directives(app) // 指令
    registerIcons(app) // icons

    // 初始化全局时区
    initGlobalTimezone()
    
    // 配置 Element Plus 时间控件使用 dayjs
    configureElementPlusDatePicker()
    
    // 配置 Element Plus 全局设置
    configureElementPlus(app)

    app.mount('#app')

    // modules start mark, Please do not remove.

    app.config.globalProperties.eventBus = mitt()
}
start()
