<template>
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        width="64%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate }}
            </div>
        </template>

        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :style="config.layout.shrink ? '' : 'width: calc(100% - ' + baTable.form.labelWidth! / 2 + 'px)'">
                <el-form
                    v-if="!baTable.form.loading"
                    ref="formRef"
                    @submit.prevent=""
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >
                    <FormItem label="机器人名称" type="string" v-model="baTable.form.items!.name" prop="name" />

                    <el-form-item label="Bot Token" prop="bot_token">
                        <div class="inline-action">
                            <el-input v-model="baTable.form.items!.bot_token" clearable show-password />
                            <el-button :loading="tokenLoading" @click="fetchBotInfo">获取机器人信息</el-button>
                        </div>
                        <div v-if="botUsername" class="form-tip">Username: @{{ botUsername }}</div>
                    </el-form-item>

                    <el-form-item label="Chat ID" prop="chat_id">
                        <div class="inline-action">
                            <el-input v-model="baTable.form.items!.chat_id" clearable placeholder="支持数字 Chat ID 或 @频道用户名" />
                            <el-button :loading="chatIdsLoading" @click="fetchChatIds(false)">获取 Chat ID</el-button>
                            <el-button :loading="deleteWebhookLoading" @click="fetchChatIds(true)">清除 Webhook 并获取</el-button>
                            <el-button :loading="chatTestLoading" @click="sendChatTest">发送测试消息</el-button>
                        </div>

                        <div class="manual-chat">
                            <el-input v-model="manualChatInput" clearable placeholder="可粘贴 @gold7ptest、https://t.me/gold7ptest、https://t.me/c/123456/789 或直接填 Chat ID" />
                            <el-button @click="fillManualChatId">解析/填入</el-button>
                        </div>

                        <el-select v-if="chatOptions.length" class="w100 chat-select" placeholder="选择最近获取到的 Chat ID" @change="selectChatId">
                            <el-option
                                v-for="item in chatOptions"
                                :key="item.chat_id"
                                :label="formatChatOption(item)"
                                :value="item.chat_id"
                            />
                        </el-select>
                        <div class="form-tip">
                            如果 getUpdates 获取不到，请把机器人设为管理员，并在群里 @机器人用户名 发送一条消息；频道用户名可以直接作为 Chat ID 测试发送。
                        </div>
                    </el-form-item>

                    <FormItem label="是否启用" type="radio" v-model="baTable.form.items!.is_enabled" prop="is_enabled" :input-attr="{ content: { 0: '否', 1: '是' } }" />
                    <FormItem label="发送间隔分钟" type="number" v-model="baTable.form.items!.send_interval_minutes" prop="send_interval_minutes" :input-attr="{ step: 1, min: 1 }" />
                    <FormItem label="每日发送上限" type="number" v-model="baTable.form.items!.daily_send_limit" prop="daily_send_limit" :input-attr="{ step: 1, min: 0 }" />
                    <FormItem label="发送开始时间" type="string" v-model="baTable.form.items!.send_time_start" prop="send_time_start" placeholder="09:00" />
                    <FormItem label="发送结束时间" type="string" v-model="baTable.form.items!.send_time_end" prop="send_time_end" placeholder="23:00" />

                    <el-form-item label="兑换码位数" prop="code_length">
                        <el-select v-model="baTable.form.items!.code_length" class="w100">
                            <el-option label="4位大写" :value="4" />
                            <el-option label="5位大写" :value="5" />
                            <el-option label="6位大写" :value="6" />
                            <el-option label="8位大写" :value="8" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="默认文案模板" prop="template_id">
                        <el-select v-model="baTable.form.items!.template_id" class="w100" clearable placeholder="不选择则使用默认模板" @clear="baTable.form.items!.template_id = 0">
                            <el-option label="不指定" :value="0" />
                            <el-option v-for="item in templateOptions" :key="item.id" :label="item.title" :value="item.id" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="红包规则" prop="redemption_rule_id">
                        <el-select v-model="baTable.form.items!.redemption_rule_id" class="w100" clearable placeholder="请选择红包兑换码规则" @clear="baTable.form.items!.redemption_rule_id = 0">
                            <el-option label="不指定" :value="0" />
                            <el-option v-for="item in ruleOptions" :key="item.id" :label="formatRuleOption(item)" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </el-form>
            </div>
        </el-scrollbar>

        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">取消</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="submit" type="primary">保存</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormItemRule } from 'element-plus'
