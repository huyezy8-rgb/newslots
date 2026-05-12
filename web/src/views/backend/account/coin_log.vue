<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 自定义筛选表单 -->
        <el-form :inline="true" :model="searchForm" class="mb-2" style="margin-bottom: 16px;">
            <el-form-item label="用户ID">
                <el-input v-model="searchForm.user_id" placeholder="用户ID" size="small" style="width: 120px;" />
            </el-form-item>
            <el-form-item label="钱包类型">
                <el-select v-model="searchForm.wallet_type" placeholder="全部" size="small" style="width: 160px">
                    <el-option label="全部" value="" />
                    <el-option label="体验钱包" value="0" />
                    <el-option label="充值钱包" value="1" />
                    <el-option label="佣金钱包" value="2" />
                    <el-option label="拼多多钱包" value="3" />
                </el-select>
            </el-form-item>
            <el-form-item label="流水类型">
                <el-select v-model="searchForm.log_type_id" placeholder="全部" size="small" style="width: 160px">
                    <el-option label="全部" value="" />
                    <el-option v-for="item in logTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
            </el-form-item>
            <el-form-item label="小数位">
                <el-input-number v-model="amountDecimals" :min="0" :max="6" size="small" :step="1" style="width: 120px" />
            </el-form-item>
            <el-form-item label="时间区间">
                <el-date-picker 
                    v-model="searchForm.time" 
                    type="daterange" 
                    range-separator="至" 
                    start-placeholder="开始日期" 
                    end-placeholder="结束日期" 
                    size="small"
                    style="width: 240px;"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                />
            </el-form-item>
            <el-form-item>
                <el-button type="primary" size="small" @click="handleSearch">查询</el-button>
                <el-button size="small" @click="handleReset">重置</el-button>
            </el-form-item>
        </el-form>

        <!-- 表格顶部菜单 -->
        <TableHeader
            :buttons="['refresh', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="
                t('Quick search placeholder', { fields: t('account.coinLog.User ID') + '/' + t('account.coinLog.Note') })
            "
        >
        </TableHeader>

        <!-- 表格 -->
        <Table />
    </div>
</template>

<script setup lang="ts">
import { provide, ref, onMounted, computed, watch } from 'vue'
import baTableClass from '/@/utils/baTable'
import { baTableApi } from '/@/api/common'
import { url, getLogTypes } from '/@/api/backend/account/accountCoinLog'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { useI18n } from 'vue-i18n'

defineOptions({
    name: 'account/coinLog',
})

const { t } = useI18n()

// 流水类型数据
const logTypes = ref<Array<{id: number, name: string}>>([])

// 筛选表单数据
const searchForm = ref({
    user_id: '',
    wallet_type: '',
    log_type_id: '',
    time: []
})

// 流水类型选项
const logTypeOptions = computed(() => {
    return logTypes.value.map(item => ({
        value: item.id,
        label: item.name
    }))
})

// 可控小数位：默认2位，持久化到本地
const amountDecimals = ref<number>(Number(localStorage.getItem('coinlog_amount_decimals') || 2))

// 监听变更持久化
watch(() => amountDecimals.value, (val) => {
    localStorage.setItem('coinlog_amount_decimals', String(val))
})

// 金额格式化为指定位小数并根据正负着色
const renderAmountHtml = (value: any) => {
    const num = Number(value || 0)
    const color = num > 0 ? '#67C23A' : num < 0 ? '#F56C6C' : '#909399'
    const fixed = Math.max(0, Math.min(6, Number(amountDecimals.value) || 0))
    const text = isNaN(num) ? (0).toFixed(fixed) : num.toFixed(fixed)
    return `<span style="color:${color}">${text}</span>`
}

// 获取流水类型数据
const fetchLogTypes = async () => {
    try {
        const res = await getLogTypes()
        logTypes.value = res.data
    } catch (error) {
        console.error('获取流水类型失败:', error)
    }
}

