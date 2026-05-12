<template>
    <!-- 对话框表单 -->
    <!-- 建议使用 Prettier 格式化代码 -->
    <!-- el-form 内可以混用 el-form-item、FormItem、ba-input 等输入组件 -->
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        width="50%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ t('View') }}
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
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="{}"
                    disabled
                >
                    <el-form-item :label="t('withdraw.accounts.user_id')">
                        <el-tag type="info" size="large">
                            {{ userDisplayName }}
                        </el-tag>
                    </el-form-item>
                    <el-form-item :label="t('withdraw.accounts.unique_tag')">
                        <el-tag type="success" size="large">
                            {{ baTable.form.items!.unique_tag }}
                        </el-tag>
                    </el-form-item>
                    <FormItem
                        :label="t('withdraw.accounts.account_name')"
                        type="string"
                        v-model="baTable.form.items!.account_name"
                        prop="account_name"
                        :placeholder="t('Please input field', { field: t('withdraw.accounts.account_name') })"
                    />
                    <el-form-item :label="t('withdraw.accounts.is_default')">
                        <el-tag :type="baTable.form.items!.is_default ? 'success' : 'info'" size="large">
                            {{ baTable.form.items!.is_default ? t('Yes') : t('No') }}
                        </el-tag>
                    </el-form-item>
                    <el-form-item :label="t('withdraw.accounts.account_info')" prop="account_info">
                        <el-table :data="infoRows" size="small" border style="width: 100%">
                            <el-table-column prop="label" :label="t('Field')" width="180" align="left" />
                            <el-table-column prop="value" :label="t('Value')" align="left" />
                        </el-table>
                    </el-form-item>
                    <el-form-item :label="t('withdraw.accounts.status')">
                        <el-tag :type="baTable.form.items!.status ? 'success' : 'danger'" size="large">
                            {{ baTable.form.items!.status ? t('withdraw.accounts.status 1') : t('withdraw.accounts.status 0') }}
                        </el-tag>
                    </el-form-item>
                    
                    <el-form-item :label="t('withdraw.accounts.create_time')">
                        <el-text>{{ formatDateTime(baTable.form.items!.create_time) }}</el-text>
                    </el-form-item>
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">{{ t('Close') }}</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormItemRule } from 'element-plus'
import { computed, inject, reactive, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    is_default: [buildValidatorData({ name: 'number', title: t('withdraw.accounts.is_default') })],
    create_time: [buildValidatorData({ name: 'date', title: t('withdraw.accounts.create_time') })],
    update_time: [buildValidatorData({ name: 'date', title: t('withdraw.accounts.update_time') })],
})

// 解析 account_info（可能是字符串或对象），转为表格行
const infoRows = computed<{ label: string; value: string }[]>(() => {
    const raw = (baTable.form.items as any)?.account_info
    let obj: any = {}
    if (!raw) return []
    try {
        if (typeof raw === 'string') {
            obj = JSON.parse(raw)
        } else if (typeof raw === 'object') {
            obj = raw
        }
    } catch (e) {
        // 不是合法 JSON，则整体显示为一行
        return [{ label: 'account_info', value: String(raw) }]
    }
    return Object.keys(obj).map((k) => ({ label: k, value: String(obj[k]) }))
})

// 用户显示名称
const userDisplayName = computed(() => {
    const user = (baTable.form.items as any)?.user
    return user?.name || user?.username || user?.mobile || baTable.form.items?.user_id || '-'
})

// 格式化日期时间
const formatDateTime = (timestamp: number | string) => {
    if (!timestamp) return '-'
    const date = new Date(Number(timestamp) * 1000)
    return date.toLocaleString()
}
</script>

<style scoped lang="scss"></style>
