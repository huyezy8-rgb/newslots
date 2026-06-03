<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']" quick-search-placeholder="机器人名称 / Chat ID" />
        <Table ref="tableRef" />
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, ref, useTemplateRef } from 'vue'
import { ElLoading, ElMessage, ElMessageBox } from 'element-plus'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'tg/bot',
})

const tableRef = useTemplateRef('tableRef')
const actionLoading = ref(false)

const getMessage = (res: any, fallback: string) => {
    return res?.msg || res?.data?.message || res?.data?.hint || res?.message || fallback
}

const getErrorMessage = (err: any, fallback = '操作失败') => {
    return err?.response?.data?.msg || err?.msg || err?.message || err?.data?.msg || fallback
}

const runAction = async (text: string, callback: () => Promise<void>) => {
    if (actionLoading.value) return
    actionLoading.value = true
    const loading = ElLoading.service({ text, background: 'rgba(255, 255, 255, 0.65)' })
    try {
        await callback()
    } finally {
        loading.close()
        actionLoading.value = false
    }
}

const testToken: OptButton = {
    render: 'tipButton',
    name: 'testToken',
    title: '测试 Token',
    text: 'Token',
    type: 'primary',
    icon: 'fa fa-check-circle',
    class: 'table-row-info',
    disabled: () => actionLoading.value,
    click: async (row: TableRow) => {
        await runAction('正在测试 Token...', async () => {
            try {
                const res = await baTable.api.postData('testToken', { id: row.id })
                ElMessage.success(getMessage(res, 'Token 可用'))
            } catch (err: any) {
                ElMessage.error(getErrorMessage(err, 'Token 测试失败'))
            }
        })
    },
}

const getChatIds: OptButton = {
    render: 'tipButton',
    name: 'getChatIds',
    title: '获取 Chat ID',
    text: 'Chat ID',
    type: 'warning',
    icon: 'fa fa-list',
    class: 'table-row-info',
    disabled: () => actionLoading.value,
    click: async (row: TableRow) => {
        await runAction('正在获取 Chat ID...', async () => {
            try {
                const res = await baTable.api.postData('getChatIds', { id: row.id })
                const list = res.data?.list || []
                const content = list.length
                    ? list.map((item: any) => `${item.chat_id} ${item.type ? '(' + item.type + ')' : ''} ${item.title || ''}`).join('\n')
                    : getMessage(res, '未获取到 Chat ID')
                await ElMessageBox.alert(content, 'Chat ID')
            } catch (err: any) {
                ElMessage.error(getErrorMessage(err, '获取 Chat ID 失败'))
            }
        })
    },
}

const sendChatTest: OptButton = {
    render: 'tipButton',
    name: 'sendChatTest',
    title: '发送测试消息',
    text: '消息',
    type: 'success',
    icon: 'fa fa-paper-plane',
    class: 'table-row-info',
    disabled: () => actionLoading.value,
    click: async (row: TableRow) => {
        await runAction('正在发送测试消息...', async () => {
            try {
                const res = await baTable.api.postData('sendChatTest', { id: row.id })
                ElMessage.success(getMessage(res, '测试消息发送成功'))
            } catch (err: any) {
                ElMessage.error(getErrorMessage(err, '发送测试消息失败'))
            }
        })
    },
}

const testSend: OptButton = {
    render: 'tipButton',
    name: 'testSend',
    title: '测试发送模板消息',
    text: '模板',
    type: 'info',
    icon: 'fa fa-send',
    class: 'table-row-info',
    disabled: () => actionLoading.value,
    click: async (row: TableRow) => {
        await runAction('正在测试发送...', async () => {
            try {
                const res = await baTable.api.postData('testSend', { id: row.id })
                ElMessage.success(getMessage(res, '测试发送成功'))
                baTable.onTableHeaderAction('refresh', {})
            } catch (err: any) {
                ElMessage.error(getErrorMessage(err, '测试发送失败'))
            }
        })
    },
}

const optButtons: OptButton[] = [testToken, getChatIds, sendChatTest, testSend, ...defaultOptButtons(['edit', 'delete'])]

const baTable = new baTableClass(
    new baTableApi('/admin/tg.bot/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: '机器人名称', prop: 'name', align: 'center', operator: 'LIKE' },
            { label: 'Chat ID', prop: 'chat_id', align: 'center', operator: 'LIKE' },
            { label: '发送间隔(分钟)', prop: 'send_interval_minutes', align: 'center', operator: 'RANGE' },
            { label: '每日上限', prop: 'daily_send_limit', align: 'center', operator: 'RANGE' },
            { label: '发送开始', prop: 'send_time_start', align: 'center', operator: false },
            { label: '发送结束', prop: 'send_time_end', align: 'center', operator: false },
            { label: '兑换码位数', prop: 'code_length', align: 'center', operator: 'eq' },
            { label: '默认模板ID', prop: 'template_id', align: 'center', operator: 'RANGE' },
            { label: '红包规则ID', prop: 'redemption_rule_id', align: 'center', operator: 'RANGE' },
            {
                label: '启用',
                prop: 'is_enabled',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                replaceValue: { 0: '否', 1: '是' },
                custom: { 0: 'danger', 1: 'success' },
            },
            { label: '操作', align: 'center', width: 360, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {
            is_enabled: 1,
            send_interval_minutes: 120,
            daily_send_limit: 0,
            send_time_start: '09:00',
            send_time_end: '23:00',
            code_length: 4,
            template_id: 0,
            redemption_rule_id: 0,
        },
    }
)

provide('baTable', baTable)

onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.mount()
    baTable.getData()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
    })
})
</script>
