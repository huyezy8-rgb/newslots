<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />
        <TableHeader :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']" quick-search-placeholder="模板标题 / 备注" />
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
    name: 'tg/template',
})

const tableRef = useTemplateRef('tableRef')
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

const baTable = new baTableClass(
    new baTableApi('/admin/tg.template/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: '模板标题', prop: 'title', align: 'center', operator: 'LIKE' },
            {
                label: '媒体类型',
                prop: 'media_type',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                replaceValue: { none: '文字', image: '图片', gif: 'GIF', video: '视频' },
            },
            { label: '媒体地址', prop: 'media_url', align: 'center', operator: 'LIKE', width: 220 },
            { label: '备注', prop: 'remark', align: 'center', operator: 'LIKE' },
            {
                label: '启用',
                prop: 'is_enabled',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                replaceValue: { 0: '否', 1: '是' },
                custom: { 0: 'danger', 1: 'success' },
            },
            {
                label: '默认',
                prop: 'is_default',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                replaceValue: { 0: '否', 1: '是' },
                custom: { 0: 'info', 1: 'success' },
            },
            { label: '创建时间', prop: 'created_at', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160 },
            { label: '操作', align: 'center', width: 110, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {
            media_type: 'none',
            media_url: '',
            is_enabled: 1,
            is_default: 0,
            buttons_json: [{ text: 'Redeem Now', url: 'https://xxx.com/p1#/?bonus_code={code}' }],
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
