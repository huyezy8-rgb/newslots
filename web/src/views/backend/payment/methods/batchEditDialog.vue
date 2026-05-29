<template>
    <el-dialog class="ba-operate-dialog payment-methods-batch-dialog" :close-on-click-modal="false" v-model="visible" width="560px">
        <template #header>
            <div class="title" v-drag="['.payment-methods-batch-dialog', '.el-dialog__header']" v-zoom="'.payment-methods-batch-dialog'">
                批量编辑（已选 {{ selectedCount }} 项）
            </div>
        </template>

        <el-form @submit.prevent="" @keyup.enter="onSubmit" label-position="right" label-width="150px">
            <el-form-item :label="t('payment.methods.show')">
                <div class="batch-edit-field">
                    <el-checkbox v-model="enabled.show">启用修改</el-checkbox>
                    <el-select v-model="form.show" :disabled="!enabled.show" class="batch-edit-control">
                        <el-option label="show all" value="all" />
                        <el-option label="show ios" value="ios" />
                        <el-option label="show android" value="android" />
                    </el-select>
                </div>
            </el-form-item>

            <el-form-item :label="t('payment.methods.status')">
                <div class="batch-edit-field">
                    <el-checkbox v-model="enabled.status">启用修改</el-checkbox>
                    <el-switch
                        v-model="form.status"
                        :disabled="!enabled.status"
                        active-value="1"
                        inactive-value="0"
                        :active-text="t('payment.methods.status 1')"
                        :inactive-text="t('payment.methods.status 0')"
                    />
                </div>
            </el-form-item>

            <el-form-item :label="t('payment.methods.is_clause')">
                <div class="batch-edit-field">
                    <el-checkbox v-model="enabled.is_clause">启用修改</el-checkbox>
                    <el-switch v-model="form.is_clause" :disabled="!enabled.is_clause" active-value="1" inactive-value="0" />
                </div>
            </el-form-item>

            <el-form-item :label="t('payment.methods.pay_method')">
                <div class="batch-edit-field">
                    <el-checkbox v-model="enabled.pay_method">启用修改</el-checkbox>
                    <el-select v-model="form.pay_method" :disabled="!enabled.pay_method" class="batch-edit-control">
                        <el-option :label="t('payment.methods.pay_method 0')" value="0" />
                        <el-option :label="t('payment.methods.pay_method 1')" value="1" />
                        <el-option :label="t('payment.methods.pay_method 2')" value="2" />
                    </el-select>
                </div>
            </el-form-item>

            <el-form-item v-for="field in amountFields" :key="field" :label="t(`payment.methods.${field}`)">
                <div class="batch-edit-field">
                    <el-checkbox v-model="enabled[field]">启用修改</el-checkbox>
                    <el-input
                        v-model="form[field]"
                        :disabled="!enabled[field]"
                        class="batch-edit-control"
                        type="number"
                        min="0"
                        step="0.01"
                        clearable
                        :placeholder="t('Please input field', { field: t(`payment.methods.${field}`) })"
                    />
                </div>
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="visible = false">{{ t('Cancel') }}</el-button>
            <el-button v-blur :loading="loading" @click="onSubmit" type="primary">应用到 {{ selectedCount }} 项</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, inject, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type baTableClass from '/@/utils/baTable'

const baTable = inject('baTable') as baTableClass
const { t } = useI18n()

const amountFields = ['min_recharge_amount', 'max_recharge_amount', 'min_withdraw_amount', 'max_withdraw_amount'] as const
type AmountField = (typeof amountFields)[number]
type BatchField = AmountField | 'show' | 'status' | 'is_clause' | 'pay_method'

const visible = ref(false)
const loading = ref(false)
const selectedCount = computed(() => baTable.table.selection?.length || 0)

const defaultForm: Record<BatchField, any> = {
    show: 'all',
    status: '1',
    is_clause: '0',
    pay_method: '1',
    min_recharge_amount: null,
    max_recharge_amount: null,
    min_withdraw_amount: null,
    max_withdraw_amount: null,
}

const form = reactive<Record<BatchField, any>>({ ...defaultForm })
const enabled = reactive<Record<BatchField, boolean>>({
    show: false,
    status: false,
    is_clause: false,
    pay_method: false,
    min_recharge_amount: false,
    max_recharge_amount: false,
    min_withdraw_amount: false,
    max_withdraw_amount: false,
})

const reset = () => {
    for (const field of Object.keys(defaultForm) as BatchField[]) {
        form[field] = defaultForm[field]
        enabled[field] = false
    }
}

const open = () => {
    reset()
    visible.value = true
}

const normalizeAmount = (field: AmountField) => {
    const value = form[field]
    if (value === '' || value === null || typeof value === 'undefined') {
        return null
    }

    const amount = Number(value)
    if (!Number.isFinite(amount) || amount < 0) {
        throw new Error(t('Please enter the correct field', { field: t(`payment.methods.${field}`) }))
    }

    return amount
}

const buildFields = () => {
    const fields: Partial<Record<BatchField, string | number | null>> = {}

    for (const field of Object.keys(enabled) as BatchField[]) {
        if (!enabled[field]) {
            continue
        }

        if ((amountFields as readonly string[]).includes(field)) {
            fields[field] = normalizeAmount(field as AmountField)
        } else {
            fields[field] = form[field]
        }
    }

    return fields
}

const validateAmountRange = (fields: Partial<Record<BatchField, string | number | null>>, minField: AmountField, maxField: AmountField) => {
    if (!(minField in fields) || !(maxField in fields) || fields[minField] === null || fields[maxField] === null) {
        return
    }

    if (Number(fields[minField]) > Number(fields[maxField])) {
        throw new Error(t('Please enter the correct field', { field: t(`payment.methods.${minField}`) }))
    }
}

const onSubmit = async () => {
    try {
        const ids = baTable.getSelectionIds()
        if (!ids.length) {
            ElMessage.warning('请先选择要批量编辑的支付方式')
            return
        }

        const fields = buildFields()
        if (!Object.keys(fields).length) {
            ElMessage.warning('请至少启用一个字段')
            return
        }

        validateAmountRange(fields, 'min_recharge_amount', 'max_recharge_amount')
        validateAmountRange(fields, 'min_withdraw_amount', 'max_withdraw_amount')

        await ElMessageBox.confirm(`确认将已启用字段应用到选中的 ${ids.length} 条支付方式？`, '批量编辑', {
            type: 'warning',
            confirmButtonText: '确认',
            cancelButtonText: t('Cancel'),
        })

        loading.value = true
        await baTable.api.postData('batchEdit', { ids, fields })
        visible.value = false
        baTable.onTableHeaderAction('refresh', {})
    } catch (error) {
        if (error instanceof Error) {
            ElMessage.error(error.message)
        }
    } finally {
        loading.value = false
    }
}

defineExpose({
    open,
})
</script>

<style scoped lang="scss">
.batch-edit-field {
    display: flex;
    align-items: center;
    width: 100%;
    min-width: 0;
    gap: 12px;
}

.batch-edit-field :deep(.el-checkbox) {
    flex: 0 0 86px;
}

.batch-edit-control {
    flex: 1 1 auto;
    min-width: 0;
}
</style>
