<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'comSearch', 'quickSearch', 'columnDisplay']" quick-search-placeholder="兑换码" />
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
    name: 'tg/codeStats',
})

const tableRef = useTemplateRef('tableRef')

const baTable = new baTableClass(new baTableApi('/admin/tg.codeStats/'), {
    pk: 'redemption_code_id',
    column: [
        { label: '兑换码ID', prop: 'redemption_code_id', align: 'center', width: 90, operator: false, sortable: 'custom' },
        { label: '兑换码', prop: 'code', align: 'center', operator: 'LIKE', width: 120 },
        { label: '机器人ID', prop: 'bot_id', align: 'center', operator: '=', width: 90 },
        { label: '机器人', prop: 'bot_name', align: 'center', operator: false, width: 160 },
        { label: '模板', prop: 'template_name', align: 'center', operator: false, width: 160 },
        { label: '首次发送时间', prop: 'first_send_time', align: 'center', render: 'datetime', operator: false, sortable: 'custom', width: 160 },
        { label: '最后发送时间', prop: 'last_send_time', align: 'center', render: 'datetime', operator: false, sortable: 'custom', width: 160 },
        { label: '发送时间', prop: 'send_time', align: 'center', render: 'datetime', operator: 'RANGE', visible: false },
        { label: '发送次数', prop: 'send_count', align: 'center', operator: false, sortable: 'custom', width: 100 },
        { label: '成功发送', prop: 'success_send_count', align: 'center', operator: false, sortable: 'custom', width: 100 },
        { label: '失败发送', prop: 'failed_send_count', align: 'center', operator: false, sortable: 'custom', width: 100 },
        {
            label: '是否有领取',
            prop: 'has_claim',
            align: 'center',
            render: 'tag',
            operator: '=',
            replaceValue: { 0: '否', 1: '是' },
            custom: { 0: 'info', 1: 'success' },
            width: 110,
        },
        {
            label: '是否有充值',
            prop: 'has_recharge',
            align: 'center',
            render: 'tag',
            operator: '=',
            replaceValue: { 0: '否', 1: '是' },
            custom: { 0: 'info', 1: 'success' },
            width: 110,
        },
        { label: '领取人数', prop: 'claim_count', align: 'center', operator: false, sortable: 'custom', width: 100 },
        { label: '领取金额', prop: 'claim_amount', align: 'center', operator: false, sortable: 'custom', width: 110 },
        { label: '注册人数', prop: 'register_count', align: 'center', operator: false, sortable: 'custom', width: 100 },
        { label: '首充人数', prop: 'first_recharge_count', align: 'center', operator: false, sortable: 'custom', width: 100 },
        { label: '首充金额', prop: 'first_recharge_amount', align: 'center', operator: false, sortable: 'custom', width: 110 },
        { label: '充值人数', prop: 'recharge_count', align: 'center', operator: false, sortable: 'custom', width: 100 },
        { label: '充值金额', prop: 'recharge_amount', align: 'center', operator: false, sortable: 'custom', width: 110 },
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
