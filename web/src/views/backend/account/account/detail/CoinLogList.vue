<template>
  <el-table :data="list" style="width: 100%">
    <el-table-column prop="id" label="ID" width="80" />
    <el-table-column label="钱包">
      <template #default="{ row }">{{ formatWallet(row.wallet_type) }}</template>
    </el-table-column>
    <el-table-column label="变动金额">
      <template #default="{ row }">{{ formatAmount(row.num) }}</template>
    </el-table-column>
    <el-table-column label="类型">
      <template #default="{ row }">{{ formatType(row.log_type_id) }}</template>
    </el-table-column>
    <el-table-column label="时间">
      <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
    </el-table-column>
    <el-table-column prop="note" label="备注" />
  </el-table>
  <div class="pager">
    <el-pagination
      background
      layout="prev, pager, next, total"
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

const fetchData = async () => {
  const res = await createAxios(
    { url: `/admin/account.Account/coinLogList`, method: 'get', params: { id: props.userId, page: page.value, limit: limit.value } },
    { cancelDuplicateRequest: false }
  )
  list.value = res.data?.list || []
  total.value = res.data?.total || 0
}

const onPage = (p: number) => { page.value = p; fetchData() }

onMounted(fetchData)
watch(() => props.userId, () => { page.value = 1; fetchData() })

// 前端格式化映射
const walletMap: Record<number, string> = {
  0: '体验钱包',
  1: '充值钱包',
  2: '佣金钱包',
  3: 'PDD奖励',
}

const typeMap: Record<number, string> = {
  1: '注册赠送',
  2: '用户充值',
  3: '余额提现',
  4: '体验钱包提现',
  5: '游戏下注',
  6: '游戏赢得',
  7: '游戏返回',
  8: '余额提现返回',
  9: '体验提现返回',
  10: '系统操作',
  11: '站内信活动',
  12: '签到活动',
  13: '体验金补充',
  14: '绑定手机赠送',
  15: '弹窗赠送',
  16: '添加桌面',
  17: '限时首充',
  18: '每日首充',
  19: '救援金',
  20: 'VIP充值',
  21: '红包兑换',
  22: '生涯首充',
  23: 'VIP游戏返利',
  24: '系统赠送',
  25: 'VIP独有充值',
  26: 'VIP6%充值',
  27: '会员升级奖励',
  28: '宝箱奖励',
  29: '排行榜日奖励',
  30: '排行榜周奖励',
  31: '排行榜月奖励',
  32: '投注返佣',
  33: '邀请转盘提现',
  34: '邀请转盘提现返还',
  35: '幸运转盘中奖',
  36: '会员周奖励',
  37: '会员月奖励',
  38: '佣金提取到余额',
  39: 'PDD初始化',
  40: 'PDD邀请奖励',
  41: 'PDD达标补齐',
  42: 'Jackpot提现',
  43: '体验金提现赠送',
  44: '七天卡奖励',
}

const formatWallet = (v: any) => walletMap[Number(v)] ?? String(v)
const formatType = (v: any) => typeMap[Number(v)] ?? String(v)
const formatAmount = (v: any) => {
  const n = Number(v)
  if (Number.isNaN(n)) return String(v)
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
</script>

<style scoped>
.pager { margin-top: 12px; text-align: right; }
</style>


