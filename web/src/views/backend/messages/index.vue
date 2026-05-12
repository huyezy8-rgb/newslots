<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('messages.quick Search Fields') })"
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
import { useAdminInfo } from '/@/stores/adminInfo'
const adminInfo = useAdminInfo()

defineOptions({
    name: 'messages',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/Messages/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('messages.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('messages.user_id'), prop: 'user_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            {
                label: t('messages.user__nickname'),
                prop: 'user.nickname',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                render: 'tags',
                operator: 'LIKE',
            },
            { label: t('messages.channel_id'), prop: 'channel_id', align: 'center', operator: adminInfo.isAdminChannelId == null ? '=' : false, operatorPlaceholder: t('Fuzzy query') },
            { label: t('messages.type'), prop: 'type', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false, formatter: (cell: unknown, row: any) => {
                const value = typeof cell === 'object' && cell !== null
                  ? (cell as any).type ?? ''
                  : cell;
                const map: Record<string, string> = { system: '系统通知', gift: '赠送奖励', event: '活动' };
                return map[String(value)] || value || '';
            } },
            { label: t('messages.title'), prop: 'title', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('messages.amount'), prop: 'amount', align: 'center', operator: 'RANGE', sortable: false },
            {
                label: t('messages.wallet_type'),
                prop: 'wallet_type',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                formatter: (cell: unknown, row: any) => {
                    const value = typeof cell === 'object' && cell !== null
                      ? (cell as any).wallet_type ?? ''
                      : cell;
                    const map: Record<string, string> = { recharge_wallet: '充值钱包', experience_wallet: '体验钱包', game_wallet: '游戏钱包' };
                    return map[String(value)] || value || '';
                },
            },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: { type: 'system' },
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
