<template>
    <div class="chest-config">
        <el-card class="box-card">
            <template #header>
                <div class="card-header">
                    <span>宝箱活动配置</span>
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

                <!-- Banner图配置 -->
                <el-divider content-position="left">Banner图配置</el-divider>
                <el-row :gutter="20">
                    <el-col :span="24">
                        <el-form-item label="Banner图" prop="banner_image">
                            <FormItem type="image" v-model="formData.banner_image" prop="banner_image" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- 宝箱图片配置 -->
                <el-divider content-position="left">宝箱图片配置</el-divider>
                <el-row :gutter="20">
                    <el-col :span="8">
                        <el-form-item label="默认图片" prop="default_image">
                            <FormItem type="image" v-model="formData.default_image" prop="default_image" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="待领取图片" prop="waiting_image">
                            <FormItem type="image" v-model="formData.waiting_image" prop="waiting_image" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="已领取图片" prop="received_image">
                            <FormItem type="image" v-model="formData.received_image" prop="received_image" />
                        </el-form-item>
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
import FormItem from '/@/components/formItem/index.vue'
import { getChestConfig, saveChestConfig } from '/@/api/activity/chest'

const formRef = ref()
const loading = ref(false)
const submitting = ref(false)

// 表单数据
const formData = reactive({
    id: null,
    name: '',
    status: 1,
    bet_multiple: 0,
    banner_image: '',
    default_image: '',
    waiting_image: '',
    received_image: '',
})

// 表单验证规则
const rules = {
    name: [{ required: true, message: '请输入活动名称', trigger: 'blur' }],
    bet_multiple: [{ required: true, message: '请输入打码倍数', trigger: 'blur' }],
    banner_image: [{ required: true, message: '请上传Banner图', trigger: 'change' }],
    default_image: [{ required: true, message: '请上传默认图片', trigger: 'change' }],
    waiting_image: [{ required: true, message: '请上传待领取图片', trigger: 'change' }],
    received_image: [{ required: true, message: '请上传已领取图片', trigger: 'change' }],
}

// 加载配置
const loadConfig = async () => {
    loading.value = true
    try {
        const res = await getChestConfig()
        if (res.code === 1 && res.data) {
            Object.assign(formData, res.data)
        }
    } catch (error) {
        ElMessage.error('加载配置失败')
    } finally {
        loading.value = false
    }
}

// 提交表单
const handleSubmit = async () => {
    try {
        await formRef.value.validate()
        submitting.value = true

        const res = await saveChestConfig(formData)
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
    formData.banner_image = ''
    formData.default_image = ''
    formData.waiting_image = ''
    formData.received_image = ''
}

// 页面加载时获取配置
onMounted(() => {
    loadConfig()
})
</script>

<style scoped>
.chest-config {
    padding: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
