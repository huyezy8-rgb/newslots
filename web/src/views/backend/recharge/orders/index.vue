<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('recharge.orders.quick Search Fields') })"
        ></TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

<!--        &lt;!&ndash; 表单 &ndash;&gt;-->
<!--        <PopupForm />-->
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, reactive, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
// import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
// import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { useAdminInfo } from '/@/stores/adminInfo'
import createAxios from '/@/utils/axios'
const adminInfo = useAdminInfo()

defineOptions({
    name: 'recharge/orders',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
// const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])
const paymentTypeOptions = reactive<Record<string, string>>({})

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/recharge.Orders/'),
    {
        pk: 'id',
        showComSearch: true,
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('recharge.orders.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            {
                label: t('recharge.orders.order_no'),
                prop: 'order_no',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('recharge.orders.user_id'), prop: 'user_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            { label: t('recharge.orders.channel_id'), prop: 'channel_id', align: 'center', operator: adminInfo.isAdminChannelId == null ? '=' : false, operatorPlaceholder: t('Fuzzy query') },
            { label: t('recharge.orders.amount'), prop: 'amount', align: 'center', operator: 'RANGE', sortable: false },
            {
                label: t('recharge.orders.pay_type'),
                prop: 'pay_type',
                align: 'center',
                operatorPlaceholder: t('Please select field', { field: t('recharge.orders.pay_type') }),
                operator: 'eq',
                comSearchRender: 'select',
                replaceValue: paymentTypeOptions,
                sortable: false,
            },
            {
                label: t('recharge.orders.pay_status'),
                prop: 'pay_status',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: {
                    0: '待支付',
                    1: '已支付',
                    2: '失败'
                },
                custom: {
                    0: 'warning',
                    1: 'success',
                    2: 'danger'
                },
            },
            {
                label: t('recharge.orders.remark'),
                prop: 'remark',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('recharge.orders.paid_at'), prop: 'paid_at', align: 'center', operator: 'eq', sortable: 'custom', width: 160, render: 'datetime' },
            { label: t('recharge.orders.created_at'), prop: 'created_at', align: 'center', operator: 'eq', sortable: 'custom', width: 160, render: 'datetime' },
            { label: t('recharge.orders.updated_at'), prop: 'updated_at', align: 'center', operator: 'eq', sortable: 'custom', width: 160, render: 'datetime' },
           ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: { pay_status: '0', created_at: 'CURRENT_TIMESTAMP', updated_at: 'CURRENT_TIMESTAMP' },
    }
)

provide('baTable', baTable)

const loadPaymentTypeOptions = () => {
    createAxios<Record<string, string>>({
        url: '/admin/payment.Methods/rechargeOptions',
        method: 'get',
    }).then((res) => {
        Object.keys(paymentTypeOptions).forEach((key) => delete paymentTypeOptions[key])
        Object.assign(paymentTypeOptions, res.data)
    })
}

onMounted(() => {
    loadPaymentTypeOptions()
    baTable.table.ref = tableRef.value
    baTable.mount()
    baTable.getData()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
    })
})
</script>

<style scoped lang="scss"></style>
