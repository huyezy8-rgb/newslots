<template>
    <ContentWrap title="充值配置">
                <el-form
                    ref="formRef"
            :model="formData"
            :rules="rules"
            label-width="120px"
            @submit.prevent
            status-icon
        >
            <!-- 开关 -->
            <el-form-item label="启用充值奖励" prop="enable_reward">
                <el-switch v-model="formData.enable_reward" :active-value="1" :inactive-value="0" />
            </el-form-item>

            <!-- 奖励策略 -->
            <el-form-item label="奖励策略" prop="reward_strategy">
                <el-select   class="amount-input" v-model="formData.reward_strategy" placeholder="请选择">
                    <el-option label="固定金额 (fixed)" value="fixed" />
                    <el-option label="区间随机 (range)" value="range" />
                    <el-option label="百分比 (percent)" value="percent" />
                </el-select>
            </el-form-item>

            <!-- 奖励值配置 -->
            <el-form-item label="奖励值配置" prop="reward_value">
                <template v-if="formData.reward_strategy === 'range'">
                    <el-input
                        v-model="rewardValueFields.min"
                        placeholder="请输入最小值 min"
                         class="amount-input"
                        type="number"
                    />
                    <el-input
                        v-model="rewardValueFields.max"
                        class="amount-input"
                        placeholder="请输入最大值 max"
                        type="number"
                    />
                </template>
                <template v-else-if="formData.reward_strategy === 'fixed'">
                    <el-input
                        v-model="rewardValueFields.fixed"
                         class="amount-input"
                        placeholder="请输入固定奖励值 fixed"
                        type="number"
                    />
                </template>
                <template v-else-if="formData.reward_strategy === 'percent'">
                    <el-input
                        v-model="rewardValueFields.percent"
                        class="percent-input"
                        placeholder="请输入奖励百分比 percent"
                        type="number"
                    />
                </template>
            </el-form-item>

            <!-- 金额配置 -->
            <el-form-item label="金额配置" prop="amount_list">
                <div class="mb-3">
                    <el-button
                        type="primary"
                        @click="addAmountItem"
                        size="small"
                        icon="el-icon-plus"
                    >
                        新增金额项
                    </el-button>
                                    </div>

                <div class="amount-list-container">
                    <div
                        v-for="(item, index) in amountListFields"
                        :key="item.id || index"
                        class="amount-item"
                    >
                        <el-input
                            v-model.number="item.amount"
                            placeholder="金额"
                            type="number"
                            class="amount-input"
                            :min="0"
                            :controls-position="right"
                            @change="validateAmountItem(item)"
                        >
                            <template #append>元</template>
                        </el-input>

                        <el-checkbox
                            v-model="item.recommend"
                            class="recommend-checkbox"
                        >
                            推荐
                        </el-checkbox>

                        <el-input
                            v-model.number="item.reward_percent"
                            placeholder="奖励百分比"
                            type="number"
                            class="percent-input"
                            :min="0"
                            :max="100"
                            :controls-position="right"
                            @change="validateAmountItem(item)"
                        >
                            <template #append>%</template>
                        </el-input>

                        <el-button
                            type="danger"
                            icon="el-icon-Delete"
                            @click="removeAmountItem(index)"
                            circle
                            plain
                            class="delete-btn"
                        />
                            </div>
                            </div>
            </el-form-item>

            <!-- 支付通道 -->
            <el-form-item label="支付通道" prop="pay_channels">
                <div class="mb-3">
                    <el-button
                        type="primary"
                        @click="addPayChannel"
                        size="small"
                        icon="el-icon-plus"
                    >
                        新增通道
                    </el-button>
                </div>

                <div class="channel-list-container">
                    <div
                        v-for="(item, index) in payChannelsFields"
                        :key="item.id || index"
                        class="channel-item"
                    >
                        <el-input
                            v-model="item.channel"
                            placeholder="通道标识"
                            class="channel-input"
                        />

                        <el-input
                            v-model.number="item.reward_percent"
                            placeholder="奖励百分比"
                            type="number"
                            class="percent-input"
                            :min="0"
                            :max="100"
                            :controls-position="right"
                        >
                            <template #append>%</template>
                        </el-input>

                        <el-button
                            type="danger"
                            icon="el-icon-Delete"
                            @click="removePayChannel(index)"
                            circle
                            plain
                            class="delete-btn"
                        />
                    </div>
                </div>
            </el-form-item>

            <!-- 提交按钮 -->
            <el-form-item>
                <el-button
                    type="primary"
                    @click="submit"
                    :loading="submitting"
                >
                    保存配置
                </el-button>
            </el-form-item>
        </el-form>

        <!-- JSON预览 -->
        <div class="json-preview">
            <h4>奖励值 JSON</h4>
            <pre>{{ prettyPrintJSON(filteredRewardValue) }}</pre>

            <h4>金额配置 JSON</h4>
            <pre>{{ prettyPrintJSON(amountListFields) }}</pre>

            <h4>支付通道 JSON</h4>
            <pre>{{ prettyPrintJSON(payChannelsFields) }}</pre>
    </div>
    </ContentWrap>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, reactive, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { baTableApi } from '/@/api/common'

