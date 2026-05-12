import { App } from 'vue'
import { getCurrentTimezone, getTodayInCurrentTimezone, getCurrentTimeWithTimezone } from './dayjs'

/**
 * 配置 Element Plus 全局设置
 */
export const configureElementPlus = (app: App): void => {
  const currentTimezone = getCurrentTimezone()
  
  // 配置全局属性
  app.config.globalProperties.$ELEMENT = {
    // 设置默认时区
    timezone: currentTimezone,
    
    // 设置日期选择器默认值
    datePicker: {
      defaultDate: getTodayInCurrentTimezone(),
      defaultTime: getCurrentTimeWithTimezone(),
    }
  }
  
  // 配置 Element Plus 组件默认属性
  const elementPlusConfig = {
    // 日期选择器配置
    datePicker: {
      timezone: currentTimezone,
      defaultDate: getTodayInCurrentTimezone(),
      defaultTime: getCurrentTimeWithTimezone(),
    },
    
    // 时间选择器配置
    timePicker: {
      timezone: currentTimezone,
    }
  }
  
  // 将配置挂载到全局
  if (typeof window !== 'undefined') {
    (window as any).__ELEMENT_PLUS_CONFIG__ = elementPlusConfig
  }
  
  console.log('[Element Plus] 全局配置已应用，时区:', currentTimezone)
}

/**
 * 获取 Element Plus 配置
 */
export const getElementPlusConfig = () => {
  if (typeof window !== 'undefined') {
    return (window as any).__ELEMENT_PLUS_CONFIG__ || {}
  }
  return {}
}

/**
 * 更新 Element Plus 时区配置
 */
export const updateElementPlusTimezone = (timezone: string): void => {
  if (typeof window !== 'undefined' && (window as any).__ELEMENT_PLUS_CONFIG__) {
    const config = (window as any).__ELEMENT_PLUS_CONFIG__
    config.datePicker.timezone = timezone
    config.timePicker.timezone = timezone
    
    console.log('[Element Plus] 时区配置已更新:', timezone)
  }
} 