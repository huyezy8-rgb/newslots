<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('banner.quick Search Fields') })"
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
    name: 'banner',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/Banner/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('banner.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('banner.channel__name'), prop: 'channel.name', align: 'center', render: 'tags', operator: false },
            {
                label: t('banner.channel__name'),
                prop: 'channel_ids',
                align: 'center',
                operator: 'FIND_IN_SET',
                show: false,
                comSearchRender: 'remoteSelect',
                remote: { pk: 'id', field: 'name', remoteUrl: '/admin/channel.Listsss/index', multiple: true },
            },
            { label: t('banner.title'), prop: 'title', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            {
                label: t('banner.content'),
                prop: 'content',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('banner.image'), prop: 'image', align: 'center', render: 'image', operator: false },
            {
                label: t('banner.jump_type'),
                prop: 'jump_type',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: { '0': t('banner.jump_type 0'), '1': t('banner.jump_type 1') },
            },
            { label: t('banner.link'), prop: 'link', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            {
                label: t('banner.activity'),
                prop: 'activity',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('banner.sort'), prop: 'sort', align: 'center', operator: 'RANGE', sortable: false },
            {
                label: t('banner.status'),
                prop: 'status',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: { '0': t('banner.status 0'), '1': t('banner.status 1') },
            },
            {
                label: t('banner.start_time'),
                prop: 'start_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: '',
            },
            {
                label: t('banner.end_time'),
                prop: 'end_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: '',
            },
            {
                label: t('banner.create_time'),
                prop: 'create_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: '',
            },
            {
                label: t('banner.update_time'),
                prop: 'update_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: '',
            },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: { jump_type: '0', status: '1' },
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
