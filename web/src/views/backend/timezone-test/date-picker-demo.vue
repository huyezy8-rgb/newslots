<template>
  <div class="date-picker-demo">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>Element Plus 时间控件演示</span>
          <TimezoneSelector />
        </div>
      </template>
      
      <el-row :gutter="20">
        <el-col :span="12">
          <h3>日期选择器</h3>
          <el-form label-width="120px">
            <el-form-item label="日期选择器">
              <el-date-picker
                v-model="dateValue"
                type="date"
                placeholder="选择日期"
                :timezone="currentTimezone"
                :default-value="getTodayInCurrentTimezone()"
              />
            </el-form-item>
            
            <el-form-item label="日期时间选择器">
              <el-date-picker
                v-model="datetimeValue"
                type="datetime"
                placeholder="选择日期时间"
                :timezone="currentTimezone"
                :default-value="getCurrentTimeWithTimezone()"
              />
            </el-form-item>
            
            <el-form-item label="日期范围选择器">
              <el-date-picker
                v-model="daterangeValue"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                :timezone="currentTimezone"
              />
            </el-form-item>
            
            <el-form-item label="日期时间范围选择器">
              <el-date-picker
                v-model="datetimerangeValue"
                type="datetimerange"
                range-separator="至"
                start-placeholder="开始日期时间"
                end-placeholder="结束日期时间"
                :timezone="currentTimezone"
              />
            </el-form-item>
          </el-form>
        </el-col>
        
        <el-col :span="12">
          <h3>选择结果</h3>
          <el-descriptions :column="1" border>
            <el-descriptions-item label="日期选择器">
              {{ formatWithTimezone(dateValue) || '未选择' }}
            </el-descriptions-item>
            <el-descriptions-item label="日期时间选择器">
              {{ formatWithTimezone(datetimeValue) || '未选择' }}
            </el-descriptions-item>
            <el-descriptions-item label="日期范围选择器">
              {{ formatDateRange(daterangeValue) || '未选择' }}
            </el-descriptions-item>
            <el-descriptions-item label="日期时间范围选择器">
              {{ formatDateRange(datetimerangeValue) || '未选择' }}
            </el-descriptions-item>
          </el-descriptions>
          
          <el-divider />
          
          <h3>当前时区信息</h3>
          <el-descriptions :column="1" border>
            <el-descriptions-item label="当前时区">
              {{ currentTimezone }}
            </el-descriptions-item>
            <el-descriptions-item label="当前时间">
              {{ formatWithTimezone(Date.now()) }}
            </el-descriptions-item>
            <el-descriptions-item label="今天日期">
              {{ formatWithTimezone(getTodayInCurrentTimezone()) }}
            </el-descriptions-item>
          </el-descriptions>
        </el-col>
      </el-row>
      
      <el-divider />
      
      <h3>自定义时区日期选择器组件</h3>
      <el-row :gutter="20">
        <el-col :span="8">
          <h4>日期选择器</h4>
          <TimezoneDatePicker
            v-model="customDateValue"
            type="date"
            placeholder="选择日期"
          />
          <p>选择结果: {{ formatWithTimezone(customDateValue) || '未选择' }}</p>
        </el-col>
        
        <el-col :span="8">
          <h4>日期时间选择器</h4>
          <TimezoneDatePicker
            v-model="customDatetimeValue"
            type="datetime"
            placeholder="选择日期时间"
          />
          <p>选择结果: {{ formatWithTimezone(customDatetimeValue) || '未选择' }}</p>
        </el-col>
        
        <el-col :span="8">
          <h4>日期范围选择器</h4>
          <TimezoneDatePicker
            v-model="customDaterangeValue"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
          />
          <p>选择结果: {{ formatDateRange(customDaterangeValue) || '未选择' }}</p>
        </el-col>
      </el-row>
      
      <el-divider />
      
      <h3>新的时区时间控件（根据用户方案）</h3>
      <el-row :gutter="20">
        <el-col :span="12">
          <h4>带时区选择的日期控件</h4>
          <TimezoneDatePickerNew
            v-model="newDateValue"
            type="date"
            placeholder="选择日期"
          />
          <p>选择结果: {{ formatWithTimezone(newDateValue) || '未选择' }}</p>
        </el-col>
        
        <el-col :span="12">
          <h4>带时区选择的日期时间控件</h4>
          <TimezoneDatePickerNew
            v-model="newDatetimeValue"
            type="datetime"
            placeholder="选择日期时间"
          />
          <p>选择结果: {{ formatWithTimezone(newDatetimeValue) || '未选择' }}</p>
        </el-col>
      </el-row>
      
      <el-row :gutter="20" style="margin-top: 20px;">
        <el-col :span="12">
          <h4>带时区选择的日期范围控件</h4>
          <TimezoneDatePickerNew
            v-model="newDaterangeValue"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
          />
          <p>选择结果: {{ formatDateRange(newDaterangeValue) || '未选择' }}</p>
        </el-col>
        
        <el-col :span="12">
          <h4>带时区选择的日期时间范围控件</h4>
          <TimezoneDatePickerNew
            v-model="newDatetimerangeValue"
            type="datetimerange"
            range-separator="至"
            start-placeholder="开始日期时间"
            end-placeholder="结束日期时间"
          />
          <p>选择结果: {{ formatDateRange(newDatetimerangeValue) || '未选择' }}</p>
        </el-col>
      </el-row>
      
      <el-divider />
      
      <h3>基于掘金文章思路的高级时区控件</h3>
      <el-alert
        title="参考掘金文章实现的高级时区控件"
        type="success"
        :closable="false"
        show-icon
        style="margin-bottom: 20px;"
      >
        <template #default>
          <p>特点：</p>
          <ul>
            <li>内置时区选择器</li>
            <li>自动禁用未来日期（基于选定时区）</li>
            <li>显示时区信息</li>
            <li>支持所有日期选择器类型</li>
            <li>时区切换时自动更新禁用规则</li>
          </ul>
        </template>
      </el-alert>
      
      <el-row :gutter="20">
        <el-col :span="12">
          <h4>高级日期选择器（禁用未来日期）</h4>
          <TimezoneDatePickerAdvanced
            v-model="advancedDateValue"
            type="date"
            placeholder="选择日期"
            :disable-future="true"
          />
          <p>选择结果: {{ formatWithTimezone(advancedDateValue) || '未选择' }}</p>
        </el-col>
        
        <el-col :span="12">
          <h4>高级日期时间选择器（禁用未来日期）</h4>
          <TimezoneDatePickerAdvanced
            v-model="advancedDatetimeValue"
            type="datetime"
            placeholder="选择日期时间"
            :disable-future="true"
          />
          <p>选择结果: {{ formatWithTimezone(advancedDatetimeValue) || '未选择' }}</p>
        </el-col>
      </el-row>
      
      <el-row :gutter="20" style="margin-top: 20px;">
        <el-col :span="12">
          <h4>高级日期范围选择器（禁用未来日期）</h4>
          <TimezoneDatePickerAdvanced
            v-model="advancedDaterangeValue"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            :disable-future="true"
          />
          <p>选择结果: {{ formatDateRange(advancedDaterangeValue) || '未选择' }}</p>
        </el-col>
        
        <el-col :span="12">
          <h4>高级日期时间范围选择器（禁用未来日期）</h4>
          <TimezoneDatePickerAdvanced
            v-model="advancedDatetimerangeValue"
            type="datetimerange"
            range-separator="至"
            start-placeholder="开始日期时间"
            end-placeholder="结束日期时间"
            :disable-future="true"
          />
          <p>选择结果: {{ formatDateRange(advancedDatetimerangeValue) || '未选择' }}</p>
        </el-col>
      </el-row>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { ElCard, ElRow, ElCol, ElForm, ElFormItem, ElDatePicker, ElDescriptions, ElDescriptionsItem, ElDivider, ElAlert } from 'element-plus'
