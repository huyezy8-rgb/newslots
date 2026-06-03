<template>
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        width="48%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate }}
            </div>
        </template>

        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :style="config.layout.shrink ? '' : 'width: calc(100% - ' + baTable.form.labelWidth! / 2 + 'px)'">
                <el-form
                    v-if="!baTable.form.loading"
                    ref="formRef"
                    @submit.prevent=""
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >
                    <FormItem label="规则名称" type="string" v-model="baTable.form.items!.rule_name" prop="rule_name" />
                    <FormItem label="最小金额" type="number" v-model="baTable.form.items!.amount_min" prop="amount_min" :input-attr="{ min: 0, step: 0.01 }" />
                    <FormItem label="最大金额" type="number" v-model="baTable.form.items!.amount_max" prop="amount_max" :input-attr="{ min: 0, step: 0.01 }" />
                    <FormItem label="有效期小时" type="number" v-model="baTable.form.items!.expire_hours" prop="expire_hours" :input-attr="{ min: 0, step: 1 }" />
                    <FormItem label="每人领取次数" type="number" v-model="baTable.form.items!.per_user_limit" prop="per_user_limit" :input-attr="{ min: 0, step: 1 }" />
                    <FormItem label="最大领取人数" type="number" v-model="baTable.form.items!.max_claim_users" prop="max_claim_users" :input-attr="{ min: 0, step: 1 }" />
                    <FormItem label="是否启用" type="radio" v-model="baTable.form.items!.is_enabled" prop="is_enabled" :input-attr="{ content: { 0: '否', 1: '是' } }" />
                </el-form>
            </div>
        </el-scrollbar>

        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">取消</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="submit" type="primary">保存</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormItemRule } from 'element-plus'
import { inject, reactive, useTemplateRef } from 'vue'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass

const submit = () => {
    baTable.onSubmit(formRef.value)
}

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    rule_name: [buildValidatorData({ name: 'required', title: '规则名称' })],
    amount_min: [buildValidatorData({ name: 'number', title: '最小金额' })],
    amount_max: [buildValidatorData({ name: 'number', title: '最大金额' })],
    expire_hours: [buildValidatorData({ name: 'number', title: '有效期小时' })],
    per_user_limit: [buildValidatorData({ name: 'number', title: '每人领取次数' })],
    max_claim_users: [buildValidatorData({ name: 'number', title: '最大领取人数' })],
})
</script>
