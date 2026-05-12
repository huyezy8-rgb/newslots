<template>
  <div class="timezone-date-picker-simple">
    <h4>简化时区日期选择器</h4>
    <el-select v-model="selectedZone" placeholder="选择时区" style="width: 200px; margin-bottom: 10px">
      <el-option label="中国标准时间 (UTC+8)" value="Asia/Shanghai" />
      <el-option label="美东时间 (UTC-5)" value="America/New_York" />
      <el-option label="美西时间 (UTC-8)" value="America/Los_Angeles" />
      <el-option label="UTC时间 (UTC+0)" value="UTC" />
    </el-select>

    <el-date-picker
      v-model="date"
      type="date"
      placeholder="选择日期"
      :timezone="selectedZone"
      style="width: 200px;"
    />
    
    <div style="margin-top: 10px; font-size: 12px; color: #666;">
      <p>当前时区: {{ selectedZone }}</p>
      <p>选择结果: {{ date ? date.toLocaleDateString() : '未选择' }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const selectedZone = ref('Asia/Shanghai')
const date = ref<Date | null>(null)
</script>

<style scoped>
.timezone-date-picker-simple {
  border: 1px solid #ddd;
  padding: 15px;
  border-radius: 4px;
  background: #f9f9f9;
}
</style> 