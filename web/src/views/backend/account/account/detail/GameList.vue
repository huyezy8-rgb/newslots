<template>
  <el-table :data="list" style="width: 100%">
    <el-table-column prop="id" label="ID" width="80" />
    <el-table-column prop="reason" label="类型" />
    <el-table-column prop="amount" label="金额" />
    <el-table-column prop="req_time" label="时间" />
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
    { url: `/admin/account.Account/gameList`, method: 'get', params: { id: props.userId, page: page.value, limit: limit.value } },
    { cancelDuplicateRequest: false }
  )
  list.value = res.data?.list || []
  total.value = res.data?.total || 0
}

const onPage = (p: number) => { page.value = p; fetchData() }

onMounted(fetchData)
watch(() => props.userId, () => { page.value = 1; fetchData() })
</script>

<style scoped>
.pager { margin-top: 12px; text-align: right; }
</style>


