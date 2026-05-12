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
                        :label="t('activity.chest.name')"
                        type="string"
                        v-model="baTable.form.items!.name"
                        prop="name"
                        :placeholder="t('Please input field', { field: t('activity.chest.name') })"
                    />
                    <FormItem
                        :label="t('activity.chest.recharge_amount')"
                        type="number"
                        v-model="baTable.form.items!.recharge_amount"
                        prop="recharge_amount"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('activity.chest.recharge_amount') })"
                    />
                    <FormItem
                        :label="t('activity.chest.invite_count')"
                        type="number"
                        v-model="baTable.form.items!.invite_count"
                        prop="invite_count"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('activity.chest.invite_count') })"
                    />
                    <FormItem
                        :label="t('activity.chest.reward_amount')"
                        type="number"
                        v-model="baTable.form.items!.reward_amount"
                        prop="reward_amount"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('activity.chest.reward_amount') })"
                    />
                    <FormItem
                        :label="t('activity.chest.sort')"
                        type="number"
                        v-model="baTable.form.items!.sort"
                        prop="sort"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('activity.chest.sort') })"
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
    recharge_amount: [buildValidatorData({ name: 'number', title: t('activity.chest.recharge_amount') })],
    invite_count: [buildValidatorData({ name: 'number', title: t('activity.chest.invite_count') })],
    reward_amount: [buildValidatorData({ name: 'number', title: t('activity.chest.reward_amount') })],
    sort: [buildValidatorData({ name: 'number', title: t('activity.chest.sort') })],
    createtime: [buildValidatorData({ name: 'date', title: t('activity.chest.createtime') })],
    updatetime: [buildValidatorData({ name: 'date', title: t('activity.chest.updatetime') })],
})
</script>

<style scoped lang="scss"></style>