import TimezoneSelector from '/@/components/timezone/TimezoneSelector.vue'
import TimezoneDatePicker from '/@/components/timezone/TimezoneDatePicker.vue'
import TimezoneDatePickerNew from '/@/components/timezone/TimezoneDatePickerNew.vue'
import TimezoneDatePickerAdvanced from '/@/components/timezone/TimezoneDatePickerAdvanced.vue'
import TimezoneDatePickerSimple from '/@/components/timezone/TimezoneDatePickerSimple.vue'
import { 
  getCurrentTimezone, 
  getTodayInCurrentTimezone, 
  getCurrentTimeWithTimezone, 
  formatWithTimezone 
} from '/@/utils/dayjs'

// 当前时区
const currentTimezone = computed(() => getCurrentTimezone())

// 日期选择器值
const dateValue = ref<Date | null>(null)
const datetimeValue = ref<Date | null>(null)
const daterangeValue = ref<[Date, Date] | null>(null)
const datetimerangeValue = ref<[Date, Date] | null>(null)

// 自定义组件值
const customDateValue = ref<Date | null>(null)
const customDatetimeValue = ref<Date | null>(null)
const customDaterangeValue = ref<[Date, Date] | null>(null)

// 新的时区控件值
const newDateValue = ref<Date | null>(null)
const newDatetimeValue = ref<Date | null>(null)
const newDaterangeValue = ref<[Date, Date] | null>(null)
const newDatetimerangeValue = ref<[Date, Date] | null>(null)

// 高级时区控件值
const advancedDateValue = ref<Date | null>(null)
const advancedDatetimeValue = ref<Date | null>(null)
const advancedDaterangeValue = ref<[Date, Date] | null>(null)
const advancedDatetimerangeValue = ref<[Date, Date] | null>(null)

// 格式化日期范围
const formatDateRange = (range: [Date, Date] | null): string => {
  if (!range || !Array.isArray(range)) return ''
  return `${formatWithTimezone(range[0])} 至 ${formatWithTimezone(range[1])}`
}
</script>

<style scoped>
.date-picker-demo {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

h3 {
  margin-bottom: 20px;
  color: #303133;
}

h4 {
  margin-bottom: 10px;
  color: #606266;
}

p {
  margin-top: 10px;
  font-size: 14px;
  color: #909399;
}
</style> 