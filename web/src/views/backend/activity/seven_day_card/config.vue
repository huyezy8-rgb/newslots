<template>
    <div class="seven-day-card-config">
        <el-card class="box-card">
            <template #header>
                <div class="card-header">
                    <span>七天卡活动配置</span>
                </div>
            </template>

            <el-form ref="formRef" :model="formData" :rules="rules" label-width="120px" v-loading="loading">
                <!-- 基本信息 -->
                <el-divider content-position="left">基本信息</el-divider>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="活动标题" prop="title">
                            <el-input v-model="formData.title" placeholder="请输入活动标题" maxlength="100" show-word-limit />
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
                        <el-form-item label="强制开启PWA" prop="is_pwa">
                            <el-switch v-model="formData.is_pwa" :active-value="1" :inactive-value="0" active-text="开启" inactive-text="关闭" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="打码倍数" prop="bet_multiple">
                            <el-input-number
                                v-model="formData.bet_multiple"
                                :min="0"
                                :max="100"
                                :precision="1"
                                :step="0.5"
                                style="width: 100%"
                                placeholder="请输入打码倍数"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- 价格配置 -->
                <el-divider content-position="left">价格配置</el-divider>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="划线价格" prop="original_price">
                            <el-input-number
                                v-model="formData.original_price"
                                :min="0"
                                :precision="2"
                                :step="0.01"
                                style="width: 100%"
                                placeholder="请输入划线价格"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="现价" prop="current_price">
                            <el-input-number
                                v-model="formData.current_price"
                                :min="0"
                                :precision="2"
                                :step="0.01"
                                style="width: 100%"
                                placeholder="请输入现价"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- 奖励配置 -->
                <el-divider content-position="left">奖励配置</el-divider>
                
                <!-- 七天奖励 -->
                <el-row :gutter="20">
                    <el-col :span="24">
                        <div class="reward-config">
                            <div class="reward-title">七天奖励配置（7天专属福利）</div>
                            <el-row :gutter="10">
                                <el-col :span="3" v-for="(item, index) in formData.seven_day_rewards" :key="`seven_day_${index}`">
                                    <div class="day-input-group">
                                        <label class="day-label">第{{ index + 1 }}天</label>
                                        <el-input-number
                                            v-model="formData.seven_day_rewards[index]"
                                            :min="0"
                                            :precision="2"
                                            :step="0.01"
                                            style="width: 100%"
                                            placeholder="金额"
                                            controls-position="right"
                                        />
                                    </div>
                                </el-col>
                            </el-row>
                        </div>
                    </el-col>
                </el-row>

                <!-- 救援金奖励 -->
                <el-row :gutter="20">
                    <el-col :span="24">
                        <div class="reward-config">
                            <div class="reward-title">救援金配置</div>
                            <el-row :gutter="10">
                                <el-col :span="3" v-for="(item, index) in formData.rescue_rewards" :key="`rescue_${index}`">
                                    <div class="day-input-group">
                                        <label class="day-label">第{{ index + 1 }}天</label>
                                        <el-input-number
                                            v-model="formData.rescue_rewards[index]"
                                            :min="0"
                                            :precision="2"
                                            :step="0.01"
                                            style="width: 100%"
                                            placeholder="金额"
                                            controls-position="right"
                                        />
                                    </div>
                                </el-col>
                            </el-row>
                        </div>
                    </el-col>
                </el-row>

                <!-- 每日奖励 -->
                <el-row :gutter="20">
                    <el-col :span="24">
                        <div class="reward-config">
                            <div class="reward-title">每日奖励配置</div>
                            <el-row :gutter="10">
                                <el-col :span="3" v-for="(item, index) in formData.daily_rewards" :key="`daily_${index}`">
                                    <div class="day-input-group">
                                        <label class="day-label">第{{ index + 1 }}天</label>
                                        <el-input-number
                                            v-model="formData.daily_rewards[index]"
                                            :min="0"
                                            :precision="2"
                                            :step="0.01"
                                            style="width: 100%"
                                            placeholder="金额"
                                            controls-position="right"
                                        />
                                    </div>
                                </el-col>
                            </el-row>
                        </div>
                    </el-col>
                </el-row>

                <!-- 操作按钮 -->
                <el-divider />
                <el-form-item>
                    <el-button type="primary" @click="handleSubmit" :loading="submitting"> 保存配置 </el-button>
                    <el-button @click="resetForm">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { baTableApi } from '/@/api/common'

