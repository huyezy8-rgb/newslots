<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('red.envelope.redemption.code.quick Search Fields') })"
        ></TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, useTemplateRef, h } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'red/envelope/redemption/code',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/red.envelope.redemption.Code/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            {
                label: t('red.envelope.redemption.code.code'),
                prop: 'code',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('red.envelope.redemption.code.amount_min'), prop: 'amount_min', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('red.envelope.redemption.code.amount_max'), prop: 'amount_max', align: 'center', operator: 'RANGE', sortable: false },
            {
                label: '已兑换数量',
                prop: 'used_count',
                align: 'center',
                render: 'tag',
                operator: 'RANGE',
                sortable: false,
                custom: {
                    0: 'info',
                },
                replaceValue: (value: number) => {
                    if (value === 0) return '0次';
                    return `${value}次`;
                }
            },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {},
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

<style scoped lang="scss">
/* 为已兑换数量标签设置颜色 */
:deep(.el-tag) {
    &.el-tag--info {
        background-color: #f4f4f5;
        border-color: #909399;
        color: #909399;
    }
}
</style>
