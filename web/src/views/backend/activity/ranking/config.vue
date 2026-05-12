<template>
    <div class="ranking-config">
        <el-card class="box-card">
            <template #header>
                <div class="card-header">
                    <span>排行榜活动配置</span>
                </div>
            </template>

            <el-form ref="formRef" :model="formData" :rules="rules" label-width="120px" v-loading="loading">
                <!-- 基本信息 -->
                <el-divider content-position="left">基本信息</el-divider>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="活动名称" prop="name">
                            <el-input v-model="formData.name" placeholder="请输入活动名称" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="活动状态" prop="status">
                            <el-switch v-model="formData.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="打码倍数" prop="bet_multiple">
                            <el-input-number v-model="formData.bet_multiple" :min="1" :max="100" :precision="1" :step="0.5" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- 奖池比例配置 -->
                <el-divider content-position="left">奖池比例配置</el-divider>
                <el-row :gutter="20">
                    <el-col :span="8">
                        <el-form-item label="日榜奖池比例" prop="daily_pool_ratio">
                            <el-input-number v-model="formData.daily_pool_ratio" :min="0" :max="100" :precision="2" :step="0.1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="周榜奖池比例" prop="weekly_pool_ratio">
                            <el-input-number v-model="formData.weekly_pool_ratio" :min="0" :max="100" :precision="2" :step="0.1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="月榜奖池比例" prop="monthly_pool_ratio">
                            <el-input-number v-model="formData.monthly_pool_ratio" :min="0" :max="100" :precision="2" :step="0.1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- 榜单人数限制 -->
                <el-divider content-position="left">榜单人数限制</el-divider>
                <el-row :gutter="20">
                    <el-col :span="8">
                        <el-form-item label="日榜人数" prop="day_limit">
                            <el-input-number v-model="formData.day_limit" :min="1" :max="1000" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="周榜人数" prop="week_limit">
                            <el-input-number v-model="formData.week_limit" :min="1" :max="1000" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="月榜人数" prop="month_limit">
                            <el-input-number v-model="formData.month_limit" :min="1" :max="1000" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- 奖励配置 -->
                <el-divider content-position="left">奖励配置</el-divider>

                <!-- 通用奖励配置说明 -->
                <el-alert title="奖励配置说明" type="info" :closable="false" show-icon class="reward-alert">
                    <template #default>
                        <div class="alert-content">
                            <p><strong>配置规则：</strong></p>
                            <ul>
                                <li>排名范围：设置奖励的排名区间，如第1-3名、第4-10名等</li>
                                <li>奖励比例：该排名区间获得的奖励比例，基于总奖池计算</li>
                                <li>奖励计算：奖励金额 = 总奖池 × 奖励比例 × 排名人数</li>
                                <li>排名规则：按打码量从高到低排序，相同打码量按时间先后排序</li>
                            </ul>
                            <p><strong>注意事项：</strong></p>
                            <ul>
                                <li>排名范围不能重叠，建议按顺序配置</li>
                                <li>奖励比例总和建议不超过100%，超出部分可能影响奖励分配</li>
                                <li>日榜每日0点重置，周榜每周一0点重置，月榜每月1号0点重置</li>
                                <li>建议奖励比例：日榜 < 周榜 < 月榜，合理分配各榜单奖励</li>
                            </ul>
                        </div>
                    </template>
                </el-alert>

                <!-- 日榜奖励 -->
                <el-card class="reward-card" shadow="never">
                    <template #header>
                        <div class="reward-header">
                            <span>日榜奖励配置</span>
                            <el-button type="primary" size="small" @click="addReward('day')"> 添加奖励规则 </el-button>
                        </div>
                    </template>

                    <!-- 日榜统计信息 -->
                    <div class="reward-stats">
                        <el-row :gutter="20">
                            <el-col :span="8">
                                <div class="stat-item">
                                    <span class="stat-label">总奖励人数：</span>
                                    <span class="stat-value">{{ getTotalRewardCount(formData.day_rewards) }}人</span>
                                </div>
                            </el-col>
                            <el-col :span="8">
                                <div class="stat-item">
                                    <span class="stat-label">总奖励比例：</span>
                                    <span class="stat-value" :class="{ warning: getTotalRewardPercent(formData.day_rewards) > 100 }">
                                        {{ getTotalRewardPercent(formData.day_rewards).toFixed(2) }}%
                                    </span>
                                </div>
                            </el-col>
                            <el-col :span="8">
                                <div class="stat-item">
                                    <span class="stat-label">配置规则数：</span>
                                    <span class="stat-value">{{ formData.day_rewards.length }}条</span>
                                </div>
                            </el-col>
                        </el-row>
                    </div>

                    <div v-if="formData.day_rewards.length === 0" class="empty-rewards">
                        <el-empty description="暂无奖励配置" />
                    </div>
                    <div v-else>
                        <div v-for="(reward, index) in formData.day_rewards" :key="index" class="reward-item">
                            <el-row :gutter="10" align="middle">
                                <el-col :span="6">
                                    <el-form-item label="排名范围">
                                        <el-input-number v-model="reward.rank_start" :min="1" size="small" style="width: 80px" />
                                        <span class="mx-2">至</span>
                                        <el-input-number v-model="reward.rank_end" :min="reward.rank_start" size="small" style="width: 80px" />
                                    </el-form-item>
                                </el-col>
                                <el-col :span="6">
                                    <el-form-item label="奖励比例">
                                        <el-input-number
                                            v-model="reward.reward_percent"
                                            :min="0"
                                            :max="100"
                                            :precision="2"
                                            :step="0.1"
                                            size="small"
                                            style="width: 100px"
                                        />
                                        <span class="ml-1">%</span>
                                    </el-form-item>
                                </el-col>
                                <el-col :span="4">
                                    <el-button type="danger" size="small" @click="removeReward('day', index)"> 删除 </el-button>
                                </el-col>
                            </el-row>
                        </div>
                    </div>
                </el-card>

                <!-- 周榜奖励 -->
                <el-card class="reward-card" shadow="never">
                    <template #header>
                        <div class="reward-header">
                            <span>周榜奖励配置</span>
                            <el-button type="primary" size="small" @click="addReward('week')"> 添加奖励规则 </el-button>
                        </div>
                    </template>

                    <!-- 周榜统计信息 -->
                    <div class="reward-stats">
                        <el-row :gutter="20">
                            <el-col :span="8">
                                <div class="stat-item">
                                    <span class="stat-label">总奖励人数：</span>
                                    <span class="stat-value">{{ getTotalRewardCount(formData.week_rewards) }}人</span>
                                </div>
                            </el-col>
                            <el-col :span="8">
                                <div class="stat-item">
                                    <span class="stat-label">总奖励比例：</span>
                                    <span class="stat-value" :class="{ warning: getTotalRewardPercent(formData.week_rewards) > 100 }">
                                        {{ getTotalRewardPercent(formData.week_rewards).toFixed(2) }}%
                                    </span>
                                </div>
                            </el-col>
                            <el-col :span="8">
                                <div class="stat-item">
                                    <span class="stat-label">配置规则数：</span>
                                    <span class="stat-value">{{ formData.week_rewards.length }}条</span>
                                </div>
                            </el-col>
                        </el-row>
                    </div>

                    <div v-if="formData.week_rewards.length === 0" class="empty-rewards">
                        <el-empty description="暂无奖励配置" />
                    </div>
                    <div v-else>
                        <div v-for="(reward, index) in formData.week_rewards" :key="index" class="reward-item">
                            <el-row :gutter="10" align="middle">
                                <el-col :span="6">
                                    <el-form-item label="排名范围">
                                        <el-input-number v-model="reward.rank_start" :min="1" size="small" style="width: 80px" />
                                        <span class="mx-2">至</span>
                                        <el-input-number v-model="reward.rank_end" :min="reward.rank_start" size="small" style="width: 80px" />
                                    </el-form-item>
                                </el-col>
                                <el-col :span="6">
                                    <el-form-item label="奖励比例">
                                        <el-input-number
                                            v-model="reward.reward_percent"
                                            :min="0"
                                            :max="100"
                                            :precision="2"
                                            :step="0.1"
                                            size="small"
                                            style="width: 100px"
                                        />
                                        <span class="ml-1">%</span>
                                    </el-form-item>
                                </el-col>
                                <el-col :span="4">
                                    <el-button type="danger" size="small" @click="removeReward('week', index)"> 删除 </el-button>
                                </el-col>
                            </el-row>
                        </div>
                    </div>
                </el-card>

                <!-- 月榜奖励 -->
                <el-card class="reward-card" shadow="never">
                    <template #header>
                        <div class="reward-header">
                            <span>月榜奖励配置</span>
                            <el-button type="primary" size="small" @click="addReward('month')"> 添加奖励规则 </el-button>
                        </div>
                    </template>

                    <!-- 月榜统计信息 -->
                    <div class="reward-stats">
                        <el-row :gutter="20">
                            <el-col :span="8">
                                <div class="stat-item">
                                    <span class="stat-label">总奖励人数：</span>
                                    <span class="stat-value">{{ getTotalRewardCount(formData.month_rewards) }}人</span>
                                </div>
                            </el-col>
                            <el-col :span="8">
                                <div class="stat-item">
                                    <span class="stat-label">总奖励比例：</span>
                                    <span class="stat-value" :class="{ warning: getTotalRewardPercent(formData.month_rewards) > 100 }">
                                        {{ getTotalRewardPercent(formData.month_rewards).toFixed(2) }}%
                                    </span>
                                </div>
                            </el-col>
                            <el-col :span="8">
                                <div class="stat-item">
                                    <span class="stat-label">配置规则数：</span>
                                    <span class="stat-value">{{ formData.month_rewards.length }}条</span>
                                </div>
                            </el-col>
                        </el-row>
                    </div>

                    <div v-if="formData.month_rewards.length === 0" class="empty-rewards">
                        <el-empty description="暂无奖励配置" />
                    </div>
                    <div v-else>
                        <div v-for="(reward, index) in formData.month_rewards" :key="index" class="reward-item">
                            <el-row :gutter="10" align="middle">
                                <el-col :span="6">
                                    <el-form-item label="排名范围">
                                        <el-input-number v-model="reward.rank_start" :min="1" size="small" style="width: 80px" />
                                        <span class="mx-2">至</span>
                                        <el-input-number v-model="reward.rank_end" :min="reward.rank_start" size="small" style="width: 80px" />
                                    </el-form-item>
                                </el-col>
                                <el-col :span="6">
                                    <el-form-item label="奖励比例">
                                        <el-input-number
                                            v-model="reward.reward_percent"
                                            :min="0"
                                            :max="100"
                                            :precision="2"
                                            :step="0.1"
                                            size="small"
                                            style="width: 100px"
                                        />
                                        <span class="ml-1">%</span>
                                    </el-form-item>
                                </el-col>
                                <el-col :span="4">
                                    <el-button type="danger" size="small" @click="removeReward('month', index)"> 删除 </el-button>
                                </el-col>
                            </el-row>
                        </div>
                    </div>
                </el-card>

                <!-- 操作按钮 -->
                <el-divider />
                <el-form-item>
                    <el-button type="primary" @click="handleSubmit" :loading="submitting"> 保存配置 </el-button>
                    <el-button @click="loadTemplate">加载模板</el-button>
                    <el-button @click="resetForm">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getRankingConfig, saveRankingConfig } from '/@/api/activity/ranking'

