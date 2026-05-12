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
                        :label="t('recharge.orders.order_no')"
                        type="string"
                        v-model="baTable.form.items!.order_no"
                        prop="order_no"
                        :placeholder="t('Please input field', { field: t('recharge.orders.order_no') })"
                    />
                    <FormItem
                        :label="t('recharge.orders.user_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.user_id"
                        prop="user_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('recharge.orders.user_id') })"
                    />
                    <FormItem
                        :label="t('recharge.orders.channel_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.channel_id"
                        prop="channel_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('recharge.orders.channel_id') })"
                    />
                    <FormItem
                        :label="t('recharge.orders.amount')"
                        type="number"
                        v-model="baTable.form.items!.amount"
                        prop="amount"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('recharge.orders.amount') })"
                    />
                    <FormItem
                        :label="t('recharge.orders.pay_type')"
                        type="string"
                        v-model="baTable.form.items!.pay_type"
                        prop="pay_type"
                        :placeholder="t('Please input field', { field: t('recharge.orders.pay_type') })"
                    />
                    <FormItem
                        :label="t('recharge.orders.pay_status')"
                        type="radio"
                        v-model="baTable.form.items!.pay_status"
                        prop="pay_status"
                        :input-attr="{ content: {} }"
                        :placeholder="t('Please select field', { field: t('recharge.orders.pay_status') })"
                    />
                    <FormItem
                        :label="t('recharge.orders.remark')"
                        type="string"
                        v-model="baTable.form.items!.remark"
                        prop="remark"
                        :placeholder="t('Please input field', { field: t('recharge.orders.remark') })"
                    />
                    <FormItem
                        :label="t('recharge.orders.paid_at')"
                        type="datetime"
                        v-model="baTable.form.items!.paid_at"
                        prop="paid_at"
                        :placeholder="t('Please select field', { field: t('recharge.orders.paid_at') })"
                    />
                    <FormItem
                        :label="t('recharge.orders.created_at')"
                        type="datetime"
                        v-model="baTable.form.items!.created_at"
                        prop="created_at"
                        :placeholder="t('Please select field', { field: t('recharge.orders.created_at') })"
                    />
                    <FormItem
                        :label="t('recharge.orders.updated_at')"
                        type="datetime"
                        v-model="baTable.form.items!.updated_at"
                        prop="updated_at"
                        :placeholder="t('Please select field', { field: t('recharge.orders.updated_at') })"
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

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    amount: [buildValidatorData({ name: 'number', title: t('recharge.orders.amount') })],
    paid_at: [buildValidatorData({ name: 'date', title: t('recharge.orders.paid_at') })],
    created_at: [buildValidatorData({ name: 'date', title: t('recharge.orders.created_at') })],
    updated_at: [buildValidatorData({ name: 'date', title: t('recharge.orders.updated_at') })],
})
</script>

<style scoped lang="scss"></style>