// 类型定义
interface RechargeConfig {
    id: number;
    enable_reward: 0 | 1;
    reward_strategy: 'fixed' | 'range' | 'percent' | '';
    reward_value: string;
    amount_list: string;
    pay_channels: string;
}

interface AmountItem {
    id?: number;
    amount: number;
    recommend: boolean;
    reward_percent: number;
}

interface PayChannel {
    id?: string;
    channel: string;
    reward_percent: number;
}

// API初始化
const api = new baTableApi('/admin/recharge.Config/')
const formRef = ref()
const submitting = ref(false)

// 表单数据
const formData = reactive<RechargeConfig>({
    id: 1,
    enable_reward: 0,
    reward_strategy: '',
    reward_value: '',
    amount_list: '',
    pay_channels: '',
})

// 动态字段
const rewardValueFields = reactive<Record<string, any>>({})
const amountListFields = ref<AmountItem[]>([])
const payChannelsFields = ref<PayChannel[]>([])

// 计算属性
const filteredRewardValue = computed(() => {
    if (formData.reward_strategy === 'range') {
        return { min: rewardValueFields.min, max: rewardValueFields.max }
    } else if (formData.reward_strategy === 'fixed') {
        return { fixed: rewardValueFields.fixed }
    } else if (formData.reward_strategy === 'percent') {
        return { percent: rewardValueFields.percent }
    }
    return {}
})



// 获取默认金额项
const getDefaultAmountItem = (): AmountItem => {
    const lastAmount = amountListFields.value.length > 0
        ? amountListFields.value[amountListFields.value.length - 1].amount
        : 0;

    return {
        amount: lastAmount + 100,
        recommend: false,
        reward_percent: 0,
    }
}

// 金额项操作
const addAmountItem = () => amountListFields.value.push(getDefaultAmountItem())
const removeAmountItem = (index: number) => amountListFields.value.splice(index, 1)

// 验证金额项
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

// 支付通道操作
const addPayChannel = () => {
    payChannelsFields.value.push({
        channel: '',
        reward_percent: 0,
    })
}
const removePayChannel = (index: number) => payChannelsFields.value.splice(index, 1)

// 表单验证规则
const rules = {
    reward_strategy: [
        { required: true, message: '请选择奖励策略', trigger: 'change' }
    ],
    enable_reward: [
        { required: true, type: 'number', message: '请设置开关', trigger: 'change' }
    ],
    reward_value: [
        {
            validator: (rule, value, callback) => {
                try {
                    const val = JSON.parse(value)
                    if (formData.reward_strategy === 'range' && (!val.min || !val.max)) {
                        callback(new Error('必须填写最小值和最大值'))
                    } else if (formData.reward_strategy === 'fixed' && !val.fixed) {
                        callback(new Error('必须填写固定值'))
                    } else if (formData.reward_strategy === 'percent' && !val.percent) {
                        callback(new Error('必须填写百分比值'))
                    } else {
                        callback()
                    }
                } catch {
                    callback(new Error('JSON格式错误'))
                }
            },
            trigger: 'blur'
        }
    ],
    amount_list: [
        {
            validator: (rule, value, callback) => {
                if (amountListFields.value.length === 0) {
                    callback(new Error('至少需要配置一个金额项'))
                    return
                }

                const hasError = amountListFields.value.some(item =>
                    item.amount <= 0 ||
                    item.reward_percent < 0 ||
                    item.reward_percent > 100
                )

                hasError ? callback(new Error('请检查金额项配置')) : callback()
            },
            trigger: 'blur'
        }
    ]
}

