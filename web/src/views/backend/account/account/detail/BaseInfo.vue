<template>
  <el-descriptions v-if="data" :column="2" border>
    <el-descriptions-item label="用户ID">{{ data.id }}</el-descriptions-item>
    <el-descriptions-item label="渠道ID">{{ data.channel_id }}</el-descriptions-item>
    <el-descriptions-item label="昵称">{{ data.nickname }}</el-descriptions-item>
    <el-descriptions-item label="玩家ID">{{ data.player_id }}</el-descriptions-item>
    <el-descriptions-item label="手机号">{{ data.mobile }}</el-descriptions-item>
    <el-descriptions-item label="VIP">{{ data.vip }}</el-descriptions-item>
    <el-descriptions-item label="充值钱包">{{ data.recharge_wallet }}</el-descriptions-item>
    <el-descriptions-item label="体验钱包">{{ data.experience_wallet }}</el-descriptions-item>
    <el-descriptions-item label="总充值金额">{{ formatAmount(data.total_recharge_amount) }}</el-descriptions-item>
    <el-descriptions-item label="充值次数">{{ data.recharge_count }}</el-descriptions-item>
    <el-descriptions-item label="充值成功次数">{{ data.success_recharge_count }}</el-descriptions-item>
    <el-descriptions-item label="提现金额">{{ formatAmount(data.total_withdraw_amount) }}</el-descriptions-item>
    <el-descriptions-item label="提现次数">{{ data.withdraw_count }}</el-descriptions-item>
    <el-descriptions-item label="注册时间">{{ formatTime(data.reg_time) }}</el-descriptions-item>
    <el-descriptions-item label="最后登录">{{ formatTime(data.last_login_time) }}</el-descriptions-item>
  </el-descriptions>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import createAxios from '/@/utils/axios'

const props = defineProps<{ userId: number }>()
const data = ref<any>(null)

const fetchData = async () => {
  const res = await createAxios(
    { url: `/admin/account.Account/detail`, method: 'get', params: { id: props.userId } },
    { cancelDuplicateRequest: false }
  )
  data.value = res.data?.base || null
}

const formatTime = (v: any) => {
  if (!v) return '-'
  const t = typeof v === 'number' ? v : Date.parse(v) / 1000
  return new Date(t * 1000).toLocaleString()
}

const formatAmount = (v: any) => {
  const n = Number(v)
  if (Number.isNaN(n)) return '0.00'
  return n.toFixed(2)
}

onMounted(fetchData)
watch(() => props.userId, fetchData)
</script>


