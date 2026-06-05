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
                    @keyup.enter="onSubmit"
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >
                    <FormItem
                        :label="t('payment.methods.channel_code')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.channel_code"
                        prop="channel_code"
                        :input-attr="{ pk: 'channels.code', field: 'name', remoteUrl: '/admin/payment.Channels/index' }"
                        :placeholder="t('Please select field', { field: t('payment.methods.channel_code') })"
                    />
                    <FormItem
                        :label="t('payment.methods.name')"
                        type="string"
                        v-model="baTable.form.items!.name"
                        prop="name"
                        :placeholder="t('Please input field', { field: t('payment.methods.name') })"
                    />
                    <FormItem
                        :label="t('payment.methods.unique_tag')"
                        type="string"
                        v-model="baTable.form.items!.unique_tag"
                        prop="unique_tag"
                        :placeholder="t('Please input field', { field: t('payment.methods.unique_tag') })"
                    />
                    <FormItem
                        :label="t('payment.methods.code')"
                        type="string"
                        v-model="baTable.form.items!.code"
                        prop="code"
                        :placeholder="t('Please input field', { field: t('payment.methods.code') })"
                    />
                    <FormItem
                        :label="t('payment.methods.description')"
                        type="textarea"
                        v-model="baTable.form.items!.description"
                        prop="description"
                        :input-attr="{ rows: 3 }"
                        @keyup.enter.stop=""
                        @keyup.ctrl.enter="onSubmit"
                        :placeholder="t('Please input field', { field: t('payment.methods.description') })"
                    />
                    <FormItem :label="t('payment.methods.icon')" type="image" v-model="baTable.form.items!.icon" prop="icon" />
                    <FormItem
                        :label="t('payment.methods.show')"
                        type="select"
                        v-model="baTable.form.items!.show"
                        prop="show"
                        :input-attr="{ content: { all: 'show all', ios: 'show ios', android: 'show android' } }"
                        :placeholder="t('Please select field', { field: t('payment.methods.show') })"
                    />
                    <FormItem
                        :label="t('payment.methods.status')"
                        type="switch"
                        v-model="baTable.form.items!.status"
                        prop="status"
                        :input-attr="{ content: { '0': t('payment.methods.status 0'), '1': t('payment.methods.status 1') } }"
                    />
                    <FormItem
                        :label="t('payment.methods.remark')"
                        type="textarea"
                        v-model="baTable.form.items!.remark"
                        prop="remark"
                        :input-attr="{ rows: 3 }"
                        @keyup.enter.stop=""
                        @keyup.ctrl.enter="onSubmit"
                        :placeholder="t('Please input field', { field: t('payment.methods.remark') })"
                    />
                    <FormItem :label="t('payment.methods.is_clause')" type="switch" v-model="baTable.form.items!.is_clause" prop="is_clause" />
                    <FormItem
                        :label="t('payment.methods.condition_recharge_amount')"
                        type="number"
                        v-model="baTable.form.items!.condition_recharge_amount"
                        prop="condition_recharge_amount"
                        :input-attr="{ min: 0, precision: 2, step: 1 }"
                        :placeholder="t('Please input field', { field: t('payment.methods.condition_recharge_amount') })"
                    />
                    <FormItem
                        :label="t('payment.methods.condition_recharge_times')"
                        type="number"
                        v-model="baTable.form.items!.condition_recharge_times"
                        prop="condition_recharge_times"
                        :input-attr="{ min: 0, precision: 0, step: 1 }"
                        :placeholder="t('Please input field', { field: t('payment.methods.condition_recharge_times') })"
                    />
                    <FormItem
                        :label="t('payment.methods.pay_method')"
                        type="select"
                        v-model="baTable.form.items!.pay_method"
                        prop="pay_method"
                        :input-attr="{
                            content: {
                                '0': t('payment.methods.pay_method 0'),
                                '1': t('payment.methods.pay_method 1'),
                                '2': t('payment.methods.pay_method 2'),
                            },
                        }"
                        :placeholder="t('Please select field', { field: t('payment.methods.pay_method') })"
                    />
                    <FormItem
                        :label="t('payment.methods.min_recharge_amount')"
                        type="number"
                        v-model="baTable.form.items!.min_recharge_amount"
                        prop="min_recharge_amount"
                        :input-attr="{ min: 0, precision: 2, step: 1 }"
                        :placeholder="t('Please input field', { field: t('payment.methods.min_recharge_amount') })"
                    />
                    <FormItem
                        :label="t('payment.methods.max_recharge_amount')"
                        type="number"
                        v-model="baTable.form.items!.max_recharge_amount"
                        prop="max_recharge_amount"
                        :input-attr="{ min: 0, precision: 2, step: 1 }"
                        :placeholder="t('Please input field', { field: t('payment.methods.max_recharge_amount') })"
                    />
                    <FormItem
                        :label="t('payment.methods.min_withdraw_amount')"
                        type="number"
                        v-model="baTable.form.items!.min_withdraw_amount"
                        prop="min_withdraw_amount"
                        :input-attr="{ min: 0, precision: 2, step: 1 }"
                        :placeholder="t('Please input field', { field: t('payment.methods.min_withdraw_amount') })"
                    />
                    <FormItem
                        :label="t('payment.methods.max_withdraw_amount')"
                        type="number"
                        v-model="baTable.form.items!.max_withdraw_amount"
                        prop="max_withdraw_amount"
                        :input-attr="{ min: 0, precision: 2, step: 1 }"
                        :placeholder="t('Please input field', { field: t('payment.methods.max_withdraw_amount') })"
                    />
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="onSubmit" type="primary">
                    {{ baTable.form.operateIds && baTable.form.operateIds.length > 1 ? t('Save and edit next item') : t('Save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormItemRule } from 'element-plus'
import { inject, reactive, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()

const amountFields = ['min_recharge_amount', 'max_recharge_amount', 'min_withdraw_amount', 'max_withdraw_amount'] as const

const normalizeAmountFields = () => {
    for (const field of amountFields) {
        const value = baTable.form.items?.[field]
        if (value === '' || value === null || typeof value === 'undefined') {
            baTable.form.items![field] = null
            continue
        }

        const amount = Number(value)
        if (Number.isFinite(amount)) {
            baTable.form.items![field] = amount
        }
    }
}

const normalizeConditionFields = () => {
    const amount = Number(baTable.form.items?.condition_recharge_amount)
    if (Number.isFinite(amount)) {
        baTable.form.items!.condition_recharge_amount = amount
    }

    const times = Number(baTable.form.items?.condition_recharge_times)
    if (Number.isInteger(times)) {
        baTable.form.items!.condition_recharge_times = times
    }
}

const onSubmit = () => {
    normalizeAmountFields()
    normalizeConditionFields()
    baTable.onSubmit(formRef.value)
}

const amountValidator = (rule: any, value: unknown, callback: (error?: Error) => void) => {
    if (value === '' || value === null || typeof value === 'undefined') {
        callback()
        return
    }

    const amount = Number(value)
    if (!Number.isFinite(amount) || amount < 0) {
        callback(new Error(t('Please enter the correct field', { field: rule.field ? t(`payment.methods.${rule.field}`) : '' })))
        return
    }

    callback()
}

const conditionValidator = (rule: any, value: unknown, callback: (error?: Error) => void) => {
    const amount = Number(value)
    if (!Number.isFinite(amount) || amount < 0) {
        callback(new Error(t('Please enter the correct field', { field: rule.field ? t(`payment.methods.${rule.field}`) : '' })))
        return
    }

    if (rule.field === 'condition_recharge_times' && !Number.isInteger(amount)) {
        callback(new Error(t('Please enter the correct field', { field: t('payment.methods.condition_recharge_times') })))
        return
    }

    callback()
}

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    unique_tag: [buildValidatorData({ name: 'required', title: t('payment.methods.unique_tag') })],
    min_recharge_amount: [{ validator: amountValidator, trigger: 'blur' }],
    max_recharge_amount: [{ validator: amountValidator, trigger: 'blur' }],
    min_withdraw_amount: [{ validator: amountValidator, trigger: 'blur' }],
    max_withdraw_amount: [{ validator: amountValidator, trigger: 'blur' }],
    condition_recharge_amount: [{ validator: conditionValidator, trigger: 'blur' }],
    condition_recharge_times: [{ validator: conditionValidator, trigger: 'blur' }],
    create_time: [buildValidatorData({ name: 'date', title: t('payment.methods.create_time') })],
    update_time: [buildValidatorData({ name: 'date', title: t('payment.methods.update_time') })],
})
</script>

<style scoped lang="scss"></style>
