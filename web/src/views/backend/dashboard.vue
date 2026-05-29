<template>
    <div class="default-main">
        <div class="filter-panel">
            <div class="filter-row">
                <el-form-item label="选择日期" class="filter-item">
                    <el-date-picker
                        v-model="filterData.date"
                        type="date"
                        placeholder="选择日期"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        :default-value="defaultDateObject"
                        :timezone="currentTimezone"
                        :disabled-date="disabledDate"
                        @change="handleFilterChange"
                    />
                </el-form-item>

                <el-form-item label="选择渠道" class="filter-item" v-if="adminInfo.isAdminChannelId == null">
                    <el-select
                        v-model="filterData.channel_id"
                        placeholder="请选择渠道"
                        clearable
                        @change="handleFilterChange"
                        style="width: 220px"
                    >
                        <el-option
                            v-for="channel in channelList"
                            :key="channel.id"
                            :label="channel.name"
                            :value="channel.id"
                        />
                    </el-select>
                </el-form-item>

                <el-button @click="resetFilter" class="filter-reset-btn">重置</el-button>
            </div>
        </div>

        <div class="statistics-panel">
            <el-row :gutter="20">
                <template v-for="(stat, index) in statisticsList" :key="index">
                    <el-col :sm="12" :lg="6">
                        <div class="stat-item">
                            <div class="stat-title">{{ t(stat.title) }}</div>
                            <div class="stat-value">{{ stat.value }}</div>
                        </div>
                    </el-col>
                </template>
            </el-row>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, onMounted, ref, computed } from 'vue'
import { index } from '/@/api/backend/dashboard'
import { useI18n } from 'vue-i18n'
import { useAdminInfo } from '/@/stores/adminInfo'
import { getTodayInCurrentTimezone, getCurrentTimezone } from '/@/utils/dayjs'
import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import timezone from 'dayjs/plugin/timezone'

dayjs.extend(utc)
dayjs.extend(timezone)

const currentTimezone = getCurrentTimezone()
dayjs.tz.setDefault(currentTimezone)

const { t } = useI18n()
const adminInfo = useAdminInfo()

const getTodayInSystemTimezone = () => {
    const today = getTodayInCurrentTimezone()
    const yyyy = today.getFullYear()
    const mm = String(today.getMonth() + 1).padStart(2, '0')
    const dd = String(today.getDate()).padStart(2, '0')
    return `${yyyy}-${mm}-${dd}`
}

const defaultDate = computed(() => getTodayInSystemTimezone())
const defaultDateObject = computed(() => getTodayInCurrentTimezone())

const disabledDate = computed(() => {
    return (time: Date) => {
        const todayString = dayjs().tz(currentTimezone).format('YYYY-MM-DD')
        const timeString = dayjs(time).format('YYYY-MM-DD')
        return timeString > todayString
    }
})

const filterData = reactive({
    date: defaultDate.value,
    channel_id: undefined as number | undefined,
})

const channelList = ref<{ id: number | string; name: string }[]>([])

const statisticsList = reactive([
    { title: '注册用户数', value: 0 },
    { title: '活跃用户数', value: 0 },
    { title: '付费用户数', value: 0 },
    { title: '留存用户数', value: 0 },
    { title: '在线用户数', value: 0 },

    { title: '生涯首充用户数', value: 0 },
    { title: '生涯首充金额', value: 0 },
    { title: '新用户首充用户数', value: 0 },
    { title: '新用户首充金额', value: 0 },
    { title: '老用户首充用户数', value: 0 },
    { title: '老用户首充金额', value: 0 },

    { title: '总充值金额', value: 0 },
    { title: '注册付费率', value: '0%' },
    { title: '提现用户数', value: 0 },
    { title: '提现金额', value: 0 },
    { title: '提现率', value: '0%' },
    { title: '下注金额', value: 0 },
    { title: '下注数量', value: 0 },
    { title: '玩家盈亏', value: 0 },
    { title: '活动彩金', value: 0, description: '活动相关的奖励金额（如注册奖励、签到奖励等）' },
    { title: '活动领取', value: 0, description: '活动钱包的成功提现金额' },
    { title: '支付成功率', value: '0%' },
])

const handleFilterChange = () => {
    fetchStatistics()
}

const resetFilter = () => {
    filterData.date = defaultDate.value
    filterData.channel_id = undefined
    fetchStatistics()
}

const fetchStatistics = async () => {
    const params: any = {}

    if (filterData.date) {
        params.date = filterData.date
    }

    if (filterData.channel_id) {
        params.channel_id = filterData.channel_id
    }

    const res = await index(params)
    const data = res.data

    if (data.channel_list) {
        channelList.value = data.channel_list
    }

    const firstChargeUsers = Number(data.firstChargeUsers || 0)
    const firstChargeAmount = Number(data.firstChargeAmount || 0)
    const newUserFirstChargeUsers = Number(data.newUserFirstChargeUsers || 0)
    const newUserFirstChargeAmount = Number(data.newUserFirstChargeAmount || 0)

    const oldUserFirstChargeUsers = Number(
        data.oldUserFirstChargeUsers ?? Math.max(firstChargeUsers - newUserFirstChargeUsers, 0)
    )

    const oldUserFirstChargeAmount = Number(
        data.oldUserFirstChargeAmount ?? Math.max(firstChargeAmount - newUserFirstChargeAmount, 0)
    )

    statisticsList[0].value = data.registeredUsers || 0
    statisticsList[1].value = data.activeUsers || 0
    statisticsList[2].value = data.paidUsers || 0
    statisticsList[3].value = data.retentionUsers || 0
    statisticsList[4].value = data.onlineUsers || 0

    statisticsList[5].value = firstChargeUsers
    statisticsList[6].value = firstChargeAmount
    statisticsList[7].value = newUserFirstChargeUsers
    statisticsList[8].value = newUserFirstChargeAmount
    statisticsList[9].value = oldUserFirstChargeUsers
    statisticsList[10].value = oldUserFirstChargeAmount

    statisticsList[11].value = data.totalRechargeAmount || 0
    statisticsList[12].value = `${data.registrationChargeRate || 0}%`
    statisticsList[13].value = data.withdrawalUsers || 0
    statisticsList[14].value = data.withdrawalAmount || 0
    statisticsList[15].value = `${data.withdrawalRate || 0}%`
    statisticsList[16].value = data.orderAmount || 0
    statisticsList[17].value = data.orderCount || 0
    statisticsList[18].value = data.playerProfitLoss || 0
    statisticsList[19].value = data.activityCashGift || 0
    statisticsList[20].value = data.activityWithdrawal || 0
    statisticsList[21].value = `${data.paymentSuccessRate || 0}%`
}

onMounted(() => {
    fetchStatistics()
})
</script>

<style scoped lang="scss">
.filter-panel {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
}

.filter-row {
    display: flex;
    align-items: center;
    gap: 20px;
}

.filter-item {
    margin-bottom: 0;
}

.filter-reset-btn {
    margin-left: 10px;
}

.statistics-panel {
    margin-top: 20px;
}

.stat-item {
    background-color: #f5f5f5;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.stat-title {
    font-size: 16px;
    color: #333;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #007bff;
    margin-top: 10px;
}
</style>