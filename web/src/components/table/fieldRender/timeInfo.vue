<template>
    <div class="time-info-cell">
        <el-popover
            placement="top"
            :width="200"
            trigger="hover"
        >
            <template #reference>
                <span class="time-info-trigger">
                    {{ formatTime(row.update_time) }}
                </span>
            </template>
            <div class="time-info-popover">
                <div class="time-info-item">
                    <span class="label">{{ field.custom?.createTimeLabel || t('Create Time') }}:</span>
                    <span class="value">{{ formatTime(row.update_time) }}</span>
                </div>
                <div class="time-info-item">
                    <span class="label">{{ field.custom?.updateTimeLabel || t('Update Time') }}:</span>
                    <span class="value">{{ formatTime(row.create_time) }}</span>
                </div>
            </div>
        </el-popover>
    </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { timeFormat } from '/@/utils/common'
const { t } = useI18n()

defineProps({
    row: {
        type: Object,
        required: true
    },
    field: {
        type: Object,
        required: true
    }
})

const formatTime = (time: string) => {
    if (!time) return '-'
    return timeFormat(time, 'yyyy-mm-dd hh:mm:ss')
}
</script>
