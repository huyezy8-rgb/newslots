<template>
    <div class="activity-config-container">
        <ContentWrap title="转盘活动配置" v-loading="loading">
            <div class="config-content">
                <el-form ref="formRef" :model="formData" :rules="rules" label-width="160px" @submit.prevent status-icon class="config-form">
                    <template v-for="field in fields" :key="field.name">
                        <el-form-item :label="field.title" :prop="field.name">
                            <el-input v-if="field.type === 'string'" v-model="formData[field.name]" :placeholder="`请输入${field.title}`" />
                            <el-input
                                v-else-if="field.type === 'textarea'"
                                v-model="formData[field.name]"
                                type="textarea"
                                :rows="4"
                                :placeholder="`请输入${field.title}`"
                            />
                            <el-input
                                v-else-if="field.type === 'number'"
                                v-model.number="formData[field.name]"
                                type="number"
                                :placeholder="`请输入${field.title}`"
                            />
                            <!-- 其他类型可扩展 -->
                        </el-form-item>
                    </template>
                    <el-form-item>
                        <el-button type="primary" @click="submit" :loading="submitting"> 保存配置 </el-button>
                    </el-form-item>
                </el-form>
            </div>
        </ContentWrap>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, reactive, computed } from 'vue'
import { ElMessage, type FormInstance } from 'element-plus'
import { baTableApi } from '/@/api/common'

const api = new baTableApi('/admin/activity.simple_activity/')
const formRef = ref<FormInstance>()
const submitting = ref(false)
const loading = ref(false)

const fields = ref<any[]>([])
const formData = reactive<Record<string, any>>({})

const rules = {
    pdd_init: [{ required: true, message: '请输入初始化进度', trigger: 'blur' }],
    pdd_withdrawal: [{ required: true, message: '请输入提现额度', trigger: 'blur' }],
    pdd_recharge_ratio: [{ required: true, message: '请输入充值奖励比例', trigger: 'blur' }],
    pdd_bind_mobile: [{ required: true, message: '请输入绑定手机奖励', trigger: 'blur' }],
}

const previewData = computed(() => ({
    ...formData,
}))

const submit = async () => {
    try {
        await formRef.value?.validate()
        submitting.value = true

        const postData = {
            ...formData,
        }

        const res = await api.postData('edit', postData)
        if (res.code === 1) {
            ElMessage.success('保存成功')
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (error) {
        console.error('提交错误:', error)
        if (!(error as any).response) {
            ElMessage.error('提交配置失败，请检查表单')
        }
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
    loading.value = true
    try {
        const res = await api.edit({ group: 'turntable' })
        if (res.code === 1 && Array.isArray(res.data)) {
            fields.value = res.data
            // 初始化 formData
            res.data.forEach((item: any) => {
                formData[item.name] = item.value
            })
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

/* 每日奖励比例列表 */
.day-reward-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.day-reward-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #fafbfc;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
}

.day-label {
    font-size: 14px;
    color: #606266;
    min-width: 60px;
}

.day-reward-input {
    flex: 1;
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

    .day-reward-list {
        grid-template-columns: 1fr;
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
