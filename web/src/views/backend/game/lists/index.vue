<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <TableHeader
            :buttons="['refresh', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('game.lists.quick Search Fields') })"
        >
            <template #default>
                <!-- 动态筛选按钮组 -->
                <el-button-group class="mr-3">

                    <el-button
                        v-for="brand in brandOptions"
                        :key="brand.id"
                        :type="(brand.name === 'HOT' ? activeFilters.hot : activeFilters.brand === brand.name) ? 'primary' : ''"
                        @click="toggleFilter('brand', brand.name)"
                    >
                        {{ brand.name }}
                    </el-button>
                    <el-button
                        :type="!activeFilters.brand && !activeFilters.hot ? 'primary' : ''"
                        @click="resetFilters()"
                    >
                        全部
                    </el-button>
                </el-button-group>

                <el-button type="primary" :loading="updateLoading" @click="handleUpdateGameList">
                    <el-icon><Refresh /></el-icon>
                    {{ t('game.lists.updateList') }}
                </el-button>
            </template>
        </TableHeader>

        <!-- 表格 -->
        <Table ref="tableRef" :dblClickNotEdit="true" />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, useTemplateRef, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Refresh } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'

import { baTableApi } from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({ name: 'game/lists' })

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const updateLoading = ref(false)

// 新增筛选状态管理
const activeFilters = ref({
    hot: false,
    brand: ''
})

// 品牌选项
const brandOptions = ref<Array<{id: number, name: string, icon: string, sort: number, status: number}>>([])

// 切换筛选状态
const toggleFilter = (type: string, value: string) => {
    if (type === 'hot') {
        activeFilters.value.hot = !activeFilters.value.hot
    } else {
        // 如果选择的是HOT品牌，则设置hot筛选
        if (value === 'HOT') {
            activeFilters.value.hot = !activeFilters.value.hot
            activeFilters.value.brand = ''
        } else {
            activeFilters.value.brand = activeFilters.value.brand === value ? '' : value
            activeFilters.value.hot = false
        }
    }
    applyFilters()
}

// 重置所有筛选
const resetFilters = () => {
    activeFilters.value = {
        hot: false,
        brand: ''
    }
    applyFilters()
}

// 应用筛选条件
const applyFilters = () => {
    // 清除现有的筛选条件
    baTable.table.filter = {}

    // 构建搜索条件数组
    const searchConditions = []

    // 热门筛选
    if (activeFilters.value.hot) {
        searchConditions.push({
            field: 'hot',
            val: 1,
            operator: 'eq'
        })
    }

    // 品牌筛选
    if (activeFilters.value.brand) {
        searchConditions.push({
            field: 'brand',
            val: activeFilters.value.brand.toLowerCase(),
            operator: 'eq'
        })
    }

    // 设置筛选参数
    if (searchConditions.length > 0) {
        baTable.table.filter = {
            search: searchConditions
        }
    }

    // 调试信息
    console.log('筛选条件:', activeFilters.value)
    console.log('搜索参数:', baTable.table.filter)

    // 重新获取数据
    baTable.getData()
}

