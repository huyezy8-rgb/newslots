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
                        :label="t('banner.channel_ids')"
                        type="remoteSelects"
                        v-model="baTable.form.items!.channel_ids"
                        prop="channel_ids"
                        :input-attr="{ pk: 'id', field: 'name', remoteUrl: '/admin/channel.Listsss/index' }"
                        :placeholder="t('Please select field', { field: t('banner.channel_ids') })"
                    />
                    <FormItem
                        :label="t('banner.title')"
                        type="string"
                        v-model="baTable.form.items!.title"
                        prop="title"
                        :placeholder="t('Please input field', { field: t('banner.title') })"
                    />
                    <FormItem
                        :label="t('banner.content')"
                        type="string"
                        v-model="baTable.form.items!.content"
                        prop="content"
                        :placeholder="t('Please input field', { field: t('banner.content') })"
                    />
                    <FormItem :label="t('banner.image')" type="image" v-model="baTable.form.items!.image" prop="image" />
                    <FormItem
                        :label="t('banner.jump_type')"
                        type="radio"
                        v-model="baTable.form.items!.jump_type"
                        prop="jump_type"
                        :input-attr="{ content: { '0': t('banner.jump_type 0'), '1': t('banner.jump_type 1') } }"
                        :placeholder="t('Please select field', { field: t('banner.jump_type') })"
                    />
                    <FormItem
                        :label="t('banner.link')"
                        type="string"
                        v-model="baTable.form.items!.link"
                        prop="link"
                        :placeholder="t('Please input field', { field: t('banner.link') })"
                    />
                    <FormItem
                        :label="t('banner.activity')"
                        type="string"
                        v-model="baTable.form.items!.activity"
                        prop="activity"
                        :placeholder="t('Please input field', { field: t('banner.activity') })"
                    />
                    <FormItem
                        :label="t('banner.sort')"
                        type="number"
                        v-model="baTable.form.items!.sort"
                        prop="sort"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('banner.sort') })"
                    />
                    <FormItem
                        :label="t('banner.status')"
                        type="radio"
                        v-model="baTable.form.items!.status"
                        prop="status"
                        :input-attr="{ content: { '0': t('banner.status 0'), '1': t('banner.status 1') } }"
                        :placeholder="t('Please select field', { field: t('banner.status') })"
                    />
                    <FormItem
                        :label="t('banner.start_time')"
                        type="datetime"
                        v-model="baTable.form.items!.start_time"
                        prop="start_time"
                        :placeholder="t('Please select field', { field: t('banner.start_time') })"
                    />
                    <FormItem
                        :label="t('banner.end_time')"
                        type="datetime"
                        v-model="baTable.form.items!.end_time"
                        prop="end_time"
                        :placeholder="t('Please select field', { field: t('banner.end_time') })"
                    />
                    <FormItem
                        :label="t('banner.remark')"
                        type="textarea"
                        v-model="baTable.form.items!.remark"
                        prop="remark"
                        :input-attr="{ rows: 3 }"
                        @keyup.enter.stop=""
                        @keyup.ctrl.enter="baTable.onSubmit(formRef)"
                        :placeholder="t('Please input field', { field: t('banner.remark') })"
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
    sort: [buildValidatorData({ name: 'number', title: t('banner.sort') })],
    start_time: [buildValidatorData({ name: 'date', title: t('banner.start_time') })],
    end_time: [buildValidatorData({ name: 'date', title: t('banner.end_time') })],
    create_time: [buildValidatorData({ name: 'date', title: t('banner.create_time') })],
    update_time: [buildValidatorData({ name: 'date', title: t('banner.update_time') })],
})
</script>

<style scoped lang="scss"></style>
