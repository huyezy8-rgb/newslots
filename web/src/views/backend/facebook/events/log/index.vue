<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh','comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('facebook.events.log.quick Search Fields') })"
        ></TableHeader>

        <!-- 趋势图（仿 Facebook 广告追踪） -->
        <div style="margin: 10px 0;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <el-button size="small" type="primary" @click="renderTrend">刷新走势图</el-button>
                <el-date-picker
                    v-model="selectedDate"
                    type="date"
                    placeholder="选择日期"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                    size="small"
                    @change="onDateChange"
                    style="width: 150px;"
                />
                <el-button size="small" @click="setToday">今天</el-button>
                <el-button size="small" @click="setYesterday">昨天</el-button>
                <el-select
                    v-model="selectedEventTypes"
                    multiple
                    placeholder="选择事件类型"
                    size="small"
                    @change="onEventTypeChange"
                    style="width: 200px;"
                    clearable
                >
                    <el-option
                        v-for="eventType in availableEventTypes"
                        :key="eventType"
                        :label="eventType"
                        :value="eventType"
                    />
                </el-select>
                <el-select
                    v-model="selectedChannels"
                    multiple
                    placeholder="选择渠道"
                    size="small"
                    @change="onChannelChange"
                    style="width: 180px;"
                    clearable
                >
                    <el-option
                        v-for="channel in availableChannels"
                        :key="channel"
                        :label="channel"
                        :value="channel"
                    />
                </el-select>
                <span style="color:#909399;font-size:12px;">按小时统计事件数量</span>
            </div>
            <div style="width:100%;height:320px;margin-top:8px;border:1px solid #ebeef5;border-radius:4px;position:relative;">
                <canvas ref="trendCanvas" style="width:100%;height:100%" @mousemove="onCanvasMouseMove" @mouseleave="onCanvasMouseLeave"></canvas>
                <!-- 悬停提示框 -->
                <div
                    ref="tooltipRef"
                    style="position:absolute;background:rgba(0,0,0,0.85);color:white;padding:12px 16px;border-radius:6px;font-size:13px;pointer-events:none;z-index:1000;display:none;box-shadow:0 4px 12px rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.1);backdrop-filter:blur(4px);transition:opacity 0.2s ease;min-width:200px;"
                >
                    <div style="font-weight:600;margin-bottom:6px;color:#409EFF;" ref="tooltipTime"></div>
                    <div ref="tooltipContent"></div>
                </div>
            </div>
        </div>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, provide, useTemplateRef, nextTick, watch, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'facebook/events/log',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const trendCanvas = useTemplateRef('trendCanvas')
const tooltipRef = useTemplateRef('tooltipRef')
const tooltipTime = useTemplateRef('tooltipTime')
const tooltipContent = useTemplateRef('tooltipContent')
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

// 筛选条件
const selectedDate = ref('')
const selectedEventTypes = ref<string[]>([])
const selectedChannels = ref<string[]>([])

// 可用选项
const availableEventTypes = ref<string[]>([])
const availableChannels = ref<string[]>([])

// 图表数据缓存
let chartData: { labels: string[], values: number[], buckets: Record<number, number> } = { labels: [], values: [], buckets: {} }
let canvasRect: DOMRect | null = null
let hoveredIndex: number = -1

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/facebook.events.Log/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('facebook.events.log.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('facebook.events.log.user_id'), prop: 'user_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            {
                label: t('facebook.events.log.channel_id'),
                prop: 'channel_id',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
            },
            {
                label: t('facebook.events.log.event_type'),
                prop: 'event_type',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            {
                label: t('facebook.events.log.event_name'),
                prop: 'event_name',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
            },
            { label: t('facebook.events.log.event_id'), prop: 'event_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' },
            {
                label: t('facebook.events.log.event_data'),
                prop: 'event_data',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: {},
            },
            {
                label: t('facebook.events.log.fb_pixel_id'),
                prop: 'fb_pixel_id',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
            },
            {
                label: t('facebook.events.log.status'),
                prop: 'status',
                align: 'center',
                render: 'tag',
                operator: 'eq',
                sortable: false,
                replaceValue: { pending: 'status pending', success: 'status success', failed: 'status failed' },
            },
            {
                label: t('facebook.events.log.fb_trace_id'),
                prop: 'fb_trace_id',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
            },
            {
                label: t('facebook.events.log.event_time'),
                prop: 'event_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,
            },
            {
                label: t('facebook.events.log.created_at'),
                prop: 'created_at',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: false,
                width: 160,
            },
            {
                label: t('facebook.events.log.updated_at'),
                prop: 'updated_at',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: false,
                width: 160,
            },

        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: { status: 'pending' },
    }
)

