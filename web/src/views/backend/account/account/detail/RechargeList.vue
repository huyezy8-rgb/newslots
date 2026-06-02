<template>
  <el-table :data="list" style="width: 100%" height="calc(92vh - 280px)">
    <el-table-column prop="id" label="ID" width="80" />
    <el-table-column label="金额">
      <template #default="{ row }">{{ formatAmount(row.amount) }}</template>
    </el-table-column>
    <el-table-column label="状态">
      <template #default="{ row }">
        <el-tag :type="getStatusType(row.pay_status)">{{ getStatusText(row.pay_status) }}</el-tag>
      </template>
    </el-table-column>
    <el-table-column label="创建时间">
      <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
    </el-table-column>
  </el-table>
  <div class="pager">
    <div class="page-size-custom">
      <span>每页</span>
      <el-input-number
        v-model="customLimit"
        class="custom-page-size-input"
        size="small"
        :min="1"
        :max="200"
        :step="10"
        :controls="false"
        @change="onCustomSizeChange"
      />
      <span>条</span>
    </div>
    <el-pagination
      background
      small
      layout="total, prev, pager, next, jumper"
      :pager-count="5"
      :total="total"
      :page-size="limit"
      :current-page="page"
      @current-change="onPage"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import createAxios from '/@/utils/axios'

const props = defineProps<{ userId: number }>()
const list = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)
const customLimit = ref(10)

const fetchData = async () => {
  const res = await createAxios(
    { url: `/admin/account.Account/rechargeList`, method: 'get', params: { id: props.userId, page: page.value, limit: limit.value } },
    { cancelDuplicateRequest: false }
  )
  list.value = res.data?.list || []
  total.value = res.data?.total || 0
}

const onPage = (p: number) => { page.value = p; fetchData() }
const applyPageSize = (size: number | null | undefined) => {
  const nextSize = Math.floor(Number(size))
  if (!Number.isFinite(nextSize) || nextSize < 1) {
    customLimit.value = limit.value
    return
  }
  if (limit.value === nextSize) {
    customLimit.value = nextSize
    return
  }
  limit.value = nextSize
  customLimit.value = nextSize
  page.value = 1
  fetchData()
}
const onCustomSizeChange = (size: number | null) => applyPageSize(size)

onMounted(fetchData)
watch(() => props.userId, () => { page.value = 1; fetchData() })

// 格式化函数
const formatAmount = (v: any) => {
  const n = Number(v)
  if (Number.isNaN(n)) return '0.00'
  return n.toFixed(2)
}

const formatTime = (v: any) => {
  if (!v) return '-'
  // 支持时间戳（秒/毫秒）和字符串
  let ts = v
  if (typeof v === 'string') {
    const parsed = Date.parse(v)
    ts = Number.isNaN(parsed) ? Number(v) : parsed / 1000
  }
  if (ts > 1e12) ts = Math.floor(ts / 1000)
  if (ts < 1e11) ts = ts * 1000
  const d = new Date(ts)
  const pad = (n: number) => (n < 10 ? '0' + n : '' + n)
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

const getStatusText = (status: any) => {
  const s = Number(status)
  switch (s) {
    case 0: return '未支付'
    case 1: return '支付成功'
    case 2: return '支付失败'
    default: return '未知状态'
  }
}

const getStatusType = (status: any) => {
  const s = Number(status)
  switch (s) {
    case 0: return 'warning'
    case 1: return 'success'
    case 2: return 'danger'
    default: return 'info'
  }
}
</script>

<style scoped>
.pager {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 12px;
  max-width: 100%;
  overflow-x: auto;
  white-space: nowrap;
}
.page-size-custom {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 6px;
  color: var(--el-text-color-regular);
  font-size: 13px;
}
.custom-page-size-input {
  width: 86px;
}

.pager :deep(.el-pagination) {
  flex: 0 0 auto;
  flex-wrap: nowrap;
  justify-content: flex-end;
  max-width: 100%;
}
</style>
