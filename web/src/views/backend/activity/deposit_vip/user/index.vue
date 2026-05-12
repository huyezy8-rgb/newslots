<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('activity.deposit_vip.user.quickSearchPlaceholder')"
        />

        <Table ref="tableRef">
            <!-- 投注信息合并列 -->
            <template #bet_info_slot="scope">
                <el-table-column :label="t('activity.deposit_vip.user.betInfo')" align="center" min-width="320">
                    <template #default="scope">
                        <div class="bet-info-container">
                            <div class="bet-info-item">
                                <span class="bet-info-label">{{ t('activity.deposit_vip.user.betProgress') }}：</span>
                                <el-progress
                                    :percentage="Math.min(100, calculatePercentage(scope.row.bet_num, scope.row.bet_num_base))"
                                    :color="getProgressColor(scope.row)"
                                    :stroke-width="16"
                                >
                                    <template #default>
                                        <div class="progress-content">
                                            <span class="progress-numbers">
                                                {{ formatNumber(scope.row.bet_num) }}/{{ formatNumber(scope.row.bet_num_base) }}
                                            </span>
                                            <span class="progress-percent">
                                                ({{ calculateUnlimitedPercentage(scope.row.bet_num, scope.row.bet_num_base) }}%)
                                            </span>
                                        </div>
                                    </template>
                                </el-progress>
                            </div>

                            <div class="bet-info-item">
                                <span class="bet-info-label">{{ t('activity.deposit_vip.user.rewardProgress') }}：</span>
                                <el-progress
                                    :percentage="Math.min(100, calculatePercentage(scope.row.bet_num_reward, scope.row.bet_num_max))"
                                    :color="getRewardColor(scope.row)"
                                    :stroke-width="16"
                                >
                                    <template #default>
                                        <div class="progress-content">
                                            <span class="progress-numbers">
                                                {{ formatNumber(scope.row.bet_num_reward) }}/{{ formatNumber(scope.row.bet_num_max) }}
                                            </span>
                                            <span class="progress-percent">
                                                ({{ calculateUnlimitedPercentage(scope.row.bet_num_reward, scope.row.bet_num_max) }}%)
                                            </span>
                                        </div>
                                    </template>
                                </el-progress>
                            </div>

                            <div class="bet-info-item">
                                <span class="bet-info-label">{{ t('activity.deposit_vip.user.amountRatio') }}：</span>
                                <el-progress
                                    :percentage="Math.min(100, calculateAmountPercentage(scope.row))"
                                    :color="getAmountColor(scope.row)"
                                    :stroke-width="16"
                                >
                                    <template #default>
                                        <div class="progress-content">
                                            <span class="progress-numbers">
                                                {{ formatNumber(scope.row.bet_money_sum) }}/{{
                                                    formatNumber(scope.row.amount * scope.row.bet_money_multiple)
                                                }}
                                            </span>
                                            <span class="progress-percent"> ({{ calculateUnlimitedAmountPercentage(scope.row) }}%) </span>
                                        </div>
                                    </template>
                                </el-progress>
                            </div>
                        </div>
                    </template>
                </el-table-column>
            </template>

            <!-- 任务状态列 -->
            <template #task_status_slot="scope">
                <el-table-column :label="t('activity.deposit_vip.user.taskStatus')" align="center" width="120">
                    <template #default="scope">
                        <el-tag :type="scope.row.task_status === 1 ? 'success' : 'info'" effect="plain">
                            {{ scope.row.task_status === 1 ? t('activity.deposit_vip.user.received') : t('activity.deposit_vip.user.pending') }}
                        </el-tag>
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

const { t } = useI18n()

// 数字格式化（去除末尾0）
const formatNumber = (num: number): string => {
    if (num === null || num === undefined) return '0'
    const rounded = Math.round(num * 100) / 100
    return rounded.toFixed(2).replace(/\.?0+$/, '')
}

// 计算百分比（限制最大100%）
const calculatePercentage = (current: number, total: number): number => {
    if (!total || total === 0) return 0
    return Math.min(100, Math.round((current / total) * 100))
}

