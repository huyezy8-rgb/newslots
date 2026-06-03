<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']" quick-search-placeholder="规则名称" />
        <Table ref="tableRef" />
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, useTemplateRef } from 'vue'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'tg/rule',
})

const tableRef = useTemplateRef('tableRef')
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

const baTable = new baTableClass(
    new baTableApi('/admin/tg.rule/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: '规则名称', prop: 'rule_name', align: 'center', operator: 'LIKE' },
            { label: '最小金额', prop: 'amount_min', align: 'center', operator: 'RANGE', sortable: 'custom' },
            { label: '最大金额', prop: 'amount_max', align: 'center', operator: 'RANGE', sortable: 'custom' },
            { label: '有效期小时', prop: 'expire_hours', align: 'center', operator: 'RANGE', sortable: 'custom' },
            { label: '每人领取次数', prop: 'per_user_limit', align: 'center', operator: 'RANGE', sortable: 'custom' },
            { label: '最大领取人数', prop: 'max_claim_users', align: 'center', operator: 'RANGE', sortable: 'custom' },
            {
                label: '启用',
                prop: 'is_enabled',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                replaceValue: { 0: '否', 1: '是' },
                custom: { 0: 'danger', 1: 'success' },
            },
            { label: '创建时间', prop: 'created_at', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160 },
            { label: '操作', align: 'center', width: 110, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {
            rule_name: '',
            amount_min: 0,
            amount_max: 0,
            expire_hours: 24,
            per_user_limit: 1,
            max_claim_users: 0,
            is_enabled: 1,
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
