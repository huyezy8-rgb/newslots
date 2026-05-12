<template>
    <div class="activity-config-container">
        <ContentWrap title="站内信活动配置" v-loading="loading">
            <div class="config-content">
                <el-form ref="formRef" :model="formData" :rules="rules" label-width="120px" @submit.prevent status-icon class="config-form">
                    <!-- 消息标题 -->
                    <el-form-item label="消息标题" prop="title">
                        <el-input v-model="formData.title" placeholder="请输入消息标题" />
                    </el-form-item>

                    <!-- 消息内容 -->
                    <el-form-item label="消息内容" prop="content">
                        <el-input v-model="formData.content" type="textarea" :rows="6" placeholder="请输入消息内容" />
                    </el-form-item>

                    <!-- 赠送金额 -->
                    <el-form-item label="赠送金额" prop="amount">
                        <el-input
                            v-model.number="formData.amount"
                            placeholder="请输入赠送金额"
                            class="amount-input"
                            type="number"
                            :min="0"
                            :precision="2"
                        >
                            <template #append>元</template>
                        </el-input>
                    </el-form-item>

                    <!-- 钱包类型 -->
                    <el-form-item label="钱包类型" prop="wallet_type">
                        <el-select v-model="formData.wallet_type" placeholder="请选择钱包类型" class="wallet-select">
                            <el-option label="体验钱包" value="experience_wallet" />
                            <el-option label="充值钱包" value="recharge_wallet" />
                            <el-option label="游戏钱包" value="game_wallet" />
                        </el-select>
                    </el-form-item>

                    <!-- 有效期 -->
                    <el-form-item label="有效期" prop="valid_hours">
                        <el-input v-model.number="formData.valid_hours" placeholder="请输入有效期小时数" class="valid-input" type="number" :min="0">
                            <template #append>小时</template>
                        </el-input>
                        <div class="valid-tip">
                            <el-text type="info" size="small">0表示永久有效</el-text>
                        </div>
                    </el-form-item>

                    <!-- 提交按钮 -->
                    <el-form-item>
                        <el-button type="primary" @click="submit" :loading="submitting"> 保存配置 </el-button>
                    </el-form-item>
                </el-form>

                <!-- 配置预览 -->
                <div class="config-preview">
                    <h4>配置预览</h4>
                    <el-card class="preview-card">
                        <template #header>
                            <div class="preview-header">
                                <span>{{ formData.title || '消息标题' }}</span>
                                <el-tag v-if="formData.valid_hours > 0" type="warning" size="small"> {{ formData.valid_hours }}小时后过期 </el-tag>
                                <el-tag v-else type="success" size="small">永久有效</el-tag>
                            </div>
                        </template>
                        <div class="preview-content">
                            <p>{{ formData.content || '消息内容' }}</p>
                            <div class="preview-info">
                                <el-text type="primary" size="large"> 赠送金额: {{ formData.amount || 0 }}元 </el-text>
                                <el-text type="info"> 钱包类型: {{ getWalletTypeName(formData.wallet_type) }} </el-text>
                            </div>
                        </div>
                    </el-card>
                </div>
            </div>
        </ContentWrap>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { ElMessage, type FormInstance } from 'element-plus'
import { baTableApi } from '/@/api/common'

// 类型定义
interface InternalMessageConfig {
    id: number
    title: string
    content: string
    amount: number
    wallet_type: string
    valid_hours: number
    create_time?: number
    update_time?: number
}

// API初始化
const api = new baTableApi('/admin/activity.internal_message/')
const formRef = ref<FormInstance>()
const submitting = ref(false)
const loading = ref(false)

// 表单数据
const formData = reactive<InternalMessageConfig>({
    id: 1,
    title: '',
    content: '',
    amount: 0,
    wallet_type: 'experience_wallet',
    valid_hours: 24,
})

// 表单验证规则
const rules = {
    title: [
        { required: true, message: '请输入消息标题', trigger: 'blur' },
        { max: 255, message: '消息标题长度不能超过255个字符', trigger: 'blur' },
    ],
    content: [{ required: true, message: '请输入消息内容', trigger: 'blur' }],
    amount: [
        { required: true, message: '请输入赠送金额', trigger: 'blur' },
        { type: 'number', min: 0, message: '赠送金额不能小于0', trigger: 'blur' },
    ],
    wallet_type: [{ required: true, message: '请选择钱包类型', trigger: 'change' }],
    valid_hours: [
        { required: true, message: '请输入有效期', trigger: 'blur' },
        { type: 'number', min: 0, message: '有效期不能小于0', trigger: 'blur' },
    ],
}

// 获取钱包类型名称
const getWalletTypeName = (type: string) => {
    const walletTypes = {
        experience_wallet: '体验钱包',
        recharge_wallet: '充值钱包',
        game_wallet: '游戏钱包',
    }
    return walletTypes[type] || type
}

// 提交表单
const submit = async () => {
    try {
        await formRef.value?.validate()

        submitting.value = true

        const postData = {
            ...formData,
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
            formData.content = row.content || ''
            formData.amount = Number(row.amount) || 0
            formData.wallet_type = row.wallet_type || 'experience_wallet'
            formData.valid_hours = Number(row.valid_hours) || 24
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

/* 输入框样式 */
.amount-input {
    width: 200px;
}

.wallet-select {
    width: 200px;
}

.valid-input {
    width: 200px;
}

.valid-tip {
    margin-top: 8px;
}

/* 配置预览区域 */
.config-preview {
    margin-top: 0;
    padding: 24px;
    border-top: 1px solid #e4e7ed;
    background-color: #fafbfc;
}

.config-preview h4 {
    margin-top: 0;
    margin-bottom: 16px;
    color: #303133;
    font-size: 16px;
    font-weight: 600;
}

.preview-card {
    max-width: 600px;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
}

.preview-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.preview-content {
    line-height: 1.6;
}

.preview-content p {
    margin-bottom: 16px;
    color: #606266;
}

.preview-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .activity-config-container {
        padding: 10px;
    }

    .config-form {
        padding: 16px;
    }

    .amount-input,
    .wallet-select,
    .valid-input {
        width: 100%;
    }

    .config-preview {
        padding: 16px;
    }
}
</style>