// 表格配置
const tableConfig = {
    pk: 'id',
    column: [
        { type: 'selection', align: 'center', operator: false },
        { label: t('game.lists.id'), prop: 'id', align: 'center', width: 70,  sortable: 'custom' },
        { label: t('game.lists.game_id'), prop: 'game_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' as const },
        { label: t('game.lists.game_name'), prop: 'game_name', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' as const },
        { label: t('game.lists.game_name_en'), prop: 'game_name_en', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' as const },
        {
            label: t('game.lists.icon'),
            prop: 'icon',
            align: 'center',
            render: 'image' as const,
            operator: false,
            width: 100,
            imageStyle: {
                width: '50px',
                height: '50px',
                'object-fit': 'contain'
            }
        },
        {
            label: t('game.lists.brand'),
            prop: 'brand',
            align: 'center',
            operatorPlaceholder: t('Fuzzy query'),
            operator: 'LIKE' as const,
        },
        { label: t('game.lists.source'), prop: 'source', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE' as const },
        {
            label: t('game.lists.sort'),
            prop: 'sort',
            align: 'center',
            width: 100,
            render: 'input' as const,
            sortable: 'custom',
            operatorPlaceholder: "请输入排序值",
            edit: {
                type: 'number',
                min: 0,
                max: 999999,
                step: 1,
                precision: 0,
                placeholder: '请输入排序值'
            }
        },
        {
            label: t('game.lists.hot'),
            prop: 'hot',
            align: 'center',
            render: 'switch' as const,
            operatorPlaceholder: "请选择",
            switch: {
                activeValue: 1,
                inactiveValue: 0,
                activeText: t('Enabled'),
                inactiveText: t('Disabled')
            }
        },
        {
            label: t('game.lists.fs'),
            prop: 'fs',
            align: 'center',
            operatorPlaceholder: "请选择",
            render: 'switch' as const,
            switch: {
                activeValue: 1,
                inactiveValue: 0,
                activeText: t('Enabled'),
                inactiveText: t('Disabled')
            }
        },
        {
            label: t('game.lists.status'),
            prop: 'status',
            align: 'center',
            operatorPlaceholder: "请选择",
            render: 'switch' as const,
            switch: {
                activeValue: 1,
                inactiveValue: 0,
                activeText: t('Enabled'),
                inactiveText: t('Disabled')
            }
        },
        {
            label: t('game.lists.time_info'),
            prop: 'time_info',
            align: 'center',
            width: 180,
            operator: false,
            render: 'timeInfo' as const,
            timeFormat: 'yyyy-MM-dd HH:mm:ss',
            custom: {
                createTimeLabel: t('game.lists.create_time'),
                updateTimeLabel: t('game.lists.update_time')
            }
        }
    ],
    dblClickNotEditColumn: ['*'],
    defaultItems: {}
}

// baTable 实例
const baTable = new baTableClass(
    new baTableApi('/admin/game.lists/'),
    tableConfig,
    { defaultItems: {} }
)

provide('baTable', baTable)

/**
 * 更新游戏列表
 */
const handleUpdateGameList = async () => {
    try {
        await ElMessageBox.confirm(
            t('game.lists.updateConfirm'),
            t('game.lists.updateTitle'),
            {
                type: 'warning',
                confirmButtonText: t('Confirm'),
                cancelButtonText: t('Cancel'),
            }
        )
        updateLoading.value = true
        const res = await baTable.api.postData('updateGameList', {})
        if (res.code === 1) {
            ElMessage.success(res.msg || t('Update successful'))
            await baTable.getData()
            // 更新后重新加载品牌选项
            await loadBrandOptions()
        } else {
            ElMessage.error(res.msg || t('Update failed'))
        }
    } catch (error) {
        if (error !== 'cancel') {
            console.error('Update failed:', error)
            ElMessage.error(t('Update failed'))
        }
    } finally {
        updateLoading.value = false
    }
}

// 获取品牌选项
const loadBrandOptions = async () => {
    try {
        const res = await baTable.api.postData('getBrands', {})
        if (res.code === 1 && res.data) {
            brandOptions.value = res.data
        }
    } catch (error) {
        console.error('Failed to load brand options:', error)
    }
}

// 初始化挂载
onMounted(async () => {
    baTable.table.ref = tableRef.value
    baTable.mount()
    await baTable.getData()
    baTable.initSort()
    baTable.dragSort()
    await loadBrandOptions()
})
</script>

<style scoped lang="scss">
.ba-table-box {
    :deep(.el-button-group) {
        margin-right: 12px;

        .el-button {
            border-radius: 4px !important;
            margin-right: 0;

            &:not(:last-child) {
                border-right: 1px solid var(--el-border-color);
            }
        }
    }

    :deep(.el-button) {
        .el-icon {
            margin-right: 4px;
        }
    }
}
</style>
