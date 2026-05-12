<template>
  <div class="timezone-test">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>时区测试</span>
        </div>
      </template>
      
      <!-- 时区信息对比 -->
      <el-row :gutter="20">
        <el-col :span="12">
          <h3>前端时区信息</h3>
          <el-descriptions :column="1" border>
            <el-descriptions-item label="当前时区">
              {{ frontendTimezone }}
            </el-descriptions-item>
            <el-descriptions-item label="系统配置时区">
              {{ systemTimezone }}
            </el-descriptions-item>
            <el-descriptions-item label="浏览器时区">
              {{ browserTimezone }}
            </el-descriptions-item>
            <el-descriptions-item label="dayjs 插件状态">
              <el-tag :type="isTimezonePluginAvailable ? 'success' : 'danger'">
                {{ isTimezonePluginAvailable ? '可用' : '不可用' }}
              </el-tag>
            </el-descriptions-item>
          </el-descriptions>
        </el-col>
        
        <el-col :span="12">
          <h3>后端时区信息</h3>
          <el-descriptions :column="1" border v-loading="backendLoading">
            <el-descriptions-item label="PHP 时区">
              {{ backendInfo.php_timezone }}
            </el-descriptions-item>
            <el-descriptions-item label="ThinkPHP 时区">
              {{ backendInfo.thinkphp_timezone }}
            </el-descriptions-item>
            <el-descriptions-item label="系统配置时区">
              {{ backendInfo.system_config_timezone }}
            </el-descriptions-item>
            <el-descriptions-item label="时区偏移">
              {{ backendInfo.timezone_offset }}
            </el-descriptions-item>
          </el-descriptions>
        </el-col>
      </el-row>
      
      <el-divider />
      
      <!-- 时区选择 -->
      <h3>时区选择</h3>
      <el-row :gutter="20">
        <el-col :span="12">
          <TimezoneSelector />
          <el-button @click="resetToSystem" style="margin-left: 10px">
            重置为系统时区
          </el-button>
        </el-col>
        <el-col :span="12">
          <el-button @click="refreshBackendInfo" :loading="backendLoading">
            刷新后端信息
          </el-button>
        </el-col>
      </el-row>
      
      <el-divider />
      
      <!-- 时间格式化对比 -->
      <h3>时间格式化对比</h3>
      <el-row :gutter="20">
        <el-col :span="8">
          <h4>当前时间</h4>
          <p>原始: {{ new Date().toISOString() }}</p>
          <p>前端: {{ formatWithTimezone(Date.now()) }}</p>
          <p>后端: {{ backendFormatted.current }}</p>
        </el-col>
        <el-col :span="8">
          <h4>固定时间戳</h4>
          <p>原始: 1704096000000</p>
          <p>前端: {{ formatWithTimezone(1704096000000) }}</p>
          <p>后端: {{ backendFormatted.fixed }}</p>
        </el-col>
        <el-col :span="8">
          <h4>10位时间戳</h4>
          <p>原始: {{ Math.floor(Date.now() / 1000) }}</p>
          <p>前端: {{ formatWithTimezone(Math.floor(Date.now() / 1000)) }}</p>
          <p>后端: {{ backendFormatted.tenDigit }}</p>
        </el-col>
      </el-row>
      
      <el-divider />
      
      <!-- 实时时间显示 -->
      <h3>实时时间显示</h3>
      <el-row :gutter="20">
        <el-col :span="6"><h4>当前时区</h4><p>{{ realTimeCurrent }}</p></el-col>
        <el-col :span="6"><h4>UTC 时间</h4><p>{{ realTimeUtc }}</p></el-col>
        <el-col :span="6"><h4>美东时间</h4><p>{{ realTimeNy }}</p></el-col>
        <el-col :span="6"><h4>后端时间</h4><p>{{ backendInfo.current_time }}</p></el-col>
      </el-row>
      
      <el-button @click="debugTimezone" type="warning" style="margin-top: 10px;">
        调试时区信息
      </el-button>
      
      <el-button @click="validateTimezone" type="info" style="margin-top: 10px; margin-left: 10px;">
        验证时区转换
      </el-button>
      
      <el-divider />
      
      <!-- 时间控件测试 -->
      <h3>时间控件测试</h3>
      <el-row :gutter="20">
        <el-col :span="8">
          <h4>日期选择器</h4>
          <el-date-picker
            v-model="datePickerValue"
            type="date"
            placeholder="选择日期"
            @change="handleDateChange"
          />
          <p>选择的值: {{ datePickerValue }}</p>
          <p>格式化后: {{ datePickerValue ? formatWithTimezone(datePickerValue) : '' }}</p>
        </el-col>
        
        <el-col :span="8">
          <h4>日期时间选择器</h4>
          <el-date-picker
            v-model="datetimePickerValue"
            type="datetime"
            placeholder="选择日期时间"
            @change="handleDateTimeChange"
          />
          <p>选择的值: {{ datetimePickerValue }}</p>
          <p>格式化后: {{ datetimePickerValue ? formatWithTimezone(datetimePickerValue) : '' }}</p>
        </el-col>
        
        <el-col :span="8">
          <h4>日期范围选择器</h4>
          <el-date-picker
            v-model="dateRangeValue"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            @change="handleDateRangeChange"
          />
          <p>选择的值: {{ dateRangeValue }}</p>
          <p>格式化后: {{ dateRangeValue ? formatDateRange(dateRangeValue) : '' }}</p>
        </el-col>
      </el-row>
      
      <el-divider />
      
      <!-- 时区转换测试 -->
      <h3>时区转换测试</h3>
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form :model="timezoneTestForm" label-width="120px">
            <el-form-item label="时间戳">
              <el-input v-model="timezoneTestForm.timestamp" placeholder="输入时间戳（支持秒级和毫秒级）" />
              <div style="font-size: 12px; color: #999; margin-top: 5px;">
                秒级时间戳：10位数字（如：1704096000）<br>
                毫秒级时间戳：13位数字（如：1704096000000）
              </div>
            </el-form-item>
            <el-form-item label="目标时区">
              <el-select v-model="timezoneTestForm.timezone" placeholder="选择时区">
                <el-option
                  v-for="tz in timezones"
                  :key="tz.value"
                  :label="tz.label"
                  :value="tz.value"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="格式">
              <el-input v-model="timezoneTestForm.format" placeholder="时间格式" />
            </el-form-item>
            <el-form-item>
              <el-button @click="testTimezoneConversion" type="primary">
                测试转换
              </el-button>
            </el-form-item>
          </el-form>
        </el-col>
        
        <el-col :span="12">
          <h4>转换结果</h4>
          <el-descriptions :column="1" border>
            <el-descriptions-item label="前端结果">
              {{ conversionResult.frontend }}
            </el-descriptions-item>
            <el-descriptions-item label="后端结果">
              {{ conversionResult.backend }}
            </el-descriptions-item>
            <el-descriptions-item label="是否一致">
              <el-tag :type="conversionResult.frontend === conversionResult.backend ? 'success' : 'danger'">
                {{ conversionResult.frontend === conversionResult.backend ? '一致' : '不一致' }}
              </el-tag>
            </el-descriptions-item>
          </el-descriptions>
        </el-col>
      </el-row>
      
      <el-divider />
      
      <!-- 时间控件演示链接 -->
      <h3>时间控件演示</h3>
      <el-alert
        title="Element Plus 时间控件已配置使用 dayjs 和当前时区"
        type="success"
        :closable="false"
        show-icon
      >
        <template #default>
          <p>所有时间控件现在都会：</p>
          <ul>
            <li>使用 dayjs 进行时区处理</li>
            <li>默认显示当前时区的日期</li>
            <li>支持时区切换</li>
            <li>自动格式化时间显示</li>
          </ul>
          <p>您可以在任何页面使用 <code>el-date-picker</code> 组件，它们都会自动应用时区配置。</p>
        </template>
      </el-alert>
      
      <el-divider />
      
      <!-- 新的时区控件说明 -->
      <h3>新的时区时间控件</h3>
      <el-alert
        title="根据用户方案实现的新时区控件"
        type="info"
        :closable="false"
        show-icon
      >
        <template #default>
          <p>新增了 <code>TimezoneDatePickerNew</code> 组件，特点：</p>
          <ul>
            <li>内置时区选择器，可直接切换时区</li>
            <li>时区切换时自动更新日期为该时区的今天</li>
            <li>支持所有日期选择器类型（date、datetime、daterange、datetimerange）</li>
            <li>使用 dayjs 进行时区处理</li>
            <li>全局时区同步</li>
          </ul>
          <p>使用方法：</p>
          <pre><code>&lt;TimezoneDatePickerNew
  v-model="dateValue"
  type="date"
  placeholder="选择日期"
