<template>
    <div class="lucky-wheel-config-container">
        <ContentWrap title="幸运转盘配置" v-loading="loading">
            <div class="config-content">
                <el-form ref="formRef" :model="formData" :rules="rules" label-width="160px" @submit.prevent status-icon class="config-form">
                    <el-form-item label="活动标题" prop="title">
                        <el-input v-model="formData.title" placeholder="请输入活动标题" maxlength="100" show-word-limit />
                    </el-form-item>

                    <el-form-item label="Banner图" prop="banner_image">
                        <FormItem type="image" v-model="formData.banner_image" prop="banner_image" />
                    </el-form-item>

                    <el-form-item label="打码倍数" prop="bet_multiple">
                        <el-input-number
                            v-model="formData.bet_multiple"
                            :min="0.1"
                            :max="10"
                            :precision="1"
                            :step="0.1"
                            placeholder="请输入打码倍数"
                        />
                        <span class="form-tip">倍数为0.1-10之间</span>
                    </el-form-item>

                    <el-form-item label="活动状态" prop="status">
                        <el-radio-group v-model="formData.status">
                            <el-radio :label="1">启用</el-radio>
                            <el-radio :label="0">禁用</el-radio>
                        </el-radio-group>
                    </el-form-item>

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
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, type FormInstance, type FormItemRule } from 'element-plus'
import FormItem from '/@/components/formItem/index.vue'
import { baTableApi } from '/@/api/common'

const api = new baTableApi('/admin/activity.lucky_wheel/')
const formRef = ref<FormInstance>()
const submitting = ref(false)
const loading = ref(false)

const formData = reactive({
    title: '',
    banner_image: '',
    bet_multiple: 1.0,
    status: 1,
})

const rules: Record<string, FormItemRule[]> = {
    title: [
        { required: true, message: '请输入活动标题', trigger: 'blur' },
        { min: 1, max: 100, message: '标题长度在1-100个字符', trigger: 'blur' },
    ],
    bet_multiple: [
        { required: true, message: '请输入打码倍数', trigger: 'blur' },
        { type: 'number' as const, min: 0.1, max: 10, message: '打码倍数在0.1-10之间', trigger: 'blur' },
    ],
    status: [{ required: true, message: '请选择活动状态', trigger: 'change' }],
}

const submit = async () => {
    try {
        await formRef.value?.validate()
        submitting.value = true

        const res = await api.postData('edit', formData)
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

const reset = () => {
    formRef.value?.resetFields()
    Object.assign(formData, {
        title: '',
        banner_image: '',
        bet_multiple: 1.0,
        status: 1,
    })
    loadData()
}

const loadData = async () => {
    loading.value = true
    try {
        const res = await api.edit({})
        if (res.code === 1 && res.data) {
            Object.assign(formData, res.data)
        }
    } catch (error) {
        console.error('加载数据错误:', error)
        ElMessage.error('加载配置失败')
    } finally {
        loading.value = false
    }
}

onMounted(loadData)
</script>

<style scoped>
.lucky-wheel-config-container {
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

.form-tip {
    margin-left: 10px;
    color: #909399;
    font-size: 12px;
}

@media (max-width: 768px) {
    .lucky-wheel-config-container {
        padding: 10px;
    }

    .config-form {
        padding: 16px;
    }
}
</style>
