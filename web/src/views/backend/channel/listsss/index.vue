<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('channel.listsss.quick Search Fields') })"
        ></TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
        <PopupForm />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, useTemplateRef, h } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'

defineOptions({
    name: 'channel/listsss',
})

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/channel.Listsss/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false, minWidth: 50 },
            { label: t('channel.listsss.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom', minWidth: 80 },
            {
                label: t('channel.listsss.name'),
                prop: 'name',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 120
            },
            {
                label: t('channel.listsss.domain'),
                prop: 'domain',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 160
            },
            {
                label: t('channel.listsss.theme'),
                prop: 'theme',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                render: 'color',
                operator: 'LIKE',
                sortable: false,
                width: 70,
                minWidth: 100
            },
            {
                label: '渠道logo',
                prop: 'logo',
                align: 'center',
                render: 'image',
                operator: false,
                minWidth: 100
            },
            {
                label: '桌面logo',
                prop: 'pwa_logo',
                align: 'center',
                render: 'image',
                operator: false,
                minWidth: 100
            },
            {
                label: 'PWA链接',
                prop: 'pwa_link',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 160
            },
            {
                label: 'favicon图标',
                prop: 'favicon',
                align: 'center',
                render: 'image',
                operator: false,
                minWidth: 100
            },
            { label: t('channel.listsss.experience_gold_limit'), prop: 'experience_gold_limit', align: 'center', operator: 'RANGE', sortable: false },
            {
                label: t('channel.listsss.facebook_pixel_id'),
                prop: 'facebook_pixel_id',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 160
            },
            // {
            //     label: t('channel.listsss.facebook_token'),
            //     prop: 'facebook_token',
            //     align: 'center',
            //     operatorPlaceholder: t('Fuzzy query'),
            //     operator: 'LIKE',
            //     sortable: false,
            //     minWidth: 160
            // },
            {
                label: t('channel.listsss.vip_kefu_account'),
                prop: 'vip_kefu_account',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 160
            },
            {
                label: t('channel.listsss.robot_kefu_account'),
                prop: 'robot_kefu_account',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 160
            },
            {
                label: t('channel.listsss.manual_kefu_account'),
                prop: 'manual_kefu_account',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 160
            },
            {
                label: t('channel.listsss.kefu_channel'),
                prop: 'kefu_channel',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 160
            },
            {
                label: t('channel.listsss.kefu_channel_url'),
                prop: 'kefu_channel_url',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 160
            },
            {
                label: t('channel.listsss.messenger_url'),
                prop: 'messenger_url',
                align: 'center',
                operatorPlaceholder: t('Fuzzy query'),
                operator: 'LIKE',
                sortable: false,
                minWidth: 180
            },
            // {
            //     label: "活动",
            //     prop: 'activity_list',
            //     align: 'center',
            //     operator: false,
            //     render: "tags",
            //     minWidth: 120
            // },
            {
                label: t('channel.listsss.create_time'),
                prop: 'create_time',
                align: 'center',
                render: 'datetime',
                operator: 'RANGE',
                sortable: 'custom',
                width: 160,

                minWidth: 160
            },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false, minWidth: 100 },
        ],
        dblClickNotEditColumn: [undefined],
    },
    {
        defaultItems: {},
    }
)

provide('baTable', baTable)

onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.mount()
    baTable.getData()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
    })
})
</script>

<style scoped lang="scss">
.el-table th .cell {
  white-space: normal !important;
  word-break: break-all;
  line-height: 1.2;
  overflow: visible !important;
  text-overflow: unset !important;
  font-size: 13px;
}
.el-table th {
  height: auto !important;
}
</style>
