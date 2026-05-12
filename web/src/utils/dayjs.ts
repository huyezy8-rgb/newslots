import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import timezone from 'dayjs/plugin/timezone'

// 扩展 dayjs 插件
dayjs.extend(utc)
dayjs.extend(timezone)

// 默认时区
const DEFAULT_TIMEZONE = 'Asia/Shanghai'

// 支持的时区列表
export const SUPPORTED_TIMEZONES = [
    { label: '中国标准时间 (UTC+8)', value: 'Asia/Shanghai' },
    { label: '美东时间 (UTC-5)', value: 'America/New_York' },
    { label: '美西时间 (UTC-8)', value: 'America/Los_Angeles' },
    { label: 'UTC时间 (UTC+0)', value: 'UTC' },
    { label: '欧洲中部时间 (UTC+1)', value: 'Europe/Berlin' },
    { label: '日本标准时间 (UTC+9)', value: 'Asia/Tokyo' },
    { label: '韩国标准时间 (UTC+9)', value: 'Asia/Seoul' },
    { label: '新加坡时间 (UTC+8)', value: 'Asia/Singapore' },
    { label: '澳大利亚东部时间 (UTC+10)', value: 'Australia/Sydney' },
    { label: '英国时间 (UTC+0)', value: 'Europe/London' }
]

/**
 * 获取系统配置的时区
 */
export const getSystemTimezone = (): string => {
    try {
        // 从全局配置中获取时区
        const siteConfig = (window as any).__SITE_CONFIG__

        // 优先使用系统配置的时区（不再校验是否在支持列表内）
        if (siteConfig?.timezone?.system) {
            console.log("系统配置的时区")
            return siteConfig.timezone.system
        }

        // 其次使用当前时区（不再校验是否在支持列表内）
        if (siteConfig?.timezone?.current) {
            console.log("当前时区")
            return siteConfig.timezone.current
        }

        // 最后使用默认时区（不再校验是否在支持列表内）
        if (siteConfig?.timezone?.default) {
            console.log("默认时区")
            return siteConfig.timezone.default
        }
    } catch (error) {
        console.warn('获取系统时区配置失败:', error)
    }
    return DEFAULT_TIMEZONE
}

/**
 * 获取当前时区
 */
export const getCurrentTimezone = (): string => {
    // 优先使用用户设置的时区
    const userTimezone = localStorage.getItem('user_timezone')
    if (userTimezone) {
        console.log("使用用户设置的时区",userTimezone)
        return userTimezone
    }

    // 其次使用系统配置的时区
    const systemTimezone = getSystemTimezone()
    if (systemTimezone) {
        console.log("使用系统配置的时区",systemTimezone)
        return systemTimezone
    }

    // 最后使用浏览器时区
    const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone
    if (browserTimezone) {
        console.log("使用浏览器时区",browserTimezone)
        return browserTimezone
    }

    // 默认时区
    return DEFAULT_TIMEZONE
}

/**
 * 设置全局时区
 */
export const setGlobalTimezone = (timezone: string): void => {
    try {
        if (!timezone) return
        // 设置 dayjs 默认时区（不再限制支持列表）
        dayjs.tz.setDefault(timezone)
        // 可选：保存到 localStorage，便于刷新保持
        localStorage.setItem('user_timezone', timezone)
        if (process.env.NODE_ENV === 'development') {
            console.log(`[Dayjs Timezone] 全局时区已设置为: ${timezone}`)
        }
    } catch (e) {
        console.warn('[Dayjs Timezone] 设置失败，回退默认时区', timezone, e)
        dayjs.tz.setDefault(DEFAULT_TIMEZONE)
    }
}

/**
 * 初始化全局时区
 */
export const initGlobalTimezone = (): void => {
    const currentTimezone = getCurrentTimezone()
    setGlobalTimezone(currentTimezone)
}

/**
 * 格式化时间（使用时区）- 修复版本
 */
export const formatWithTimezone = (date: string | number | Date | dayjs.Dayjs, format = 'YYYY-MM-DD HH:mm:ss', timezone?: string): string => {
    // 处理时间戳转换
    let processedDate = date
    if (typeof date === 'number') {
        // 如果是数字，判断是秒级还是毫秒级时间戳
        if (date.toString().length === 10) {
            processedDate = date * 1000
        }
    }
    try {
        const targetTimezone = timezone || getCurrentTimezone()
        // 新增：如果是字符串且格式为 yyyy-MM-dd HH:mm:ss，按 UTC 解析
        if (typeof processedDate === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(processedDate)) {
            return dayjs.utc(processedDate, 'YYYY-MM-DD HH:mm:ss').tz(targetTimezone).format(format)
        }
        return dayjs(processedDate).tz(targetTimezone).format(format)
    } catch (error) {
        console.warn('dayjs 格式化失败:', error, 'date:', date, 'processedDate:', processedDate)
        return '-'
    }
}

/**
 * 获取当前时区的今天日期
 */
export const getTodayInCurrentTimezone = (): Date => {
    const currentTimezone = getCurrentTimezone()
    // 直接获取当前时区的今天开始时间
    return dayjs().tz(currentTimezone).startOf('day').toDate()
}

/**
 * 获取当前时区的当前时间
 */
export const getCurrentTimeWithTimezone = () => {
    const currentTimezone = getCurrentTimezone()
    return dayjs().tz(currentTimezone)
}

/**
 * 检查 dayjs 时区插件是否可用
 */
export const isTimezonePluginAvailable = (): boolean => {
    return typeof dayjs.tz !== 'undefined'
}

// 开发环境下输出调试信息
if (process.env.NODE_ENV === 'development') {
    console.log(`[Dayjs Timezone] 时区插件可用: ${isTimezonePluginAvailable()}`)
}

