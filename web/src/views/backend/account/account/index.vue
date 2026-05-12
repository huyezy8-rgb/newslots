<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 在线状态统计 -->
        <div class="online-stats" v-if="onlineStats">
            <el-card class="stats-card">
                <div class="stats-content">
                    <div class="stat-item">
                        <span class="stat-label">{{ t('account.account.Total Users') }}:</span>
                        <span class="stat-value">{{ onlineStats.total_count }}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">{{ t('account.account.Online Users') }}:</span>
                        <span class="stat-value online">{{ onlineStats.online_count }}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">{{ t('account.account.Online Rate') }}:</span>
                        <span class="stat-value">{{ onlineRate }}%</span>
                    </div>
                </div>
            </el-card>
        </div>

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('account.account.nickname') })"
        ></TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
        <PopupForm />
        <PopupDetail v-if="showDetail" v-model:visible="showDetail" :user-id="detailUserId as number" />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, useTemplateRef, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import PopupDetail from './popupDetail.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { useAdminInfo } from '/@/stores/adminInfo'

defineOptions({
    name: 'account/account',
})
const adminInfo = useAdminInfo()
const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const router = useRouter()
const showDetail = ref(false)
const detailUserId = ref<number | null>(null)
const optButtons: OptButton[] = [
    {
        render: 'tipButton',
        name: 'detail',
        title: '详情',
        text: '详情',
        type: 'primary',
        icon: 'fa fa-info-circle',
        class: 'table-row-detail',
        disabledTip: false,
        click: (row) => {
            const id = (row as any).id
            detailUserId.value = Number(id)
            showDetail.value = true
        },
    },
    ...defaultOptButtons(['edit', 'delete'])
]

// 在线状态统计
const onlineStats = ref<any>(null)

// 计算在线率
const onlineRate = computed(() => {
    if (!onlineStats.value || onlineStats.value.total_count === 0) return 0
    return Math.round((onlineStats.value.online_count / onlineStats.value.total_count) * 100)
})

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/account.Account/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('account.account.id'), prop: 'id', align: 'center', width: 70, sortable: 'custom' },
            { label: t('account.account.channel_id'),
                prop: 'channel_id',
                align: 'center',
                operator:adminInfo.isAdminChannelId == null?"=":false,
            },
            { label: t('account.account.p_id'), prop: 'p_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            {
                label: t('account.account.nickname'),
                prop: 'nickname',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('account.account.player_id'), prop: 'player_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            {
                label: t('account.account.mobile'),
                prop: 'mobile',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            {
                label: t('account.account.Online Status'),
                prop: 'is_online',
                align: 'center',
                render: 'tag',
                custom: { true: 'success', false: 'info' },
                replaceValue: { true: t('account.account.Online'), false: t('account.account.Offline') },
                width: 100,
                operator: false, // 禁止进入通用搜索
            },
            { label: t('account.account.vip'), prop: 'vip', align: 'center', sortable: 'custom' },
            {
                label: t('account.account.invite_code'),
                prop: 'invite_code',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('account.account.last_login_time'), prop: 'last_login_time', align: 'center', render: 'datetime', useTimezone: false, operator: 'RANGE', comSearchRender: 'date', sortable: 'custom', width: 160 },
            { label: t('account.account.reg_time'), prop: 'reg_time', align: 'center', render: 'datetime', useTimezone: false, operator: 'RANGE', comSearchRender: 'date', sortable: 'custom', width: 160 },
            {
                label: t('account.account.is_black'),
                prop: 'is_black',
                align: 'center',
                comSearchRender: 'select',
                replaceValue: { '0': "否", '1': "是" },
                operatorPlaceholder: "请选择",
                sortable: false,
                formatter: (row) => {
                    return row.is_black === 1 ? '是' : '否';
                },
                render: 'tag',
                dict: [
                    { label: '否', value: 0, type: 'success' }, // 绿色
                    { label: '是', value: 1, type: 'danger' },  // 红色
                ]
            },
            { label: t('account.account.experience_wallet'), prop: 'experience_wallet', align: 'center', operator: false, sortable: 'custom' },
            { label: t('account.account.recharge_wallet'), prop: 'recharge_wallet', align: 'center', operator: false, sortable: 'custom' },
            { label: t('Operate'), align: 'center', width: 180, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: [undefined],
        defaultOrder: { prop: 'last_login_time', order: 'desc' },
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

        // 获取在线状态统计 - 通过API重新获取
        fetchOnlineStats()
    })
})

// 获取在线状态统计
const fetchOnlineStats = async () => {
    try {
        const api = new baTableApi('/admin/account.Account/')
        const res = await api.index()
        if (res && res.data) {
            onlineStats.value = {
                online_count: res.data.online_count || 0,
                total_count: res.data.total_count || 0
            }
        }
    } catch (error) {
        console.error('获取在线状态统计失败:', error)
    }
}
</script>

<style scoped lang="scss">
.online-stats {
    margin-bottom: 16px;

    .stats-card {
        .stats-content {
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0;

            .stat-item {
                display: flex;
                align-items: center;
                gap: 8px;

                .stat-label {
                    font-weight: 500;
                    color: #606266;
                }

                .stat-value {
                    font-size: 18px;
                    font-weight: 600;
                    color: #303133;

                    &.online {
                        color: #67c23a;
                    }
                }
            }
        }
    }
}

.online-filter {
    margin-bottom: 16px;

    .filter-card {
        .filter-content {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;

            .filter-label {
                font-weight: 500;
                color: #606266;
                white-space: nowrap;
            }
        }
    }
}
</style>
