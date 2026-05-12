<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <TableHeader
            :buttons="['refresh', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="'支持ID/订单号/用户ID查询'"
        />

        <Table ref="tableRef">
            <template #columnAppend>
                <el-table-column label="操作" align="center" width="160">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" @click="openReward(row)">奖励详情</el-button>
                    </template>
                </el-table-column>
            </template>
        </Table>

        <RewardDetail v-if="rewardVisible" v-model:visible="rewardVisible" :order-id="currentOrderId as number" />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, ref, useTemplateRef } from 'vue'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { baTableApi } from '/@/api/common'
import { useAdminInfo } from '/@/stores/adminInfo'
import RewardDetail from './rewardDetail.vue'

const adminInfo = useAdminInfo()

defineOptions({ name: 'activity/seven_day_card/order' })

const tableRef = useTemplateRef('tableRef')

const baTable = new baTableClass(
    new baTableApi('/admin/activity.SevenDayCardOrder/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: 'ID', prop: 'id', align: 'center', width: 80, operator: 'RANGE', sortable: 'custom' },
            { label: '订单号', prop: 'order_no', align: 'center', operatorPlaceholder: '模糊查询', operator: 'LIKE' },
            { label: '用户ID', prop: 'user_id', align: 'center', operatorPlaceholder: '模糊查询', operator: 'LIKE' },
            { label: '渠道', prop: 'channel_name', align: 'center', operatorPlaceholder: '模糊查询', operator: 'LIKE' },
            { label: '金额', prop: 'amount', align: 'center', operator: 'RANGE' },
            { label: '开通时间', prop: 'start_time', align: 'center', operator: 'eq', sortable: 'custom', width: 160, render: 'datetime' },
            { label: '结束时间', prop: 'end_time', align: 'center', operator: 'eq', sortable: 'custom', width: 160, render: 'datetime' },
            { label: '创建时间', prop: 'created_at', align: 'center', operator: 'eq', sortable: 'custom', width: 160, render: 'datetime' },
            { label: '更新时间', prop: 'updated_at', align: 'center', operator: 'eq', sortable: 'custom', width: 160, render: 'datetime' },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {}
)

provide('baTable', baTable)

const rewardVisible = ref(false)
const currentOrderId = ref<number | null>(null)
const openReward = (row: any) => {
    currentOrderId.value = row.id
    rewardVisible.value = true
}

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