// 计算无限制百分比（可超过100%）
const calculateUnlimitedPercentage = (current: number, total: number): number => {
    if (!total || total === 0) return 0
    return Math.round((current / total) * 100)
}

// 计算金额百分比（限制最大100%）
const calculateAmountPercentage = (row: any): number => {
    const denominator = row.amount * row.bet_money_multiple
    if (!denominator || denominator === 0) return 0
    return Math.min(100, Math.round((row.bet_money_sum / denominator) * 100))
}

// 计算无限制金额百分比（可超过100%）
const calculateUnlimitedAmountPercentage = (row: any): number => {
    const denominator = row.amount * row.bet_money_multiple
    if (!denominator || denominator === 0) return 0
    return Math.round((row.bet_money_sum / denominator) * 100)
}

// 进度条颜色
const getProgressColor = (row: any) => {
    const progress = calculatePercentage(row.bet_num, row.bet_num_base)
    return progress >= 100 ? '#67c23a' : progress >= 50 ? '#e6a23c' : '#f56c6c'
}

const getRewardColor = (row: any) => {
    const progress = calculatePercentage(row.bet_num_reward, row.bet_num_max)
    return progress >= 100 ? '#67c23a' : progress >= 50 ? '#e6a23c' : '#f56c6c'
}

const getAmountColor = (row: any) => {
    const progress = calculateAmountPercentage(row)
    return progress >= 100 ? '#67c23a' : progress >= 50 ? '#e6a23c' : '#f56c6c'
}

const tableRef = ref()
const baTable = new baTableClass(
    new baTableApi('/admin/activity.deposit_vip.User/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false, width: 50 },
            { label: t('activity.deposit_vip.user.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('activity.deposit_vip.user.userId'), prop: 'user_id', align: 'center', operator: 'LIKE' },
            {
                label: t('activity.deposit_vip.user.channelId'),
                prop: 'channel_id',
                align: 'center',
                operator: adminInfo.isAdminChannelId == null ? '=' : false,
            },
            { label: t('activity.deposit_vip.user.level'), prop: 'level', align: 'center', operator: 'LIKE', sortable: false },
            { label: t('activity.deposit_vip.user.amount'), prop: 'amount', align: 'center', operator: 'RANGE', sortable: false },
            {
                label: t('activity.deposit_vip.user.betInfo'),
                prop: 'bet_info',
                align: 'center',
                render: 'slot',
                slotName: 'bet_info_slot',
            },
            {
                label: t('activity.deposit_vip.user.taskStatus'),
                prop: 'task_status',
                align: 'center',
                render: 'slot',
                slotName: 'task_status_slot',
            },
            { label: t('activity.deposit_vip.user.expireTime'), prop: 'expire_time', align: 'center', render: 'datetime', width: 160 },
            { label: t('activity.deposit_vip.user.updateTime'), prop: 'update_time', align: 'center', render: 'datetime', width: 160 },
            { label: t('activity.deposit_vip.user.createTime'), prop: 'create_time', align: 'center', render: 'datetime', width: 160 },
        ],
    },
    {
        defaultItems: { bet_num_base: 100, bet_money_multiple: 60 },
    }
)

provide('baTable', baTable)

onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.mount()
    baTable.getIndex()?.then(() => {
        baTable.initSort()
    })
})
</script>

<style scoped lang="scss">
.bet-info-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 10px;

    .bet-info-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 12px;

        .bet-info-label {
            font-weight: 500;
            color: var(--el-text-color-secondary);
            min-width: 80px;
            margin-right: 10px;
        }

        :deep(.el-progress) {
            flex: 1;
            position: relative;
            padding-right: 60px;

            .progress-content {
                position: absolute;
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0 10px;
                box-sizing: border-box;
                font-size: 12px;
                color: var(--el-text-color-regular);
                line-height: 16px;

                .progress-numbers {
                    flex: 1;
                    text-align: left;
                }

                .progress-percent {
                    width: 50px;
                    text-align: right;
                }
            }

            .el-progress__text {
                display: none;
            }
        }
    }
}
</style>
