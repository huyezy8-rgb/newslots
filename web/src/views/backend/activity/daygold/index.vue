<template>
    <div class="activity-config-container">
        <ContentWrap title="每日奖励活动配置" class="daygold-container">
            <div class="config-content">
                <el-card shadow="hover" class="config-card">
                    <!-- 表单内容区域 -->
                    <div class="form-content">
                        <!-- 活动图片上传 -->
                        <el-form-item prop="pic" class="form-item-image">
                            <div class="form-item-container">
                                <div class="item-label">活动宣传图</div>
                                <div class="item-control">
                                    <el-upload
                                        class="image-uploader"
                                        action="#"
                                        :show-file-list="false"
                                        :auto-upload="false"
                                        :on-change="handleImageChange"
                                        accept="image/*"
                                    >
                                        <div class="uploader-content">
                                            <el-image v-if="formData.pic" :src="formData.pic" fit="cover" class="uploaded-image">
                                                <template #error>
                                                    <div class="image-error">
                                                        <el-icon><Picture /></el-icon>
                                                        <span>图片加载失败</span>
                                                    </div>
                                                </template>
                                            </el-image>
                                            <div v-else class="uploader-placeholder">
                                                <el-icon class="upload-icon"><Plus /></el-icon>
                                                <div class="upload-text">点击上传活动图片</div>
                                                <div class="upload-tip">建议尺寸：750×350像素</div>
                                            </div>
                                        </div>
                                    </el-upload>
                                </div>
                            </div>
                        </el-form-item>

                        <!-- 7天奖励配置 - 支持小数 -->
                        <el-form-item prop="rewards" class="form-item-rewards">
                            <div class="form-item-container">
                                <div class="item-label">每日奖励配置</div>
                                <div class="item-control">
                                    <div class="rewards-horizontal">
                                        <div v-for="day in 7" :key="day" class="reward-item-horizontal">
                                            <div class="reward-day">第{{ day }}天</div>
                                            <el-input-number
                                                v-model="formData.rewards[day - 1]"
                                                :min="0"
                                                :step="0.1"
                                                :precision="2"
                                                controls-position="right"
                                                class="reward-input"
                                            />
                                            <span class="reward-unit">元</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </el-form-item>

                        <!-- 截止时间 -->
                        <el-form-item prop="deadline_hour" class="form-item-time">
                            <div class="form-item-container">
                                <div class="item-label">每日截止时间</div>
                                <div class="item-control">
                                    <el-input-number
                                        v-model="formData.deadline_hour"
                                        :min="0"
                                        :max="23"
                                        :precision="0"
                                        controls-position="right"
                                        class="time-input"
                                    />
                                    <span class="time-suffix">点整（0-23时）</span>
                                </div>
                            </div>
                        </el-form-item>
                    </div>

                    <!-- 底部操作按钮 -->
                    <div class="form-actions">
                        <el-button type="primary" @click="submitForm" :loading="submitting" round class="action-btn">
                            <el-icon><Check /></el-icon> 保存配置
                        </el-button>
                        <el-button @click="resetToDefault" round class="action-btn">
                            <el-icon><Refresh /></el-icon> 恢复默认
                        </el-button>
                    </div>
                </el-card>
            </div>
        </ContentWrap>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance, type UploadProps } from 'element-plus'
import { Plus, Picture, Check, Refresh } from '@element-plus/icons-vue'
import { baTableApi } from '/@/api/common'
import { fileUpload } from '/@/api/common'

interface DayGoldActivity {
    id?: number
    pic?: string
    rewards?: number[]
    deadline_hour?: number
    create_time?: string
    update_time?: string
}

const api = new baTableApi('/admin/activity.daygold/')
const formRef = ref<FormInstance>()
const submitting = ref(false)
const loading = ref(false)

// 默认7天奖励配置（支持小数）
const defaultRewards = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]
const defaultData: DayGoldActivity = {
    id: 1,
    pic: '',
    rewards: [...defaultRewards],
    deadline_hour: 23,
}

const formData = reactive<DayGoldActivity>({
    ...defaultData,
    rewards: [...defaultRewards], // 深拷贝默认值
})

