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
                        :label="t('account.account.nickname')"
                        type="string"
                        v-model="baTable.form.items!.nickname"
                        prop="nickname"
                        :placeholder="t('Please input field', { field: t('account.account.nickname') })"
                    />
                    <FormItem
                        :label="t('account.account.mobile')"
                        type="string"
                        v-model="baTable.form.items!.mobile"
                        prop="mobile"
                        :placeholder="t('Please input field', { field: t('account.account.mobile') })"
                    />
                    <FormItem
                        :key="baTable.form.operate + '-password'"
                        :label="t('account.account.password')"
                        type="string"
                        v-model="passwordLocal"
                        prop="password"
                        :input-attr="{ type: 'password', autocomplete: 'new-password', name: 'new-password', 'data-lpignore': 'true', 'data-1p-ignore': 'true' }"
                        :placeholder="
                            baTable.form.operate == 'Add'
                                ? t('Please input field', { field: t('account.account.password') })
                                : t('用户密码如不修改，请留空')
                        "
                    />
                    <FormItem
                        :label="t('account.account.vip')"
                        type="number"
                        v-model="baTable.form.items!.vip"
                        prop="vip"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('account.account.vip') })"
                    />
                    <FormItem
                        :label="'p_id'"
                        type="number"
                        v-model="baTable.form.items!.p_id"
                        prop="p_id"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: 'p_id' })"
                    />
                    <FormItem
                        :label="'rebate_rate'"
                        type="number"
                        v-model="baTable.form.items!.rebate_rate"
                        prop="rebate_rate"
                        :input-attr="{ step: 0.01 }"
                        :placeholder="t('Please input field', { field: 'rebate_rate' })"
                    />
                    <FormItem
                        :label="t('account.account.is_black')"
                        type="radio"
                        v-model="baTable.form.items!.is_black"
                        :input-attr="{
                            border: true,
                            content: { 0: '正常', 1: '拉黑' },
                        }"
                    />
                    <FormItem
                        :label="t('account.account.experience_wallet')"
                        type="number"
                        v-model="baTable.form.items!.experience_wallet"
                        prop="experience_wallet"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('account.account.experience_wallet') })"
                    />
                    <FormItem
                        :label="t('account.account.recharge_wallet')"
                        type="number"
                        v-model="baTable.form.items!.recharge_wallet"
                        prop="recharge_wallet"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('account.account.recharge_wallet') })"
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
import { inject, reactive, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()

// 本地密码状态，避免绑定后端返回数据
const passwordLocal = ref('')

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    vip: [buildValidatorData({ name: 'number', title: t('account.account.vip') })],
    last_login_time: [buildValidatorData({ name: 'date', title: t('account.account.last_login_time') })],
    reg_time: [buildValidatorData({ name: 'date', title: t('account.account.reg_time') })],
    is_black: [buildValidatorData({ name: 'number', title: t('account.account.is_black') })],
    experience_wallet: [buildValidatorData({ name: 'number', title: t('account.account.experience_wallet') })],
    recharge_wallet: [buildValidatorData({ name: 'number', title: t('account.account.recharge_wallet') })],
    // 密码可为空，不做全局必填校验；如需校验，仅在非空时由后端或提交前处理
    password: [],
})

// 编辑时不回显密码，始终置空以避免渲染
watch(
    () => baTable.form.operate,
    (val) => {
        if (val === 'Edit') {
            // 编辑态：本地密码清空，并移除提交模型里的 password，防止把后端返回的哈希提交回去
            passwordLocal.value = ''
            if (baTable.form.items && (baTable.form.items as any).password) {
                delete (baTable.form.items as any).password
            }
        }
    },
    { immediate: true }
)

// 同步本地密码到提交模型：为空则不提交（避免触发后端校验）
watch(
    () => passwordLocal.value,
    (val) => {
        if (!baTable.form.items) return
        if (!val) {
            delete (baTable.form.items as any).password
        } else {
            ;(baTable.form.items as any).password = val
        }
    }
)
</script>

<style scoped lang="scss"></style>
