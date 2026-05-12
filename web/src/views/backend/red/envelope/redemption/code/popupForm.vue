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
                        :label="t('red.envelope.redemption.code.code')"
                        type="string"
                        v-model="baTable.form.items!.code"
                        prop="code"
                        :placeholder="t('Please input field', { field: t('red.envelope.redemption.code.code') })"
                    />
                    <FormItem
                        :label="t('red.envelope.redemption.code.amount_min')"
                        type="number"
                        v-model="baTable.form.items!.amount_min"
                        prop="amount_min"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('red.envelope.redemption.code.amount_min') })"
                    />
                    <FormItem
                        :label="t('red.envelope.redemption.code.amount_max')"
                        type="number"
                        v-model="baTable.form.items!.amount_max"
                        prop="amount_max"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('red.envelope.redemption.code.amount_max') })"
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
import { inject, reactive, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()

// 生成20位随机兑换码（字母和数字）
const generateRedemptionCode = (): string => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
    let result = ''
    for (let i = 0; i < 20; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length))
    }
    return result
}

// 监听表单打开，自动生成兑换码
watch(() => baTable.form.operate, (newOperate) => {
    if (newOperate === 'Add') {
        // 延迟一下确保表单数据已经初始化
        setTimeout(() => {
            if (baTable.form.items) {
                baTable.form.items.code = generateRedemptionCode()
            }
        }, 100)
    }
})

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    amount_min: [buildValidatorData({ name: 'number', title: t('red.envelope.redemption.code.amount_min') })],
    amount_max: [buildValidatorData({ name: 'number', title: t('red.envelope.redemption.code.amount_max') })],
    is_used: [buildValidatorData({ name: 'number', title: t('red.envelope.redemption.code.is_used') })],
    used_at: [buildValidatorData({ name: 'date', title: t('red.envelope.redemption.code.used_at') })],
})
</script>

<style scoped lang="scss"></style>
