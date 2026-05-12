<template>
  <el-date-picker
    v-model="dateValue"
    v-bind="$attrs"
    :timezone="currentTimezone"
    :default-value="defaultDate"
    @change="handleChange"
    @update:model-value="handleUpdate"
  />
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { ElDatePicker } from 'element-plus'
import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import timezone from 'dayjs/plugin/timezone'
import { getCurrentTimezone, getTodayInTimezone, getNowInTimezone } from '/@/utils/dayjs'

// 扩展 dayjs 插件
dayjs.extend(utc)
dayjs.extend(timezone)

interface Props {
  modelValue?: Date | string | number | null
  type?: 'date' | 'datetime' | 'daterange' | 'datetimerange'
  defaultDate?: Date
  timezone?: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'date',
  defaultDate: undefined,
  timezone: undefined
})

const emit = defineEmits<{
  'update:modelValue': [value: Date | string | number | null]
  'change': [value: Date | string | number | null]
}>()

// 当前时区
const currentTimezone = ref(props.timezone || getCurrentTimezone())

// 默认日期
const defaultDate = computed(() => {
  if (props.defaultDate) {
    return props.defaultDate
  }
  
  // 根据类型设置默认值
  switch (props.type) {
    case 'date':
      return getTodayInTimezone(currentTimezone.value)
    case 'datetime':
      return getNowInTimezone(currentTimezone.value)
    case 'daterange':
      return [getTodayInTimezone(currentTimezone.value), getTodayInTimezone(currentTimezone.value)]
    case 'datetimerange':
      return [getNowInTimezone(currentTimezone.value), getNowInTimezone(currentTimezone.value)]
    default:
      return getTodayInTimezone(currentTimezone.value)
  }
})

// 日期值
const dateValue = ref<Date | string | number | null>(props.modelValue || null)

// 监听 modelValue 变化
watch(() => props.modelValue, (newValue) => {
  dateValue.value = newValue || null
}, { immediate: true })

// 监听时区变化
watch(currentTimezone, (newTimezone) => {
  // 如果当前没有值，自动设置为新时区的今天
  if (!dateValue.value) {
    if (props.type === 'date') {
      dateValue.value = getTodayInTimezone(newTimezone)
    } else if (props.type === 'datetime') {
      dateValue.value = getNowInTimezone(newTimezone)
    }
  }
})

// 处理值变化
const handleChange = (value: Date | string | number | null) => {
  emit('change', value)
}

// 处理值更新
const handleUpdate = (value: Date | string | number | null) => {
  dateValue.value = value
  emit('update:modelValue', value)
}

// 组件挂载时设置默认值
onMounted(() => {
  if (!dateValue.value && props.type.includes('range')) {
    // 范围选择器设置默认范围
    const today = getTodayInTimezone(currentTimezone.value)
    const tomorrow = dayjs(today).tz(currentTimezone.value).add(1, 'day').toDate()
    
    if (props.type === 'daterange') {
      dateValue.value = [today, tomorrow] as any
    } else if (props.type === 'datetimerange') {
      const now = getNowInTimezone(currentTimezone.value)
      const nextHour = dayjs(now).tz(currentTimezone.value).add(1, 'hour').toDate()
      dateValue.value = [now, nextHour] as any
    }
  } else if (!dateValue.value) {
    // 单个日期选择器设置默认值
    if (props.type === 'date') {
      dateValue.value = getTodayInTimezone(currentTimezone.value)
    } else if (props.type === 'datetime') {
      dateValue.value = getNowInTimezone(currentTimezone.value)
    }
  }
  
  // 监听全局时区变化事件
  if (typeof window !== 'undefined') {
    window.addEventListener('timezone-changed', (event: any) => {
      if (event.detail?.timezone) {
        currentTimezone.value = event.detail.timezone
      }
    })
  }
})
</script> 