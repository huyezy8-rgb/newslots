<template>
  <el-table :data="list" style="width: 100%" height="calc(92vh - 280px)">
    <el-table-column prop="id" label="ID" width="80" />
    <el-table-column prop="reason" label="类型" />
    <el-table-column prop="amount" label="金额" />
    <el-table-column prop="req_time" label="时间" />
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
    { url: `/admin/account.Account/gameList`, method: 'get', params: { id: props.userId, page: page.value, limit: limit.value } },
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
