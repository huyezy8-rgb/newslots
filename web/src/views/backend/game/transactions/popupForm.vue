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
                        :label="t('game.transactions.user_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.user_id"
                        prop="user_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('game.transactions.user_id') })"
                    />
                    <FormItem
                        :label="t('game.transactions.game_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.game_id"
                        prop="game_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('game.transactions.game_id') })"
                    />
                    <FormItem
                        :label="t('game.transactions.channel_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.channel_id"
                        prop="channel_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('game.transactions.channel_id') })"
                    />
                    <FormItem
                        :label="t('game.transactions.amount')"
                        type="number"
                        v-model="baTable.form.items!.amount"
                        prop="amount"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('game.transactions.amount') })"
                    />
                    <FormItem
                        :label="t('game.transactions.real_amount')"
                        type="number"
                        v-model="baTable.form.items!.real_amount"
                        prop="real_amount"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('game.transactions.real_amount') })"
                    />
                    <FormItem
                        :label="t('game.transactions.reason')"
                        type="string"
                        v-model="baTable.form.items!.reason"
                        prop="reason"
                        :placeholder="t('Please input field', { field: t('game.transactions.reason') })"
                    />
                    <FormItem
                        :label="t('game.transactions.round_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.round_id"
                        prop="round_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('game.transactions.round_id') })"
                    />
                    <FormItem
                        :label="t('game.transactions.transaction_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.transaction_id"
                        prop="transaction_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('game.transactions.transaction_id') })"
                    />
                    <FormItem
                        :label="t('game.transactions.req_time')"
                        type="datetime"
                        v-model="baTable.form.items!.req_time"
                        prop="req_time"
                        :placeholder="t('Please select field', { field: t('game.transactions.req_time') })"
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
    amount: [buildValidatorData({ name: 'number', title: t('game.transactions.amount') })],
    real_amount: [buildValidatorData({ name: 'number', title: t('game.transactions.real_amount') })],
    req_time: [buildValidatorData({ name: 'date', title: t('game.transactions.req_time') })],
})
</script>

<style scoped lang="scss"></style>
