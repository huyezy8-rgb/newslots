<template>
  <div class="default-main">
    <!-- 筛选区 -->
    <el-form :inline="true" class="filter-panel">
      <el-form-item label="日期">
        <el-date-picker v-model="dateRange" type="daterange" range-separator="-" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" style="width: 260px" />
      </el-form-item>
      <el-form-item label="渠道" v-if="adminInfo.isAdminChannelId == null">
        <el-select v-model="channelId" placeholder="全部渠道" clearable style="width: 180px">
          <el-option label="全部" :value="''" />
          <el-option v-for="item in channelList" :key="item.id" :label="item.name" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" :loading="loading" @click="fetchData">查询</el-button>
        <el-button @click="resetFilter">重置</el-button>
        <el-button type="success" :loading="exportLoading" @click="exportData">导出</el-button>
      </el-form-item>
    </el-form>
    <!-- 数据表格 -->
    <el-table
      :data="tableData"
      border
      :header-cell-style="{ background: '#f5f7fa', fontWeight: 'bold', borderRight: '2px solid #dcdfe6' }"
      style="width: 100%; min-width: 1200px; margin-bottom: 20px;"
      v-loading="loading"
    >
      <el-table-column prop="date" label="日期" width="120" />
      <el-table-column
        v-for="col in dynamicColumns"
        :key="col"
        :prop="col"
        :label="col"
        width="100"
        align="right"
      />
    </el-table>
    <!-- 分页 -->
    <el-pagination
      background
      layout="total, sizes, prev, pager, next, jumper"
      :total="total"
      :page-size="limit"
      :current-page="page"
      @size-change="handleSizeChange"
      @current-change="handlePageChange"
      :page-sizes="[10, 20, 50, 100]"
      style="margin-top: 10px;"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { getPayRetentionData } from '/@/api/backend/pay_retention';
import createAxios from '/@/utils/axios';
import { exportRowsToCsv } from '/@/utils/exportCsv'
import dayjs from 'dayjs'

const dateRange = ref<string[]>([]);
const channelId = ref('');
const channelList = ref<{id: number|string, name: string}[]>([]);
const tableData = ref<any[]>([]);
const page = ref(1);
const limit = ref(20);
const total = ref(0);
const loading = ref(false);
const exportLoading = ref(false);
import { useAdminInfo } from '/@/stores/adminInfo'

const dynamicColumns = computed(() => {
  if (tableData.value.length === 0) return [];
  return Object.keys(tableData.value[0]).filter(key => key !== 'date');
});

const adminInfo = useAdminInfo()
const fetchChannels = async () => {
  const res = await createAxios({ url: '/admin/channel.listsss/all', method: 'get' });
  if (res.code === 1 && Array.isArray(res.data)) {
    channelList.value = res.data;
  }
};

const fetchData = async () => {
  loading.value = true;
  const params: any = {
    page: page.value,
    limit: limit.value,
  };
  if (dateRange.value && dateRange.value.length === 2) {
    params.start_date = dateRange.value[0];
    params.end_date = dateRange.value[1];
  }
  if (channelId.value) params.channel_id = channelId.value;
  const res = await getPayRetentionData(params);
  if (res.code === 1) {
    tableData.value = res.data.list || [];
    total.value = res.data.total || 0;
  }
  loading.value = false;
};

const resetFilter = () => {
  dateRange.value = [];
  channelId.value = '';
  page.value = 1;
  fetchData();
};

const handleSizeChange = (size: number) => {
  limit.value = size;
  page.value = 1;
  fetchData();
};
const handlePageChange = (p: number) => {
  page.value = p;
  fetchData();
};

const exportData = async () => {
  exportLoading.value = true;
  try {
    const params: any = {
      page: 1,
      limit: total.value || limit.value,
    };
    if (dateRange.value && dateRange.value.length === 2) {
      params.start_date = dateRange.value[0];
      params.end_date = dateRange.value[1];
    }
    if (channelId.value) params.channel_id = channelId.value;
    const res = await getPayRetentionData(params);
    const rows = res.data.list || [];
    const headers = Object.keys(rows[0] || tableData.value[0] || {}).map((key) => ({ label: key === 'date' ? '日期' : key, prop: key }));
    exportRowsToCsv(`pay_retention_${dayjs().format('YYYYMMDDHHmmss')}.csv`, headers, rows);
  } finally {
    exportLoading.value = false;
  }
};

onMounted(() => {
  fetchChannels();
  fetchData();
});
</script>

<style scoped lang="scss">
.default-main {
  padding: 24px;
  background: #f5f7fa;
  min-height: 100vh;
}
.filter-panel {
  background-color: #fff;
  border-radius: 8px;
  padding: 20px 20px 0 20px;
  margin-bottom: 20px;
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.05);
}
</style>
