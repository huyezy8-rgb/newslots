<template>
    <div class="turntable-config-container">
        <ContentWrap title="转盘2配置" v-loading="loading">
            <div class="config-content">
                <el-form ref="formRef" :model="formData" :rules="rules" label-width="160px" @submit.prevent status-icon class="config-form">
                    <el-form-item label="转盘名称" prop="wheel_name">
                        <el-input v-model="formData.wheel_name" placeholder="请输入转盘名称" maxlength="50" show-word-limit />
                    </el-form-item>

                    <el-form-item label="解锁条件" prop="unlock_condition">
                        <el-input-number
                            v-model="formData.unlock_condition"
                            :min="0"
                            :max="999999"
                            :precision="2"
                            :step="1"
                            placeholder="充值达到多少解锁转转盘"
                        />
                        <span class="form-tip">元</span>
                    </el-form-item>

                    <el-form-item label="赠送次数" prop="free_times">
                        <el-input-number v-model="formData.free_times" :min="0" :max="100" :precision="0" :step="1" placeholder="默认赠送次数" />
                        <span class="form-tip">次</span>
                    </el-form-item>

                    <el-form-item label="用户最大次数" prop="max_user_times">
                        <el-input-number
                            v-model="formData.max_user_times"
                            :min="0"
                            :max="999999"
                            :precision="0"
                            :step="1"
                            placeholder="用户最大次数限制"
                        />
                        <span class="form-tip">次（0表示无限制）</span>
                    </el-form-item>

                    <el-form-item label="转盘状态" prop="status">
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

                <!-- 奖项配置 -->
                <div class="prizes-section">
                    <div class="section-header">
                        <h3>奖项配置（支持动态添加删除）</h3>
                        <div class="section-actions">
                            <el-button type="primary" @click="addPrize" size="small">
                                <el-icon><Plus /></el-icon>
                                添加奖项
                            </el-button>
                            <el-button type="success" @click="savePrizes" :loading="savingPrizes" size="small"> 保存奖项 </el-button>
                        </div>
                    </div>

                    <div class="prizes-info">
                        <el-alert title="奖项配置说明" type="info" :closable="false" show-icon>
                            <template #default>
                                <p>• 当前奖项数量：{{ prizesList.length }}个</p>
                                <p>• 当前总概率：{{ totalProbability.toFixed(4) }}</p>
                                <p>• 当前总奖励金额：{{ totalRewardAmount.toFixed(2) }}元</p>
                            </template>
                        </el-alert>
                    </div>

                    <div class="prizes-list">
                        <div class="table-header">
                            <el-row :gutter="20">
                                <el-col :span="7">
                                    <span class="header-text">奖项标题</span>
                                </el-col>
                                <el-col :span="5">
                                    <span class="header-text">金额(元)</span>
                                </el-col>
                                <el-col :span="5">
                                    <span class="header-text">概率</span>
                                </el-col>
                                <el-col :span="4">
                                    <span class="header-text">排序</span>
                                </el-col>
                                <el-col :span="3">
                                    <span class="header-text">操作</span>
                                </el-col>
                            </el-row>
                        </div>
                        <div v-for="(prize, index) in prizesList" :key="index" class="prize-item">
                            <el-row :gutter="20">
                                <el-col :span="7">
                                    <el-input v-model="prize.title" placeholder="奖项标题" maxlength="100" />
                                </el-col>
                                <el-col :span="5">
                                    <el-input-number v-model="prize.amount" :min="0" :max="999999" :precision="2" placeholder="金额" />
                                </el-col>
                                <el-col :span="5">
                                    <el-input-number
                                        v-model="prize.probability"
                                        :min="0"
                                        :max="10"
                                        :precision="4"
                                        :step="0.0001"
                                        placeholder="概率"
                                    />
                                </el-col>
                                <el-col :span="4">
                                    <el-input-number v-model="prize.sort" :min="1" :max="100" :precision="0" placeholder="排序" />
                                </el-col>
                                <el-col :span="3">
                                    <el-button type="danger" size="small" @click="removePrize(index)" :disabled="prizesList.length <= 1">
                                        <el-icon><Delete /></el-icon>
                                        删除
                                    </el-button>
                                </el-col>
                            </el-row>
                        </div>
                    </div>

                    <div v-if="prizesList.length === 0" class="empty-prizes">
                        <el-empty description="暂无奖项，请添加奖项" />
                    </div>
                </div>

                <!-- 规则配置 -->
                <div class="rules-section">
                    <div class="section-header">
                        <h3>转盘规则</h3>
                        <div class="section-actions">
                            <el-button type="primary" @click="addRule" size="small">
                                <el-icon><Plus /></el-icon>
                                添加规则
                            </el-button>
                            <el-button type="success" @click="saveRules" :loading="savingRules" size="small"> 保存规则 </el-button>
                        </div>
                    </div>

                    <div class="rules-info">
                        <el-alert title="规则配置说明" type="info" :closable="false" show-icon>
                            <template #default>
                                <p>• 当用户充值达到指定金额时，自动赠送转盘次数</p>
                            </template>
                        </el-alert>
                    </div>

                    <div class="rules-list">
                        <div class="table-header">
                            <el-row :gutter="20">
                                <el-col :span="8">
                                    <span class="header-text">规则类型</span>
                                </el-col>
                                <el-col :span="8">
                                    <span class="header-text">条件值(元)</span>
                                </el-col>
                                <el-col :span="8">
                                    <span class="header-text">赠送次数</span>
                                </el-col>
                            </el-row>
                        </div>
                        <div v-for="(rule, index) in rulesList" :key="index" class="rule-item">
                            <el-row :gutter="20">
                                <el-col :span="8">
                                    <el-select v-model="rule.rule_type" placeholder="规则类型">
                                        <el-option label="充值达到" :value="2" />
                                    </el-select>
                                </el-col>
                                <el-col :span="8">
                                    <el-input-number v-model="rule.condition_value" :min="0" :max="999999" :precision="2" placeholder="条件值" />
                                </el-col>
                                <el-col :span="8">
                                    <el-input-number v-model="rule.reward_times" :min="0" :max="100" :precision="0" placeholder="赠送次数" />
                                </el-col>
                            </el-row>
                            <div class="rule-actions">
                                <el-button type="danger" size="small" @click="removeRule(index)">
                                    <el-icon><Delete /></el-icon>
                                    删除
                                </el-button>
                            </div>
                        </div>
                    </div>

                    <div v-if="rulesList.length === 0" class="empty-rules">
                        <el-empty description="暂无规则，请添加规则" />
                    </div>
                </div>
            </div>
        </ContentWrap>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, type FormInstance, type FormItemRule } from 'element-plus'