const api = new baTableApi('/admin/activity.seven_day_card/')
const formRef = ref()
const loading = ref(false)
const submitting = ref(false)

// 表单数据
const formData = reactive({
    id: null,
    title: '',
    bet_multiple: 1.0,
    original_price: 0.00,
    current_price: 19.99,
    seven_day_rewards: [22, 5, 7, 4, 4, 4, 8],
    rescue_rewards: [3, 3, 3, 3, 3, 3, 3],
    daily_rewards: [1, 1, 3, 1, 1, 1, 5],
    status: 1,
    is_pwa: 0,
})

// 表单验证规则
const rules = {
    title: [
        { required: true, message: '请输入活动标题', trigger: 'blur' },
        { min: 1, max: 100, message: '标题长度在1-100个字符', trigger: 'blur' },
    ],
    bet_multiple: [
        { required: true, message: '请输入打码倍数', trigger: 'blur' },
        { type: 'number', min: 0, max: 100, message: '打码倍数在0-100之间', trigger: 'blur' },
    ],
    original_price: [
        { required: true, message: '请输入划线价格', trigger: 'blur' },
        { type: 'number', min: 0, message: '划线价格不能小于0', trigger: 'blur' },
    ],
    current_price: [
        { required: true, message: '请输入现价', trigger: 'blur' },
        { type: 'number', min: 0, message: '现价不能小于0', trigger: 'blur' },
    ],
    status: [{ required: true, message: '请选择活动状态', trigger: 'change' }],
    is_pwa: [{ required: true, message: '请选择PWA开关状态', trigger: 'change' }],
}

// 加载配置
const loadConfig = async () => {
    loading.value = true
    try {
        const res = await api.edit({})
        if (res.code === 1 && res.data) {
            Object.assign(formData, res.data)
        }
    } catch (error) {
        ElMessage.error('加载配置失败')
    } finally {
        loading.value = false
    }
}

// 验证奖励配置
const validateRewards = () => {
    // 验证七天奖励
    if (!formData.seven_day_rewards || formData.seven_day_rewards.length !== 7) {
        ElMessage.error('七天奖励配置不完整，必须包含7天的数据')
        return false
    }
    
    // 验证救援金
    if (!formData.rescue_rewards || formData.rescue_rewards.length !== 7) {
        ElMessage.error('救援金配置不完整，必须包含7天的数据')
        return false
    }
    
    // 验证每日奖励
    if (!formData.daily_rewards || formData.daily_rewards.length !== 7) {
        ElMessage.error('每日奖励配置不完整，必须包含7天的数据')
        return false
    }
    
    // 验证数值有效性
    const allRewards = [...formData.seven_day_rewards, ...formData.rescue_rewards, ...formData.daily_rewards]
    for (let i = 0; i < allRewards.length; i++) {
        if (allRewards[i] < 0) {
            ElMessage.error(`第${Math.floor(i / 7) + 1}个奖励配置中第${(i % 7) + 1}天的金额不能为负数`)
            return false
        }
    }
    
    return true
}

// 提交表单
const handleSubmit = async () => {
    try {
        await formRef.value.validate()
        
        // 验证奖励配置
        if (!validateRewards()) {
            return
        }
        
        submitting.value = true

        const res = await api.postData('edit', formData)
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
    Object.assign(formData, {
        title: '',
        bet_multiple: 1.0,
        original_price: 0.00,
        current_price: 19.99,
        seven_day_rewards: [22, 5, 7, 4, 4, 4, 8],
        rescue_rewards: [3, 3, 3, 3, 3, 3, 3],
        daily_rewards: [1, 1, 3, 1, 1, 1, 5],
        status: 1,
        is_pwa: 0,
    })
    loadConfig()
}

// 页面加载时获取配置
onMounted(() => {
    loadConfig()
})
</script>

<style scoped>
.seven-day-card-config {
    padding: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.reward-config {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    margin-bottom: 20px;
}

.reward-title {
    font-weight: 600;
    color: #495057;
    margin-bottom: 15px;
    font-size: 14px;
}

.day-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.day-label {
    font-size: 12px;
    color: #606266;
    font-weight: 500;
    text-align: center;
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .seven-day-card-config {
        padding: 10px;
    }
    
    .reward-config {
        padding: 15px;
    }
    
    .day-input-group {
        margin-bottom: 15px;
    }
    
    .day-label {
        font-size: 11px;
    }
}

@media (max-width: 480px) {
    .reward-config {
        padding: 10px;
    }
    
    .day-input-group {
        margin-bottom: 10px;
    }
}
</style>
