<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'comSearch', 'quickSearch', 'columnDisplay']" quick-search-placeholder="兑换码 / Chat ID / 消息ID" />
        <Table ref="tableRef" />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, useTemplateRef } from 'vue'
import { baTableApi } from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'tg/log',
})

const tableRef = useTemplateRef('tableRef')

const formatJson = (value: any) => {
    if (!value) return ''
    return typeof value === 'string' ? value : JSON.stringify(value)
}

const baTable = new baTableClass(new baTableApi('/admin/tg.log/'), {
    pk: 'id',
    column: [
        { label: 'ID', prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
        { label: '发送时间', prop: 'send_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160 },
        { label: '机器人', prop: 'bot.name', align: 'center', operator: false },
        { label: '模板', prop: 'template_name', align: 'center', operator: 'LIKE' },
        { label: '兑换码', prop: 'code', align: 'center', operator: 'LIKE' },
        { label: 'Chat ID', prop: 'chat_id', align: 'center', operator: 'LIKE' },
        { label: '消息ID', prop: 'message_id', align: 'center', operator: 'LIKE' },
        {
            label: '媒体类型',
            prop: 'media_type',
            align: 'center',
            render: 'tag',
            operator: 'eq',
            replaceValue: { none: '文字', image: '图片', gif: 'GIF', video: '视频' },
        },
        { label: '媒体地址', prop: 'media_url', align: 'center', operator: 'LIKE', width: 220 },
        {
            label: '发送类型',
            prop: 'send_type',
            align: 'center',
            render: 'tag',
            operator: 'eq',
            replaceValue: { auto: '自动', manual: '手动', test: '测试' },
        },
        {
            label: '状态',
            prop: 'send_status',
            align: 'center',
            render: 'tag',
            operator: 'eq',
            replaceValue: { 0: '失败', 1: '成功' },
            custom: { 0: 'danger', 1: 'success' },
        },
        { label: '失败原因', prop: 'fail_reason', align: 'center', operator: 'LIKE', width: 220 },
        { label: '实际文案', prop: 'content', align: 'center', operator: 'LIKE', width: 260 },
        { label: '实际按钮JSON', prop: 'buttons_json', align: 'center', operator: false, width: 260, formatter: (row: TableRow) => formatJson(row.buttons_json) },
        { label: '领取人数', prop: 'claim_count', align: 'center', operator: 'RANGE' },
        { label: '领取金额', prop: 'claim_amount', align: 'center', operator: 'RANGE' },
        { label: '注册人数', prop: 'register_count', align: 'center', operator: 'RANGE' },
        { label: '首充人数', prop: 'first_recharge_count', align: 'center', operator: 'RANGE' },
        { label: '首充金额', prop: 'first_recharge_amount', align: 'center', operator: 'RANGE' },
        { label: '充值人数', prop: 'recharge_count', align: 'center', operator: 'RANGE' },
        { label: '充值金额', prop: 'recharge_amount', align: 'center', operator: 'RANGE' },
    ],
    dblClickNotEditColumn: [undefined],
})

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