/&gt;</code></pre>
        </template>
      </el-alert>
      
      <el-divider />
      
      <!-- 基于掘金文章思路的说明 -->
      <h3>基于掘金文章思路的高级时区控件</h3>
      <el-alert
        title="参考掘金文章实现的高级时区控件"
        type="success"
        :closable="false"
        show-icon
      >
        <template #default>
          <p>新增了 <code>TimezoneDatePickerAdvanced</code> 组件，基于掘金文章思路：</p>
          <ul>
            <li>内置时区选择器</li>
            <li><strong>自动禁用未来日期</strong>（基于选定时区）</li>
            <li>显示时区信息（今天、现在）</li>
            <li>支持所有日期选择器类型</li>
            <li>时区切换时自动更新禁用规则</li>
            <li>使用 <code>picker-options.disabledDate</code> 实现禁用功能</li>
          </ul>
          <p>核心实现：</p>
          <pre><code>// 获取当前时区的今天结束时间（23:59:59）
const todayEnd = dayjs().tz(timezone).endOf('day').toDate()

// 禁用超过今天的日期
disabledDate: (time) => time.getTime() > todayEnd.getTime()</code></pre>
          <p>使用方法：</p>
          <pre><code>&lt;TimezoneDatePickerAdvanced
  v-model="dateValue"
  type="date"
  :disable-future="true"
