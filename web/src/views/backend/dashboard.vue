<template>
    <div class="default-main">
        <!-- 筛选条件 -->
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
                <el-form-item label="选择渠道" class="filter-item" v-if="adminInfo.isAdminChannelId == null" >
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
import createAxios from '/@/utils/axios'
import { useAdminInfo } from '/@/stores/adminInfo'
import { getTodayInCurrentTimezone, getCurrentTimezone } from '/@/utils/dayjs'
import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import timezone from 'dayjs/plugin/timezone'

// 扩展 dayjs 插件
dayjs.extend(utc)
dayjs.extend(timezone)

// 设置默认时区
const currentTimezone = getCurrentTimezone()
dayjs.tz.setDefault(currentTimezone)

const { t } = useI18n()
const adminInfo = useAdminInfo()

// 获取当前时区
// const currentTimezone = getCurrentTimezone()

// 获取系统时区的今天日期
const getTodayInSystemTimezone = () => {
    const today = getTodayInCurrentTimezone()
    const yyyy = today.getFullYear()
    const mm = String(today.getMonth() + 1).padStart(2, '0')
    const dd = String(today.getDate()).padStart(2, '0')
    return `${yyyy}-${mm}-${dd}`
}

// 使用计算属性确保时区正确
const defaultDate = computed(() => getTodayInSystemTimezone())
const defaultDateObject = computed(() => getTodayInCurrentTimezone())

// 日期选择器配置
const disabledDate = computed(() => {
    return (time: Date) => {
        // 获取当前时区的今天日期字符串
        const todayString = dayjs().tz(currentTimezone).format('YYYY-MM-DD')
        
        // 直接使用传入时间的本地日期，因为 Element Plus 传入的时间已经是正确的日期
        const timeString = dayjs(time).format('YYYY-MM-DD')
        
        // 禁用超过今天的日期（包括明天及以后）
        return timeString > todayString
    }
})

// 筛选数据
const filterData = reactive({
    date: defaultDate.value,
    channel_id: undefined as number | undefined
})

// 渠道列表
const channelList = ref<{id: number|string, name: string}[]>([])

// 定义统计数据列表
const statisticsList = reactive([
    { title: '注册用户数', value: 0 },
    { title: '活跃用户数', value: 0 },
    { title: '付费用户数', value: 0 },
    { title: '留存用户数', value: 0 },
    { title: '在线用户数', value: 0 },
    { title: '首充用户数', value: 0 },
    { title: '首充金额', value: 0 },
    { title: '新用户首充用户数', value: 0 },
    { title: '新用户首充金额', value: 0 },
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

// 处理筛选条件变化
const handleFilterChange = () => {
    // 自动触发查询
    fetchStatistics()
}

// 重置筛选条件
const resetFilter = () => {
    filterData.date = defaultDate.value
    filterData.channel_id = undefined
    fetchStatistics()
}

// 获取统计数据
const fetchStatistics = async () => {
    const params: any = {}

    // 添加筛选参数
    if (filterData.date) {
        params.date = filterData.date
    }
    if (filterData.channel_id) {
        params.channel_id = filterData.channel_id
    }

    const res = await index(params)
    const data = res.data

    // 更新渠道列表
    if (data.channel_list) {
        channelList.value = data.channel_list
    }

    // 更新统计数据
    statisticsList[0].value = data.registeredUsers
    statisticsList[1].value = data.activeUsers
    statisticsList[2].value = data.paidUsers
    statisticsList[3].value = data.retentionUsers
    statisticsList[4].value = data.onlineUsers
    statisticsList[5].value = data.firstChargeUsers
    statisticsList[6].value = data.firstChargeAmount
    statisticsList[7].value = data.newUserFirstChargeUsers
    statisticsList[8].value = data.newUserFirstChargeAmount
    statisticsList[9].value = data.totalRechargeAmount
    statisticsList[10].value = `${data.registrationChargeRate}%`
    statisticsList[11].value = data.withdrawalUsers
    statisticsList[12].value = data.withdrawalAmount
    statisticsList[13].value = `${data.withdrawalRate}%`
    statisticsList[14].value = data.orderAmount
    statisticsList[15].value = data.orderCount
    statisticsList[16].value = data.playerProfitLoss
    statisticsList[17].value = data.activityCashGift
    statisticsList[18].value = data.activityWithdrawal
    statisticsList[19].value = `${data.paymentSuccessRate}%`
}

const fetchChannels = async () => {
    const res = await createAxios({ url: '/admin/channel.listsss/all', method: 'get' });
    if (res.code === 1 && Array.isArray(res.data)) {
        channelList.value = res.data;
    }
};

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
