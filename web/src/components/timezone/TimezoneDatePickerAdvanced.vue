<template>
  <div class="timezone-date-picker-advanced">
    <el-select v-model="selectedZone" placeholder="选择时区" style="width: 240px; margin-bottom: 10px">
      <el-option v-for="tz in timezones" :key="tz" :label="getTimezoneLabel(tz)" :value="tz" />
    </el-select>

    <el-date-picker
      v-model="date"
      :type="type"
      :placeholder="placeholder"
      :default-value="date"
      :timezone="selectedZone"
      :picker-options="pickerOptions"
      v-bind="$attrs"
      @change="handleChange"
      @update:model-value="handleUpdate"
    />
    
    <div class="timezone-info" style="margin-top: 10px; font-size: 12px; color: #666;">
      <p>当前时区: {{ getTimezoneLabel(selectedZone) }}</p>
      <p>时区今天: {{ formatTodayInTimezone(selectedZone) }}</p>
      <p>时区现在: {{ formatNowInTimezone(selectedZone) }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import timezone from 'dayjs/plugin/timezone'
import { getCurrentTimezone, getNowInTimezone } from '/@/utils/dayjs'

// 扩展 dayjs 插件
dayjs.extend(utc)
dayjs.extend(timezone)

interface Props {
  modelValue?: Date | string | number | null
  type?: 'date' | 'datetime' | 'daterange' | 'datetimerange'
  placeholder?: string
  timezone?: string
  disableFuture?: boolean // 是否禁用未来日期
}

const props = withDefaults(defineProps<Props>(), {
  type: 'date',
  placeholder: '选择日期',
  timezone: undefined,
  disableFuture: true
})

const emit = defineEmits<{
  'update:modelValue': [value: Date | string | number | null]
  'change': [value: Date | string | number | null]
}>()

// 可切换的时区列表
const timezones = [
  'Asia/Shanghai',
  'Asia/Tokyo', 
  'Europe/London',
  'America/New_York',
  'America/Los_Angeles',
  'UTC',
  'Europe/Berlin',
  'Asia/Seoul',
  'Asia/Singapore',
  'Australia/Sydney'
]

// 获取指定时区的今天
const getTodayInTimezone = (timezone: string): Date => {
  return dayjs().tz(timezone).startOf('day').toDate()
}

const selectedZone = ref(props.timezone || getCurrentTimezone()) // 默认使用系统配置的时区
const date = ref<Date | null>(props.modelValue ? new Date(props.modelValue) : getTodayInTimezone(selectedZone.value))

// 获取时区标签
const getTimezoneLabel = (tz: string): string => {
  const timezoneMap: Record<string, string> = {
    'Asia/Shanghai': '中国标准时间 (UTC+8)',
    'Asia/Tokyo': '日本标准时间 (UTC+9)',
    'Europe/London': '英国时间 (UTC+0)',
    'America/New_York': '美东时间 (UTC-5)',
    'America/Los_Angeles': '美西时间 (UTC-8)',
    'UTC': 'UTC时间 (UTC+0)',
    'Europe/Berlin': '欧洲中部时间 (UTC+1)',
    'Asia/Seoul': '韩国标准时间 (UTC+9)',
    'Asia/Singapore': '新加坡时间 (UTC+8)',
    'Australia/Sydney': '澳大利亚东部时间 (UTC+10)'
  }
  return timezoneMap[tz] || tz
}

// 格式化时区今天
const formatTodayInTimezone = (timezone: string): string => {
  return dayjs().tz(timezone).startOf('day').format('YYYY-MM-DD HH:mm:ss')
}

// 格式化时区现在
const formatNowInTimezone = (timezone: string): string => {
  return dayjs().tz(timezone).format('YYYY-MM-DD HH:mm:ss')
}

// 计算 picker-options，实现禁用未来日期的功能
const pickerOptions = computed(() => {
  if (!props.disableFuture) {
    return {}
  }
  
  // 获取当前时区的今天结束时间（23:59:59）
  const todayEnd = dayjs().tz(selectedZone.value).endOf('day').toDate()
  
  return {
    disabledDate: (time: Date) => {
      // 禁用超过今天的日期
      return time.getTime() > todayEnd.getTime()
    }
  }
})

// 监听时区切换，自动更新 date 为该时区下的今日
watch(selectedZone, (zone) => {
  if (!date.value) {
    // 设置为该时区的今天
    date.value = getTodayInTimezone(zone)
  }
})

// 监听 modelValue 变化
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    date.value = new Date(newValue)
  }
}, { immediate: true })

// 处理值变化
const handleChange = (value: Date | string | number | null) => {
  emit('change', value)
}

// 处理值更新
const handleUpdate = (value: Date | string | number | null) => {
  date.value = value ? new Date(value) : null
  emit('update:modelValue', value)
}

onMounted(() => {
  // 初始化时区
  if (!props.timezone) {
    // 从 localStorage 获取用户设置的时区
    const userTimezone = localStorage.getItem('user_timezone')
    if (userTimezone && timezones.includes(userTimezone)) {
      selectedZone.value = userTimezone
    }
  }
  
  // 监听全局时区变化事件
  if (typeof window !== 'undefined') {
    window.addEventListener('timezone-changed', (event: any) => {
      if (event.detail?.timezone && event.detail.timezone !== selectedZone.value) {
        selectedZone.value = event.detail.timezone
      }
    })
  }
})
</script>

<style scoped>
.timezone-date-picker-advanced {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.timezone-info {
  background: #f5f5f5;
  padding: 8px;
  border-radius: 4px;
  border-left: 3px solid #409eff;
}

.timezone-info p {
  margin: 2px 0;
}
</style> 