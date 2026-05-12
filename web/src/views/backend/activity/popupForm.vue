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
                        :label="t('activity.type')"
                        type="string"
                        v-model="baTable.form.items!.type"
                        prop="type"
                        :placeholder="t('Please input field', { field: t('activity.type') })"
                    />
                    <FormItem
                        :label="t('activity.name')"
                        type="string"
                        v-model="baTable.form.items!.name"
                        prop="name"
                        :placeholder="t('Please input field', { field: t('activity.name') })"
                    />
                    <FormItem
                        :label="t('activity.is_popup')"
                        type="switch"
                        v-model="baTable.form.items!.is_popup"
                        prop="is_popup"
                        :input-attr="{ content: { '0': t('activity.is_popup 0'), '1': t('activity.is_popup 1') } }"
                    />
                    <FormItem
                        :label="t('activity.is_sidebar')"
                        type="switch"
                        v-model="baTable.form.items!.is_sidebar"
                        prop="is_sidebar"
                        :input-attr="{ content: { '0': t('activity.is_sidebar 0'), '1': t('activity.is_sidebar 1') } }"
                    />
                    <FormItem
                        :label="t('activity.is_bet_multiplier')"
                        type="switch"
                        v-model="baTable.form.items!.is_bet_multiplier"
                        prop="is_bet_multiplier"
                        :input-attr="{ content: { '0': t('activity.is_bet_multiplier 0'), '1': t('activity.is_bet_multiplier 1') } }"
                    />
                    <FormItem
                        :label="t('activity.group')"
                        type="select"
                        v-model="baTable.form.items!.group"
                        prop="group"
                        :input-attr="{
                            content: { Rewards: t('activity.group Rewards'), Events: t('activity.group Events'), null: t('activity.group null') },
                        }"
                        :placeholder="t('Please select field', { field: t('activity.group') })"
                    />
                    <FormItem
                        :label="t('activity.status')"
                        type="number"
                        v-model="baTable.form.items!.status"
                        prop="status"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('activity.status') })"
                    />
                    <FormItem
                        :label="t('activity.sort')"
                        type="number"
                        v-model="baTable.form.items!.sort"
                        prop="sort"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('activity.sort') })"
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
    status: [buildValidatorData({ name: 'number', title: t('activity.status') })],
    create_time: [buildValidatorData({ name: 'date', title: t('activity.create_time') })],
    update_time: [buildValidatorData({ name: 'date', title: t('activity.update_time') })],
})
</script>

<style scoped lang="scss"></style>