import { Plus, Delete } from '@element-plus/icons-vue'
import { baTableApi } from '/@/api/common'
import { ElAlert, ElEmpty } from 'element-plus'

const api = new baTableApi('/admin/activity.lucky_wheel_turntable/')
const formRef = ref<FormInstance>()
const submitting = ref(false)
const loading = ref(false)
const savingPrizes = ref(false)
const savingRules = ref(false)

const formData = reactive({
    id: 2,
    wheel_name: '转盘2',
    unlock_condition: 0,
    free_times: 0,
    max_user_times: 0,
    status: 1,
})

const prizesList = ref([
    { title: '谢谢参与', amount: 0, probability: 0.3, sort: 1 },
    { title: '1元', amount: 1, probability: 0.2, sort: 2 },
    { title: '2元', amount: 2, probability: 0.15, sort: 3 },
    { title: '5元', amount: 5, probability: 0.1, sort: 4 },
    { title: '10元', amount: 10, probability: 0.08, sort: 5 },
    { title: '20元', amount: 20, probability: 0.05, sort: 6 },
    { title: '50元', amount: 50, probability: 0.02, sort: 7 },
    { title: '100元', amount: 100, probability: 0.01, sort: 8 },
])

const rulesList = ref<
    Array<{
        rule_type: number
        condition_value: number
        reward_times: number
        status: number
    }>
>([])

// 计算总概率
const totalProbability = computed(() => {
    return prizesList.value.reduce((sum, prize) => sum + (prize.probability || 0), 0)
})

// 计算总奖励金额
const totalRewardAmount = computed(() => {
    return prizesList.value.reduce((sum, prize) => sum + (prize.amount || 0), 0)
})

// 获取规则类型文本
const getRuleTypeText = (ruleType: number) => {
    const ruleTypes: Record<number, string> = {
        2: '充值达到',
    }
    return ruleTypes[ruleType] || '充值达到'
}

const rules: Record<string, FormItemRule[]> = {
    wheel_name: [
        { required: true, message: '请输入转盘名称', trigger: 'blur' },
        { min: 1, max: 50, message: '名称长度在1-50个字符', trigger: 'blur' },
    ],
    unlock_condition: [
        { required: true, message: '请输入解锁条件', trigger: 'blur' },
        { type: 'number' as const, min: 0, message: '解锁条件不能小于0', trigger: 'blur' },
    ],
    free_times: [
        { required: true, message: '请输入赠送次数', trigger: 'blur' },
        { type: 'number' as const, min: 0, message: '赠送次数不能小于0', trigger: 'blur' },
    ],
    max_user_times: [{ type: 'number' as const, min: 0, message: '用户最大次数不能小于0', trigger: 'blur' }],
    status: [{ required: true, message: '请选择转盘状态', trigger: 'change' }],
}

