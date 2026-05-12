<template>
    <div class="activity-config-container">
        <ContentWrap title="摇一摇比例配置" v-loading="loading">
            <div class="config-content">
                <el-form ref="formRef" :model="formData" :rules="rules" label-width="160px" @submit.prevent status-icon class="config-form">
                    <!-- 奖金金额 -->
                    <el-form-item label="奖金金额" prop="bonus_amount">
                        <el-input
                            v-model.number="formData.bonus_amount"
                            type="number"
                            placeholder="请输入奖金金额"
                            :input-attr="{ step: 0.01, min: 0 }"
                        />
                    </el-form-item>

                    <!-- 每日投资阈值 -->
                    <el-form-item label="每日投资阈值" prop="daily_invest_threshold">
                        <el-input
                            v-model.number="formData.daily_invest_threshold"
                            type="number"
                            placeholder="请输入每日投资阈值"
                            :input-attr="{ step: 0.01, min: 0 }"
                        />
                    </el-form-item>

                    <!-- 摇一摇比例配置 -->
                    <el-form-item label="摇一摇比例配置" prop="shake_ratio_config">
                        <div class="array-container">
                            <div class="array-header">
                                <span class="array-title">投资金额范围 - 中奖比例</span>
                                <el-button type="primary" size="small" @click="addRatioItem"> 添加比例 </el-button>
                            </div>
                            <div class="array-items">
                                <div v-for="(item, index) in formData.shake_ratio_config" :key="index" class="array-item">
                                    <el-input v-model="item.range" placeholder="投资范围" class="array-input" />
                                    <el-input
                                        v-model.number="item.ratio"
                                        placeholder="中奖比例 (%)"
                                        type="number"
                                        :input-attr="{ min: 0, max: 100 }"
                                        class="array-input"
                                    />
                                    <el-button type="danger" size="small" @click="removeRatioItem(index)"> 删除 </el-button>
                                </div>
                            </div>
                        </div>
                    </el-form-item>

                    <!-- 提交按钮 -->
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
import { ref, onMounted, reactive } from 'vue'
import { ElMessage, type FormInstance } from 'element-plus'
import { baTableApi } from '/@/api/common'

const api = new baTableApi('/admin/activity.jackpot/')
const formRef = ref<FormInstance>()
const submitting = ref(false)
const loading = ref(false)

// 定义比例配置项类型
interface RatioItem {
    range: string
    ratio: number
}

const formData = reactive({
    bonus_amount: 0,
    daily_invest_threshold: 0,
    shake_ratio_config: [] as RatioItem[],
})

const rules = {
    bonus_amount: [
        { required: true, message: '请输入奖金金额', trigger: 'blur' },
        {
            validator: (rule: any, value: any, callback: any) => {
                const numValue = parseFloat(value)
                if (isNaN(numValue) || numValue < 0) {
                    callback(new Error('奖金金额必须大于等于0'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
    daily_invest_threshold: [
        { required: true, message: '请输入每日投资阈值', trigger: 'blur' },
        {
            validator: (rule: any, value: any, callback: any) => {
                const numValue = parseFloat(value)
                if (isNaN(numValue) || numValue < 0) {
                    callback(new Error('每日投资阈值必须大于等于0'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
    shake_ratio_config: [
        {
            validator: (rule: any, value: RatioItem[], callback: any) => {
                if (!value || value.length === 0) {
                    callback(new Error('至少需要配置一个比例项'))
                    return
                }
                const hasError = value.some((item) => !item.range || item.ratio < 0 || item.ratio > 100)
                if (hasError) {
                    callback(new Error('请检查比例配置'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
}

// 添加比例项
const addRatioItem = () => {
    formData.shake_ratio_config.push({
        range: '',
        ratio: 0,
    })
}

// 删除比例项
const removeRatioItem = (index: number) => {
    formData.shake_ratio_config.splice(index, 1)
}

const submit = async () => {
    try {
        await formRef.value?.validate()
        submitting.value = true

        // 将 shake_ratio_config 数组转换为对象格式
        const shakeRatioObj: Record<string, number> = {}
        formData.shake_ratio_config.forEach((item) => {
            if (item.range && item.ratio !== undefined) {
                shakeRatioObj[item.range] = item.ratio
            }
        })

        const postData = {
            bonus_amount: formData.bonus_amount,
            daily_invest_threshold: formData.daily_invest_threshold,
            shake_ratio_config: shakeRatioObj,
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
        const res = await api.edit({})
        if (res.code === 1 && res.data) {
            const data = res.data

            // 填充表单数据
            formData.bonus_amount = data.bonus_amount ? parseFloat(data.bonus_amount) : 0
            formData.daily_invest_threshold = data.daily_invest_threshold ? parseFloat(data.daily_invest_threshold) : 0

            // 处理 shake_ratio_config
            if (data.shake_ratio_config && typeof data.shake_ratio_config === 'object') {
                formData.shake_ratio_config = Object.entries(data.shake_ratio_config).map(([range, ratio]) => ({
                    range,
                    ratio: Number(ratio),
                }))
            } else {
                formData.shake_ratio_config = []
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

/* 数组配置容器 */
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
