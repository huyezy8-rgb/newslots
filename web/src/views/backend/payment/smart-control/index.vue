<template>
    <ContentWrap title="支付智能控制">
        <el-form
            ref="formRef"
            v-loading="loading"
            :model="formData"
            :rules="rules"
            label-width="150px"
            status-icon
            @submit.prevent
        >
            <el-divider content-position="left">提现金额控制</el-divider>
            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :lg="8">
                    <el-form-item label="启用规则" prop="withdraw_amount_enabled">
                        <el-switch
                            v-model="formData.withdraw_amount_enabled"
                            :active-value="1"
                            :inactive-value="0"
                            active-text="启用"
                            inactive-text="禁用"
                        />
                    </el-form-item>
                </el-col>
            </el-row>
            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :lg="8">
                    <el-form-item label="提现金额大于" prop="withdraw_amount_threshold">
                        <el-input-number
                            v-model="formData.withdraw_amount_threshold"
                            :min="0"
                            :precision="2"
                            :step="10"
                            controls-position="right"
                            class="form-control"
                        />
                    </el-form-item>
                </el-col>
                <el-col :xs="24" :sm="12" :lg="10">
                    <el-form-item label="显示指定支付方式" prop="withdraw_pay_types">
                        <el-select
                            v-model="formData.withdraw_pay_types"
                            multiple
                            filterable
                            collapse-tags
                            collapse-tags-tooltip
                            placeholder="请选择提现支付方式"
                            class="form-control"
                        >
                            <el-option
                                v-for="item in withdrawPayTypeOptions"
                                :key="item.unique_tag"
                                :label="`${item.name || item.unique_tag} (${item.unique_tag})`"
                                :value="item.unique_tag"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-divider content-position="left">充值次数控制</el-divider>
            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :lg="8">
                    <el-form-item label="启用规则" prop="recharge_count_enabled">
                        <el-switch
                            v-model="formData.recharge_count_enabled"
                            :active-value="1"
                            :inactive-value="0"
                            active-text="启用"
                            inactive-text="禁用"
                        />
                    </el-form-item>
                </el-col>
            </el-row>
            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :lg="8">
                    <el-form-item label="成功充值次数达到" prop="recharge_count_threshold">
                        <el-input-number
                            v-model="formData.recharge_count_threshold"
                            :min="0"
                            :precision="0"
                            :step="1"
                            controls-position="right"
                            class="form-control"
                        />
                    </el-form-item>
                </el-col>
                <el-col :xs="24" :sm="12" :lg="10">
                    <el-form-item label="追加显示支付方式" prop="recharge_pay_types">
                        <el-select
                            v-model="formData.recharge_pay_types"
                            multiple
                            filterable
                            collapse-tags
                            collapse-tags-tooltip
                            placeholder="请选择充值支付方式"
                            class="form-control"
                        >
                            <el-option
                                v-for="item in rechargePayTypeOptions"
                                :key="item.unique_tag"
                                :label="`${item.name || item.unique_tag} (${item.unique_tag})`"
                                :value="item.unique_tag"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-divider />
            <el-form-item>
                <el-button type="primary" :loading="submitting" @click="submit">保存配置</el-button>
                <el-button :loading="loading" @click="getInfo">刷新</el-button>
            </el-form-item>
        </el-form>
    </ContentWrap>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import createAxios from '/@/utils/axios'

defineOptions({
    name: 'payment/smart-control',
})

interface SmartControlConfig {
    withdraw_amount_enabled: 0 | 1
    withdraw_amount_threshold: number
    withdraw_pay_types: string[]
    recharge_count_enabled: 0 | 1
    recharge_count_threshold: number
    recharge_pay_types: string[]
}

interface PayTypeOption {
    unique_tag: string
    name: string
    status: number
}

const formRef = ref<FormInstance>()
const loading = ref(false)
const submitting = ref(false)
const withdrawPayTypeOptions = ref<PayTypeOption[]>([])
const rechargePayTypeOptions = ref<PayTypeOption[]>([])

const formData = reactive<SmartControlConfig>({
    withdraw_amount_enabled: 0,
    withdraw_amount_threshold: 0,
    withdraw_pay_types: [],
    recharge_count_enabled: 0,
    recharge_count_threshold: 0,
    recharge_pay_types: [],
})

const rules: FormRules = {
    withdraw_amount_threshold: [{ required: true, type: 'number', min: 0, message: '提现金额阈值不能小于 0', trigger: 'change' }],
    recharge_count_threshold: [{ required: true, type: 'number', min: 0, message: '充值次数阈值不能小于 0', trigger: 'change' }],
}

const normalizeStringList = (value: unknown): string[] => {
    if (!Array.isArray(value)) {
        return []
    }
    return Array.from(new Set(value.map((item) => String(item).trim()).filter(Boolean)))
}

const fillForm = (config: Partial<SmartControlConfig>) => {
    formData.withdraw_amount_enabled = Number(config.withdraw_amount_enabled || 0) ? 1 : 0
    formData.withdraw_amount_threshold = Number(config.withdraw_amount_threshold || 0)
    formData.withdraw_pay_types = normalizeStringList(config.withdraw_pay_types)
    formData.recharge_count_enabled = Number(config.recharge_count_enabled || 0) ? 1 : 0
    formData.recharge_count_threshold = Number(config.recharge_count_threshold || 0)
    formData.recharge_pay_types = normalizeStringList(config.recharge_pay_types)
}

const getInfo = async () => {
    loading.value = true
    try {
        const res = await createAxios({
            url: '/admin/payment.SmartControl/detail',
            method: 'get',
        })
        fillForm(res.data.config || {})
        withdrawPayTypeOptions.value = res.data.options?.withdraw_pay_types || []
        rechargePayTypeOptions.value = res.data.options?.recharge_pay_types || []
    } finally {
        loading.value = false
    }
}

const submit = async () => {
    await formRef.value?.validate()
    submitting.value = true
    try {
        const res = await createAxios({
            url: '/admin/payment.SmartControl/edit',
            method: 'post',
            data: formData,
        })
        if (res.code === 1) {
            ElMessage.success('保存成功')
            await getInfo()
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } finally {
        submitting.value = false
    }
}

onMounted(getInfo)
</script>

<style scoped lang="scss">
.form-control {
    width: 100%;
}
</style>
