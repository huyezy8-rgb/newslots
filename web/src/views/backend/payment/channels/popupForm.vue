<template>
    <!-- 对话框表单 -->
    <!-- 建议使用 Prettier 格式化代码 -->
    <!-- el-form 内可以混用 el-form-item、FormItem、ba-input 等输入组件 -->
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        width="60%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate ? t(baTable.form.operate) : '' }}
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div
                class="ba-operate-form"
                :class="'ba-' + baTable.form.operate + '-form'"
                :style="config.layout.shrink ? '' : 'width: calc(100% - ' + baTable.form.labelWidth! / 2 + 'px)'"
            >
                <el-form
                    v-if="!baTable.form.loading"
                    ref="formRef"
                    @submit.prevent=""
                    @keyup.enter="baTable.onSubmit(formRef)"
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >
                    <FormItem
                        :label="t('payment.channels.name')"
                        type="string"
                        v-model="baTable.form.items!.name"
                        prop="name"
                        :placeholder="t('Please input field', { field: t('payment.channels.name') })"
                    />
                    <FormItem
                        :label="t('payment.channels.code')"
                        type="string"
                        v-model="baTable.form.items!.code"
                        prop="code"
                        :placeholder="t('Please input field', { field: t('payment.channels.code') })"
                    />
                    <FormItem
                        :label="t('payment.channels.description')"
                        type="textarea"
                        v-model="baTable.form.items!.description"
                        prop="description"
                        :input-attr="{ rows: 3 }"
                        @keyup.enter.stop=""
                        @keyup.ctrl.enter="baTable.onSubmit(formRef)"
                        :placeholder="t('Please input field', { field: t('payment.channels.description') })"
                    />
                    
                    <!-- Config 字段 -->
                    <el-form-item :label="t('payment.channels.config')" prop="config">
                        <div class="config-editor">
                            <div class="config-toolbar">
                                <el-button size="small" @click="formatJson" type="primary">
                                    <el-icon><Refresh /></el-icon>
                                    格式化JSON
                                </el-button>
                                <el-button size="small" @click="validateJson" type="warning">
                                    <el-icon><Check /></el-icon>
                                    验证JSON
                                </el-button>
                                <el-button size="small" @click="clearConfig" type="danger">
                                    <el-icon><Delete /></el-icon>
                                    清空
                                </el-button>
                                <el-button size="small" @click="autoFormatJson" type="success">
                                    <el-icon><Star /></el-icon>
                                    自动格式化
                                </el-button>
                            </div>
                            <el-input
                                v-model="baTable.form.items!.config"
                                type="textarea"
                                :rows="8"
                                :placeholder="configPlaceholder"
                                @blur="validateJsonOnBlur"
                                @input="autoFormatOnInput"
                                :class="{ 'is-error': configError }"
                            />
                            <div v-if="configError" class="config-error">
                                <el-icon><Warning /></el-icon>
                                {{ configError }}
                            </div>
                        </div>
                    </el-form-item>

                    <FormItem
                        :label="t('payment.channels.status')"
                        type="switch"
                        v-model="baTable.form.items!.status"
                        prop="status"
                        :input-attr="{ content: { '0': t('payment.channels.status 0'), '1': t('payment.channels.status 1') } }"
                    />
                    <FormItem
                        :label="t('payment.channels.weight')"
                        type="number"
                        v-model="baTable.form.items!.weight"
                        prop="weight"
                        :input-attr="{ min: 0, precision: 0, step: 1 }"
                        :placeholder="t('Please input field', { field: t('payment.channels.weight') })"
                    />
                    <FormItem
                        :label="t('payment.channels.remark')"
                        type="textarea"
                        v-model="baTable.form.items!.remark"
                        prop="remark"
                        :input-attr="{ rows: 3 }"
                        @keyup.enter.stop=""
                        @keyup.ctrl.enter="baTable.onSubmit(formRef)"
                        :placeholder="t('Please input field', { field: t('payment.channels.remark') })"
                    />
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="baTable.onSubmit(formRef)" type="primary">
                    {{ baTable.form.operateIds && baTable.form.operateIds.length > 1 ? t('Save and edit next item') : t('Save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormItemRule } from 'element-plus'
import { inject, reactive, useTemplateRef, ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Refresh, Check, Delete, Warning, Star } from '@element-plus/icons-vue'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()

// Config 相关状态
const configError = ref('')

// 监听 config 字段变化，处理 JSON 对象转换
watch(() => baTable.form.items?.config, (newValue) => {
    if (newValue && typeof newValue === 'object') {
        // 如果是对象，转换为 JSON 字符串
        baTable.form.items!.config = JSON.stringify(newValue, null, 2)
    } else if (newValue && typeof newValue === 'string') {
        // 如果是字符串，尝试解析为 JSON 对象再格式化
        try {
            const parsed = JSON.parse(newValue)
            baTable.form.items!.config = JSON.stringify(parsed, null, 2)
        } catch (error) {
            // 如果解析失败，保持原样
        }
    }
}, { immediate: true })

// 配置占位符
const configPlaceholder = computed(() => {
    const channelCode = baTable.form.items?.code || ''
    switch (channelCode) {
        case 'amopay':
            return `{
  "client_id": "your_client_id",
  "secret_key": "your_secret_key",
  "api_url": "https://api.ramp.amopay.io/api"
}`
        case 'succuspay':
            return `{
  "mch_no": "your_merchant_number",
  "key": "your_api_key",
  "api_url": "your_api_url"
}`
        case 'bank_transfer':
            return `{
  "bank_name": "Bank Name",
  "account_name": "Account Holder Name",
  "account_number": "Account Number",
  "swift_code": "SWIFT Code"
}`
        default:
            return `{
  "key1": "value1",
  "key2": "value2"
}`
    }
})

// 格式化 JSON
const formatJson = () => {
    try {
        let configValue = baTable.form.items?.config || '{}'
        
        // 如果是对象，先转换为字符串
        if (typeof configValue === 'object') {
            configValue = JSON.stringify(configValue)
        }
        
        const parsed = JSON.parse(configValue)
        baTable.form.items!.config = JSON.stringify(parsed, null, 2)
        configError.value = ''
        ElMessage.success('JSON格式化成功')
    } catch (error) {
        configError.value = 'JSON格式无效'
        ElMessage.error('JSON格式无效')
    }
}

// 验证 JSON
const validateJson = () => {
    try {
        let configValue = baTable.form.items?.config || '{}'
        
        // 如果是对象，先转换为字符串
        if (typeof configValue === 'object') {
            configValue = JSON.stringify(configValue)
        }
        
        JSON.parse(configValue)
        configError.value = ''
        ElMessage.success('JSON格式正确')
    } catch (error) {
        configError.value = 'JSON格式无效'
        ElMessage.error('JSON格式无效')
    }
}

// 失焦时验证 JSON
const validateJsonOnBlur = () => {
    if (baTable.form.items?.config) {
        try {
            let configValue = baTable.form.items.config
            
            // 如果是对象，先转换为字符串
            if (typeof configValue === 'object') {
                configValue = JSON.stringify(configValue)
            }
            
            JSON.parse(configValue)
            configError.value = ''
        } catch (error) {
            configError.value = 'JSON格式无效'
        }
    }
}

// 清空配置
const clearConfig = () => {
    baTable.form.items!.config = '{}'
    configError.value = ''
    ElMessage.info('配置已清空')
}

// 自动格式化 JSON
const autoFormatJson = () => {
    try {
        let configValue = baTable.form.items?.config || '{}'
        
        // 如果是对象，先转换为字符串
        if (typeof configValue === 'object') {
            configValue = JSON.stringify(configValue)
        }
        
        const parsed = JSON.parse(configValue)
        baTable.form.items!.config = JSON.stringify(parsed, null, 2)
        configError.value = ''
        ElMessage.success('JSON自动格式化成功')
    } catch (error) {
        configError.value = 'JSON格式无效'
        ElMessage.error('JSON格式无效')
    }
}

// 输入时自动格式化 JSON
const autoFormatOnInput = () => {
    try {
        let configValue = baTable.form.items?.config || '{}'
        
        // 如果是对象，先转换为字符串
        if (typeof configValue === 'object') {
            configValue = JSON.stringify(configValue)
        }
        
        const parsed = JSON.parse(configValue)
        baTable.form.items!.config = JSON.stringify(parsed, null, 2)
        configError.value = ''
    } catch (error) {
        configError.value = 'JSON格式无效'
    }
}

// 表单验证规则
const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    name: [
        { required: true, message: `请输入${t('payment.channels.name')}`, trigger: 'blur' }
    ],
    code: [
        { required: true, message: `请输入${t('payment.channels.code')}`, trigger: 'blur' }
    ],
    weight: [
        { required: true, message: `请输入${t('payment.channels.weight')}`, trigger: 'blur' },
        {
            validator: (_rule: any, value: any, callback: any) => {
                const weight = Number(value)
                if (!Number.isInteger(weight) || weight < 0) {
                    callback(new Error('Invalid weight'))
                    return
                }
                callback()
            },
            trigger: 'blur',
        },
    ],
    config: [
        {
            validator: (rule: any, value: any, callback: any) => {
                if (!value) {
                    callback()
                    return
                }
                try {
                    let configValue = value
                    
                    // 如果是对象，先转换为字符串
                    if (typeof configValue === 'object') {
                        configValue = JSON.stringify(configValue)
                    }
                    
                    JSON.parse(configValue)
                    callback()
                } catch (error) {
                    callback(new Error('JSON格式无效'))
                }
            },
            trigger: 'blur'
        }
    ],
    create_time: [buildValidatorData({ name: 'date', title: t('payment.channels.create_time') })],
    update_time: [buildValidatorData({ name: 'date', title: t('payment.channels.update_time') })],
})
</script>

<style scoped lang="scss">
.config-editor {
    .config-toolbar {
        margin-bottom: 8px;
        display: flex;
        gap: 8px;
    }

    .config-error {
        margin-top: 4px;
        color: #f56c6c;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .is-error {
        :deep(.el-textarea__inner) {
            border-color: #f56c6c;
        }
    }
}
</style>