/&gt;</code></pre>
        </template>
      </el-alert>
      
      <el-divider />
      
      <!-- Datetime 渲染器测试 -->
      <h3>Datetime 渲染器测试</h3>
      <el-alert
        title="测试 datetime 渲染器的时区显示"
        type="info"
        :closable="false"
        show-icon
      >
        <template #default>
          <p>测试 <code>render: 'datetime'</code> 的时区显示功能：</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
              <h4>测试数据</h4>
              <div class="text-sm space-y-2">
                <div>当前时间戳: {{ Date.now() }}</div>
                <div>当前时间: {{ new Date().toISOString() }}</div>
                <div>美东时间: {{ formatWithTimezone(new Date(), 'YYYY-MM-DD HH:mm:ss') }}</div>
                <div>中国时间: {{ formatWithTimezone(new Date(), 'YYYY-MM-DD HH:mm:ss', 'Asia/Shanghai') }}</div>
              </div>
            </div>
            <div>
              <h4>模拟表格数据</h4>
              <div class="text-sm space-y-2">
                <div>created_at: {{ formatWithTimezone('2024-01-27 10:30:00', 'YYYY-MM-DD HH:mm:ss') }}</div>
                <div>updated_at: {{ formatWithTimezone('2024-01-27 15:45:00', 'YYYY-MM-DD HH:mm:ss') }}</div>
                <div>paid_at: {{ formatWithTimezone('2024-01-27 12:20:00', 'YYYY-MM-DD HH:mm:ss') }}</div>
              </div>
            </div>
          </div>
        </template>
      </el-alert>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { ElMessage } from 'element-plus'
