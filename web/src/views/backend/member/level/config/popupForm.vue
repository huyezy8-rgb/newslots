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
                        :label="t('member.level.config.name')"
                        type="string"
                        v-model="baTable.form.items!.name"
                        prop="name"
                        :placeholder="t('Please input field', { field: t('member.level.config.name') })"
                    />
                    <FormItem
                        :label="t('member.level.config.level')"
                        type="number"
                        v-model="baTable.form.items!.level"
                        prop="level"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('member.level.config.level') })"
                    />
                    <FormItem
                        :label="t('member.level.config.recharge_requirement')"
                        type="number"
                        v-model="baTable.form.items!.recharge_requirement"
                        prop="recharge_requirement"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('member.level.config.recharge_requirement') })"
                    />
                    <FormItem
                        :label="t('member.level.config.withdraw_limit')"
                        type="number"
                        v-model="baTable.form.items!.withdraw_limit"
                        prop="withdraw_limit"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('member.level.config.withdraw_limit') })"
                    />
                    <FormItem
                        :label="t('member.level.config.daily_withdraw_times')"
                        type="number"
                        v-model="baTable.form.items!.daily_withdraw_times"
                        prop="daily_withdraw_times"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('member.level.config.daily_withdraw_times') })"
                    />
                    <FormItem
                        :label="t('member.level.config.withdraw_fee_percent')"
                        type="number"
                        v-model="baTable.form.items!.withdraw_fee_percent"
                        prop="withdraw_fee_percent"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('member.level.config.withdraw_fee_percent') })"
                    />
                    <FormItem
                        :label="t('member.level.config.bonus_percent')"
                        type="number"
                        v-model="baTable.form.items!.bonus_percent"
                        prop="bonus_percent"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('member.level.config.bonus_percent') })"
                    />
                    <FormItem
                        :label="t('member.level.config.upgrade_reward')"
                        type="number"
                        v-model="baTable.form.items!.upgrade_reward"
                        prop="upgrade_reward"
                        :input-attr="{ step: 0.01 }"
                        :placeholder="t('Please input field', { field: t('member.level.config.upgrade_reward') })"
                    />
                    <FormItem
                        :label="t('member.level.config.weekly_reward')"
                        type="number"
                        v-model="baTable.form.items!.weekly_reward"
                        prop="weekly_reward"
                        :input-attr="{ step: 0.01 }"
                        :placeholder="t('Please input field', { field: t('member.level.config.weekly_reward') })"
                    />
                    <FormItem
                        :label="t('member.level.config.monthly_reward')"
                        type="number"
                        v-model="baTable.form.items!.monthly_reward"
                        prop="monthly_reward"
                        :input-attr="{ step: 0.01 }"
                        :placeholder="t('Please input field', { field: t('member.level.config.monthly_reward') })"
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
    level: [buildValidatorData({ name: 'number', title: t('member.level.config.level') })],
    recharge_requirement: [buildValidatorData({ name: 'number', title: t('member.level.config.recharge_requirement') })],
    withdraw_limit: [buildValidatorData({ name: 'number', title: t('member.level.config.withdraw_limit') })],
    daily_withdraw_times: [buildValidatorData({ name: 'number', title: t('member.level.config.daily_withdraw_times') })],
    withdraw_fee_percent: [buildValidatorData({ name: 'number', title: t('member.level.config.withdraw_fee_percent') })],
    bonus_percent: [buildValidatorData({ name: 'number', title: t('member.level.config.bonus_percent') })],
    upgrade_reward: [
        { 
            validator: (rule: any, value: any, callback: any) => {
                // 允许空值或0
                if (value === '' || value === null || value === undefined || value === 0 || value === '0') {
                    callback();
                    return;
                }
                // 转换为数字进行验证
                const num = parseFloat(value);
                if (isNaN(num)) {
                    callback(new Error(t('Please enter the correct field', { field: t('member.level.config.upgrade_reward') })));
                } else if (num < 0) {
                    callback(new Error(t('Please enter the correct field', { field: t('member.level.config.upgrade_reward') })));
                } else {
                    callback();
                }
            },
            trigger: 'blur'
        }
    ],
    weekly_reward: [
        { 
            validator: (rule: any, value: any, callback: any) => {
                // 允许空值或0
                if (value === '' || value === null || value === undefined || value === 0 || value === '0') {
                    callback();
                    return;
                }
                // 转换为数字进行验证
                const num = parseFloat(value);
                if (isNaN(num)) {
                    callback(new Error(t('Please enter the correct field', { field: t('member.level.config.weekly_reward') })));
                } else if (num < 0) {
                    callback(new Error(t('Please enter the correct field', { field: t('member.level.config.weekly_reward') })));
                } else {
                    callback();
                }
            },
            trigger: 'blur'
        }
    ],
    monthly_reward: [
        { 
            validator: (rule: any, value: any, callback: any) => {
                // 允许空值或0
                if (value === '' || value === null || value === undefined || value === 0 || value === '0') {
                    callback();
                    return;
                }
                // 转换为数字进行验证
                const num = parseFloat(value);
                if (isNaN(num)) {
                    callback(new Error(t('Please enter the correct field', { field: t('member.level.config.monthly_reward') })));
                } else if (num < 0) {
                    callback(new Error(t('Please enter the correct field', { field: t('member.level.config.monthly_reward') })));
                } else {
                    callback();
                }
            },
            trigger: 'blur'
        }
    ],
    create_time: [buildValidatorData({ name: 'date', title: t('member.level.config.create_time') })],
    update_time: [buildValidatorData({ name: 'date', title: t('member.level.config.update_time') })],
})
</script>

<style scoped lang="scss"></style>
