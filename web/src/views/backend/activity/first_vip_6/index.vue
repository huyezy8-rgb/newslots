<template>
    <div class="activity-config-container">
        <ContentWrap title="6%VIP充值活动配置" v-loading="loading">
            <div class="config-content">
                <el-form ref="formRef" :model="formData" :rules="rules" label-width="120px" @submit.prevent status-icon class="config-form">
                    <!-- 活动标题 -->
                    <!--                    <el-form-item label="活动标题" prop="title">-->
                    <!--                        <el-input-->
                    <!--                            v-model="formData.title"-->
                    <!--                            placeholder="请输入活动标题"-->
                    <!--                            maxlength="255"-->
                    <!--                            show-word-limit-->
                    <!--                        />-->
                    <!--                    </el-form-item>-->

                    <!--                    &lt;!&ndash; 活动说明 &ndash;&gt;-->
                    <!--                    <el-form-item label="活动说明" prop="context">-->
                    <!--                        <el-input-->
                    <!--                            v-model="formData.context"-->
                    <!--                            type="textarea"-->
                    <!--                            :rows="4"-->
                    <!--                            placeholder="请输入活动说明内容"-->
                    <!--                            maxlength="1000"-->
                    <!--                            show-word-limit-->
                    <!--                        />-->
                    <!--                    </el-form-item>-->

                    <!-- 启用状态 -->
                    <el-form-item label="充值奖励状态" prop="enable_reward">
                        <el-switch v-model="formData.enable_reward" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" />
                    </el-form-item>

                    <!-- 奖励策略 -->
                    <el-form-item label="奖励策略" prop="reward_strategy">
                        <el-select v-model="formData.reward_strategy" placeholder="请选择奖励策略" style="width: 100%">
                            <el-option label="固定金额" value="fixed" />
                            <el-option label="随机范围" value="range" />
                            <el-option label="百分比" value="percent" />
                        </el-select>
                    </el-form-item>

                    <!-- 奖励值配置 -->
                    <el-form-item label="奖励值配置" prop="reward_value">
                        <div class="reward-value-container">
                            <!-- 固定金额 -->
                            <div v-if="formData.reward_strategy === 'fixed'" class="reward-strategy-item">
                                <el-input
                                    v-model.number="rewardValueConfig.fixed"
                                    placeholder="固定奖励金额"
                                    type="number"
                                    class="reward-input"
                                    :min="0"
                                >
                                    <template #append>元</template>
                                </el-input>
                            </div>

                            <!-- 随机范围 -->
                            <div v-if="formData.reward_strategy === 'range'" class="reward-strategy-item">
                                <el-input
                                    v-model.number="rewardValueConfig.min"
                                    placeholder="最小奖励金额"
                                    type="number"
                                    class="reward-input"
                                    :min="0"
                                >
                                    <template #append>元</template>
                                </el-input>
                                <span class="range-separator">至</span>
                                <el-input
                                    v-model.number="rewardValueConfig.max"
                                    placeholder="最大奖励金额"
                                    type="number"
                                    class="reward-input"
                                    :min="0"
                                >
                                    <template #append>元</template>
                                </el-input>
                            </div>

                            <!-- 百分比 -->
                            <div v-if="formData.reward_strategy === 'percent'" class="reward-strategy-item">
                                <el-input
                                    v-model.number="rewardValueConfig.percent"
                                    placeholder="奖励百分比"
                                    type="number"
                                    class="reward-input"
                                    :min="0"
                                    :max="100"
                                >
                                    <template #append>%</template>
                                </el-input>
                            </div>
                        </div>
                    </el-form-item>
                    <!-- 金额配置 -->
                    <el-form-item label="金额配置" prop="amount_list">
                        <div class="amount-list-container">
                            <div class="amount-item">
                                <el-input
                                    v-model.number="amountConfig.amount"
                                    placeholder="金额"
                                    type="number"
                                    class="amount-input"
                                    :min="0"
                                    @change="validateAmountConfig"
                                >
                                    <template #append>元</template>
                                </el-input>

                                <el-input
                                    v-model.number="amountConfig.reward_percent"
                                    placeholder="奖励百分比"
                                    type="number"
                                    class="percent-input"
                                    :min="0"
                                    :max="100"
                                    @change="validateAmountConfig"
                                >
                                    <template #append>%</template>
                                </el-input>

                                <el-checkbox v-model="amountConfig.recommend" class="recommend-checkbox"> 推荐 </el-checkbox>
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
                            <h5>基础配置:</h5>
                            <pre>{{
                                prettyPrintJSON({
                                    title: formData.title,
                                    context: formData.context,
                                    enable_reward: formData.enable_reward,
                                    reward_strategy: formData.reward_strategy,
                                })
                            }}</pre>
                        </div>
                        <div class="json-section">
                            <h5>金额配置:</h5>
                            <pre>{{ prettyPrintJSON([amountConfig]) }}</pre>
                        </div>
                        <div class="json-section">
                            <h5>支付通道配置:</h5>
                            <pre>{{ prettyPrintJSON(payChannelsFields) }}</pre>
                        </div>
                        <div class="json-section">
                            <h5>奖励值配置:</h5>
                            <pre>{{ prettyPrintJSON(rewardValueConfig) }}</pre>
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
interface FirstVip6Config {
    id: number
    title: string
    context: string
    amount_list: string
    pay_channels: string
    reward_value: string
    enable_reward: number
    reward_strategy: string
    update_time?: number
}