// 数据转换
const objToArray = (obj: any) => {
    if (!obj) return []
    if (Array.isArray(obj)) return obj
    return Object.keys(obj)
        .sort((a, b) => Number(a) - Number(b))
        .map(key => obj[key])
}

// 数据监听
watch(() => [formData.reward_strategy, formData.reward_value], () => {
    try {
        const parsed = JSON.parse(formData.reward_value || '{}')
        Object.assign(rewardValueFields, parsed)
    } catch {
        Object.keys(rewardValueFields).forEach(k => delete rewardValueFields[k])
    }
}, { immediate: true })

watch(() => formData.amount_list, () => {
    try {
        const parsed = JSON.parse(formData.amount_list || '[]')
        amountListFields.value = objToArray(parsed).map(item => ({
            amount: Number(item.amount) || 0,
            recommend: !!item.recommend,
            reward_percent: Number(item.reward_percent) || 0,

        }))
    } catch {
        amountListFields.value = []
    }
}, { immediate: true })

watch(() => formData.pay_channels, () => {
    try {
        const parsed = JSON.parse(formData.pay_channels || '[]')
        payChannelsFields.value = objToArray(parsed).map(item => ({
            channel: item.channel || '',
            reward_percent: Number(item.reward_percent) || 0,
        }))
    } catch {
        payChannelsFields.value = []
    }
}, { immediate: true })

watch(rewardValueFields, () => {
    formData.reward_value = JSON.stringify(filteredRewardValue.value, null, 2)
}, { deep: true })

watch(amountListFields, () => {
    formData.amount_list = JSON.stringify(amountListFields.value, null, 2)
}, { deep: true })

watch(payChannelsFields, () => {
    formData.pay_channels = JSON.stringify(payChannelsFields.value, null, 2)
}, { deep: true })

// 提交表单
const submit = async () => {
    try {
        await formRef.value.validate()
        submitting.value = true

        const postData = {
            ...formData,
            reward_value: JSON.stringify(filteredRewardValue.value),
            amount_list: JSON.stringify(amountListFields.value),
            pay_channels: JSON.stringify(payChannelsFields.value),
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

// 错误处理
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

// 初始化加载数据
const getInfo = async () => {
    try {
        const res = await api.edit({ id: 1 })
        if (res.code === 1) {
            const row = res.data.row
            formData.id = row.id || 1
            formData.enable_reward = Number(row.enable_reward) || 0
            formData.reward_strategy = row.reward_strategy || ''
            formData.reward_value = JSON.stringify(row.reward_value ?? {}, null, 2)
            formData.amount_list = JSON.stringify(row.amount_list ?? [], null, 2)
            formData.pay_channels = JSON.stringify(row.pay_channels ?? [], null, 2)
        } else {
            ElMessage.error(res.msg || '加载配置失败')
        }
    } catch (error) {
        handleError(error)
    }
}

onMounted(getInfo)

// JSON美化输出
const prettyPrintJSON = (obj: any) => {
    try {
        return JSON.stringify(obj, null, 2)
    } catch {
        return '{}'
    }
}
</script>

<style scoped>
/* 金额配置容器 */
.amount-list-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* 支付通道容器 */
.channel-list-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* 单个配置项 */
.amount-item,
.channel-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 4px;
    transition: all 0.3s;
}

.amount-item:hover,
.channel-item:hover {
    background: #f1f3f5;
}

/* 输入框样式 */
.amount-input {
    width: 180px;
}

.channel-input {
    width: 200px;
}

.percent-input {
    width: 150px;
}

/* 推荐复选框 */
.recommend-checkbox {
    margin: 0 12px;
}

/* 删除按钮 */
.delete-btn {
    margin-left: auto;
    flex-shrink: 0;
}

/* 按钮间距 */
.mb-3 {
    margin-bottom: 12px;
}

/* JSON预览区域 */
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

/* 响应式调整 */
@media (max-width: 768px) {
    .amount-item,
    .channel-item {
        flex-wrap: wrap;
    }

    .amount-input,
    .channel-input,
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