const rules = {
    pic: [{ required: true, message: '请上传活动宣传图片', trigger: 'blur' }],
    rewards: [
        {
            validator: (_rule: any, value: number[], callback: (error?: Error) => void) => {
                if (!value || value.length !== 7) {
                    callback(new Error('必须配置7天奖励金额'))
                    return
                }
                if (value.some((item) => item < 0)) {
                    callback(new Error('奖励金额不能为负数'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
    deadline_hour: [
        {
            required: true,
            type: 'number',
            validator: (_rule: any, value: number, callback: (error?: Error) => void) => {
                if (value === null || value === undefined) {
                    callback(new Error('请设置截止时间'))
                } else if (!Number.isInteger(value) || value < 0 || value > 23) {
                    callback(new Error('请输入0-23之间的整点时间'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
}

const handleImageChange: UploadProps['onChange'] = async (file) => {
    try {
        if (!file?.raw) {
            throw new Error('文件无效')
        }

        const isValidType = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.raw.type)
        const isLt5M = file.raw.size / 1024 / 1024 < 5

        if (!isValidType) {
            throw new Error('请上传JPG/PNG/GIF/WEBP格式的图片')
        }

        if (!isLt5M) {
            throw new Error('图片大小不能超过5MB')
        }

        const fd = new FormData()
        fd.append('file', file.raw)

        const res = await fileUpload(fd)
        if (res?.code === 1 && res.data?.file?.full_url) {
            formData.pic = res.data.file.full_url
            ElMessage.success('图片上传成功')
        } else {
            throw new Error(res?.msg || '图片上传失败')
        }
    } catch (err: any) {
        console.error('图片上传错误:', err)
        ElMessage.error(err.message)
    }
}

const loadConfigData = async () => {
    try {
        loading.value = true
        const res = await api.edit({ id: 1 })

        if (res?.code === 1) {
            const apiData = res.data?.row || {}

            // 处理奖励数据，支持小数
            const loadedRewards = Array.isArray(apiData.rewards)
                ? apiData.rewards.map(Number)
                : apiData.rewards && typeof apiData.rewards === 'object'
                  ? Object.values(apiData.rewards).map(Number)
                  : [...defaultRewards]

            // 确保有7天数据，不足补0
            const finalRewards =
                loadedRewards.length >= 7 ? loadedRewards.slice(0, 7) : [...loadedRewards, ...defaultRewards.slice(loadedRewards.length)]

            Object.assign(formData, {
                pic: apiData.pic || defaultData.pic,
                deadline_hour: apiData.deadline_hour ?? defaultData.deadline_hour,
                rewards: finalRewards,
            })

            if (apiData.id) {
                formData.id = apiData.id
            }
        } else {
            throw new Error(res?.msg || '获取配置失败')
        }
    } catch (err: any) {
        console.error('加载配置失败:', err)
        ElMessage.error(err.message || '加载配置失败，已恢复默认配置')
        resetToDefault(false)
    } finally {
        loading.value = false
    }
}

const resetToDefault = (showMessage = true) => {
    Object.assign(formData, {
        ...defaultData,
        rewards: [...defaultRewards], // 重置为全0
    })
    if (showMessage) {
        ElMessage.success('已恢复默认配置')
    }
}

const submitForm = async () => {
    try {
        await formRef.value?.validate()
        submitting.value = true

        const submitData = {
            ...formData,
            rewards: formData.rewards?.slice(0, 7) || [...defaultRewards],
        }

        const res = await api.postData('edit', submitData)

        if (res?.code === 1) {
            ElMessage.success('配置保存成功')
            // 更新本地数据
            const updatedData = res.data?.row || {}
            const updatedRewards = Array.isArray(updatedData.rewards)
                ? updatedData.rewards.map(Number)
                : updatedData.rewards && typeof updatedData.rewards === 'object'
                  ? Object.values(updatedData.rewards).map(Number)
                  : formData.rewards

            Object.assign(formData, {
                pic: updatedData.pic || formData.pic,
                deadline_hour: updatedData.deadline_hour ?? formData.deadline_hour,
                rewards:
                    updatedRewards?.length >= 7
                        ? updatedRewards.slice(0, 7)
                        : [...(updatedRewards || []), ...defaultRewards.slice(updatedRewards?.length || 0)],
            })
        } else {
            throw new Error(res?.msg || '保存失败')
        }
    } catch (err: any) {
        console.error('保存失败:', err)
        ElMessage.error(err.message || '保存配置失败')
    } finally {
        submitting.value = false
    }
}

onMounted(() => {
    // 确保数据初始化
    if (!formData.rewards || formData.rewards.length !== 7) {
        formData.rewards = [...defaultRewards]
    }
    loadConfigData()
})
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

.daygold-container {
    background: transparent;
}

.config-card {
    border: none;
    box-shadow: none;
    background: #fff;
}

.form-content {
    padding: 24px;
}

/* 表单项容器 */
.form-item-container {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
}

.item-label {
    min-width: 120px;
    font-weight: 600;
    color: #303133;
    line-height: 32px;
}

.item-control {
    flex: 1;
}

/* 图片上传区域 */
.image-uploader {
    width: 100%;
}

.uploader-content {
    width: 100%;
    min-height: 200px;
    border: 2px dashed #d9d9d9;
    border-radius: 6px;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    transition: border-color 0.3s;
    background: #fafbfc;
}

.uploader-content:hover {
    border-color: #409eff;
    background: #f0f9ff;
}

.uploaded-image {
    width: 100%;
    height: 200px;
    border-radius: 6px;
}

.uploader-placeholder {
    text-align: center;
    color: #909399;
}

.upload-icon {
    font-size: 48px;
    margin-bottom: 16px;
    color: #c0c4cc;
}

.upload-text {
    font-size: 16px;
    margin-bottom: 8px;
}

.upload-tip {
    font-size: 12px;
    color: #c0c4cc;
}

.image-error {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 200px;
    color: #909399;
}

/* 奖励配置区域 */
.rewards-horizontal {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.reward-item-horizontal {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px;
    background: #fafbfc;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.reward-item-horizontal:hover {
    background: #f0f2f5;
    border-color: #c0c4cc;
}

.reward-day {
    font-weight: 600;
    color: #409eff;
    min-width: 60px;
}

.reward-input {
    flex: 1;
}

.reward-unit {
    color: #606266;
    font-size: 14px;
}

/* 时间配置区域 */
.form-item-time .item-control {
    display: flex;
    align-items: center;
    gap: 8px;
}

.time-input {
    width: 120px;
}

.time-suffix {
    color: #606266;
    font-size: 14px;
}

/* 操作按钮区域 */
.form-actions {
    padding: 24px;
    border-top: 1px solid #e4e7ed;
    background: #fafbfc;
    display: flex;
    gap: 12px;
    justify-content: center;
}

.action-btn {
    min-width: 120px;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .activity-config-container {
        padding: 10px;
    }

    .form-content {
        padding: 16px;
    }

    .form-item-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .item-label {
        min-width: auto;
    }

    .rewards-horizontal {
        grid-template-columns: 1fr;
    }

    .reward-item-horizontal {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .reward-input {
        width: 100%;
    }

    .form-actions {
        padding: 16px;
        flex-direction: column;
    }

    .action-btn {
        width: 100%;
    }
}
</style>
