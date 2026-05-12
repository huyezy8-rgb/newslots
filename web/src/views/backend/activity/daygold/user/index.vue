<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <TableHeader
            :buttons="['refresh', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('activity.daygold.user.quick Search Fields') })"
        />

        <Table ref="tableRef" class="custom-daygold-table">
            <template #rewards_status_slot="scope">
                <el-table-column :label="t('activity.daygold.user.rewards_status')" align="center" min-width="320">
                    <template #default="scope">
                        <div class="reward-status-grid">
                            <div v-for="(reward, index) in parseRewards(scope.row)" :key="index" class="reward-item">
                                <el-tag size="small" :class="getStatusClass(scope.row, index)" effect="plain" class="status-tag">
                                    <span class="reward-day">{{ t('activity.daygold.user.day_label', { day: getDayLabel(index) }) }}</span>
                                    <span class="reward-amount">{{ reward }}{{ t('activity.daygold.user.yuan') }}</span>
                                    <span class="reward-status">{{ getStatusText(scope.row, index) }}</span>
                                </el-tag>
                            </div>
                        </div>
                    </template>
                </el-table-column>
            </template>
        </Table>
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { baTableApi } from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { useAdminInfo } from '/@/stores/adminInfo'
const adminInfo = useAdminInfo()

defineOptions({
    name: 'activity/daygold/user',
})

const { t } = useI18n()
const tableRef = ref()

// 计算天数标签
const getDayLabel = (index: number): string => {
    return (Number(index) + 1).toString()
}

// 解析奖励金额
const parseRewards = (row: any): number[] => {
    try {
        return typeof row.rewards === 'string' ? JSON.parse(row.rewards) : row.rewards || []
    } catch (e) {
        console.error('解析奖励数据出错:', e)
        return []
    }
}

// 获取领取状态
const getReceiveStatus = (row: any): number[] => {
    try {
        return typeof row.receive_status === 'string' ? JSON.parse(row.receive_status) : row.receive_status || []
    } catch (e) {
        console.error('解析领取状态出错:', e)
        return []
    }
}

// 获取状态类名
const getStatusClass = (row: any, index: number): Record<string, boolean> => {
    const status = getReceiveStatus(row)[index]
    return {
        'reward-received': status === 2, // 已领取
        'reward-available': status === 1, // 可领取
        'reward-pending': status === 0, // 未领取
    }
}

// 获取状态文本
const getStatusText = (row: any, index: number): string => {
    const status = getReceiveStatus(row)[index]
    return status === 2
        ? t('activity.daygold.user.received')
        : status === 1
          ? t('activity.daygold.user.available')
          : t('activity.daygold.user.pending')
}

const baTable = new baTableClass(
    new baTableApi('/admin/activity.daygold.User/'),
    {
        pk: 'uid',
        column: [
            { type: 'selection', align: 'center', operator: false, width: 50 },
            {
                label: t('activity.daygold.user.uid'),
                prop: 'uid',
                align: 'center',
                sortable: false,
                width: 100,
            },
            {
                label: t('activity.daygold.user.channel_id'),
                prop: 'channel_id',
                align: 'center',
                operator: adminInfo.isAdminChannelId == null ? '=' : false,
                width: 100,
            },
            {
                label: t('activity.daygold.user.rewards_status'),
                prop: 'rewards_status',
                align: 'center',
                operator: false,
                sortable: false,
                render: 'slot',
                slotName: 'rewards_status_slot',
            },
            {
                label: t('activity.daygold.user.times'),
                prop: 'times',
                align: 'center',
                operator: false,
                sortable: false,
                width: 100,
                formatter: (row: any) => `${row.times}${t('activity.daygold.user.times_unit')}`,
            },
            {
                label: t('activity.daygold.user.last_receive_time'),
                prop: 'last_receive_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: 'YYYY-MM-DD HH:mm:ss',
            },
            {
                label: t('activity.daygold.user.create_time'),
                prop: 'create_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: 'YYYY-MM-DD HH:mm:ss',
            },
            {
                label: t('activity.daygold.user.update_time'),
                prop: 'update_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
                timeFormat: 'YYYY-MM-DD HH:mm:ss',
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

<style scoped lang="scss">
.custom-daygold-table {
    margin: 0 auto;
    width: 98%;

    :deep(.el-table__body-wrapper) {
        overflow-x: hidden;
    }

    .reward-status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
        padding: 10px;

        .reward-item {
            display: flex;
            justify-content: center;

            .status-tag {
                display: inline-flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 10px;
                height: 28px;
                line-height: 26px;
                border-radius: 4px;
                font-size: 12px;
                width: 100%;
                min-width: 140px;
                transition: all 0.3s ease;

                .reward-day {
                    font-weight: 500;
                    margin-right: 6px;
                    min-width: 30px;
                }

                .reward-amount {
                    font-weight: 500;
                    margin: 0 6px;
                    flex-grow: 1;
                    text-align: center;
                }

                .reward-status {
                    font-size: 12px;
                    min-width: 40px;
                    text-align: right;
                }

                &.reward-received {
                    background-color: var(--el-color-success-light-9);
                    border-color: var(--el-color-success-light-8);
                    color: var(--el-color-success);
                }

                &.reward-available {
                    background-color: var(--el-color-warning-light-9);
                    border-color: var(--el-color-warning-light-8);
                    color: var(--el-color-warning);
                    font-weight: bold;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                &.reward-pending {
                    background-color: var(--el-color-info-light-9);
                    border-color: var(--el-color-info-light-8);
                    color: var(--el-color-info);
                }
            }
        }
    }

    :deep(.el-table__cell) {
        padding: 10px 0;
    }

    :deep(.el-table th.el-table__cell) {
        background-color: #f8f8f8;
        font-weight: 600;
    }
}
</style>
