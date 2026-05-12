<template>
  <el-dialog
    v-model="visible"
    title="排行榜详情"
    width="80%"
    :before-close="handleClose"
  >
    <div v-if="rewardLog" class="reward-info">
      <el-descriptions title="奖励发放信息" :column="3" border>
        <el-descriptions-item label="排行榜类型">
          <el-tag :type="getTypeTagType(rewardLog.type) as any">
            {{ getTypeText(rewardLog.type) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="渠道">
          {{ rewardLog.channel_name || '全部渠道' }}
        </el-descriptions-item>
        <el-descriptions-item label="奖池总金额">
          {{ formatAmount(rewardLog.pool_amount) }}
        </el-descriptions-item>
        <el-descriptions-item label="实际发放金额">
          {{ formatAmount(rewardLog.distributed_amount) }}
        </el-descriptions-item>
        <el-descriptions-item label="发放人数">
          {{ rewardLog.success_count }} 人
        </el-descriptions-item>
        <el-descriptions-item label="发放成功率">
          {{ rewardLog.success_rate }}
        </el-descriptions-item>
        <el-descriptions-item label="发放时间">
          {{ formatTime(rewardLog.create_time) }}
        </el-descriptions-item>
      </el-descriptions>
    </div>

    <div class="leaderboard-container" v-if="rewardLog">
      <div class="leaderboard-header">
        <h3>排行榜榜单</h3>
        <div class="leaderboard-stats">
          <span>共 {{ (leaderboardData && leaderboardData.length) || 0 }} 名用户</span>
        </div>
      </div>

      <el-empty v-if="!leaderboardData || leaderboardData.length === 0" description="暂无榜单数据" />

      <el-table
        v-else
        :data="leaderboardData"
        border
        stripe
        style="width: 100%"
        max-height="500"
      >
        <el-table-column prop="rank" label="排名" width="100" align="center">
          <template #default="{ row }">
            <div class="rank-cell">
              <span v-if="row.rank != null && row.rank !== undefined && Number(row.rank) >= 1 && Number(row.rank) <= 3" :class="['top-rank', 'rank-' + row.rank]">
                {{ row.rank }}
              </span>
              <span v-else-if="row.rank != null && row.rank !== undefined" class="normal-rank">{{ row.rank }}</span>
              <span v-else class="normal-rank">-</span>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column label="用户信息" width="200">
          <template #default="{ row }">
            <div class="user-info">
              <div class="user-details">
                <div class="nickname">{{ row.nickname }}</div>
                <div class="user-id">ID: {{ row.user_id }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column prop="total_bet" label="打码总额" width="140">
          <template #default="{ row }">
            {{ formatAmount(row.total_bet) }}
          </template>
        </el-table-column>
        
        <el-table-column prop="reward_ratio" label="奖励比例" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="getRewardRatioTagType(row.reward_ratio)">
              {{ row.reward_ratio }}%
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column prop="reward_amount" label="奖励金额" width="140">
          <template #default="{ row }">
            <span class="reward-amount">
              {{ formatAmount(row.reward_amount) }}
            </span>
          </template>
        </el-table-column>
        
        <el-table-column prop="reward_remark" label="备注" width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <span class="reward-remark">
              {{ row.reward_remark || '' }}
            </span>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="handleClose">关闭</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

// Props
const props = defineProps<{
  modelValue: boolean
  rewardLog: any
  leaderboardData: any[]
}>()

// Emits
const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

// 响应式数据
const visible = ref(false)

// 监听 modelValue 变化
watch(() => props.modelValue, (val) => {
  visible.value = val
})

// 监听 visible 变化
watch(visible, (val) => {
  emit('update:modelValue', val)
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
const getTypeTagType = (type: string) => {
  const types: { [key: string]: string } = {
    daily: 'success',
    weekly: 'warning',
    monthly: 'danger'
  }
  return types[type] || 'info'
}

// 获取奖励比例标签类型
const getRewardRatioTagType = (ratio: number) => {
  if (ratio >= 10) return 'danger'
  if (ratio >= 5) return 'warning'
  if (ratio > 0) return 'success'
  return 'info'
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

// 关闭对话框
const handleClose = () => {
  visible.value = false
}
</script>

<style scoped>
.reward-info {
  margin-bottom: 20px;
}

.leaderboard-container {
  margin-top: 20px;
}

.leaderboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid #ebeef5;
}

.leaderboard-header h3 {
  margin: 0;
  color: #303133;
}

.leaderboard-stats {
  color: #909399;
  font-size: 14px;
}

.rank-cell {
  display: flex;
  justify-content: center;
  align-items: center;
}

.top-rank {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 14px;
}

.rank-1 {
  background: linear-gradient(135deg, #ffd700, #ffed4e);
  color: #333;
}

.rank-2 {
  background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
  color: #333;
}

.rank-3 {
  background: linear-gradient(135deg, #cd7f32, #daa520);
  color: white;
}

.normal-rank {
  font-weight: bold;
  color: #606266;
}

.user-info {
  display: flex;
  align-items: center;
}

.user-details {
  flex: 1;
  min-width: 0;
}

.nickname {
  font-weight: 500;
  color: #303133;
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-id {
  font-size: 12px;
  color: #909399;
}

.reward-amount {
  font-weight: bold;
  color: #67c23a;
}

.reward-remark {
  color: #606266;
  font-size: 13px;
}

.dialog-footer {
  text-align: right;
}
</style>
