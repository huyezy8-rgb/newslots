<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('jackpot.withdraw.log.quick Search Fields') })"
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
    name: 'jackpot/withdraw/log',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/jackpot.withdraw.Log/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('jackpot.withdraw.log.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('jackpot.withdraw.log.user_id'), prop: 'user_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            {
                label: t('jackpot.withdraw.log.user__nickname'),
                prop: 'user.nickname',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                render: 'tags',
                operator: 'LIKE',
            },
            { label: t('jackpot.withdraw.log.current_amount'), prop: 'current_amount', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('jackpot.withdraw.log.withdraw_amount'), prop: 'withdraw_amount', align: 'center', operator: 'RANGE', sortable: false },
            {
                label: t('jackpot.withdraw.log.status'),
                prop: 'status',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: {
                    0: '未提现',
                    1: '已提现'
                },
                custom: {
                    0: 'warning',
                    1: 'success'
                },
            },
            {
                label: t('jackpot.withdraw.log.is_lucky'),
                prop: 'is_lucky',
                align: 'center',
                operator: 'RANGE',
                sortable: false,
                render: 'tag',
                replaceValue: {
                    0: '否',
                    1: '是'
                },
                custom: {
                    0: 'info',
                    1: 'danger'
                },
            },
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
