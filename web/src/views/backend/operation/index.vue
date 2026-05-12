<template>
    <div class="default-main">
        <!-- 筛选区 -->
        <el-form :inline="true" :model="filterData" class="filter-panel">
            <el-form-item label="日期">
                <el-date-picker
                    v-model="filterData.dateRange"
                    type="daterange"
                    range-separator="-"
                    start-placeholder="Start Date"
                    end-placeholder="End Date"
                    value-format="YYYY-MM-DD"
                    style="width: 260px"
                />
            </el-form-item>
            <el-form-item label="渠道" v-if="adminInfo.isAdminChannelId == null">
                <el-select v-model="filterData.channel_id" placeholder="请选择渠道" clearable style="width: 180px">
                    <el-option v-for="item in channelList" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
            </el-form-item>
            <el-form-item>
                <el-button type="primary" @click="fetchData">查询</el-button>
                <el-button @click="resetFilter">重置</el-button>
                <el-button type="success" @click="exportData">导出</el-button>
            </el-form-item>
        </el-form>

        <!-- 今日数据更新时间 -->
        <div v-if="todayUpdateTime" style="margin-bottom: 10px; color: #409EFF;">
            今日数据更新时间：{{ todayUpdateTime }}
        </div>

        <!-- 数据表格 -->
        <el-table
            :data="tableData"
            border
            style="width: 100%; min-width: 1800px; margin-bottom: 20px;"
            :header-cell-style="{ background: '#f5f7fa', fontWeight: 'bold', borderRight: '2px solid #dcdfe6' }"
            :span-method="spanMethod"
            v-loading="loading"
            :scroll-x="true"
            class="operation-table"
        >
            <template v-for="col in columns" :key="col.prop || col.label">
                <!-- 没有子列的普通列 -->
                <el-table-column
                    v-if="!col.children"
                    :prop="col.prop"
                    :label="col.label"
                    :fixed="col.fixed"
                    :width="col.width"
                    :min-width="col.minWidth || 120"
                    :formatter="col.formatter ? (row, column, cellValue) => formatCell(cellValue, col.formatter) : undefined"
                />

                <!-- 有子列的分组列 -->
                <el-table-column
                    v-else
                    :label="col.label"
                    :header-align="col.headerAlign || 'center'"
                >
                    <template v-for="child in col.children" :key="child.prop || child.label">
                        <!-- 子列没有children，是普通列 -->
                        <el-table-column
                            v-if="!child.children"
                            :prop="child.prop"
                            :label="child.label"
                            :width="child.width"
                            :min-width="child.minWidth || 120"
                            :header-align="child.headerAlign || 'center'"
                            :formatter="child.formatter ? (row, column, cellValue) => formatCell(cellValue, child.formatter) : undefined"
                        />

                        <!-- 子列有children，是三级分组 -->
                        <el-table-column
                            v-else
                            :label="child.label"
                            :header-align="child.headerAlign || 'center'"
                        >
                            <el-table-column
                                v-for="grandChild in child.children"
                                :key="grandChild.prop"
                                :prop="grandChild.prop"
                                :label="grandChild.label"
                                :width="grandChild.width"
                                :min-width="grandChild.minWidth || 120"
                                :header-align="grandChild.headerAlign || 'center'"
                                :formatter="grandChild.formatter ? (row, column, cellValue) => formatCell(cellValue, grandChild.formatter) : undefined"
                            />
                        </el-table-column>
                    </template>
                </el-table-column>
            </template>
        </el-table>

        <!-- 分页 -->
        <el-pagination
            v-if="pagination.total > 0"
            background
            layout="total, sizes, prev, pager, next, jumper"
            :total="pagination.total"
            :page-size="pagination.pageSize"
            :current-page="pagination.page"
            @size-change="handleSizeChange"
            @current-change="handlePageChange"
            style="margin-top: 10px;"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { index as fetchOperationData, getChannels } from '/@/api/backend/operation'
import { useI18n } from 'vue-i18n'
import createAxios from '/@/utils/axios'
import { useAdminInfo } from '/@/stores/adminInfo'
import dayjs from 'dayjs'
import { computed } from 'vue'

const { t } = useI18n()

const filterData = reactive({
    dateRange: [],
    channel_id: undefined,
    tag: undefined,
    page: 1,
    pageSize: 15,
})

const channelList = ref<{id: number|string, name: string}[]>([])
const tagList = ref<Array<{ value: string; label: string }>>([]) // 预留
const columns = ref<any[]>([])
const tableData = ref<any[]>([])
const pagination = reactive({ total: 0, page: 1, pageSize: 15 })
const loading = ref(false)

const adminInfo = useAdminInfo()

const fetchChannels = async () => {
    const res = await createAxios({ url: '/admin/channel.listsss/all', method: 'get' });
    if (res.code === 1 && Array.isArray(res.data)) {
        channelList.value = res.data;
    }
};