provide('baTable', baTable)

// 窗口大小变化监听器
let resizeObserver: ResizeObserver | null = null

onMounted(() => {
    // 初始化日期为今天
    setToday()

    baTable.table.ref = tableRef.value
    baTable.mount()
    baTable.getData()?.then(() => {
        baTable.initSort()
        baTable.dragSort()

        // 初始化可用选项
        initAvailableOptions()

        nextTick(() => {
            // 延迟渲染确保DOM完全加载
            setTimeout(() => renderTrend(), 200)
        })
    })

    // 监听Canvas容器大小变化
    const canvasContainer = trendCanvas.value?.parentElement
    if (canvasContainer && window.ResizeObserver) {
        resizeObserver = new ResizeObserver(() => {
            setTimeout(() => renderTrend(), 100)
        })
        resizeObserver.observe(canvasContainer)
    }
})

onUnmounted(() => {
    if (resizeObserver) {
        resizeObserver.disconnect()
    }
})

// 监听表格数据变化，只更新可用选项，不刷新图表
watch(() => baTable.table.data, () => {
    initAvailableOptions()
}, { deep: true })

// 日期处理函数
function setToday() {
    const today = new Date()
    selectedDate.value = today.toISOString().split('T')[0]
    renderTrend()
}

function setYesterday() {
    const yesterday = new Date()
    yesterday.setDate(yesterday.getDate() - 1)
    selectedDate.value = yesterday.toISOString().split('T')[0]
    renderTrend()
}

function onDateChange() {
    renderTrend()
}

function onEventTypeChange() {
    renderTrend()
}

function onChannelChange() {
    renderTrend()
}

// 初始化可用选项
function initAvailableOptions() {
    const allRows = baTable.table.data || []
    const eventTypes = new Set<string>()
    const channels = new Set<string>()

    allRows.forEach((row: any) => {
        if (row.event_type) eventTypes.add(row.event_type)
        if (row.channel) channels.add(row.channel)
    })

    availableEventTypes.value = Array.from(eventTypes).sort()
    availableChannels.value = Array.from(channels).sort()
}

// 鼠标事件处理
function onCanvasMouseMove(event: MouseEvent) {
    if (!chartData.labels.length || !canvasRect) return

    const canvas = trendCanvas.value as HTMLCanvasElement
    if (!canvas) return

    // 获取鼠标相对于canvas的位置
    const rect = canvas.getBoundingClientRect()
    const x = event.clientX - rect.left
    const y = event.clientY - rect.top

    // 计算图表区域
    const padding = { left: 48, right: 16, top: 16, bottom: 40 }
    const cssW = canvas.clientWidth
    const plotW = cssW - padding.left - padding.right

    // 检查是否在图表区域内
    if (x < padding.left || x > padding.left + plotW) {
        hideTooltip()
        return
    }

    // 计算最接近的数据点
    const dataIndex = Math.round(((x - padding.left) / plotW) * (chartData.labels.length - 1))
    const clampedIndex = Math.max(0, Math.min(dataIndex, chartData.labels.length - 1))

    // 如果悬停的数据点发生变化，重新渲染图表
    if (hoveredIndex !== clampedIndex) {
        hoveredIndex = clampedIndex
        renderTrend() // 重新渲染以显示高亮效果
    }

    // 显示提示框
    showTooltip(event.clientX, event.clientY, chartData.labels[clampedIndex], chartData.values[clampedIndex])
}

function onCanvasMouseLeave() {
    hideTooltip()
    hoveredIndex = -1
    renderTrend() // 重新渲染以移除高亮效果
}

