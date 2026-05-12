<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh','comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('api.log.quick Search Fields') })"
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
import { baTableApi } from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'api/log',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/api.Log/'),
    {
        pk: 'id',
        column: [
            { label: t('api.log.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('api.log.route'), prop: 'route', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('api.log.method'), prop: 'method', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('api.log.ip'), prop: 'ip', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            {
                label: t('api.log.user_agent'),
                prop: 'user_agent',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('api.log.status'), prop: 'status', align: 'center',  sortable: 'custom', },
            { label: t('api.log.cost_ms'), prop: 'cost_ms', align: 'center',  sortable: 'custom' },
            {
                label: t('api.log.create_time'),
                prop: 'create_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,

            },

        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: { status: 200 },
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