const submit = async () => {
    try {
        await formRef.value?.validate()
        submitting.value = true

        const res = await api.postData('edit', formData)
        if (res.code === 1) {
            ElMessage.success('保存成功')
            // 移除重载数据，保持页面动态效果
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
        id: 2,
        wheel_name: '转盘2',
        unlock_condition: 0,
        free_times: 0,
        max_user_times: 0,
        status: 1,
    })
    loadData()
}

// 添加奖项
const addPrize = () => {
    const newSort = prizesList.value.length > 0 ? Math.max(...prizesList.value.map((p) => p.sort)) + 1 : 1
    prizesList.value.push({
        title: '',
        amount: 0,
        probability: 0.1,
        sort: newSort,
    })
}

// 删除奖项
const removePrize = (index: number) => {
    if (prizesList.value.length <= 1) {
        ElMessage.warning('至少需要保留一个奖项')
        return
    }
    prizesList.value.splice(index, 1)
}

const savePrizes = async () => {
    // 验证奖项数据
    if (prizesList.value.length === 0) {
        ElMessage.error('请至少添加一个奖项')
        return
    }

    // 检查必填字段
    for (let i = 0; i < prizesList.value.length; i++) {
        const prize = prizesList.value[i]
        if (!prize.title.trim()) {
            ElMessage.error(`第${i + 1}个奖项的标题不能为空`)
            return
        }
        if (prize.probability < 0) {
            ElMessage.error(`第${i + 1}个奖项的概率不能为负数`)
            return
        }
    }

    savingPrizes.value = true
    try {
        const res = await api.postData('updatePrizes', {
            id: formData.id,
            prizes: prizesList.value,
        })
        if (res.code === 1) {
            ElMessage.success('奖项保存成功')
            // 移除重载数据，保持页面动态效果
        } else {
            ElMessage.error(res.msg || '奖项保存失败')
        }
    } catch (error) {
        console.error('保存奖项错误:', error)
        ElMessage.error('保存奖项失败')
    } finally {
        savingPrizes.value = false
    }
}

const addRule = () => {
    rulesList.value.push({
        rule_type: 2, // 默认选择充值达到
        condition_value: 0,
        reward_times: 0,
        status: 1,
    })
}

const removeRule = (index: number) => {
    rulesList.value.splice(index, 1)
}

const saveRules = async () => {
    savingRules.value = true
    try {
        const res = await api.postData('updateRules', {
            id: formData.id,
            rules: rulesList.value,
        })
        if (res.code === 1) {
            ElMessage.success('规则保存成功')
            // 移除重载数据，保持页面动态效果
        } else {
            ElMessage.error(res.msg || '规则保存失败')
        }
    } catch (error) {
        console.error('保存规则错误:', error)
        ElMessage.error('保存规则失败')
    } finally {
        savingRules.value = false
    }
}

const loadData = async () => {
    loading.value = true
    try {
        const res = await api.edit({ id: formData.id })
        if (res.code === 1 && res.data) {
            // 确保数字字段被正确转换为数字类型
            const data = {
                ...res.data,
                unlock_condition: parseInt(res.data.unlock_condition) || 0,
                free_times: parseInt(res.data.free_times) || 0,
                max_user_times: parseInt(res.data.max_user_times) || 0,
                status: parseInt(res.data.status) || 1,
            }
            Object.assign(formData, data)
            // 后端已确保返回数组格式
            prizesList.value = res.data.prizes_list || []
            rulesList.value = res.data.rules_list || []
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
.turntable-config-container {
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
    border-bottom: 1px solid #e4e7ed;
}

.form-tip {
    margin-left: 10px;
    color: #909399;
    font-size: 12px;
}

.prizes-section,
.rules-section {
    padding: 24px;
    border-bottom: 1px solid #e4e7ed;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h3 {
    margin: 0;
    color: #303133;
    font-size: 18px;
    font-weight: 600;
}

.section-actions {
    display: flex;
    gap: 10px;
}

.rule-actions {
    margin-top: 12px;
    text-align: right;
}

.prizes-info,
.rules-info {
    margin-bottom: 20px;
}

.prize-item,
.rule-item {
    padding: 16px;
    margin-bottom: 12px;
    background: #fafbfc;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.prize-item:hover,
.rule-item:hover {
    background: #f0f2f5;
    border-color: #c0c4cc;
}

.prizes-list,
.rules-list {
    margin-bottom: 20px;
}

.table-header {
    padding: 16px;
    background: #f0f2f5;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    margin-bottom: 12px;
}

.header-text {
    font-weight: 600;
    color: #303133;
    font-size: 14px;
}

.empty-prizes,
.empty-rules {
    padding: 40px 0;
    text-align: center;
}

@media (max-width: 768px) {
    .turntable-config-container {
        padding: 10px;
    }

    .config-form,
    .prizes-section,
    .rules-section {
        padding: 16px;
    }

    .prize-item,
    .rule-item {
        padding: 12px;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .section-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
