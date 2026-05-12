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
                        :label="t('team.commission.log.user_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.user_id"
                        prop="user_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('team.commission.log.user_id') })"
                    />
                    <FormItem
                        :label="t('team.commission.log.channel_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.channel_id"
                        prop="channel_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('team.commission.log.channel_id') })"
                    />
                    <FormItem
                        :label="t('team.commission.log.source_user_id')"
                        type="remoteSelect"
                        v-model="baTable.form.items!.source_user_id"
                        prop="source_user_id"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '' }"
                        :placeholder="t('Please select field', { field: t('team.commission.log.source_user_id') })"
                    />
                    <FormItem
                        :label="t('team.commission.log.bet_amount')"
                        type="number"
                        v-model="baTable.form.items!.bet_amount"
                        prop="bet_amount"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('team.commission.log.bet_amount') })"
                    />
                    <FormItem
                        :label="t('team.commission.log.base_rate')"
                        type="number"
                        v-model="baTable.form.items!.base_rate"
                        prop="base_rate"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('team.commission.log.base_rate') })"
                    />
                    <FormItem
                        :label="t('team.commission.log.point_diff')"
                        type="number"
                        v-model="baTable.form.items!.point_diff"
                        prop="point_diff"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('team.commission.log.point_diff') })"
                    />
                    <FormItem
                        :label="t('team.commission.log.commission')"
                        type="number"
                        v-model="baTable.form.items!.commission"
                        prop="commission"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('team.commission.log.commission') })"
                    />
                    <FormItem
                        :label="t('team.commission.log.level')"
                        type="number"
                        v-model="baTable.form.items!.level"
                        prop="level"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('team.commission.log.level') })"
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
    bet_amount: [buildValidatorData({ name: 'number', title: t('team.commission.log.bet_amount') })],
    base_rate: [buildValidatorData({ name: 'number', title: t('team.commission.log.base_rate') })],
    point_diff: [buildValidatorData({ name: 'number', title: t('team.commission.log.point_diff') })],
    commission: [buildValidatorData({ name: 'number', title: t('team.commission.log.commission') })],
    level: [buildValidatorData({ name: 'number', title: t('team.commission.log.level') })],
    create_time: [buildValidatorData({ name: 'date', title: t('team.commission.log.create_time') })],
})
</script>

<style scoped lang="scss"></style>
