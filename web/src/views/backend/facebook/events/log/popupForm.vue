<template>
    <!-- 对话框展示 -->
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        width="70%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ t('facebook.events.log.detail') }}
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div v-if="!baTable.form.loading" class="event-detail-container">
                <!-- 基本信息 -->
                <el-card class="detail-card" shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('facebook.events.log.basic_info') }}</span>
                        </div>
                    </template>
                    <el-descriptions :column="3" border>
                        <el-descriptions-item :label="t('facebook.events.log.id')">
                            {{ baTable.form.items?.id || '-' }}
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.user_id')">
                            {{ baTable.form.items?.user_id || '-' }}
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.channel_id')">
                            {{ baTable.form.items?.channel_id || '-' }}
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.event_type')">
                            <el-tag size="small">{{ baTable.form.items?.event_type || '-' }}</el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.event_name')">
                            {{ baTable.form.items?.event_name || '-' }}
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.event_id')">
                            {{ baTable.form.items?.event_id || '-' }}
                        </el-descriptions-item>
                    </el-descriptions>
                </el-card>

                <!-- Facebook配置信息 -->
                <el-card class="detail-card" shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('facebook.events.log.facebook_config') }}</span>
                        </div>
                    </template>
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="t('facebook.events.log.fb_pixel_id')">
                            {{ baTable.form.items?.fb_pixel_id || '-' }}
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.fb_token')">
                            <el-input 
                                :model-value="baTable.form.items?.fb_token" 
                                readonly 
                                size="small"
                                :show-password="true"
                            />
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.fb_event_id')">
                            {{ baTable.form.items?.fb_event_id || '-' }}
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.fb_trace_id')">
                            {{ baTable.form.items?.fb_trace_id || '-' }}
                        </el-descriptions-item>
                    </el-descriptions>
                </el-card>

                <!-- 状态信息 -->
                <el-card class="detail-card" shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('facebook.events.log.status_info') }}</span>
                        </div>
                    </template>
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="t('facebook.events.log.status')">
                            <el-tag 
                                :type="getStatusType(baTable.form.items?.status)"
                                size="small"
                            >
                                {{ getStatusText(baTable.form.items?.status) }}
                            </el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.event_time')">
                            {{ formatDateTime(baTable.form.items?.event_time) }}
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.created_at')">
                            {{ formatDateTime(baTable.form.items?.created_at) }}
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('facebook.events.log.updated_at')">
                            {{ formatDateTime(baTable.form.items?.updated_at) }}
                        </el-descriptions-item>
                    </el-descriptions>
                </el-card>

                <!-- 错误信息 -->
                <el-card v-if="baTable.form.items?.error_message" class="detail-card" shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('facebook.events.log.error_info') }}</span>
                        </div>
                    </template>
                    <el-alert
                        :title="t('facebook.events.log.error_message')"
                        type="error"
                        :description="baTable.form.items?.error_message"
                        show-icon
                        :closable="false"
                    />
                </el-card>

                <!-- 事件数据 -->
                <el-card class="detail-card" shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('facebook.events.log.event_data') }}</span>
                        </div>
                    </template>
                    <el-tabs type="border-card">
                        <el-tab-pane :label="t('facebook.events.log.event_data')" name="event_data">
                            <pre class="json-display">{{ formatJson(baTable.form.items?.event_data) }}</pre>
                        </el-tab-pane>
                        <el-tab-pane :label="t('facebook.events.log.custom_data')" name="custom_data">
                            <pre class="json-display">{{ formatJson(baTable.form.items?.custom_data) }}</pre>
                        </el-tab-pane>
                        <el-tab-pane :label="t('facebook.events.log.user_data')" name="user_data">
                            <pre class="json-display">{{ formatJson(baTable.form.items?.user_data) }}</pre>
                        </el-tab-pane>
                    </el-tabs>
                </el-card>
            </div>
        </el-scrollbar>
        <template #footer>
            <div class="dialog-footer">
                <el-button @click="baTable.toggleForm()">{{ t('Close') }}</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { inject, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()

// 获取状态类型
const getStatusType = (status: string) => {
    switch (status) {
        case 'pending':
            return 'warning'
        case 'success':
            return 'success'
        case 'failed':
            return 'danger'
        default:
            return 'info'
    }
}

// 获取状态文本
const getStatusText = (status: string) => {
    switch (status) {
        case 'pending':
            return t('status pending')
        case 'success':
            return t('status success')
        case 'failed':
            return t('status failed')
        default:
            return status || '-'
    }
}

// 格式化日期时间
const formatDateTime = (timestamp: any) => {
    if (!timestamp) return '-'
    const date = new Date(timestamp * 1000)
    return date.toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    })
}

// 格式化JSON
const formatJson = (data: any) => {
    if (!data) return '-'
    try {
        const parsed = typeof data === 'string' ? JSON.parse(data) : data
        return JSON.stringify(parsed, null, 2)
    } catch (e) {
        return data
    }
}
</script>

<style scoped lang="scss">
.event-detail-container {
    padding: 20px;
    
    .detail-card {
        margin-bottom: 20px;
        
        &:last-child {
            margin-bottom: 0;
        }
        
        .card-header {
            font-weight: 600;
            color: #303133;
        }
    }
    
    .json-display {
        background-color: #f5f7fa;
        border: 1px solid #e4e7ed;
        border-radius: 4px;
        padding: 12px;
        margin: 0;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 12px;
        line-height: 1.5;
        color: #606266;
        white-space: pre-wrap;
        word-wrap: break-word;
        max-height: 300px;
        overflow-y: auto;
    }
}

.dialog-footer {
    text-align: center;
    padding-top: 10px;
}

:deep(.el-descriptions__label) {
    font-weight: 600;
    color: #606266;
}

:deep(.el-descriptions__content) {
    color: #303133;
}

:deep(.el-tabs__content) {
    padding: 0;
}
</style>