const formRef = ref()
const loading = ref(false)
const submitting = ref(false)

// 表单数据
const formData = reactive({
    id: null,
    name: '',
    status: 1,
    daily_pool_ratio: 1.0,
    weekly_pool_ratio: 1.0,
    monthly_pool_ratio: 1.0,
    bet_multiple: 0,
    day_limit: 100,
    week_limit: 100,
    month_limit: 100,
    day_rewards: [],
    week_rewards: [],
    month_rewards: [],
})

// 表单验证规则
const rules = {
    name: [{ required: true, message: '请输入活动名称', trigger: 'blur' }],
    daily_pool_ratio: [{ required: true, message: '请输入日榜奖池比例', trigger: 'blur' }],
    weekly_pool_ratio: [{ required: true, message: '请输入周榜奖池比例', trigger: 'blur' }],
    monthly_pool_ratio: [{ required: true, message: '请输入月榜奖池比例', trigger: 'blur' }],
    bet_multiple: [{ required: true, message: '请输入打码倍数', trigger: 'blur' }],
    day_limit: [{ required: true, message: '请输入日榜人数限制', trigger: 'blur' }],
    week_limit: [{ required: true, message: '请输入周榜人数限制', trigger: 'blur' }],
    month_limit: [{ required: true, message: '请输入月榜人数限制', trigger: 'blur' }],
}

