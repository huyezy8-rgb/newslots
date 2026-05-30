<template>
    <ContentWrap title="充值配置">
        <el-form ref="formRef" :model="formData" :rules="rules" label-width="140px" @submit.prevent status-icon>
            <el-form-item label="启用充值奖励" prop="enable_reward">
                <el-switch v-model="formData.enable_reward" :active-value="1" :inactive-value="0" />
            </el-form-item>

            <el-form-item label="奖励策略" prop="reward_strategy">
                <el-select class="amount-input" v-model="formData.reward_strategy" placeholder="请选择奖励策略">
                    <el-option label="固定金额" value="fixed" />
                    <el-option label="区间随机" value="range" />
                    <el-option label="百分比" value="percent" />
                </el-select>
            </el-form-item>

            <el-form-item label="奖励值配置" prop="reward_value">
                <template v-if="formData.reward_strategy === 'range'">
                    <el-input v-model="rewardValueFields.min" class="amount-input" placeholder="最小值" type="number" />
                    <el-input v-model="rewardValueFields.max" class="amount-input" placeholder="最大值" type="number" />
                </template>
                <template v-else-if="formData.reward_strategy === 'fixed'">
                    <el-input v-model="rewardValueFields.fixed" class="amount-input" placeholder="固定金额" type="number" />
                </template>
                <template v-else-if="formData.reward_strategy === 'percent'">
                    <el-input v-model="rewardValueFields.percent" class="percent-input" placeholder="奖励百分比" type="number">
                        <template #append>%</template>
                    </el-input>
                </template>
            </el-form-item>

            <el-form-item label="金额配置" prop="amount_list">
                <div class="mb-3">
                    <el-button type="primary" @click="addAmountItem" size="small">新增金额项</el-button>
                </div>

                <div class="amount-list-container">
                    <div v-for="(item, index) in amountListFields" :key="index" class="amount-item">
                        <el-input
                            v-model.number="item.amount"
                            placeholder="金额"
                            type="number"
                            class="amount-input"
                            :min="0"
                            @change="validateAmountItem(item)"
                        />

                        <el-checkbox v-model="item.recommend" class="recommend-checkbox">推荐</el-checkbox>

                        <el-input
                            v-model.number="item.reward_percent"
                            placeholder="奖励百分比"
                            type="number"
                            class="percent-input"
                            :min="0"
                            :max="100"
                            @change="validateAmountItem(item)"
                        >
                            <template #append>%</template>
                        </el-input>

                        <el-button type="danger" icon="el-icon-Delete" @click="removeAmountItem(index)" circle plain class="delete-btn" />
                    </div>
                </div>
            </el-form-item>

            <el-form-item>
                <el-button type="primary" @click="submit" :loading="submitting">保存配置</el-button>
            </el-form-item>
        </el-form>

        <div class="json-preview">
            <h4>奖励值 JSON</h4>
            <pre>{{ prettyPrintJSON(filteredRewardValue) }}</pre>

            <h4>金额配置 JSON</h4>
            <pre>{{ prettyPrintJSON(amountListFields) }}</pre>
        </div>
    </ContentWrap>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { baTableApi } from '/@/api/common'

interface RechargeConfig {
    id: number
    enable_reward: 0 | 1
    reward_strategy: 'fixed' | 'range' | 'percent' | ''
    reward_value: string
    amount_list: string
}

interface AmountItem {
    amount: number
    recommend: boolean
    reward_percent: number
}

const api = new baTableApi('/admin/recharge.Config/')
const formRef = ref()
const submitting = ref(false)

const formData = reactive<RechargeConfig>({
    id: 1,
    enable_reward: 0,
    reward_strategy: '',
    reward_value: '',
    amount_list: '',
})

const rewardValueFields = reactive<Record<string, any>>({})
const amountListFields = ref<AmountItem[]>([])

const filteredRewardValue = computed(() => {
    if (formData.reward_strategy === 'range') {
        return { min: rewardValueFields.min, max: rewardValueFields.max }
    }
    if (formData.reward_strategy === 'fixed') {
        return { fixed: rewardValueFields.fixed }
    }
    if (formData.reward_strategy === 'percent') {
        return { percent: rewardValueFields.percent }
    }
    return {}
})

const getDefaultAmountItem = (): AmountItem => {
    const lastAmount = amountListFields.value.length > 0 ? amountListFields.value[amountListFields.value.length - 1].amount : 0
    return {
        amount: lastAmount + 100,
        recommend: false,
        reward_percent: 0,
    }
}

const addAmountItem = () => amountListFields.value.push(getDefaultAmountItem())
const removeAmountItem = (index: number) => amountListFields.value.splice(index, 1)

const validateAmountItem = (item: AmountItem) => {
    if (item.amount < 0) {
        ElMessage.warning('金额不能为负数')
        item.amount = 0
    }
    if (item.reward_percent < 0) {
        item.reward_percent = 0
    } else if (item.reward_percent > 100) {
        item.reward_percent = 100
    }
}

