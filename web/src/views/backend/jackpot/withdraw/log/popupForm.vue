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
                        :label="t('jackpot.withdraw.log.user_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.user_id"
                        prop="user_id"
                        :input-attr="{ pk: 'account.id', field: 'nickname', remoteUrl: '/admin/account.Account/index' }"
                        :placeholder="t('Please select field', { field: t('jackpot.withdraw.log.user_id') })"
                    />
                    <FormItem
                        :label="t('jackpot.withdraw.log.current_amount')"
                        type="number"
                        v-model="baTable.form.items!.current_amount"
                        prop="current_amount"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('jackpot.withdraw.log.current_amount') })"
                    />
                    <FormItem
                        :label="t('jackpot.withdraw.log.withdraw_amount')"
                        type="number"
                        v-model="baTable.form.items!.withdraw_amount"
                        prop="withdraw_amount"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('jackpot.withdraw.log.withdraw_amount') })"
                    />
                    <FormItem
                        :label="t('jackpot.withdraw.log.status')"
                        type="radio"
                        v-model="baTable.form.items!.status"
                        prop="status"
                        :input-attr="{ content: {} }"
                        :placeholder="t('Please select field', { field: t('jackpot.withdraw.log.status') })"
                    />
                    <FormItem
                        :label="t('jackpot.withdraw.log.is_lucky')"
                        type="number"
                        v-model="baTable.form.items!.is_lucky"
                        prop="is_lucky"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('jackpot.withdraw.log.is_lucky') })"
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
    current_amount: [buildValidatorData({ name: 'number', title: t('jackpot.withdraw.log.current_amount') })],
    withdraw_amount: [buildValidatorData({ name: 'number', title: t('jackpot.withdraw.log.withdraw_amount') })],
    is_lucky: [buildValidatorData({ name: 'number', title: t('jackpot.withdraw.log.is_lucky') })],
})
</script>

<style scoped lang="scss"></style>