import TimezoneSelector from '/@/components/timezone/TimezoneSelector.vue'
import { 
  getCurrentTimezone, 
  getTodayInCurrentTimezone, 
  getCurrentTimeWithTimezone, 
  formatWithTimezone,
  debugTimezoneInfo,
  validateTimezoneConversion,
  getSystemTimezone,
  setGlobalTimezone,
  isTimezonePluginAvailable as checkTimezonePluginAvailable,
  SUPPORTED_TIMEZONES
} from '/@/utils/dayjs'
import { getTimezoneInfo, testTimezoneFormat } from '/@/api/backend/timezone'

// 时区信息
const frontendTimezone = ref('')
const systemTimezone = ref('')
const browserTimezone = ref('')
const isTimezonePluginAvailable = ref(false)

// 后端信息
const backendInfo = ref({
  php_timezone: '',
  thinkphp_timezone: '',
  system_config_timezone: '',
  current_time: '',
  utc_time: '',
  timestamp: 0,
  timezone_offset: '',
  supported_timezones: []
})
const backendLoading = ref(false)

// 实时时间
const realTimeCurrent = ref('')
const realTimeUtc = ref('')
const realTimeNy = ref('')

// 时间控件测试
const datePickerValue = ref<Date | ''>('')
const datetimePickerValue = ref<Date | ''>('')
const dateRangeValue = ref<[Date, Date] | ''>('')

// 时区转换测试
const timezoneTestForm = ref({
  timestamp: Date.now().toString(),
  timezone: 'America/New_York',
  format: 'YYYY-MM-DD HH:mm:ss'
})
const conversionResult = ref({
  frontend: '',
  backend: ''
})

// 后端格式化结果
const backendFormatted = ref({
  current: '',
  fixed: '',
  tenDigit: ''
})

const timezones = ref(SUPPORTED_TIMEZONES)
let timer: NodeJS.Timeout | null = null

// 更新实时时间
const updateRealTime = () => {
  const now = Date.now()
  realTimeCurrent.value = formatWithTimezone(now)
  realTimeUtc.value = formatWithTimezone(now, 'YYYY-MM-DD HH:mm:ss')
  realTimeNy.value = formatWithTimezone(now, 'YYYY-MM-DD HH:mm:ss')
}

// 获取后端信息
const getBackendInfo = async () => {
  try {
    backendLoading.value = true
    const response = await getTimezoneInfo()
    if (response.data.code === 1) {
      backendInfo.value = response.data.data
    }
  } catch (error) {
    console.error('获取后端时区信息失败:', error)
    ElMessage.error('获取后端时区信息失败')
  } finally {
    backendLoading.value = false
  }
}

// 刷新后端信息
const refreshBackendInfo = () => {
  getBackendInfo()
}

// 重置为系统时区
const resetToSystem = () => {
  const systemTz = getSystemTimezone()
  setGlobalTimezone(systemTz)
  frontendTimezone.value = systemTz
  ElMessage.success('已重置为系统时区')
}

// 时间控件事件处理
const handleDateChange = (value: Date | '') => {
  console.log('日期选择器变化:', value)
}

const handleDateTimeChange = (value: Date | '') => {
  console.log('日期时间选择器变化:', value)
}

const handleDateRangeChange = (value: [Date, Date] | '') => {
  console.log('日期范围选择器变化:', value)
}

// 格式化日期范围
const formatDateRange = (range: [Date, Date] | '') => {
  if (!range || range === '' || !Array.isArray(range)) return ''
  return `${formatWithTimezone(range[0])} - ${formatWithTimezone(range[1])}`
}