/**
 * 配置 Element Plus 时间控件使用 dayjs
 */
export const configureElementPlusDatePicker = (): void => {
    try {
        // 获取当前时区
        const currentTimezone = getCurrentTimezone()

        // 设置 dayjs 默认时区
        dayjs.tz.setDefault(currentTimezone)

        // 配置 Element Plus 的日期选择器
        if (typeof window !== 'undefined' && (window as any).ElementPlus) {
            const { ElDatePicker } = (window as any).ElementPlus

            // 设置默认时区
            if (ElDatePicker && ElDatePicker.props) {
                // 修改默认属性
                const originalProps = ElDatePicker.props

                // 设置默认时区
                if (originalProps.timezone) {
                    originalProps.timezone.default = currentTimezone
                }

                // 设置默认值
                if (originalProps.modelValue) {
                    // 对于日期选择器，设置默认值为当前时区的今天
                    const today = getTodayInCurrentTimezone()
                    originalProps.modelValue.default = today
                }
            }
        }

        // 全局配置 dayjs 为 Element Plus 的日期库
        if (typeof window !== 'undefined') {
            (window as any).__ELEMENT_PLUS_DAYJS__ = dayjs
        }

        console.log(`[Element Plus] 时间控件已配置使用 dayjs，时区: ${currentTimezone}`)
    } catch (error) {
        console.warn('[Element Plus] 配置时间控件失败:', error)
    }
}

/**
 * 获取指定时区的今天日期
 */
export const getTodayInTimezone = (timezone: string): Date => {
    // 直接获取指定时区的今天开始时间
    return dayjs().tz(timezone).startOf('day').toDate()
}

/**
 * 获取指定时区的当前时间
 */
export const getNowInTimezone = (timezone: string): Date => {
    // 直接获取指定时区的当前时间
    return dayjs().tz(timezone).toDate()
}

/**
 * 监听时区变化并更新全局配置
 */
export const watchTimezoneChange = (callback?: (timezone: string) => void): void => {
    // 监听 localStorage 变化
    if (typeof window !== 'undefined') {
        window.addEventListener('storage', (event) => {
            if (event.key === 'user_timezone' && event.newValue) {
                const newTimezone = event.newValue
                setGlobalTimezone(newTimezone)
                configureElementPlusDatePicker()
                callback?.(newTimezone)
            }
        })
    }
}

/**
 * 调试时区信息
 */
export const debugTimezoneInfo = (): void => {
    const currentTimezone = getCurrentTimezone()
    const systemTimezone = getSystemTimezone()
    const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone

    console.log('=== 时区调试信息 ===')
    console.log('当前时区:', currentTimezone)
    console.log('系统时区:', systemTimezone)
    console.log('浏览器时区:', browserTimezone)

    // 获取当前时区的信息
    const currentTzNow = dayjs().tz(currentTimezone)
    const currentTzToday = currentTzNow.startOf('day')

    console.log('当前时区的今天:', currentTzToday.format('YYYY-MM-DD HH:mm:ss'))
    console.log('当前时区的现在:', currentTzNow.format('YYYY-MM-DD HH:mm:ss'))

    // 获取 UTC 信息
    const utcNow = dayjs().utc()
    const utcToday = utcNow.startOf('day')

    console.log('UTC 今天:', utcToday.format('YYYY-MM-DD HH:mm:ss'))
    console.log('UTC 现在:', utcNow.format('YYYY-MM-DD HH:mm:ss'))

    // 获取本地时间信息
    const localNow = dayjs()
    const localToday = localNow.startOf('day')

    console.log('本地今天:', localToday.format('YYYY-MM-DD HH:mm:ss'))
    console.log('本地现在:', localNow.format('YYYY-MM-DD HH:mm:ss'))

    // 测试不同时区的今天
    console.log('美东时区的今天:', dayjs().tz('America/New_York').startOf('day').format('YYYY-MM-DD HH:mm:ss'))
    console.log('美西时区的今天:', dayjs().tz('America/Los_Angeles').startOf('day').format('YYYY-MM-DD HH:mm:ss'))
    console.log('中国时区的今天:', dayjs().tz('Asia/Shanghai').startOf('day').format('YYYY-MM-DD HH:mm:ss'))

    // 时区偏移信息
    console.log('当前时区偏移:', currentTzNow.format('Z'))
    console.log('UTC 偏移:', utcNow.format('Z'))
    console.log('本地偏移:', localNow.format('Z'))

    console.log('==================')
}

/**
 * 验证时区转换是否正确
 */
export const validateTimezoneConversion = (): void => {
    console.log('=== 时区转换验证 ===')

    // 测试美东时间
    const nyNow = dayjs().tz('America/New_York')
    const nyToday = nyNow.startOf('day')
    const utcFromNy = nyNow.utc()

    console.log('美东现在:', nyNow.format('YYYY-MM-DD HH:mm:ss Z'))
    console.log('美东今天:', nyToday.format('YYYY-MM-DD HH:mm:ss Z'))
    console.log('转换为UTC:', utcFromNy.format('YYYY-MM-DD HH:mm:ss Z'))

    // 测试中国时间
    const shNow = dayjs().tz('Asia/Shanghai')
    const shToday = shNow.startOf('day')
    const utcFromSh = shNow.utc()

    console.log('中国现在:', shNow.format('YYYY-MM-DD HH:mm:ss Z'))
    console.log('中国今天:', shToday.format('YYYY-MM-DD HH:mm:ss Z'))
    console.log('转换为UTC:', utcFromSh.format('YYYY-MM-DD HH:mm:ss Z'))

    // 验证时区偏移
    console.log('美东偏移:', nyNow.format('Z'))
    console.log('中国偏移:', shNow.format('Z'))

    console.log('==================')
}