function showTooltip(x: number, y: number, time: string, value: number) {
    const tooltip = tooltipRef.value as HTMLElement
    const timeEl = tooltipTime.value as HTMLElement
    const contentEl = tooltipContent.value as HTMLElement

    if (!tooltip || !timeEl || !contentEl) return

    timeEl.textContent = time

    // 获取当前时间段的事件类型统计
    const timeSlotEvents = getTimeSlotEventTypes(time)

    // 构建内容HTML
    let contentHtml = `<div style="color:#E6F7FF;margin-bottom:4px;">总事件数: ${value}</div>`

    if (timeSlotEvents.length > 0) {
        contentHtml += '<div style="border-top:1px solid rgba(255,255,255,0.2);padding-top:4px;margin-top:4px;">'
        timeSlotEvents.forEach(event => {
            contentHtml += `<div style="margin:2px 0;color:#E6F7FF;">• ${event.type}: ${event.count}</div>`
        })
        contentHtml += '</div>'
    }

    contentEl.innerHTML = contentHtml

    // 显示提示框
    tooltip.style.display = 'block'

    // 计算位置，避免超出屏幕
    const tooltipRect = tooltip.getBoundingClientRect()
    const viewportWidth = window.innerWidth
    const viewportHeight = window.innerHeight

    let left = x + 10
    let top = y - 10

    // 右边界检查
    if (left + tooltipRect.width > viewportWidth) {
        left = x - tooltipRect.width - 10
    }

    // 下边界检查
    if (top + tooltipRect.height > viewportHeight) {
        top = y - tooltipRect.height - 10
    }

    // 上边界检查
    if (top < 0) {
        top = y + 10
    }

    tooltip.style.left = left + 'px'
    tooltip.style.top = top + 'px'
}

// 获取指定时间段的事件类型统计
function getTimeSlotEventTypes(timeSlotLabel: string) {
    const allRows = baTable.table.data || []
    const targetDate = selectedDate.value || new Date().toISOString().split('T')[0]
    const targetDateObj = new Date(targetDate + 'T00:00:00')
    const nextDayObj = new Date(targetDateObj.getTime() + 24 * 60 * 60 * 1000)

    // 解析时间段（每小时）
    const [startTime, endTime] = timeSlotLabel.split('-')
    const startHour = parseInt(startTime.split(':')[0])
    const endHour = parseInt(endTime.split(':')[0])

    const timeSlotEvents: { type: string, count: number }[] = []
    const eventTypeCount: Record<string, number> = {}

    allRows.forEach((row: any) => {
        let ts = 0
        if (row.event_time) {
            ts = typeof row.event_time === 'string' ? new Date(row.event_time).getTime() / 1000 : Number(row.event_time)
        } else if (row.created_at) {
            ts = typeof row.created_at === 'string' ? new Date(row.created_at).getTime() / 1000 : Number(row.created_at)
        }

        if (!ts || ts <= 0) return

        const eventDate = new Date(ts * 1000)

        // 检查是否在目标日期和时间段内
        if (eventDate >= targetDateObj && eventDate < nextDayObj) {
            const hour = eventDate.getHours()

            // 检查是否在指定时间段内
            if (hour >= startHour && hour < endHour) {
                // 应用事件类型筛选
                if (selectedEventTypes.value.length > 0 && !selectedEventTypes.value.includes(row.event_type)) {
                    return
                }

                // 应用渠道筛选
                if (selectedChannels.value.length > 0 && !selectedChannels.value.includes(row.channel)) {
                    return
                }

                const eventType = row.event_type || '未知'
                eventTypeCount[eventType] = (eventTypeCount[eventType] || 0) + 1
            }
        }
    })

    // 转换为数组并排序
    Object.entries(eventTypeCount).forEach(([type, count]) => {
        timeSlotEvents.push({ type, count })
    })

    return timeSlotEvents.sort((a, b) => b.count - a.count)
}

function hideTooltip() {
    const tooltip = tooltipRef.value as HTMLElement
    if (tooltip) {
        tooltip.style.display = 'none'
    }
}