// 添加奖励规则
const addReward = (type) => {
    const newReward = {
        rank_start: 1,
        rank_end: 1,
        reward_percent: 0.0,
    }

    switch (type) {
        case 'day':
            formData.day_rewards.push(newReward)
            break
        case 'week':
            formData.week_rewards.push(newReward)
            break
        case 'month':
            formData.month_rewards.push(newReward)
            break
    }
}

// 删除奖励规则
const removeReward = (type, index) => {
    ElMessageBox.confirm('确定要删除这条奖励规则吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
    }).then(() => {
        switch (type) {
            case 'day':
                formData.day_rewards.splice(index, 1)
                break
            case 'week':
                formData.week_rewards.splice(index, 1)
                break
            case 'month':
                formData.month_rewards.splice(index, 1)
                break
        }
        ElMessage.success('删除成功')
    })
}

// 加载配置
const loadConfig = async () => {
    loading.value = true
    try {
        const res = await getRankingConfig()
        if (res.code === 1 && res.data) {
            Object.assign(formData, res.data)
        }
    } catch (error) {
        ElMessage.error('加载配置失败')
    } finally {
        loading.value = false
    }
}

// 加载模板
const loadTemplate = () => {
    const template = {
        day: [
            { rank_start: 1, rank_end: 1, reward_percent: 10.0 },
            { rank_start: 2, rank_end: 3, reward_percent: 5.0 },
            { rank_start: 4, rank_end: 10, reward_percent: 2.0 },
        ],
        week: [
            { rank_start: 1, rank_end: 1, reward_percent: 15.0 },
            { rank_start: 2, rank_end: 3, reward_percent: 8.0 },
            { rank_start: 4, rank_end: 10, reward_percent: 3.0 },
        ],
        month: [
            { rank_start: 1, rank_end: 1, reward_percent: 20.0 },
            { rank_start: 2, rank_end: 3, reward_percent: 10.0 },
            { rank_start: 4, rank_end: 10, reward_percent: 5.0 },
        ],
    }

    formData.day_rewards = template.day
    formData.week_rewards = template.week
    formData.month_rewards = template.month
    ElMessage.success('模板加载成功')
}