import { inject, onMounted, reactive, ref, useTemplateRef } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { baTableApi } from '/@/api/common'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

type ChatOption = {
    chat_id: string
    title?: string
    type?: string
}

type RuleOption = {
    id: number
    rule_name: string
    amount_min?: string | number
    amount_max?: string | number
    expire_hours?: number
    per_user_limit?: number
    max_claim_users?: number
}

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass
const templateOptions = ref<{ id: number; title: string }[]>([])
const ruleOptions = ref<RuleOption[]>([])
const chatOptions = ref<ChatOption[]>([])
const botUsername = ref('')
const manualChatInput = ref('')
const tokenLoading = ref(false)
const chatIdsLoading = ref(false)
const deleteWebhookLoading = ref(false)
const chatTestLoading = ref(false)
const botApi = new baTableApi('/admin/tg.bot/')

const getErrorMessage = (err: any, fallback = '操作失败') => {
    return err?.response?.data?.msg || err?.msg || err?.message || err?.data?.msg || fallback
}

const getResponseMessage = (res: any, fallback: string) => {
    return res?.msg || res?.data?.message || res?.message || fallback
}

const submit = () => {
    if (!baTable.form.items!.template_id) {
        baTable.form.items!.template_id = 0
    }
    if (!baTable.form.items!.redemption_rule_id) {
        baTable.form.items!.redemption_rule_id = 0
    }
    try {
        baTable.onSubmit(formRef.value)
    } catch (err: any) {
        ElMessage.error(getErrorMessage(err, '保存失败'))
    }
}

const fetchBotInfo = async () => {
    tokenLoading.value = true
    try {
        const res = await botApi.postData('tokenInfo', {
            bot_token: baTable.form.items!.bot_token || '',
        })
        const data = res.data || {}
        botUsername.value = data.username || ''
        if (!baTable.form.items!.name && (data.name || data.first_name || data.username)) {
            baTable.form.items!.name = data.name || data.first_name || data.username
        }
        ElMessage.success(getResponseMessage(res, 'Token 可用'))
    } catch (err: any) {
        ElMessage.error(getErrorMessage(err, '获取机器人信息失败'))
    } finally {
        tokenLoading.value = false
    }
}

const fetchChatIds = async (deleteWebhook: boolean) => {
    if (deleteWebhook) {
        deleteWebhookLoading.value = true
    } else {
        chatIdsLoading.value = true
    }
    try {
        const action = deleteWebhook ? 'chatIdsAfterDeleteWebhook' : 'chatIdsByToken'
        const res = await botApi.postData(action, {
            bot_token: baTable.form.items!.bot_token || '',
        })
        chatOptions.value = res.data?.list || []
        if (chatOptions.value.length) {
            ElMessage.success(getResponseMessage(res, '获取 Chat ID 成功'))
        } else {
            await ElMessageBox.alert(getResponseMessage(res, '未获取到 Chat ID'), 'Chat ID 获取提示', {
                type: 'warning',
                customClass: 'tg-chat-id-alert',
            })
        }
    } catch (err: any) {
        chatOptions.value = []
        ElMessage.error(getErrorMessage(err, '获取 Chat ID 失败'))
    } finally {
        chatIdsLoading.value = false
        deleteWebhookLoading.value = false
    }
}