function fetchData() {
    loading.value = true

    // 调试：打印传递的参数
    console.log('传递的参数:', {
        dateRange: filterData.dateRange,
        channel_id: filterData.channel_id,
        tag: filterData.tag,
        page: pagination.page,
        pageSize: pagination.pageSize,
    })

    fetchOperationData({
        dateRange: filterData.dateRange,
        channel_id: filterData.channel_id,
        tag: filterData.tag,
        page: pagination.page,
        pageSize: pagination.pageSize,
    }).then(res => {
        columns.value = res.data.columns
        tableData.value = res.data.list
        pagination.total = res.data.pagination.total
        pagination.page = res.data.pagination.page
        pagination.pageSize = res.data.pagination.pageSize

        // 调试：打印后端返回的调试信息
        if (res.data.debug) {
            console.log('后端调试信息:', res.data.debug)
        }

        // 调试：打印columns结构
        console.log('表头结构:', JSON.stringify(columns.value, null, 2))
    }).finally(() => {
        loading.value = false
    })
}

function resetFilter() {
    filterData.dateRange = []
    filterData.channel_id = undefined
    filterData.tag = undefined
    pagination.page = 1
    fetchData()
}

function handleSizeChange(size: number) {
    pagination.pageSize = size
    pagination.page = 1
    fetchData()
}
function handlePageChange(page: number) {
    pagination.page = page
    fetchData()
}

function exportData() {
    // TODO: 实现导出功能，可调用后端导出接口或前端导出csv
    alert('TODO: 实现导出功能')
}

function formatCell(val: any, type: string) {
    if (type === 'percent') {
        return (val * 100).toFixed(2) + '%'
    }
    return val
}

// 合并表头（如有需要）
function spanMethod({ row, column, rowIndex, columnIndex }: any) {
    // 可根据需要自定义合并逻辑
    return [1, 1]
}

// 计算今日字符串
const todayStr = dayjs().format('YYYY-MM-DD')

// 只取今日数据
const todayRow = computed(() => tableData.value.find(row => row.date === todayStr))

// 今日更新时间
const todayUpdateTime = computed(() => {
  if (!todayRow.value) return ''
  if (todayRow.value.today_update_time) return todayRow.value.today_update_time
  if (todayRow.value.update_time) return dayjs(todayRow.value.update_time * 1000).format('YYYY-MM-DD HH:mm:ss')
  return ''
})

onMounted(() => {
    // 获取渠道列表
    fetchChannels()
    // 获取运营数据
    fetchData()
})
</script>

<style scoped lang="scss">
.filter-panel {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px 20px 0 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.05);
}

.operation-table {
    :deep(.el-table__header) {
        .el-table__cell {
            border-right: 2px solid #dcdfe6 !important;
            border-bottom: 2px solid #dcdfe6 !important;

            // 最后一列不显示右边框
            &:last-child {
                border-right: none !important;
            }
        }

        // 主表头行（第一行）- 所有用户、新用户、老用户
        .el-table__header-row:first-child {
            .el-table__cell {
                border-bottom: 3px solid #c0c4cc !important;
                font-weight: bold;
                font-size: 14px;
                background-color: #f0f2f5 !important;
            }
        }

        // 子表头行（第二行）- 下单、返奖等分组
        .el-table__header-row:nth-child(2) {
            .el-table__cell {
                border-bottom: 2px solid #dcdfe6 !important;
                font-weight: 600;
                font-size: 13px;
                background-color: #f5f7fa !important;
            }
        }

        // 三级表头行（第三行）- 笔数、人数、总金额等具体字段
        .el-table__header-row:last-child {
            .el-table__cell {
                border-bottom: 2px solid #dcdfe6 !important;
                font-weight: 500;
                font-size: 12px;
                background-color: #fafafa !important;
            }
        }
    }

    :deep(.el-table__body) {
        .el-table__cell {
            border-right: 1px solid #ebeef5;
            border-bottom: 1px solid #ebeef5;

            // 最后一列不显示右边框
            &:last-child {
                border-right: none !important;
            }
        }
    }

    // 固定列的分界线
    :deep(.el-table__fixed) {
        .el-table__cell {
            border-right: 2px solid #dcdfe6 !important;
        }
    }

    :deep(.el-table__fixed-right) {
        .el-table__cell {
            border-left: 2px solid #dcdfe6 !important;
        }
    }

    // 表头分组样式优化
    :deep(.el-table__header-wrapper) {
        .el-table__header {
            th {
                &.el-table__cell {
                    // 主分组表头
                    &.is-leaf {
                        background-color: #f0f2f5 !important;
                        font-weight: bold;
                        color: #303133;
                    }

                    // 子分组表头
                    &:not(.is-leaf) {
                        background-color: #f5f7fa !important;
                        font-weight: 600;
                        color: #606266;
                    }
                }
            }
        }
    }
}
</style>