// 提交表单
const handleSubmit = async () => {
    try {
        await formRef.value.validate()
        submitting.value = true

        const res = await saveRankingConfig(formData)
        if (res.code === 1) {
            ElMessage.success('配置保存成功')
            await loadConfig()
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (error) {
        ElMessage.error('保存失败')
    } finally {
        submitting.value = false
    }
}

// 重置表单
const resetForm = () => {
    formRef.value.resetFields()
    formData.day_rewards = []
    formData.week_rewards = []
    formData.month_rewards = []
}

// 计算总奖励人数
const getTotalRewardCount = (rewards) => {
    if (!rewards || rewards.length === 0) return 0
    return rewards.reduce((total, reward) => {
        const count = (reward.rank_end || 0) - (reward.rank_start || 0) + 1
        return total + count
    }, 0)
}

// 计算总奖励比例
const getTotalRewardPercent = (rewards) => {
    if (!rewards || rewards.length === 0) return 0
    return rewards.reduce((total, reward) => {
        return total + (reward.reward_percent || 0)
    }, 0)
}

// 页面加载时获取配置
onMounted(() => {
    loadConfig()
})
</script>

<style scoped>
.ranking-config {
    padding: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.reward-card {
    margin-bottom: 20px;
}

.reward-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.reward-item {
    padding: 10px;
    border: 1px solid #ebeef5;
    border-radius: 4px;
    margin-bottom: 10px;
    background-color: #fafafa;
}

.empty-rewards {
    padding: 20px;
    text-align: center;
}

.mx-2 {
    margin: 0 8px;
}

.ml-1 {
    margin-left: 4px;
}

.reward-alert {
    margin-bottom: 16px;
}

.alert-content {
    font-size: 14px;
    line-height: 1.6;
}

.alert-content p {
    margin: 8px 0;
}

.alert-content ul {
    margin: 8px 0;
    padding-left: 20px;
}

.alert-content li {
    margin: 4px 0;
    color: #606266;
}

.alert-content strong {
    color: #303133;
}

.reward-stats {
    padding: 16px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 16px;
    border: 1px solid #e9ecef;
}

.stat-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
}

.stat-label {
    font-size: 14px;
    color: #606266;
    font-weight: 500;
}

.stat-value {
    font-size: 16px;
    color: #303133;
    font-weight: 600;
}

.stat-value.warning {
    color: #e6a23c;
    font-weight: 700;
}
</style>
