<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('team.commission.log.quick Search Fields') })"
        ></TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单已移除 - 团队返佣记录只允许查看，不允许添加、修改、删除 -->
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { baTableApi } from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'team/commission/log',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
// 移除操作按钮 - 团队返佣记录只允许查看
const optButtons: OptButton[] = []

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/team.commission.Log/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('team.commission.log.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('team.commission.log.user_id'), prop: 'user_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            {
                label: t('team.commission.log.channel__name'),
                prop: 'channel.name',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                render: 'tags',
                operator: 'LIKE',
            },
            {
                label: t('team.commission.log.source_user_id'),
                prop: 'source_user_id',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
            },
            { label: t('team.commission.log.bet_amount'), prop: 'bet_amount', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('team.commission.log.base_rate'), prop: 'base_rate', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('team.commission.log.point_diff'), prop: 'point_diff', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('team.commission.log.commission'), prop: 'commission', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('team.commission.log.level'), prop: 'level', align: 'center', operator: 'RANGE', sortable: false },
            {
                label: t('team.commission.log.create_time'),
                prop: 'create_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: '',
            },
            // 移除操作列 - 团队返佣记录只允许查看
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

<style scoped lang="scss"></style>
