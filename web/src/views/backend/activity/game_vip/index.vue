<template>
    <div class="activity-config-container">
        <ContentWrap title="游戏VIP活动配置" v-loading="loading">
            <div class="config-content">
                <el-form ref="formRef" :model="formData" :rules="rules" label-width="160px" @submit.prevent status-icon class="config-form">
                    <template v-for="field in fields" :key="field.name">
                        <el-form-item :prop="field.name">
                            <!-- Array类型特殊处理 -->
                            <div class="array-container">
                                <div class="array-header">
                                    <span class="array-title">{{ field.title }}</span>
                                    <el-button type="primary" size="small" @click="addArrayItem(field.name)"> 添加项目 </el-button>
                                </div>
                                <div class="array-items">
                                    <div v-for="(item, index) in formData[field.name]" :key="index" class="array-item">
                                        <el-input v-model="item.total_bet" placeholder="总投注金额" type="number" class="array-input" />
                                        <el-input v-model="item.bonus" placeholder="返利额度" type="number" class="array-input" />
                                        <el-button type="danger" size="small" @click="removeArrayItem(field.name, index)"> 删除 </el-button>
                                    </div>
                                </div>
                            </div>
                            <!-- 其他类型可扩展 -->
                        </el-form-item>
                    </template>
                    <el-form-item>
                        <el-button type="primary" @click="submit" :loading="submitting"> 保存配置 </el-button>
                        <el-button @click="reset">重置</el-button>
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

// 定义数组项目的类型
interface ArrayItem {
    total_bet: number
    bonus: number
}

const rules = {
    // 动态生成验证规则
}

const previewData = computed(() => ({
    ...formData,
}))

// 获取选择框选项
const getSelectOptions = (field: any) => {
    if (field.content && typeof field.content === 'object') {
        return Object.entries(field.content).map(([value, label]) => ({
            value,
            label,
        }))
    }
    return []
}

// 添加数组项目
const addArrayItem = (fieldName: string) => {
    if (!Array.isArray(formData[fieldName])) {
        formData[fieldName] = []
    }
    formData[fieldName].push({
        total_bet: 0,
        bonus: 0,
    } as ArrayItem)
}

// 删除数组项目
const removeArrayItem = (fieldName: string, index: number) => {
    if (Array.isArray(formData[fieldName])) {
        formData[fieldName].splice(index, 1)
    }
}

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

const reset = () => {
    getInfo()
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
        const res = await api.edit({ group: 'game_vip_375' })
        if (res.code === 1 && Array.isArray(res.data)) {
            fields.value = res.data
            // 初始化 formData
            res.data.forEach((item: any) => {
                if (item.type === 'array') {
                    // 处理数组类型
                    if (typeof item.value === 'string') {
                        try {
                            formData[item.name] = JSON.parse(item.value)
                        } catch {
                            formData[item.name] = []
                        }
                    } else if (Array.isArray(item.value)) {
                        formData[item.name] = item.value
                    } else {
                        formData[item.name] = []
                    }
                } else if (item.type === 'number') {
                    formData[item.name] = parseFloat(item.value) || 0
                } else if (item.type === 'switch') {
                    formData[item.name] = parseInt(item.value) || 0
                } else {
                    formData[item.name] = item.value
                }
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

/* Array类型样式 */
.array-container {
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    padding: 16px;
    background: #fafbfc;
}

.array-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.array-title {
    font-weight: 600;
    color: #303133;
}

.array-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.array-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
}

.array-input {
    flex: 1;
    max-width: 200px;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .activity-config-container {
        padding: 10px;
    }

    .config-form {
        padding: 16px;
    }

    .array-item {
        flex-wrap: wrap;
    }

    .array-input {
        max-width: 100%;
    }
}
</style>
