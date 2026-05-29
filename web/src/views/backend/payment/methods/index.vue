<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />
        <div class="payment-channel-filter">
            <span class="payment-channel-filter__label">{{ t('payment.methods.channelcodetable__name') }}</span>
            <el-tabs v-model="activeChannelTab" class="payment-channel-tabs" @tab-change="onChannelTabChange">
                <el-tab-pane v-for="channel in channelTabs" :key="channel" :label="channel" :name="channel" />
            </el-tabs>
        </div>

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('payment.methods.quick Search Fields') })"
        >
            <el-tooltip v-if="baTable.auth('edit')" content="编辑" placement="top">
                <el-button v-blur @click="openSingleEdit" :disabled="selectedCount !== 1" class="payment-methods-header-button" type="primary">
                    <Icon name="fa fa-pencil" />
                    <span class="payment-methods-header-button__text">编辑</span>
                </el-button>
            </el-tooltip>
            <el-tooltip v-if="baTable.auth('edit')" content="批量编辑" placement="top">
                <el-button v-blur @click="openBatchEdit" :disabled="!selectedCount" class="payment-methods-header-button" type="primary">
                    <Icon name="fa fa-edit" />
                    <span class="payment-methods-header-button__text">批量编辑</span>
                </el-button>
            </el-tooltip>
        </TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
        <PopupForm />
        <BatchEditDialog ref="batchEditDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, provide, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BatchEditDialog from './batchEditDialog.vue'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import createAxios from '/@/utils/axios'

defineOptions({
    name: 'payment/methods',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const batchEditDialogRef = ref<InstanceType<typeof BatchEditDialog>>()
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])
const ALL_CHANNEL_TAB = '全部'
const channelTabs = ref([ALL_CHANNEL_TAB])
const activeChannelTab = ref(ALL_CHANNEL_TAB)

const loadChannelTabs = async () => {
    const res = await createAxios<string[]>({
        url: '/admin/payment.Methods/channels',
        method: 'get',
    })
    const tabs = [ALL_CHANNEL_TAB, ...res.data.filter(Boolean)]

    channelTabs.value = Array.from(new Set(tabs))
    if (!tabs.some((item) => item === activeChannelTab.value)) {
        activeChannelTab.value = ALL_CHANNEL_TAB
    }
}

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/payment.Methods/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('payment.methods.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            {
                label: t('payment.methods.channelcodetable__name'),
                prop: 'channelCodeTable.name',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                render: 'tags',
                operator: 'LIKE',
            },
            {
                label: t('payment.methods.name'),
                prop: 'name',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            {
                label: t('payment.methods.unique_tag'),
                prop: 'unique_tag',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            {
                label: t('payment.methods.code'),
                prop: 'code',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('payment.methods.icon'), prop: 'icon', align: 'center', render: 'image', operator: false },
            {
                label: t('payment.methods.show'),
                prop: 'show',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: { all: 'show all', ios: 'show ios', android: 'show android' },
            },
            {
                label: t('payment.methods.status'),
                prop: 'status',
                align: 'center',
                render: 'switch',
                operator: 'eq',
                sortable: false,
                replaceValue: { '0': t('payment.methods.status 0'), '1': t('payment.methods.status 1') },
            },
            {
                label: t('payment.methods.pay_method'),
                prop: 'pay_method',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: {
                    '0': t('payment.methods.pay_method 0'),
                    '1': t('payment.methods.pay_method 1'),
                    '2': t('payment.methods.pay_method 2'),
                },
            },
            {
                label: t('payment.methods.min_recharge_amount'),
                prop: 'min_recharge_amount',
                align: 'center',
                operator: 'RANGE',
                sortable: false,
                width: 140,
            },
            {
                label: t('payment.methods.max_recharge_amount'),
                prop: 'max_recharge_amount',
                align: 'center',
                operator: 'RANGE',
                sortable: false,
                width: 140,
            },
            {
                label: t('payment.methods.min_withdraw_amount'),
                prop: 'min_withdraw_amount',
                align: 'center',
                operator: 'RANGE',
                sortable: false,
                width: 140,
            },
            {
                label: t('payment.methods.max_withdraw_amount'),
                prop: 'max_withdraw_amount',
                align: 'center',
                operator: 'RANGE',
                sortable: false,
                width: 140,
            },
            {
                label: t('payment.methods.create_time'),
                prop: 'create_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
            },
            {
                label: t('payment.methods.update_time'),
                prop: 'update_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
            },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined, 'status', 'is_clause'],
    },
    {
        defaultItems: {
            show: 'all',
            status: '1',
            pay_method: '1',
            min_recharge_amount: null,
            max_recharge_amount: null,
            min_withdraw_amount: null,
            max_withdraw_amount: null,
        },
    }
)

provide('baTable', baTable)

const selectedCount = computed(() => baTable.table.selection?.length || 0)

const openSingleEdit = () => {
    if (selectedCount.value !== 1) {
        return
    }
    baTable.onTableHeaderAction('edit', {})
}

const openBatchEdit = () => {
    batchEditDialogRef.value?.open()
}

const onChannelTabChange = (tabName: string | number) => {
    const currentLimit = baTable.table.filter?.limit
    const search = (baTable.table.filter?.search || []).filter((item: anyObj) => item.field !== 'channelCodeTable.name')
    const channelCode = String(tabName)

    if (channelCode !== ALL_CHANNEL_TAB) {
        search.push({
            field: 'channelCodeTable.name',
            operator: '=',
            val: channelCode,
        })
    }

    baTable.table.filter = {
        ...(baTable.table.filter || {}),
        page: 1,
        limit: currentLimit,
        search,
    }
    baTable.getData()
}

onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.mount()
    loadChannelTabs()
    baTable.getData()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
    })
})
</script>

<style scoped lang="scss">
.payment-channel-filter {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.payment-channel-filter__label {
    flex: 0 0 auto;
    margin-right: 12px;
    color: var(--el-text-color-regular);
    font-size: 14px;
}

.payment-channel-tabs {
    flex: 1 1 auto;
    min-width: 0;
}

.payment-channel-tabs :deep(.el-tabs__header) {
    margin-bottom: 0;
}

.payment-methods-header-button {
    margin-left: 12px;
}

.payment-methods-header-button__text {
    margin-left: 6px;
}
</style>
