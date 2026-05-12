<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('activity.quick Search Fields') })"
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
import { onMounted, provide, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'activity',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const optButtons: OptButton[] = defaultOptButtons(['weigh-sort', 'edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/Activity/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('activity.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('activity.type'), prop: 'type', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('activity.name'), prop: 'name', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            {
                label: t('activity.is_popup'),
                prop: 'is_popup',
                align: 'center',
                render: 'switch',
                operator: 'eq',
                sortable: false,
                replaceValue: { '0': t('activity.is_popup 0'), '1': t('activity.is_popup 1') },
            },
            {
                label: t('activity.is_sidebar'),
                prop: 'is_sidebar',
                align: 'center',
                render: 'switch',
                operator: 'eq',
                sortable: false,
                replaceValue: { '0': t('activity.is_sidebar 0'), '1': t('activity.is_sidebar 1') },
            },
            {
                label: t('activity.is_bet_multiplier'),
                prop: 'is_bet_multiplier',
                align: 'center',
                render: 'switch',
                operator: 'eq',
                sortable: false,
                replaceValue: { '0': t('activity.is_bet_multiplier 0'), '1': t('activity.is_bet_multiplier 1') },
            },
            {
                label: t('activity.group'),
                prop: 'group',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: { Rewards: t('activity.group Rewards'), Events: t('activity.group Events'), null: t('activity.group null') },
            },
            { label: t('activity.status'), prop: 'status', align: 'center', operator: 'RANGE', sortable: false },
            {
                label: t('activity.create_time'),
                prop: 'create_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: '',
            },
            {
                label: t('activity.update_time'),
                prop: 'update_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: '',
            },
            { label: t('activity.sort'), prop: 'sort', align: 'center', operator: 'RANGE', sortable: 'custom' },
            { label: t('Operate'), align: 'center', width: 140, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined, 'is_popup', 'is_sidebar', 'is_bet_multiplier'],
        defaultOrder: { prop: 'sort', order: 'desc' },
    },
    {
        defaultItems: { is_sidebar: '1', is_bet_multiplier: '1', group: 'null', status: 1 },
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

<style scoped lang="scss"></style>