// 处理查询（显式覆盖筛选字段，并将页码重置为1，保留每页条数）
const handleSearch = () => {
    const currentLimit = baTable.table.filter?.limit
    const hasTime = Array.isArray(searchForm.value.time) && searchForm.value.time.length === 2
    baTable.table.filter = {
        ...(baTable.table.filter || {}),
        page: 1,
        limit: currentLimit, // 保留每页条数
        user_id: searchForm.value.user_id || '',
        wallet_type: searchForm.value.wallet_type !== '' ? searchForm.value.wallet_type : '',
        log_type_id: searchForm.value.log_type_id !== '' ? searchForm.value.log_type_id : '',
        start_time: hasTime ? searchForm.value.time[0] : '',
        end_time: hasTime ? searchForm.value.time[1] : '',
    }
    baTable.getData()
}

// 处理重置（清空筛选并重置页码，保留每页条数）
const handleReset = () => {
    const currentLimit = baTable.table.filter?.limit
    searchForm.value = { user_id: '', wallet_type: '', log_type_id: '', time: [] }
    baTable.table.filter = {
        ...(baTable.table.filter || {}),
        page: 1,
        limit: currentLimit,
        user_id: '',
        wallet_type: '',
        log_type_id: '',
        start_time: '',
        end_time: '',
    }
    baTable.getData()
}

const baTable = new baTableClass(
    new baTableApi(url),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('Id'), prop: 'id', align: 'center', operator: '=', operatorPlaceholder: t('Id'), width: 70 },
            { label: t('account.coinLog.User ID'), prop: 'user_id', align: 'center', width: 80, operator: '=', operatorPlaceholder: t('account.coinLog.User ID') },
            { 
                label: t('account.coinLog.Wallet Type'), 
                prop: 'wallet_type_text', 
                align: 'center', 
                width: 100,
                operator: '=',
                operatorPlaceholder: t('account.coinLog.Wallet Type'),
            },
            { 
                label: t('account.coinLog.Old Balance'), 
                prop: 'old_num', 
                align: 'center', 
                operator: 'RANGE', 
                sortable: 'custom',
                render: 'customTemplate',
                customTemplate: (row: any, field: any, cellValue: any) => renderAmountHtml(cellValue)
            },
            { 
                label: t('account.coinLog.Change Amount'), 
                prop: 'num', 
                align: 'center', 
                operator: 'RANGE', 
                sortable: 'custom',
                render: 'customTemplate',
                customTemplate: (row: any, field: any, cellValue: any) => renderAmountHtml(cellValue)
            },
            { 
                label: t('account.coinLog.New Balance'), 
                prop: 'new_num', 
                align: 'center', 
                operator: 'RANGE', 
                sortable: 'custom',
                render: 'customTemplate',
                customTemplate: (row: any, field: any, cellValue: any) => renderAmountHtml(cellValue)
            },
            { 
                label: t('account.coinLog.Log Type'), 
                prop: 'log_type_text', 
                align: 'center', 
                width: 120,
                operator: '=',
                operatorPlaceholder: t('account.coinLog.Log Type'),
            },
            { 
                label: t('account.coinLog.Note'), 
                prop: 'note', 
                align: 'center', 
                operator: 'LIKE', 
                operatorPlaceholder: t('Fuzzy query'),
                showOverflowTooltip: true 
            },
            { label: t('Create time'), prop: 'create_time', align: 'center', render: 'datetime', sortable: 'custom', operator: 'RANGE', width: 160 },
        ],
        dblClickNotEditColumn: ['all'],
    },
    {
        defaultItems: {
            user_id: '',
            wallet_type: '',
            log_type_id: '',
            start_time: '',
            end_time: '',
        },
    }
)

// 页面加载时获取流水类型数据
onMounted(() => {
    fetchLogTypes()
})

baTable.mount()
baTable.getData()

provide('baTable', baTable)
</script>

<style scoped lang="scss">
.mb-2 {
    margin-bottom: 16px;
}
</style>