// 测试时区转换
const testTimezoneConversion = async () => {
  try {
    let timestamp = parseInt(timezoneTestForm.value.timestamp) || Math.floor(Date.now() / 1000)
    const timezone = timezoneTestForm.value.timezone
    const format = timezoneTestForm.value.format
    
    // 如果时间戳大于 9999999999，说明是毫秒级，转换为秒级
    if (timestamp > 9999999999) {
      timestamp = Math.floor(timestamp / 1000)
    }
    
    // 前端转换（使用原始时间戳）
    const frontendTimestamp = parseInt(timezoneTestForm.value.timestamp) || Date.now()
    conversionResult.value.frontend = formatWithTimezone(frontendTimestamp, format)
    
    // 后端转换（使用秒级时间戳）
    const response = await testTimezoneFormat({
      timestamp,
      timezone,
      format: format.replace(/YYYY/g, 'Y').replace(/MM/g, 'm').replace(/DD/g, 'd').replace(/HH/g, 'H').replace(/mm/g, 'i').replace(/ss/g, 's')
    })
    
    if (response.data.code === 1) {
      conversionResult.value.backend = response.data.data.formatted
    }
  } catch (error) {
    console.error('时区转换测试失败:', error)
    ElMessage.error('时区转换测试失败')
  }
}

// 更新后端格式化结果
const updateBackendFormatted = async () => {
  try {
    const currentTimestamp = Math.floor(Date.now() / 1000) // 转换为秒级时间戳
    const fixedTimestamp = Math.floor(1704096000000 / 1000) // 转换为秒级时间戳
    const tenDigitTimestamp = Math.floor(Date.now() / 1000)
    
    // 获取当前时区的格式化结果
    const currentResponse = await testTimezoneFormat({
      timestamp: currentTimestamp,
      timezone: getCurrentTimezone(),
      format: 'Y-m-d H:i:s'
    })
    
    const fixedResponse = await testTimezoneFormat({
      timestamp: fixedTimestamp,
      timezone: getCurrentTimezone(),
      format: 'Y-m-d H:i:s'
    })
    
    const tenDigitResponse = await testTimezoneFormat({
      timestamp: tenDigitTimestamp,
      timezone: getCurrentTimezone(),
      format: 'Y-m-d H:i:s'
    })
    
    if (currentResponse.data.code === 1) {
      backendFormatted.value.current = currentResponse.data.data.formatted
    }
    if (fixedResponse.data.code === 1) {
      backendFormatted.value.fixed = fixedResponse.data.data.formatted
    }
    if (tenDigitResponse.data.code === 1) {
      backendFormatted.value.tenDigit = tenDigitResponse.data.data.formatted
    }
  } catch (error) {
    console.error('更新后端格式化结果失败:', error)
  }
}

// 调试时区信息
const debugTimezone = () => {
  // 调用 dayjs 调试函数
  debugTimezoneInfo()
  
  // 显示前端调试信息
  ElMessage.info(`
    前端时区信息已输出到控制台
    当前时区: ${frontendTimezone.value}
    系统配置时区: ${systemTimezone.value}
    浏览器时区: ${browserTimezone.value}
    时区插件状态: ${isTimezonePluginAvailable.value ? '可用' : '不可用'}
  `)
}

// 验证时区转换
const validateTimezone = () => {
  validateTimezoneConversion()
  ElMessage.success('时区转换验证信息已输出到控制台')
}

onMounted(async () => {
  // 初始化前端信息
  frontendTimezone.value = getCurrentTimezone()
  systemTimezone.value = getSystemTimezone()
  browserTimezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone
  isTimezonePluginAvailable.value = checkTimezonePluginAvailable()
  
  // 获取后端信息
  await getBackendInfo()
  await updateBackendFormatted()
  
  // 启动实时更新
  updateRealTime()
  timer = setInterval(updateRealTime, 1000)
})

onUnmounted(() => {
  if (timer) {
    clearInterval(timer)
  }
})
</script>

<style scoped>
.timezone-test {
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
  margin: 5px 0;
  font-family: monospace;
  font-size: 12px;
}

.el-form-item {
  margin-bottom: 15px;
}
</style> 