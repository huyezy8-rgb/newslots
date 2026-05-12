<template>
  <div class="default-main ba-table-box">
    <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

    <!-- 表格顶部菜单 -->
    <TableHeader
      :buttons="['refresh', 'comSearch', 'quickSearch', 'columnDisplay']"
      :quick-search-placeholder="'快速搜索排行榜奖励记录'"
    />

    <!-- 搜索表单 -->
    <div class="search-form">
      <el-form :model="searchForm" :inline="true" class="demo-form-inline">
        <el-form-item label="排行榜类型">
          <el-select v-model="searchForm.type" placeholder="请选择类型" clearable style="width: 150px;">
            <el-option label="日榜" value="daily" />
            <el-option label="周榜" value="weekly" />
            <el-option label="月榜" value="monthly" />
          </el-select>
        </el-form-item>
        <el-form-item label="渠道">
          <el-select v-model="searchForm.channel_id" placeholder="请选择渠道" clearable style="width: 200px;">
            <el-option
              v-for="channel in channelOptions"
              :key="channel.id"
              :label="channel.name"
              :value="channel.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 表格 -->
    <Table ref="tableRef">
      <template #type_slot>
        <el-table-column prop="type" label="排行榜类型" width="120">
          <template #default="{ row }">
            <el-tag :type="getTypeTagType(row.type)">
              {{ getTypeText(row.type) }}
            </el-tag>
          </template>
        </el-table-column>
      </template>
      
      <template #pool_amount_slot>
        <el-table-column prop="pool_amount" label="奖池总金额" width="140">
          <template #default="{ row }">
            {{ formatAmount(row.pool_amount) }}
          </template>
        </el-table-column>
      </template>
      
      <template #distributed_amount_slot>
        <el-table-column prop="distributed_amount" label="实际发放金额" width="140">
          <template #default="{ row }">
            {{ formatAmount(row.distributed_amount) }}
          </template>
        </el-table-column>
      </template>
      
      <template #create_time_slot>
        <el-table-column prop="create_time" label="发放时间" width="160">
          <template #default="{ row }">
            {{ formatTime(row.create_time) }}
          </template>
        </el-table-column>
      </template>
      
      <template #opt_slot>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button
              type="primary"
              size="small"
              @click="handleViewLeaderboard(row)"
            >
              查看榜单
            </el-button>
          </template>
        </el-table-column>
      </template>
    </Table>

    <!-- 查看榜单弹出层 -->
    <LeaderboardDetail
      v-model="showLeaderboardDetail"
      :reward-log="selectedRewardLog"
      :leaderboard-data="leaderboardData"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, useTemplateRef, provide } from 'vue'
import createAxios from '/@/utils/axios'
import LeaderboardDetail from './components/LeaderboardDetail.vue'
import baTableClass from '/@/utils/baTable'
import { baTableApi } from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'

// 接口实例
const api = createAxios({
  baseURL: '/admin',
  timeout: 10000,
})

// baTable 实例
const baTable = new baTableClass(
  new baTableApi('/admin/leaderboard.LeaderboardReward/'),
  {
    pk: 'id',
    column: [
      { type: 'selection', align: 'left', operator: false, width: 50 },
      { label: 'ID', prop: 'id', align: 'center', width: 80, sortable: 'custom' },
      { label: '渠道', prop: 'channel_name', align: 'center', width: 120 },
      { label: '排行榜类型', prop: 'type', align: 'center', width: 120, render: 'slot', slotName: 'type_slot' },
      { label: '周期', prop: 'period', align: 'center', width: 120 },
      { label: '奖池总金额', prop: 'pool_amount', align: 'center', width: 140, render: 'slot', slotName: 'pool_amount_slot' },
      { label: '发放人数', prop: 'success_count', align: 'center', width: 100 },
      { label: '实际发放金额', prop: 'distributed_amount', align: 'center', width: 140, render: 'slot', slotName: 'distributed_amount_slot' },
      { label: '发放成功率', prop: 'success_rate', align: 'center', width: 120 },
      { label: '发放时间', prop: 'create_time', align: 'center', width: 160, render: 'slot', slotName: 'create_time_slot' },
      { label: '操作', align: 'center', width: 120, render: 'slot', slotName: 'opt_slot' },
    ],
    dblClickNotEditColumn: ['create_time', 'update_time'],
  }
)

// 表格引用
const tableRef = useTemplateRef('tableRef')

// 提供 baTable 给子组件
provide('baTable', baTable)

// 响应式数据
const channelOptions = ref<Array<{id: number, name: string}>>([])
const showLeaderboardDetail = ref(false)
const selectedRewardLog = ref(null)
const leaderboardData = ref([])

// 搜索表单
const searchForm = reactive({
  type: '',
  channel_id: ''
})

// 获取排行榜类型文本
const getTypeText = (type: string) => {
  const types: { [key: string]: string } = {
    daily: '日榜',
    weekly: '周榜',
    monthly: '月榜'
  }
  return types[type] || '未知'
}

// 获取排行榜类型标签类型
const getTypeTagType = (type: string): 'success' | 'warning' | 'danger' | 'info' => {
  const types: { [key: string]: 'success' | 'warning' | 'danger' | 'info' } = {
    daily: 'success',
    weekly: 'warning',
    monthly: 'danger'
  }
  return types[type] || 'info'
}

// 格式化金额
const formatAmount = (amount: any) => {
  const num = Number(amount)
  if (Number.isNaN(num)) return '0.00'
  return num.toFixed(2)
}

// 格式化时间
const formatTime = (timestamp: any) => {
  if (!timestamp) return '-'
  const date = new Date(timestamp * 1000)
  return date.toLocaleString()
}


// 获取渠道选项
const fetchChannelOptions = async () => {
  try {
    const dashboardApi = new baTableApi('/admin/dashboard/')
    const response = await dashboardApi.index()
    
    if (response.code === 1) {
      channelOptions.value = (response.data as any).channel_list || []
    }
  } catch (error) {
    console.error('获取渠道选项失败:', error)
  }
}

// 搜索
const handleSearch = () => {
  baTable.table.filter = { ...searchForm }
  baTable.onTableHeaderAction('refresh', {})
}

// 重置
const handleReset = () => {
  searchForm.type = ''
  searchForm.channel_id = ''
  baTable.table.filter = {}
  baTable.onTableHeaderAction('refresh', {})
}

// 查看榜单
const handleViewLeaderboard = async (row: any) => {
  try {
    console.log('查看榜单，行数据:', row)
    console.log('发送的ID:', row.id)
    
    const response = await baTable.api.postData('detail', {
      id: row.id
    })
    
    console.log('详情接口响应:', response)
    
    if (response.code === 1) {
      selectedRewardLog.value = response.data.reward_log
      leaderboardData.value = response.data.leaderboard_data || []
      console.log('设置的榜单数据:', leaderboardData.value)
      showLeaderboardDetail.value = true
    } else {
      console.error('获取榜单详情失败:', response.msg)
    }
  } catch (error) {
    console.error('请求失败:', error)
  }
}

// 初始化
onMounted(() => {
  fetchChannelOptions()
  // 自动加载表格数据
  baTable.onTableHeaderAction('refresh', {})
})

// 调试：监听表格数据变化
// baTable.table.on('refresh', (data: any) => {
//   console.log('表格数据:', data)
// })
</script>

<style scoped>
.search-form {
  background: #fff;
  padding: 20px;
  border-radius: 4px;
  margin-bottom: 20px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.table-container {
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.pagination-container {
  margin-top: 20px;
  text-align: right;
}
</style>
