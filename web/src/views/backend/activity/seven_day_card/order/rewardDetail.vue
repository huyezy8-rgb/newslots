<template>
  <el-dialog v-model="visibleInner" title="奖励详情" width="900px" destroy-on-close>
    <!-- 订单基础信息 -->
    <div v-if="order" class="order-info-row">
      <el-descriptions :column="2" size="small" border>
        <el-descriptions-item label="ID">{{ order.id }}</el-descriptions-item>
        <el-descriptions-item label="订单号">{{ order.order_no }}</el-descriptions-item>
        <el-descriptions-item label="用户ID">{{ order.user_id }}</el-descriptions-item>
        <el-descriptions-item label="渠道">{{ order.channel_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="金额">{{ formatAmount(order.amount) }}</el-descriptions-item>
        <el-descriptions-item label="开通时间">{{ formatTime(order.start_time) }}</el-descriptions-item>
        <el-descriptions-item label="结束时间">{{ formatTime(order.end_time) }}</el-descriptions-item>
      </el-descriptions>
    </div>
    <div v-else style="margin-bottom:16px;">订单信息加载中...</div>
    <!-- 三个奖励横向排列 -->
    <div class="reward-board-row">
      <div class="reward-board">
        <div class="reward-title">七天奖励</div>
        <div class="reward-list">
          <div class="reward-item" v-for="item in rewards.main" :key="'main-'+item.day">
            <span class="day">第{{ item.day }}天</span>
            <span class="amount">{{ formatAmount(item.reward) }}</span>
            <el-tag size="small" :type="item.status == 1 ? 'success' : 'info'">{{ item.status == 1 ? '已领取' : '未领取' }}</el-tag>
          </div>
        </div>
      </div>
      <div class="reward-board">
        <div class="reward-title">救援金</div>
        <div class="reward-list">
          <div class="reward-item" v-for="item in rewards.rescue" :key="'rescue-'+item.day">
            <span class="day">第{{ item.day }}天</span>
            <span class="amount">{{ formatAmount(item.reward) }}</span>
            <el-tag size="small" :type="item.status == 1 ? 'success' : 'info'">{{ item.status == 1 ? '已领取' : '未领取' }}</el-tag>
          </div>
        </div>
      </div>
      <div class="reward-board">
        <div class="reward-title">每日奖励</div>
        <div class="reward-list">
          <div class="reward-item" v-for="item in rewards.daily" :key="'daily-'+item.day">
            <span class="day">第{{ item.day }}天</span>
            <span class="amount">{{ formatAmount(item.reward) }}</span>
            <el-tag size="small" :type="item.status == 1 ? 'success' : 'info'">{{ item.status == 1 ? '已领取' : '未领取' }}</el-tag>
          </div>
        </div>
      </div>
    </div>
    <template #footer>
      <el-button type="primary" @click="visibleInner=false">关闭</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import createAxios from '/@/utils/axios'

// 标准props/emit（不要用v-model:visible，内部用visibleInner）
const props = defineProps<{ visible: boolean; orderId: number }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void }>()

// 内部显隐变量、双向同步
const visibleInner = ref(props.visible)
watch(() => props.visible, v => visibleInner.value = v)
watch(visibleInner, v => emit('update:visible', v))

// 原有内容
const rewards = ref<{ main: any[]; rescue: any[]; daily: any[] }>({ main: [], rescue: [], daily: [] })
const order = ref<any>(null)

const fetchData = async () => {
  if (!props.orderId) return;
  const res = await createAxios({ url: '/admin/activity.SevenDayCardOrder/detail', method: 'get', params: { id: props.orderId } }, { cancelDuplicateRequest: false })
  rewards.value = { main: res.data?.rewards?.main || [], rescue: res.data?.rewards?.rescue || [], daily: res.data?.rewards?.daily || [] }
  order.value = res.data?.order || null
  fillToSeven(rewards.value.main)
  fillToSeven(rewards.value.rescue)
  fillToSeven(rewards.value.daily)
}
function fillToSeven(arr:any[]){
  while(arr.length < 7){
    arr.push({day:arr.length+1,reward:'',status:0})
  }
}

// 弹窗打开时fetch，orderid变也fetch
onMounted(() => { if (visibleInner.value && props.orderId) fetchData() })
watch([visibleInner, () => props.orderId], ([v, oid]) => { if (v && oid) fetchData() })

const formatAmount = (v: any) => {
  const n = Number(v)
  if (!v && v !== 0) return '-'
  if (Number.isNaN(n)) return '0.00'
  return n.toFixed(2)
}
const formatTime = (v:any) => {
  if(!v) return '-';
  let time = v.toString().length==10? v*1000: v;
  let d = new Date(time)
  return `${d.getFullYear()}-${(d.getMonth()+1).toString().padStart(2,'0')}-${d.getDate().toString().padStart(2,'0')} ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`
}
</script>

<style scoped lang="scss">
.order-info-row{margin-bottom:20px;}
.reward-board-row{
  display:flex;
  gap:18px;
  justify-content:space-between;
  margin-top:18px;
}
.reward-board{
  flex:1;
  background:#fafbfc;
  border-radius:7px;
  box-shadow:0 2px 8px #eee3;
  padding:16px 8px 10px;
  min-width:195px;
  display:flex;
  flex-direction:column;
  align-items:center;
}
.reward-title{
  margin-bottom:8px;
  font-weight:bold;
}
.reward-list {
  display:flex; flex-direction:column; gap:8px; width:100%; align-items:center;
}
.reward-item{display:flex;align-items:center;gap:16px; font-size:15px;justify-content:center;}
.reward-item .day{width:56px;text-align:right; color:#aaa;}
.reward-item .amount{width:66px;text-align:right; color:#444;font-weight:500;margin-left:8px;}
</style>