function renderTrend() {
    try {
        // 获取所有数据，不受表格筛选影响
        const allRows = baTable.table.data || []
        console.log('Facebook Events Log 全部数据:', allRows.length, '条记录')

        // 获取选中日期
        const targetDate = selectedDate.value || new Date().toISOString().split('T')[0]
        const targetDateObj = new Date(targetDate + 'T00:00:00')
        const nextDayObj = new Date(targetDateObj.getTime() + 24 * 60 * 60 * 1000)

        console.log('目标日期:', targetDate, '时间范围:', targetDateObj.toISOString(), '到', nextDayObj.toISOString())

        // 按小时统计事件（每小时一个时间段）
        const timeSlots = []
        for (let i = 0; i < 24; i++) {
            const startHour = String(i).padStart(2, '0')
            const endHour = String(i + 1).padStart(2, '0')
            timeSlots.push({
                start: i,
                end: i + 1,
                label: `${startHour}:00-${endHour}:00`
            })
        }

        const buckets: Record<number, number> = {}
        timeSlots.forEach((slot, index) => {
            buckets[index] = 0
        })

        for (const row of allRows) {
            // 尝试多种时间字段格式
            let ts = 0
            if (row.event_time) {
                ts = typeof row.event_time === 'string' ? new Date(row.event_time).getTime() / 1000 : Number(row.event_time)
            } else if (row.created_at) {
                ts = typeof row.created_at === 'string' ? new Date(row.created_at).getTime() / 1000 : Number(row.created_at)
            }

            if (!ts || ts <= 0) continue

            const eventDate = new Date(ts * 1000)

            // 检查是否在目标日期范围内
            if (eventDate >= targetDateObj && eventDate < nextDayObj) {
                // 应用事件类型筛选
                if (selectedEventTypes.value.length > 0 && !selectedEventTypes.value.includes(row.event_type)) {
                    continue
                }

                // 应用渠道筛选
                if (selectedChannels.value.length > 0 && !selectedChannels.value.includes(row.channel)) {
                    continue
                }

                const hour = eventDate.getHours()

                // 找到对应的时间段
                const slotIndex = timeSlots.findIndex(slot => hour >= slot.start && hour < slot.end)
                if (slotIndex !== -1) {
                    buckets[slotIndex] = (buckets[slotIndex] || 0) + 1
                }
            }
        }

        // 生成时间段标签和数值
        const labels: string[] = []
        const values: number[] = []
        timeSlots.forEach((slot, index) => {
            labels.push(slot.label)
            values.push(buckets[index] || 0)
        })

        // 保存图表数据供鼠标事件使用
        chartData = { labels, values, buckets }

        console.log('24小时聚合数据:', { labels, values, buckets })

        const canvas = trendCanvas.value as HTMLCanvasElement | null
        if (!canvas) {
            console.warn('Canvas 元素未找到')
            return
        }

        // 确保父容器有尺寸
        const parent = canvas.parentElement as HTMLElement
        if (!parent || parent.clientWidth === 0 || parent.clientHeight === 0) {
            console.warn('Canvas 父容器尺寸为0，延迟渲染')
            setTimeout(() => renderTrend(), 100)
            return
        }

        const cssW = parent.clientWidth
        const cssH = parent.clientHeight
        const dpr = window.devicePixelRatio || 1

            // 设置 Canvas 尺寸
            canvas.width = Math.floor(cssW * dpr)
            canvas.height = Math.floor(cssH * dpr)
            canvas.style.width = cssW + 'px'
            canvas.style.height = cssH + 'px'

            // 更新canvas位置信息供鼠标事件使用
            canvasRect = canvas.getBoundingClientRect()

            const ctx = canvas.getContext('2d')!
            if (!ctx) {
                console.warn('无法获取 Canvas 2D 上下文')
                return
            }

            ctx.scale(dpr, dpr)

        // 绘制背景
        ctx.clearRect(0, 0, cssW, cssH)
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, cssW, cssH)

        // 边距与坐标轴
        const padding = { left: 48, right: 16, top: 16, bottom: 40 }
        const plotW = Math.max(10, cssW - padding.left - padding.right)
        const plotH = Math.max(10, cssH - padding.top - padding.bottom)

        // 绘制边框
        ctx.strokeStyle = '#ebeef5'
        ctx.lineWidth = 1
        ctx.strokeRect(padding.left, padding.top, plotW, plotH)

        // 如果没有数据，显示提示
        if (labels.length === 0) {
            ctx.fillStyle = '#909399'
            ctx.font = '14px sans-serif'
            ctx.textAlign = 'center'
            ctx.textBaseline = 'middle'
            ctx.fillText('暂无数据', cssW / 2, cssH / 2)
            return
        }

        // 计算纵轴刻度
        const maxV = values.length ? Math.max(...values) : 0
        const niceMax = maxV <= 5 ? 5 : Math.ceil(maxV / 5) * 5
        const yTicks = 5

        // 绘制网格线和Y轴标签
        ctx.fillStyle = '#909399'
        ctx.font = '12px sans-serif'
        ctx.textAlign = 'right'
        ctx.textBaseline = 'middle'

        for (let i = 0; i <= yTicks; i++) {
            const y = padding.top + (plotH * i) / yTicks
            const val = Math.round(niceMax - (niceMax * i) / yTicks)

            // 网格线
            ctx.strokeStyle = '#f2f6fc'
            ctx.lineWidth = 1
            ctx.beginPath()
            ctx.moveTo(padding.left, y)
            ctx.lineTo(padding.left + plotW, y)
            ctx.stroke()

            // Y轴标签
            ctx.fillStyle = '#909399'
            ctx.fillText(String(val), padding.left - 6, y)
        }

        // 绘制折线和面积
        if (labels.length >= 1) {
            const xStep = labels.length > 1 ? plotW / (labels.length - 1) : 0
            const xAt = (idx: number) => padding.left + xStep * idx
            const yAt = (v: number) => padding.top + plotH * (1 - v / (niceMax || 1))

            // 区域渐变
            const grad = ctx.createLinearGradient(0, padding.top, 0, padding.top + plotH)
            grad.addColorStop(0, 'rgba(64,158,255,0.25)')
            grad.addColorStop(1, 'rgba(64,158,255,0.02)')

            // 填充面积
            ctx.beginPath()
            ctx.moveTo(xAt(0), yAt(values[0] || 0))
            for (let i = 1; i < labels.length; i++) {
                ctx.lineTo(xAt(i), yAt(values[i] || 0))
            }
            ctx.lineTo(xAt(labels.length - 1), padding.top + plotH)
            ctx.lineTo(xAt(0), padding.top + plotH)
            ctx.closePath()
            ctx.fillStyle = grad
            ctx.fill()

            // 折线
            ctx.beginPath()
            ctx.moveTo(xAt(0), yAt(values[0] || 0))
            for (let i = 1; i < labels.length; i++) {
                ctx.lineTo(xAt(i), yAt(values[i] || 0))
            }
            ctx.strokeStyle = '#409EFF'
            ctx.lineWidth = 2
            ctx.stroke()

                // 圆点
                for (let i = 0; i < labels.length; i++) {
                    const x = xAt(i)
                    const y = yAt(values[i] || 0)

                    // 高亮当前悬停的点
                    if (i === hoveredIndex) {
                        // 外圈高亮
                        ctx.fillStyle = '#409EFF'
                        ctx.beginPath()
                        ctx.arc(x, y, 6, 0, Math.PI * 2)
                        ctx.fill()

                        // 内圈白色
                        ctx.fillStyle = '#ffffff'
                        ctx.beginPath()
                        ctx.arc(x, y, 4, 0, Math.PI * 2)
                        ctx.fill()

                        // 中心点
                        ctx.fillStyle = '#409EFF'
                        ctx.beginPath()
                        ctx.arc(x, y, 2, 0, Math.PI * 2)
                        ctx.fill()
                    } else {
                        // 普通圆点
                        ctx.fillStyle = '#409EFF'
                        ctx.beginPath()
                        ctx.arc(x, y, 3, 0, Math.PI * 2)
                        ctx.fill()
                    }
                }
        }

        // X 轴标签（每小时显示，每4小时显示一个标签避免重叠）
        ctx.fillStyle = '#909399'
        ctx.font = '12px sans-serif'
        ctx.textAlign = 'center'
        ctx.textBaseline = 'top'

        // 每4小时显示一个标签：00:00, 04:00, 08:00, 12:00, 16:00, 20:00
        const labelStep = 4
        for (let i = 0; i < labels.length; i += labelStep) {
            const x = padding.left + (plotW * i) / Math.max(1, labels.length - 1)
            ctx.fillText(labels[i], x, padding.top + plotH + 8)
        }

        // 添加日期标题
        ctx.fillStyle = '#303133'
        ctx.font = '14px sans-serif'
        ctx.textAlign = 'center'
        ctx.textBaseline = 'top'
        ctx.fillText(`${targetDate} 事件趋势 (按小时统计)`, cssW / 2, 8)

        console.log('走势图渲染完成')
    } catch (e) {
        console.error('走势图渲染异常:', e)
    }
}
</script>

<style scoped lang="scss"></style>
