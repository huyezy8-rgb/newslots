<template>
  <el-select v-model="selectedZone" placeholder="选择时区" @change="handleTimezoneChange" style="width: 240px">
    <el-option v-for="tz in timezones" :key="tz" :label="getTimezoneLabel(tz)" :value="tz" />
  </el-select>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import timezone from 'dayjs/plugin/timezone'
import { SUPPORTED_TIMEZONES, getCurrentTimezone, setGlobalTimezone, getTodayInTimezone } from '/@/utils/dayjs'
import { updateElementPlusTimezone } from '/@/utils/element-plus-config'

// 扩展 dayjs 插件
dayjs.extend(utc)
dayjs.extend(timezone)

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

const selectedZone = ref(getCurrentTimezone()) // 默认使用系统配置的时区

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

// 监听时区切换
watch(selectedZone, (zone) => {
  // 设置全局时区
  setGlobalTimezone(zone)
  
  // 更新 Element Plus 配置
  updateElementPlusTimezone(zone)
  
  // 触发全局事件，通知其他组件时区已变化
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('timezone-changed', { 
      detail: { 
        timezone: zone,
        today: getTodayInTimezone(zone)
      } 
    }))
  }
  
  ElMessage.success(`时区已切换到: ${getTimezoneLabel(zone)}`)
})

const handleTimezoneChange = (timezone: string) => {
  selectedZone.value = timezone
}

onMounted(() => {
  // 初始化时区
  selectedZone.value = getCurrentTimezone()
  
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