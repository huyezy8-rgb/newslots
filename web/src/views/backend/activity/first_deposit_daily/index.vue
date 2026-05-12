<template>
    <div class="activity-config-container">
        <ContentWrap title="每日首充活动配置" v-loading="loading">
            <div class="config-content">
                <el-form ref="formRef" :model="formData" :rules="rules" label-width="120px" @submit.prevent status-icon class="config-form">
                    <!--            &lt;!&ndash; 基本信息 &ndash;&gt;-->
                    <!--            <el-form-item label="配置标题" prop="title">-->
                    <!--                <el-input v-model="formData.title" placeholder="请输入配置标题" />-->
                    <!--            </el-form-item>-->

                    <!--            <el-form-item label="说明内容" prop="context">-->
                    <!--                <el-input-->
                    <!--                    v-model="formData.context"-->
                    <!--                    type="textarea"-->
                    <!--                    :rows="4"-->
                    <!--                    placeholder="请输入活动说明内容"-->
                    <!--                />-->
                    <!--            </el-form-item>-->

                    <!-- 开关 -->
                    <el-form-item label="启用充值奖励" prop="enable_reward">
                        <el-switch v-model="formData.enable_reward" :active-value="1" :inactive-value="0" />
                    </el-form-item>

                    <!-- 奖励策略 -->
                    <el-form-item label="奖励策略" prop="reward_strategy">
                        <el-select v-model="formData.reward_strategy" placeholder="请选择" class="strategy-select">
                            <el-option label="固定金额 (fixed)" value="fixed" />
                            <el-option label="区间随机 (range)" value="range" />
                            <el-option label="百分比 (percent)" value="percent" />
                        </el-select>
                    </el-form-item>

                    <!-- 奖励值配置 -->
                    <el-form-item label="奖励值配置" prop="reward_value">
                        <template v-if="formData.reward_strategy === 'range'">
                            <el-input v-model.number="rewardValueFields.min" placeholder="最小值" class="reward-input" type="number" />
                            <span class="mx-2">至</span>
                            <el-input v-model.number="rewardValueFields.max" placeholder="最大值" class="reward-input" type="number" />
                        </template>
                        <template v-else-if="formData.reward_strategy === 'fixed'">
                            <el-input v-model.number="rewardValueFields.fixed" placeholder="固定奖励值" class="reward-input" type="number" />
                        </template>
                        <template v-else-if="formData.reward_strategy === 'percent'">
                            <el-input v-model.number="rewardValueFields.percent" placeholder="奖励百分比" class="reward-input" type="number" />
                            <span class="ml-2">%</span>
                        </template>
                    </el-form-item>

                    <!-- 金额配置 -->
                    <el-form-item label="金额配置" prop="amount_list">
                        <div class="mb-3">
                            <el-button type="primary" @click="addAmountItem" size="small" icon="el-icon-plus"> 新增金额项 </el-button>
                        </div>

                        <div class="amount-list-container">
                            <div v-for="(item, index) in amountListFields" :key="item.id || index" class="amount-item">
                                <el-input
                                    v-model.number="item.amount"
                                    placeholder="金额"
                                    type="number"
                                    class="amount-input"
                                    :min="0"
                                    @change="validateAmountItem(item)"
                                >
                                    <template #append>元</template>
                                </el-input>

                                <el-checkbox v-model="item.recommend" class="recommend-checkbox"> 推荐 </el-checkbox>

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

                    <!-- 支付通道 -->
                    <el-form-item label="支付通道" prop="pay_channels">
                        <div class="mb-3">
                            <el-button type="primary" @click="addPayChannel" size="small" icon="el-icon-plus"> 新增通道 </el-button>
                        </div>

                        <div class="channel-list-container">
                            <div v-for="(item, index) in payChannelsFields" :key="item.id || index" class="channel-item">
                                <el-input v-model="item.channel" placeholder="通道标识" class="channel-input" />

                                <el-input
                                    v-model.number="item.reward_percent"
                                    placeholder="奖励百分比"
                                    type="number"
                                    class="percent-input"
                                    :min="0"
                                    :max="100"
                                >
                                    <template #append>%</template>
                                </el-input>

                                <el-button type="danger" icon="el-icon-Delete" @click="removePayChannel(index)" circle plain class="delete-btn" />
                            </div>
                        </div>
                    </el-form-item>

                    <!-- 任务奖励 -->
                    <el-form-item label="任务奖励" prop="task_reward">
                        <el-input v-model.number="formData.task_reward" placeholder="任务奖励金额" class="reward-input" type="number" :min="0">
                            <template #append>元</template>
                        </el-input>
                    </el-form-item>

                    <!-- 提交按钮 -->
                    <el-form-item>
                        <el-button type="primary" @click="submit" :loading="submitting"> 保存配置 </el-button>
                    </el-form-item>
                </el-form>

                <!-- JSON预览区域 -->
                <div class="json-preview">
                    <h4>配置预览</h4>
                    <div class="json-content">
                        <div class="json-section">
                            <h5>金额配置:</h5>
                            <pre>{{ prettyPrintJSON(amountListFields) }}</pre>
                        </div>
                        <div class="json-section">
                            <h5>支付通道配置:</h5>
                            <pre>{{ prettyPrintJSON(payChannelsFields) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </ContentWrap>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, reactive, computed } from 'vue'
import { ElMessage, type FormInstance } from 'element-plus'
import { baTableApi } from '/@/api/common'

// 类型定义
interface FirstDepositDailyConfig {
    id: number
    title: string
    context: string
    enable_reward: 0 | 1
    reward_strategy: string
    reward_value: string
    amount_list: string
    pay_channels: string
    task_reward: number
    update_time?: number
}

interface AmountItem {
    id?: number
    amount: number
    recommend: boolean
    reward_percent: number
}

interface PayChannel {
    id?: string
    channel: string
    reward_percent: number
}

// API初始化
const api = new baTableApi('/admin/activity.first_deposit_daily/')
const formRef = ref<FormInstance>()
const submitting = ref(false)
const loading = ref(false)

// 表单数据
const formData = reactive<FirstDepositDailyConfig>({
    id: 1,
    title: '',
    context: '',
    enable_reward: 0,
    reward_strategy: '',
    reward_value: '{}',
    amount_list: '[]',
    pay_channels: '[]',
    task_reward: 0,
})

// 动态字段
const rewardValueFields = reactive<Record<string, any>>({})
const amountListFields = ref<AmountItem[]>([])
const payChannelsFields = ref<PayChannel[]>([])

// 表单验证规则
const rules = {
    title: [{ required: true, message: '请输入配置标题', trigger: 'blur' }],
    context: [{ required: true, message: '请输入说明内容', trigger: 'blur' }],
    reward_strategy: [{ required: true, message: '请选择奖励策略', trigger: 'change' }],
    enable_reward: [{ required: true, type: 'number', message: '请设置开关', trigger: 'change' }],
    reward_value: [
        {
            validator: (rule: any, value: string, callback: any) => {
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
            trigger: 'blur',
        },
    ],
    amount_list: [
        {
            validator: (rule: any, value: string, callback: any) => {
                if (amountListFields.value.length === 0) {
                    callback(new Error('至少需要配置一个金额项'))
                    return
                }

                const hasError = amountListFields.value.some((item) => item.amount <= 0 || item.reward_percent < 0 || item.reward_percent > 100)

                hasError ? callback(new Error('请检查金额项配置')) : callback()
            },
            trigger: 'blur',
        },
    ],
    pay_channels: [
        {
            validator: (rule: any, value: string, callback: any) => {
                if (payChannelsFields.value.length === 0) {
                    callback(new Error('至少需要配置一个支付通道'))
                    return
                }

                const hasError = payChannelsFields.value.some((item) => !item.channel || item.reward_percent < 0 || item.reward_percent > 100)

                hasError ? callback(new Error('请检查支付通道配置')) : callback()
            },
            trigger: 'blur',
        },
    ],
    task_reward: [{ required: true, type: 'number', message: '请输入任务奖励', trigger: 'blur' }],
}

// 预览数据
const previewData = computed(() => ({
    ...formData,
    reward_value: filteredRewardValue.value,
    amount_list: amountListFields.value,
    pay_channels: payChannelsFields.value,
}))

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
    const lastAmount = amountListFields.value.length > 0 ? amountListFields.value[amountListFields.value.length - 1].amount : 0

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

// 数据转换
const objToArray = (obj: any) => {
    if (!obj) return []
    if (Array.isArray(obj)) return obj
    return Object.values(obj)
}

// 数据监听
watch(
    () => [formData.reward_strategy, formData.reward_value],
    () => {
        try {
            const parsed = JSON.parse(formData.reward_value || '{}')
            Object.assign(rewardValueFields, parsed)
        } catch {
            Object.keys(rewardValueFields).forEach((k) => delete rewardValueFields[k])
        }
    },
    { immediate: true }
)

watch(
    () => formData.amount_list,
    () => {
        try {
            const parsed = JSON.parse(formData.amount_list || '[]')
            amountListFields.value = objToArray(parsed).map((item: any) => ({
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
    () => formData.pay_channels,
    () => {
        try {
            const parsed = JSON.parse(formData.pay_channels || '[]')
            payChannelsFields.value = objToArray(parsed).map((item: any) => ({
                channel: item.channel || '',
                reward_percent: Number(item.reward_percent) || 0,
            }))
        } catch {
            payChannelsFields.value = []
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

watch(
    payChannelsFields,
    () => {
        formData.pay_channels = JSON.stringify(payChannelsFields.value, null, 2)
    },
    { deep: true }
)

// 提交表单
const submit = async () => {
    try {
        await formRef.value?.validate()

        submitting.value = true

        const postData = {
            ...formData,
            reward_value: JSON.stringify(filteredRewardValue.value),
            amount_list: JSON.stringify(amountListFields.value),
            pay_channels: JSON.stringify(payChannelsFields.value),
            update_time: Math.floor(Date.now() / 1000),
        }

        const res = await api.postData('edit', postData)
        if (res.code === 1) {
            ElMessage.success('保存成功')
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (error) {
        console.error('提交错误:', error)
        ElMessage.error('提交配置失败，请检查表单')
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
    loading.value = true
    try {
        const res = await api.edit({ id: 1 })
        if (res.code === 1) {
            const row = res.data.row

            // 基础数据
            formData.id = row.id || 1
            formData.title = row.title || ''
            formData.context = row.context || ''
            formData.enable_reward = Number(row.enable_reward) || 0
            formData.reward_strategy = row.reward_strategy || ''
            formData.task_reward = Number(row.task_reward) || 0

            // 处理reward_value
            if (typeof row.reward_value === 'object') {
                formData.reward_value = JSON.stringify(row.reward_value, null, 2)
                Object.assign(rewardValueFields, row.reward_value)
            } else {
                formData.reward_value = row.reward_value || '{}'
                try {
                    Object.assign(rewardValueFields, JSON.parse(row.reward_value || '{}'))
                } catch {}
            }

            // 处理amount_list
            if (row.amount_list && typeof row.amount_list === 'object') {
                amountListFields.value = objToArray(row.amount_list).map((item: any) => ({
                    amount: Number(item.amount) || 0,
                    recommend: !!item.recommend,
                    reward_percent: Number(item.reward_percent) || 0,
                }))
                formData.amount_list = JSON.stringify(amountListFields.value, null, 2)
            } else {
                try {
                    const parsed = JSON.parse(row.amount_list || '[]')
                    amountListFields.value = objToArray(parsed)
                    formData.amount_list = JSON.stringify(amountListFields.value, null, 2)
                } catch {
                    amountListFields.value = []
                    formData.amount_list = '[]'
                }
            }

            // 处理pay_channels
            if (row.pay_channels && typeof row.pay_channels === 'object') {
                payChannelsFields.value = objToArray(row.pay_channels).map((item: any) => ({
                    channel: item.channel || '',
                    reward_percent: Number(item.reward_percent) || 0,
                }))
                formData.pay_channels = JSON.stringify(payChannelsFields.value, null, 2)
            } else {
                try {
                    const parsed = JSON.parse(row.pay_channels || '[]')
                    payChannelsFields.value = objToArray(parsed)
                    formData.pay_channels = JSON.stringify(payChannelsFields.value, null, 2)
                } catch {
                    payChannelsFields.value = []
                    formData.pay_channels = '[]'
                }
            }
        } else {
            ElMessage.error(res.msg || '加载配置失败')
        }
    } catch (error) {
        handleError(error)
    } finally {
        loading.value = false
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
/* 活动配置容器 */
.activity-config-container {
    background: #f5f7fa;
    min-height: 100vh;
    padding: 20px;
}

.config-content {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.config-form {
    padding: 24px;
    background: #fff;
}

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
    padding: 16px;
    background: #fafbfc;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.amount-item:hover,
.channel-item:hover {
    background: #f0f2f5;
    border-color: #c0c4cc;
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

.reward-input {
    width: 120px;
}

.strategy-select {
    width: 200px;
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
    margin-top: 0;
    padding: 24px;
    border-top: 1px solid #e4e7ed;
    background-color: #fafbfc;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', Consolas, monospace;
    font-size: 13px;
    line-height: 1.6;
}

.json-preview h4 {
    margin-top: 0;
    margin-bottom: 16px;
    color: #303133;
    font-size: 16px;
    font-weight: 600;
}

.json-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.json-section {
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    padding: 16px;
}

.json-section h5 {
    margin: 0 0 12px 0;
    color: #409eff;
    font-size: 14px;
    font-weight: 600;
}

.json-section pre {
    margin: 0;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 4px;
    overflow-x: auto;
    color: #606266;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .activity-config-container {
        padding: 10px;
    }

    .config-form {
        padding: 16px;
    }

    .amount-item,
    .channel-item {
        flex-wrap: wrap;
    }

    .amount-input,
    .channel-input,
    .percent-input,
    .reward-input {
        width: 100%;
    }

    .delete-btn {
        margin-left: 0;
        margin-top: 8px;
    }

    .recommend-checkbox {
        margin: 8px 0;
    }

    .json-preview {
        padding: 16px;
    }
}
</style>