interface AmountItem {
    id?: number
    amount: number
    reward_percent: number
    recommend: boolean
}

interface PayChannel {
    id?: string
    channel: string
    reward_percent: number
}

interface RewardValue {
    fixed?: number
    min?: number
    max?: number
    percent?: number
}

// API初始化
const api = new baTableApi('/admin/activity.first_vip_6/')
const formRef = ref<FormInstance>()
const submitting = ref(false)
const loading = ref(false)

// 表单数据
const formData = reactive<FirstVip6Config>({
    id: 1,
    title: '6%VIP充值活动',
    context: '首次充值享受6%VIP奖励',
    amount_list: '[]',
    pay_channels: '[]',
    reward_value: '{}',
    enable_reward: 1,
    reward_strategy: 'percent',
})

// 动态字段
const amountConfig = reactive<AmountItem>({
    amount: 100,
    reward_percent: 6,
    recommend: true,
})
const payChannelsFields = ref<PayChannel[]>([])
const rewardValueConfig = reactive<RewardValue>({
    percent: 6,
})

// 表单验证规则
const rules = {
    title: [
        { required: true, message: '请输入活动标题', trigger: 'blur' },
        { max: 255, message: '标题长度不能超过255个字符', trigger: 'blur' },
    ],
    context: [{ required: true, message: '请输入活动说明', trigger: 'blur' }],
    enable_reward: [{ required: true, message: '请选择启用状态', trigger: 'change' }],
    reward_strategy: [{ required: true, message: '请选择奖励策略', trigger: 'change' }],
    amount_list: [
        {
            validator: (rule: any, value: string, callback: any) => {
                if (amountConfig.amount <= 0) {
                    callback(new Error('金额必须大于0'))
                    return
                }
                if (amountConfig.reward_percent < 0 || amountConfig.reward_percent > 100) {
                    callback(new Error('奖励百分比必须在0-100之间'))
                    return
                }
                callback()
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
    reward_value: [
        {
            validator: (rule: any, value: string, callback: any) => {
                const strategy = formData.reward_strategy
                if (strategy === 'fixed' && (!rewardValueConfig.fixed || rewardValueConfig.fixed < 0)) {
                    callback(new Error('固定奖励金额必须大于等于0'))
                    return
                }
                if (strategy === 'range') {
                    if (!rewardValueConfig.min || rewardValueConfig.min < 0) {
                        callback(new Error('最小奖励金额必须大于等于0'))
                        return
                    }
                    if (!rewardValueConfig.max || rewardValueConfig.max < 0) {
                        callback(new Error('最大奖励金额必须大于等于0'))
                        return
                    }
                    if (rewardValueConfig.min > rewardValueConfig.max) {
                        callback(new Error('最小奖励金额不能大于最大奖励金额'))
                        return
                    }
                }
                if (strategy === 'percent' && (!rewardValueConfig.percent || rewardValueConfig.percent < 0 || rewardValueConfig.percent > 100)) {
                    callback(new Error('奖励百分比必须在0-100之间'))
                    return
                }
                callback()
            },
            trigger: 'blur',
        },
    ],
}

// 验证金额配置
const validateAmountConfig = () => {
    if (amountConfig.amount < 0) {
        ElMessage.warning('金额不能为负数')
        amountConfig.amount = 0
    }
    if (amountConfig.reward_percent < 0) {
        amountConfig.reward_percent = 0
    }
    if (amountConfig.reward_percent > 100) {
        amountConfig.reward_percent = 100
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
    () => formData.amount_list,
    () => {
        try {
            const parsed = JSON.parse(formData.amount_list || '[]')
            if (parsed && parsed.length > 0) {
                const item = parsed[0]
                amountConfig.amount = Number(item.amount) || 100
                amountConfig.reward_percent = Number(item.reward_percent) || 6
                amountConfig.recommend = !!item.recommend
            }
        } catch {
            // 使用默认值
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
    () => formData.reward_value,
    () => {
        try {
            const parsed = JSON.parse(formData.reward_value || '{}')
            if (parsed) {
                rewardValueConfig.fixed = Number(parsed.fixed) || 0
                rewardValueConfig.min = Number(parsed.min) || 0
                rewardValueConfig.max = Number(parsed.max) || 0
                rewardValueConfig.percent = Number(parsed.percent) || 6
            }
        } catch {
            // 使用默认值
        }
    },
    { immediate: true }
)

watch(
    amountConfig,
    () => {
        formData.amount_list = JSON.stringify([amountConfig], null, 2)
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

watch(
    rewardValueConfig,
    () => {
        formData.reward_value = JSON.stringify(rewardValueConfig, null, 2)
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
            amount_list: JSON.stringify([amountConfig]),
            pay_channels: JSON.stringify(payChannelsFields.value),
            reward_value: JSON.stringify(rewardValueConfig),
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
            formData.title = row.title || '6%VIP充值活动'
            formData.context = row.context || '首次充值享受6%VIP奖励'
            formData.enable_reward = row.enable_reward ?? 1
            formData.reward_strategy = row.reward_strategy || 'percent'

            // 处理amount_list
            if (row.amount_list && typeof row.amount_list === 'object') {
                const amountArray = objToArray(row.amount_list)
                if (amountArray.length > 0) {
                    const item = amountArray[0]
                    amountConfig.amount = Number(item.amount) || 100
                    amountConfig.reward_percent = Number(item.reward_percent) || 6
                    amountConfig.recommend = !!item.recommend
                }
                formData.amount_list = JSON.stringify([amountConfig], null, 2)
            } else {
                try {
                    const parsed = JSON.parse(row.amount_list || '[]')
                    if (parsed && parsed.length > 0) {
                        const item = parsed[0]
                        amountConfig.amount = Number(item.amount) || 100
                        amountConfig.reward_percent = Number(item.reward_percent) || 6
                        amountConfig.recommend = !!item.recommend
                    }
                    formData.amount_list = JSON.stringify([amountConfig], null, 2)
                } catch {
                    formData.amount_list = JSON.stringify([amountConfig], null, 2)
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

            // 处理reward_value
            if (row.reward_value && typeof row.reward_value === 'object') {
                rewardValueConfig.fixed = Number(row.reward_value.fixed) || 0
                rewardValueConfig.min = Number(row.reward_value.min) || 0
                rewardValueConfig.max = Number(row.reward_value.max) || 0
                rewardValueConfig.percent = Number(row.reward_value.percent) || 6
                formData.reward_value = JSON.stringify(rewardValueConfig, null, 2)
            } else {
                try {
                    const parsed = JSON.parse(row.reward_value || '{}')
                    rewardValueConfig.fixed = Number(parsed.fixed) || 0
                    rewardValueConfig.min = Number(parsed.min) || 0
                    rewardValueConfig.max = Number(parsed.max) || 0
                    rewardValueConfig.percent = Number(parsed.percent) || 6
                    formData.reward_value = JSON.stringify(rewardValueConfig, null, 2)
                } catch {
                    formData.reward_value = JSON.stringify(rewardValueConfig, null, 2)
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

/* 奖励值配置容器 */
.reward-value-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.reward-strategy-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #fafbfc;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
}

.range-separator {
    color: #606266;
    font-weight: 500;
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
    .channel-item,
    .reward-strategy-item {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }

    .amount-input,
    .channel-input,
    .percent-input,
    .reward-input {
        width: 100%;
    }

    .delete-btn {
        margin-left: 0;
        align-self: flex-end;
    }

    .range-separator {
        text-align: center;
        margin: 8px 0;
    }
}
</style>