const sendChatTest = async () => {
    chatTestLoading.value = true
    try {
        const res = await botApi.postData('sendChatTestByConfig', {
            bot_token: baTable.form.items!.bot_token || '',
            chat_id: baTable.form.items!.chat_id || '',
        })
        ElMessage.success(getResponseMessage(res, '测试消息发送成功'))
    } catch (err: any) {
        ElMessage.error(getErrorMessage(err, '发送测试消息失败'))
    } finally {
        chatTestLoading.value = false
    }
}

const selectChatId = (chatId: string) => {
    baTable.form.items!.chat_id = chatId
}

const fillManualChatId = () => {
    const value = parseChatInput(manualChatInput.value)
    if (!value) {
        ElMessage.warning('请输入频道用户名、Telegram 链接或 Chat ID')
        return
    }
    baTable.form.items!.chat_id = value
    ElMessage.success('已填入 Chat ID')
}

const parseChatInput = (input: string) => {
    const value = input.trim()
    if (!value) return ''
    if (/^-?\d+$/.test(value) || value.startsWith('@')) return value

    const channelMatch = value.match(/t\.me\/c\/(\d+)/i)
    if (channelMatch?.[1]) {
        return '-100' + channelMatch[1]
    }

    const usernameMatch = value.match(/t\.me\/([A-Za-z0-9_]+)/i)
    if (usernameMatch?.[1] && usernameMatch[1] !== 'c') {
        return '@' + usernameMatch[1]
    }

    return value
}

const formatChatOption = (item: ChatOption) => {
    const type = item.type ? `(${item.type})` : ''
    const title = item.title ? ` ${item.title}` : ''
    return `${item.chat_id} ${type}${title}`
}

const formatRuleOption = (item: RuleOption) => {
    return `${item.rule_name} / ${item.amount_min ?? 0}-${item.amount_max ?? 0} / ${item.expire_hours ?? 0}小时`
}

const loadTemplates = async () => {
    try {
        const res = await new baTableApi('/admin/tg.template/').index({
            limit: 999,
            search: [{ field: 'is_enabled', operator: 'eq', val: '1' }],
        } as any)
        templateOptions.value = (res.data?.list || []).map((item: any) => ({
            id: Number(item.id),
            title: item.title || `#${item.id}`,
        }))
    } catch (err: any) {
        templateOptions.value = []
        ElMessage.error(getErrorMessage(err, '文案模板加载失败'))
    }
}

const loadRules = async () => {
    try {
        const res = await botApi.postData('redemptionRules', {})
        ruleOptions.value = (res.data?.list || []).map((item: any) => ({
            id: Number(item.id),
            rule_name: item.rule_name || `#${item.id}`,
            amount_min: item.amount_min,
            amount_max: item.amount_max,
            expire_hours: item.expire_hours,
            per_user_limit: item.per_user_limit,
            max_claim_users: item.max_claim_users,
        }))
    } catch (err: any) {
        ruleOptions.value = []
        ElMessage.error(getErrorMessage(err, '红包规则加载失败'))
    }
}

onMounted(() => {
    loadTemplates()
    loadRules()
})

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    name: [buildValidatorData({ name: 'required', title: '机器人名称' })],
    bot_token: [buildValidatorData({ name: 'required', title: 'Bot Token' })],
    chat_id: [buildValidatorData({ name: 'required', title: 'Chat ID' })],
    send_interval_minutes: [buildValidatorData({ name: 'number', title: '发送间隔分钟' })],
    daily_send_limit: [buildValidatorData({ name: 'number', title: '每日发送上限' })],
})
</script>

<style scoped lang="scss">
.w100 {
    width: 100%;
}

.inline-action,
.manual-chat {
    display: flex;
    width: 100%;
    gap: 8px;
}

.inline-action .el-input,
.manual-chat .el-input {
    flex: 1;
}

.manual-chat,
.chat-select {
    margin-top: 8px;
}

.form-tip {
    margin-top: 6px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 18px;
}
</style>