const rules = {
    reward_strategy: [{ required: true, message: '请选择奖励策略', trigger: 'change' }],
    enable_reward: [{ required: true, message: '请设置奖励开关', trigger: 'change' }],
    reward_value: [
        {
            validator: (_rule: unknown, value: string, callback: (error?: Error) => void) => {
                try {
                    const val = JSON.parse(value || '{}')
                    if (formData.reward_strategy === 'range' && (!val.min || !val.max)) {
                        callback(new Error('最小值和最大值不能为空'))
                    } else if (formData.reward_strategy === 'fixed' && !val.fixed) {
                        callback(new Error('固定金额不能为空'))
                    } else if (formData.reward_strategy === 'percent' && !val.percent) {
                        callback(new Error('百分比不能为空'))
                    } else {
                        callback()
                    }
                } catch {
                    callback(new Error('JSON 格式错误'))
                }
            },
            trigger: 'blur',
        },
    ],
    amount_list: [
        {
            validator: (_rule: unknown, _value: string, callback: (error?: Error) => void) => {
                if (amountListFields.value.length === 0) {
                    callback(new Error('至少需要配置一个金额项'))
                    return
                }

                const hasError = amountListFields.value.some((item) => item.amount <= 0 || item.reward_percent < 0 || item.reward_percent > 100)
                hasError ? callback(new Error('请检查金额配置')) : callback()
            },
            trigger: 'blur',
        },
    ],
}

const objToArray = (obj: any) => {
    if (!obj) return []
    if (Array.isArray(obj)) return obj
    return Object.keys(obj)
        .sort((a, b) => Number(a) - Number(b))
        .map((key) => obj[key])
}

watch(
    () => [formData.reward_strategy, formData.reward_value],
    () => {
        try {
            const parsed = JSON.parse(formData.reward_value || '{}')
            Object.keys(rewardValueFields).forEach((key) => delete rewardValueFields[key])
            Object.assign(rewardValueFields, parsed)
        } catch {
            Object.keys(rewardValueFields).forEach((key) => delete rewardValueFields[key])
        }
    },
    { immediate: true }
)

watch(
    () => formData.amount_list,
    () => {
        try {
            const parsed = JSON.parse(formData.amount_list || '[]')
            amountListFields.value = objToArray(parsed).map((item) => ({
                amount: Number(item.amount) || 0,
                recommend: !!item.recommend,
                reward_percent: Number(item.reward_percent) || 0,
            }))
        } catch {
            amountListFields.value = []
        }
    },
    { immediate: true }
)

watch(
    rewardValueFields,
    () => {
        formData.reward_value = JSON.stringify(filteredRewardValue.value, null, 2)
    },
    { deep: true }
)

watch(
    amountListFields,
    () => {
        formData.amount_list = JSON.stringify(amountListFields.value, null, 2)
    },
    { deep: true }
)

const submit = async () => {
    try {
        await formRef.value.validate()
        submitting.value = true

        const postData = {
            ...formData,
            reward_value: JSON.stringify(filteredRewardValue.value),
            amount_list: JSON.stringify(amountListFields.value),
        }

        const res = await api.postData('edit', postData)
        if (res.code === 1) {
            ElMessage.success('保存成功')
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (error) {
        handleError(error)
    } finally {
        submitting.value = false
    }
}

const handleError = (error: unknown) => {
    if (error instanceof Error) {
        ElMessage.error(`操作失败: ${error.message}`)
    } else if (typeof error === 'string') {
        ElMessage.error(error)
    } else {
        ElMessage.error('发生未知错误')
    }
    console.error(error)
}

const getInfo = async () => {
    try {
        const res = await api.edit({ id: 1 })
        if (res.code === 1) {
            const row = res.data.row
            formData.id = row.id || 1
            formData.enable_reward = (Number(row.enable_reward) || 0) as 0 | 1
            formData.reward_strategy = row.reward_strategy || ''
            formData.reward_value = JSON.stringify(row.reward_value ?? {}, null, 2)
            formData.amount_list = JSON.stringify(row.amount_list ?? [], null, 2)
        } else {
            ElMessage.error(res.msg || '加载配置失败')
        }
    } catch (error) {
        handleError(error)
    }
}

onMounted(getInfo)

const prettyPrintJSON = (obj: any) => {
    try {
        return JSON.stringify(obj, null, 2)
    } catch {
        return '{}'
    }
}
</script>

<style scoped>
.amount-list-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.amount-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 4px;
    transition: all 0.3s;
}

.amount-item:hover {
    background: #f1f3f5;
}

.amount-input {
    width: 180px;
}

.percent-input {
    width: 150px;
}

.recommend-checkbox {
    margin: 0 12px;
}

.delete-btn {
    margin-left: auto;
    flex-shrink: 0;
}

.mb-3 {
    margin-bottom: 12px;
}

.json-preview {
    margin-top: 24px;
    padding: 16px;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    background-color: #f8f9fa;
    font-family: Consolas, monospace;
    font-size: 14px;
    line-height: 1.5;
    max-height: 400px;
    overflow-y: auto;
}

.json-preview h4 {
    margin-top: 0;
    margin-bottom: 12px;
    color: #495057;
}

@media (max-width: 768px) {
    .amount-item {
        flex-wrap: wrap;
    }

    .amount-input,
    .percent-input {
        width: 100%;
    }

    .delete-btn {
        margin-left: 0;
        margin-top: 8px;
    }

    .recommend-checkbox {
        margin: 8px 0;
    }
}
</style>
