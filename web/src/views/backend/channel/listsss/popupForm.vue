<template>
    <!-- 对话框表单 -->
    <!-- 建议使用 Prettier 格式化代码 -->
    <!-- el-form 内可以混用 el-form-item、FormItem、ba-input 等输入组件 -->
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        width="50%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate ? t(baTable.form.operate) : '' }}
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div
                class="ba-operate-form"
                :class="'ba-' + baTable.form.operate + '-form'"
                :style="config.layout.shrink ? '' : 'width: calc(100% - ' + baTable.form.labelWidth! / 2 + 'px)'"
            >
                <el-form
                    v-if="!baTable.form.loading"
                    ref="formRef"
                    @submit.prevent=""
                    @keyup.enter="baTable.onSubmit(formRef)"
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >
                    <FormItem
                        :label="t('channel.listsss.name')"
                        type="string"
                        v-model="baTable.form.items!.name"
                        prop="name"
                        :placeholder="t('Please input field', { field: t('channel.listsss.name') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.domain')"
                        type="string"
                        v-model="baTable.form.items!.domain"
                        prop="domain"
                        :placeholder="t('Please input field', { field: t('channel.listsss.domain') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.theme')"
                        type="color"
                        v-model="baTable.form.items!.theme"
                        prop="theme"
                        :placeholder="t('Please input field', { field: t('channel.listsss.theme') })"
                    />
                    <FormItem
                        :label="'渠道logo'"
                        type="image"
                        v-model="baTable.form.items!.logo"
                        prop="logo"
                        :input-attr="{ limit: 1 }"
                    />
                    <FormItem
                        :label="'桌面logo'"
                        type="image"
                        v-model="baTable.form.items!.pwa_logo"
                        prop="pwa_logo"
                        :input-attr="{ limit: 1 }"
                    />
                    <FormItem
                        :label="'PWA链接'"
                        type="string"
                        v-model="baTable.form.items!.pwa_link"
                        prop="pwa_link"
                        :placeholder="'可为空，示例：https://example.com/pwa'"
                    />
                    <FormItem
                        :label="'favicon图标'"
                        type="image"
                        v-model="baTable.form.items!.favicon"
                        prop="favicon"
                        :input-attr="{
                            limit: 1,
                            accept: 'image/*,.ico',
                        }"
                        tip="支持 ICO、PNG、JPG 格式，建议尺寸 16x16、32x32 或 64x64 像素"
                    />
                    <FormItem
                        :label="t('channel.listsss.facebook_pixel_id')"
                        type="string"
                        v-model="baTable.form.items!.facebook_pixel_id"
                        prop="facebook_pixel_id"
                        :placeholder="t('Please input field', { field: t('channel.listsss.facebook_pixel_id') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.facebook_token')"
                        type="string"
                        v-model="baTable.form.items!.facebook_token"
                        prop="facebook_token"
                        :placeholder="t('Please input field', { field: t('channel.listsss.facebook_token') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.vip_kefu_account')"
                        type="string"
                        v-model="baTable.form.items!.vip_kefu_account"
                        prop="vip_kefu_account"
                        :placeholder="t('Please input field', { field: t('channel.listsss.vip_kefu_account') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.robot_kefu_account')"
                        type="string"
                        v-model="baTable.form.items!.robot_kefu_account"
                        prop="robot_kefu_account"
                        :placeholder="t('Please input field', { field: t('channel.listsss.robot_kefu_account') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.manual_kefu_account')"
                        type="string"
                        v-model="baTable.form.items!.manual_kefu_account"
                        prop="manual_kefu_account"
                        :placeholder="t('Please input field', { field: t('channel.listsss.manual_kefu_account') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.kefu_channel')"
                        type="string"
                        v-model="baTable.form.items!.kefu_channel"
                        prop="kefu_channel"
                        :placeholder="t('Please input field', { field: t('channel.listsss.kefu_channel') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.kefu_channel_url')"
                        type="string"
                        v-model="baTable.form.items!.kefu_channel_url"
                        prop="kefu_channel_url"
                        :placeholder="t('Please input field', { field: t('channel.listsss.kefu_channel_url') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.messenger_url')"
                        type="string"
                        v-model="baTable.form.items!.messenger_url"
                        prop="messenger_url"
                        :placeholder="'https://m.me/your_page 或 Messenger 跳转链接'"
                    />
                    <!-- 语言 / 货币 / 时区 新增字段 -->
                    <FormItem
                        :label="t('channel.listsss.lang')"
                        type="select"
                        v-model="baTable.form.items!.lang"
                        prop="lang"
                        :input-attr="{ content: langOptions }"
                        :placeholder="t('Please select field', { field: t('channel.listsss.lang') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.currency_code')"
                        type="select"
                        v-model="baTable.form.items!.currency_code"
                        prop="currency_code"
                        :input-attr="{ content: currencyCodeOptions }"
                        :placeholder="t('Please select field', { field: t('channel.listsss.currency_code') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.currency_symbol')"
                        type="string"
                        v-model="baTable.form.items!.currency_symbol"
                        prop="currency_symbol"
                        :placeholder="t('Please input field', { field: t('channel.listsss.currency_symbol') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.time_zone')"
                        type="select"
                        v-model="baTable.form.items!.time_zone"
                        prop="time_zone"
                        :input-attr="{ content: timeZoneOptions }"
                        :placeholder="t('Please select field', { field: t('channel.listsss.time_zone') })"
                    />
                    <FormItem
                        :label="t('channel.listsss.experience_gold_limit')"
                        type="number"
                        v-model="baTable.form.items!.experience_gold_limit"
                        prop="experience_gold_limit"
                        :input-attr="{ step: 1 }"
                        :placeholder="t('Please input field', { field: t('channel.listsss.experience_gold_limit') })"
                    />
                    <!-- 渠道活动全量开关（基于 available_activities） -->
                    <el-divider>活动设置</el-divider>
                    <el-card class="activity-section" shadow="never" body-style="padding: 12px;">
                        <div v-if="homePopupOrderItems.length" class="home-popup-sort">
                            <div class="home-popup-sort-header">
                                <span>首页弹窗设置</span>
                            </div>
                            <div ref="homePopupSortRef" class="home-popup-sort-list">
                                <div
                                    v-for="(it, index) in homePopupOrderItems"
                                    :key="getActivityKey(it)"
                                    class="home-popup-sort-item"
                                    :class="{ disabled: !activityConfigs[getActivityKey(it)]?.popup_enabled_home }"
                                    :data-key="getActivityKey(it)"
                                >
                                    <div class="home-popup-sort-drag-area" title="拖动排序">
                                        <el-icon class="drag-handle"><Rank /></el-icon>
                                        <span class="order">{{ getHomePopupOrderLabel(getActivityKey(it), index) }}</span>
                                        <div class="content">
                                            <span class="name">{{ activityOptions[getActivityKey(it)] || getActivityKey(it) }}</span>
                                            <el-tag size="small" type="info">{{ getActivityKey(it) }}</el-tag>
                                        </div>
                                    </div>
                                    <div class="popup-switch">
                                        <el-switch v-model="activityConfigs[getActivityKey(it)].popup_enabled_home" active-text="开" inactive-text="关" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="filteredActivities.length">
                            <div v-for="it in filteredActivities" :key="getActivityKey(it)" class="activity-item">
                                <div class="activity-header">
                                    <div class="title">
                                        <strong>{{ activityOptions[getActivityKey(it)] || getActivityKey(it) }}</strong>
                                        <el-tag size="small" type="info">{{ getActivityKey(it) }}</el-tag>
                                    </div>
                                    <el-button text size="small" @click="resetOne(getActivityKey(it), it)">重置</el-button>
                                </div>
                                <div class="activity-field">
                                    <span class="label">启用</span>
                                    <el-switch v-model="activityConfigs[getActivityKey(it)].enabled" :active-text="t('Enable')" :inactive-text="t('Disable')" />
                                </div>
                                <!-- 充值页弹窗 控制组 -->
                                <template v-if="isPopupRechargeSupported(it)">
                                    <div class="activity-field">
                                        <span class="label">充值弹窗</span>
                                        <el-switch v-model="activityConfigs[getActivityKey(it)].popup_enabled_recharge" active-text="Popup" inactive-text="No Popup" />
                                    </div>
                                    <div class="activity-field">
                                        <span class="label">充值弹窗顺序</span>
                                        <el-input-number v-model="activityConfigs[getActivityKey(it)].popup_order_recharge" :min="0" :max="999" :step="1" controls-position="right" style="width: 140px;" />
                                        <el-tooltip content="弹窗显示顺序：0=没有顺序，1-999按数字从小到大排列，0可以重复，其他数字不能重复" placement="top">
                                            <el-icon style="margin-left: 4px; color: var(--el-text-color-secondary); cursor: help;"><QuestionFilled /></el-icon>
                                        </el-tooltip>
                                    </div>
                                </template>
                                
                                <!-- 打码倍率 控制组 -->
                                <template v-if="isBetMultiplierSupported(it)">
                                    <div class="activity-field">
                                        <span class="label">打码倍率</span>
                                        <el-input-number v-model="activityConfigs[getActivityKey(it)].bet_multiplier" :min="1" :step="1" controls-position="right" style="width: 140px;" />
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div v-else style="color: var(--el-text-color-secondary);">暂无可配置活动</div>
                    </el-card>
                  
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="baTable.onSubmit(formRef)"
                           type="primary">
                    {{
                        baTable.form.operateIds && baTable.form.operateIds.length > 1 ? t('Save and edit next item') : t('Save')
                    }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type {FormItemRule} from 'element-plus'
import Sortable, { type SortableEvent } from 'sortablejs'
import {inject, reactive, useTemplateRef, onMounted, onBeforeUnmount, ref, watch, computed, nextTick} from 'vue'
import {useI18n} from 'vue-i18n'
import FormItem from '/@/components/formItem/index.vue'
import {useConfig} from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import {buildValidatorData} from '/@/utils/validate'
import request from '/@/utils/axios'
import { QuestionFilled, Rank } from '@element-plus/icons-vue'

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass

const {t} = useI18n()

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    name: [buildValidatorData({ name: 'required', title: t('channel.listsss.name') })],
    domain: [buildValidatorData({ name: 'required', title: t('channel.listsss.domain') })],
    lang: [buildValidatorData({ name: 'required', title: t('channel.listsss.lang') })],
    currency_code: [buildValidatorData({ name: 'required', title: t('channel.listsss.currency_code') })],
    currency_symbol: [buildValidatorData({ name: 'required', title: t('channel.listsss.currency_symbol') })],
    time_zone: [buildValidatorData({ name: 'required', title: t('channel.listsss.time_zone') })],
    experience_gold_limit: [buildValidatorData({name: 'number', title: t('channel.listsss.experience_gold_limit')})],
    create_time: [buildValidatorData({name: 'date', title: t('channel.listsss.create_time')})],
})

// 语言与货币/时区选项
const langOptions: Record<string, string> = reactive({
    'en': 'English',
    'zh-cn': '简体中文',
    'ar': 'العربية',
})
const currencyCodeOptions: Record<string, string> = reactive({
    'USD': 'USD',
    'CNY': 'CNY',
    'INR': 'INR',
})
const timeZoneOptions: Record<string, string> = reactive({
    'America/New_York': 'America/New_York',
    'America/Los_Angeles': 'America/Los_Angeles',
    'UTC': 'UTC',
    'Asia/Shanghai': 'Asia/Shanghai',
})

// 活动可选项（从 slot_activity 拉取，失败时降级为静态映射）
const activityOptions: Record<string, string> = reactive({})
const activityConfigs = reactive<Record<string, any>>({})
const availableActivities = ref<any[]>([])
const activityFilterText = ref('')
const activityFilterStatus = ref<'all' | 'enabled' | 'disabled'>('all')
const homePopupSortRef = ref<HTMLElement>()
const homePopupOrderKeys = ref<string[]>([])
let homePopupSortable: Sortable | null = null
const filteredActivities = computed(() => {
    const kw = activityFilterText.value.trim().toLowerCase()
    return availableActivities.value.filter((it: any) => {
        const key = getActivityKey(it)
        const name = activityOptions[key] || key
        const matchKw = !kw || name.toLowerCase().includes(kw) || key.toLowerCase().includes(kw)
        const enabled = !!activityConfigs[key]?.enabled
        const matchStatus = activityFilterStatus.value === 'all' || (activityFilterStatus.value === 'enabled' ? enabled : !enabled)
        return matchKw && matchStatus
    })
})
const homePopupOrderItems = computed(() => {
    return homePopupOrderKeys.value
        .map((key) => availableActivities.value.find((it: any) => getActivityKey(it) === key))
        .filter(Boolean)
})

const activityDefaultConfigs: Record<string, Partial<{
    enabled: boolean
    popup_enabled_home: boolean
    popup_order_home: number
    popup_enabled_recharge: boolean
    popup_order_recharge: number
    bet_multiplier: number
}>> = {
    bind_mobile: { enabled: true, popup_enabled_home: false, popup_order_home: 0, popup_enabled_recharge: false, popup_order_recharge: 0, bet_multiplier: 1 },
    pop_up: { enabled: true, popup_enabled_home: true, popup_order_home: 1, popup_enabled_recharge: false, popup_order_recharge: 0, bet_multiplier: 1 },
    red_envelope: { enabled: true, bet_multiplier: 1 },
    rescue_funds: { enabled: true, popup_enabled_home: false, popup_order_home: 0, popup_enabled_recharge: false, popup_order_recharge: 0, bet_multiplier: 1 },
    turntable: { enabled: true, popup_enabled_home: true, popup_order_home: 3, popup_enabled_recharge: false, popup_order_recharge: 0, bet_multiplier: 1 },
    daygold: { enabled: true, popup_enabled_home: true, popup_order_home: 5, popup_enabled_recharge: false, popup_order_recharge: 0, bet_multiplier: 1 },
    pwa: { enabled: true, popup_enabled_home: true, popup_order_home: 6, popup_enabled_recharge: false, popup_order_recharge: 0, bet_multiplier: 1 },
    first_vip_49: { enabled: true, bet_multiplier: 1 },
    deposit_vip: { enabled: true, popup_enabled_home: true, popup_order_home: 6, popup_enabled_recharge: true, popup_order_recharge: 0, bet_multiplier: 1 },
    internal_message: { enabled: true, bet_multiplier: 1 },
    first_deposit_daily: { enabled: true, popup_enabled_home: false, popup_order_home: 0, popup_enabled_recharge: false, popup_order_recharge: 0, bet_multiplier: 1 },
    first_deposit_270: { enabled: true, popup_enabled_home: true, popup_order_home: 7, popup_enabled_recharge: true, popup_order_recharge: 0, bet_multiplier: 1 },
    first_vip_6: { enabled: true, bet_multiplier: 1 },
    first_deposit_25: { enabled: true, popup_enabled_home: true, popup_order_home: 8, popup_enabled_recharge: false, popup_order_recharge: 0, bet_multiplier: 1 },
    pop_up_success: { enabled: true, popup_enabled_home: true, popup_order_home: 2, popup_enabled_recharge: false, popup_order_recharge: 0 },
    turntable_success: { enabled: true, popup_enabled_home: true, popup_order_home: 4, popup_enabled_recharge: false, popup_order_recharge: 0 },
    banlance_pop_up: { enabled: true },
    game_vip_375: { enabled: true, bet_multiplier: 1 },
    customer_service: { enabled: true },
    chest: { enabled: true, bet_multiplier: 1 },
    leaderboard: { enabled: true, bet_multiplier: 1 },
}

function toggleAll(state: boolean) {
    availableActivities.value.forEach((it: any) => {
        const key = getActivityKey(it)
        if (!activityConfigs[key]) activityConfigs[key] = createDefaultActivityConfig(it)
        activityConfigs[key].enabled = state
    })
}

function resetOne(key: string, it: any) {
    activityConfigs[key] = createDefaultActivityConfig(it)
    refreshHomePopupOrderKeys()
}

function resetAll() {
    availableActivities.value.forEach((it: any) => resetOne(getActivityKey(it), it))
}

function getActivityKey(it: any) { return it?.key ?? it?.type }

function createDefaultActivityConfig(it: any) {
    const key = getActivityKey(it)
    const preset = activityDefaultConfigs[key] || {}
    const defMul = it?.option?.bet_multiplier
    const betMul = Number(preset.bet_multiplier) > 0 ? Number(preset.bet_multiplier) : defMul && Number(defMul) > 0 ? Number(defMul) : 1
    return {
        enabled: preset.enabled ?? true,
        popup_enabled_home: preset.popup_enabled_home ?? false,
        popup_enabled_recharge: preset.popup_enabled_recharge ?? false,
        popup_order_home: preset.popup_order_home ?? 0,
        popup_order_recharge: preset.popup_order_recharge ?? 0,
        bet_multiplier: betMul,
    }
}

function isPopupHomeSupported(it: any): boolean {
    const key = getActivityKey(it)
    const found = availableActivities.value.find((x: any) => getActivityKey(x) === key)
    return Number(found?.is_popup_home) === 1 || Number(found?.is_popup) === 1
}

function isPopupRechargeSupported(it: any): boolean {
    const key = getActivityKey(it)
    const found = availableActivities.value.find((x: any) => getActivityKey(x) === key)
    return Number(found?.is_popup_recharge) === 1 || Number(found?.is_popup) === 1
}

function isBetMultiplierSupported(it: any): boolean {
    const key = getActivityKey(it)
    const found = availableActivities.value.find((x: any) => getActivityKey(x) === key)
    return Number(found?.is_bet_multiplier) === 1
}

function getHomePopupSupportedKeys() {
    return availableActivities.value.filter((it: any) => isPopupHomeSupported(it)).map((it: any) => getActivityKey(it))
}

function refreshHomePopupOrderKeys() {
    const supportedKeys = getHomePopupSupportedKeys()
    const availableIndex = new Map(supportedKeys.map((key, index) => [key, index]))

    homePopupOrderKeys.value = [...supportedKeys].sort((a, b) => {
        const orderA = Number(activityConfigs[a]?.popup_order_home) || 0
        const orderB = Number(activityConfigs[b]?.popup_order_home) || 0

        if (orderA > 0 && orderB > 0) return orderA - orderB
        if (orderA > 0) return -1
        if (orderB > 0) return 1
        return (availableIndex.get(a) ?? 0) - (availableIndex.get(b) ?? 0)
    })
    nextTick(() => initHomePopupSortable())
}

function getHomePopupOrderLabel(key: string, index: number) {
    if (!activityConfigs[key]?.popup_enabled_home) return '-'
    return Number(activityConfigs[key]?.popup_order_home) || index + 1
}

function syncHomePopupOrderToConfigs(renumber = false) {
    homePopupOrderKeys.value.forEach((key, index) => {
        const item = availableActivities.value.find((it: any) => getActivityKey(it) === key)
        if (!item) return
        if (!activityConfigs[key]) activityConfigs[key] = createDefaultActivityConfig(item)

        const currentOrder = Number(activityConfigs[key].popup_order_home) || 0
        const nextOrder = activityConfigs[key].popup_enabled_home ? (renumber || currentOrder <= 0 ? index + 1 : currentOrder) : 0
        if (Number(activityConfigs[key].popup_order_home) !== nextOrder) {
            activityConfigs[key].popup_order_home = nextOrder
        }
    })
}

function moveHomePopupOrder(oldIndex: number, newIndex: number) {
    const keys = [...homePopupOrderKeys.value]
    const [moved] = keys.splice(oldIndex, 1)
    keys.splice(newIndex, 0, moved)
    homePopupOrderKeys.value = keys
    syncHomePopupOrderToConfigs(true)
    composeActivityPayload()
}

function initHomePopupSortable() {
    if (!homePopupSortRef.value || homePopupOrderItems.value.length < 2) {
        homePopupSortable?.destroy()
        homePopupSortable = null
        return
    }
    homePopupSortable?.destroy()
    homePopupSortable = Sortable.create(homePopupSortRef.value, {
        animation: 180,
        handle: '.home-popup-sort-drag-area',
        draggable: '.home-popup-sort-item',
        ghostClass: 'home-popup-sort-ghost',
        chosenClass: 'home-popup-sort-chosen',
        onEnd: (evt: SortableEvent) => {
            if (evt.oldIndex === undefined || evt.newIndex === undefined || evt.oldIndex === evt.newIndex) return
            moveHomePopupOrder(evt.oldIndex, evt.newIndex)
        },
    })
}

function ensureDefaults() {
    const items = baTable.form.items as any
    // 默认美国
    items.lang = items.lang ?? 'en'
    items.currency_code = items.currency_code ?? 'USD'
    items.currency_symbol = items.currency_symbol ?? '$'
    items.time_zone = items.time_zone ?? 'America/New_York'
}

async function loadActivityOptions(row?: any) {
    Object.keys(activityOptions).forEach((k) => delete activityOptions[k])
    availableActivities.value = Array.isArray(row?.available_activities) ? row!.available_activities : []
    if (Array.isArray(availableActivities.value)) {
        availableActivities.value.forEach((it: any) => {
            const key = it?.key ?? it?.type
            const title = it?.title ?? it?.name ?? key
            if (key) activityOptions[key] = title
        })
    }
}

function initActivityFromModel() {
    const raw = (baTable.form.items as any)?.activity
    
    // 即使没有原始数据，也要为所有活动设置默认值
    if (!raw) {
        availableActivities.value.forEach((it: any) => {
            const key = getActivityKey(it)
            activityConfigs[key] = createDefaultActivityConfig(it)
        })
        return
    }
    let arr: any[] = []
    try {
        arr = typeof raw === 'string' ? JSON.parse(raw) : raw
        if (!Array.isArray(arr)) arr = []
    } catch (e) { arr = [] }
    
    // 初始化所有 available_activities 的默认开关
    availableActivities.value.forEach((it: any) => {
        const key = getActivityKey(it)
        const found = arr.find((x: any) => x.key === key)
        
        // 计算 bet_multiplier：优先保存记录的 option.bet_multiplier；否则取 available_activities 默认；再否则 1
        let betMul = 1
        const savedMul = found?.option?.bet_multiplier
        if (savedMul && Number(savedMul) > 0) {
            betMul = Number(savedMul)
        } else {
            const defMul = it?.option?.bet_multiplier
            betMul = defMul && Number(defMul) > 0 ? Number(defMul) : 1
        }
        
        // 确保弹窗顺序有默认值
        const homeOrder = typeof found?.popup_order_home === 'number' ? found.popup_order_home : 0
        const rechargeOrder = typeof found?.popup_order_recharge === 'number' ? found.popup_order_recharge : 0
        
        activityConfigs[key] = {
            enabled: Boolean(found?.enabled ?? true),
            // 兼容旧 popup_enabled：若未提供则两端继承它，顺序默认 0
            popup_enabled_home: Boolean(found?.popup_enabled_home ?? (found?.popup_enabled ?? false)),
            popup_enabled_recharge: Boolean(found?.popup_enabled_recharge ?? (found?.popup_enabled ?? false)),
            popup_order_home: homeOrder,
            popup_order_recharge: rechargeOrder,
            bet_multiplier: betMul,
        }
    })
}

function composeActivityPayload() {
    const payload = Object.keys(activityConfigs).map((key) => {
        const config = activityConfigs[key]
        const item = availableActivities.value.find((x: any) => getActivityKey(x) === key)
        const out: any = {
            key,
            option: {},
            enabled: Boolean(config?.enabled),
        }
        
        // 只有当 is_bet_multiplier 开启时才在 option 中包含 bet_multiplier 字段
        if (isBetMultiplierSupported(item)) {
            out.option.bet_multiplier = Number(config?.bet_multiplier) || 1
        }

        if (isPopupHomeSupported(item)) {
            out.popup_enabled_home = Boolean(config?.popup_enabled_home)
            out.popup_order_home = Number(config?.popup_order_home) || 0
        }
        if (isPopupRechargeSupported(item)) {
            out.popup_enabled_recharge = Boolean(config?.popup_enabled_recharge)
            out.popup_order_recharge = Number(config?.popup_order_recharge) || 0
        }
        return out
    })
    ;(baTable.form.items as any).activity = JSON.stringify(payload)
    return payload
}

watch(activityConfigs, () => {
    syncHomePopupOrderToConfigs()
    composeActivityPayload()
}, { deep: true, immediate: false })

async function fetchAvailableActivitiesIfNeeded() {
    if (Array.isArray(availableActivities.value) && availableActivities.value.length > 0) return
    try {
        const resp: any = await request({ url: '/admin/channel.listsss/availableActivities', method: 'GET' })
        if (resp && resp.code === 1) {
            const arr = resp?.data?.available_activities
            if (Array.isArray(arr)) {
                availableActivities.value = arr
                // 同步 activityOptions
                arr.forEach((it: any) => {
                    const key = getActivityKey(it)
                    const title = it?.title ?? it?.name ?? key
                    if (key) activityOptions[key] = title
                })
                // 新增场景：没有原始 activity，按默认初始化 configs
                if (!(baTable.form.items as any)?.activity) {
                    availableActivities.value.forEach((it: any) => {
                        const key = getActivityKey(it)
                        activityConfigs[key] = createDefaultActivityConfig(it)
                    })
                    refreshHomePopupOrderKeys()
                    composeActivityPayload()
                }
            }
        }
    } catch (e) {}
}

onMounted(async () => {
    ensureDefaults()
    await loadActivityOptions(baTable.form.items as any)
    // 若没有 available_activities，则主动拉取
    if (!availableActivities.value || availableActivities.value.length === 0) {
        await fetchAvailableActivitiesIfNeeded()
    }
    // 确保在 available_activities 加载完成后初始化
    if (availableActivities.value && availableActivities.value.length > 0) {
        initActivityFromModel()
        refreshHomePopupOrderKeys()
        composeActivityPayload()
    }
})

onBeforeUnmount(() => {
    homePopupSortable?.destroy()
    homePopupSortable = null
})

// 等待后端异步注入 row 后再初始化（首次和后续替换时都会触发）
watch(
    () => baTable.form.items,
    (val) => {
        if (!val) return
        // 仅当 available_activities 存在且为数组时初始化
        if (Array.isArray((val as any).available_activities)) {
            loadActivityOptions(val as any)
            // 确保在下一个 tick 中初始化，让 available_activities 完全加载
            nextTick(() => {
                initActivityFromModel()
                refreshHomePopupOrderKeys()
                composeActivityPayload()
            })
        }
    },
    { immediate: false }
)
</script>

<style scoped lang="scss">
.activity-section {
  .home-popup-sort {
    margin-bottom: 14px;
    padding: 12px;
    border: 1px solid var(--el-border-color);
    border-radius: 8px;
    background: var(--el-fill-color-blank);
  }
  .home-popup-sort-header {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
    font-weight: 600;
    color: var(--el-text-color-primary);
  }
  .home-popup-sort-header::before {
    display: inline-block;
    width: 3px;
    height: 14px;
    margin-right: 8px;
    border-radius: 2px;
    background: var(--el-color-primary);
    content: '';
  }
  .home-popup-sort-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 8px;
  }
  .home-popup-sort-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    min-width: 0;
    min-height: 58px;
    padding: 8px 10px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 6px;
    background: var(--el-fill-color-blank);
    column-gap: 8px;
    row-gap: 4px;
    transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      background-color 0.2s ease;
  }
  .home-popup-sort-item:hover {
    border-color: var(--el-color-primary-light-5);
    box-shadow: 0 2px 8px rgb(0 0 0 / 5%);
  }
  .home-popup-sort-item.disabled {
    color: var(--el-text-color-secondary);
    background: var(--el-fill-color-extra-light);
  }
  .home-popup-sort-drag-area {
    display: grid;
    grid-template-columns: auto auto minmax(0, 1fr);
    align-items: center;
    min-width: 0;
    gap: 8px;
    cursor: move;
  }
  .home-popup-sort-item .drag-handle {
    flex: 0 0 auto;
    color: var(--el-text-color-secondary);
    cursor: move;
  }
  .home-popup-sort-item .order {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
    font-size: 12px;
    font-weight: 600;
  }
  .home-popup-sort-item .content {
    display: flex;
    align-items: flex-start;
    flex-direction: column;
    flex: 1 1 auto;
    min-width: 0;
    gap: 4px;
  }
  .home-popup-sort-item .name {
    width: 100%;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .home-popup-sort-item .popup-switch {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex: 0 0 auto;
    min-width: 52px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    white-space: nowrap;
  }
  :deep(.home-popup-sort-ghost) {
    opacity: 0.45;
  }
  :deep(.home-popup-sort-chosen) {
    border-color: var(--el-color-primary);
  }
  @media (max-width: 640px) {
    .home-popup-sort-list {
      grid-template-columns: 1fr;
    }
    .home-popup-sort-item {
      grid-template-columns: 1fr;
    }
    .home-popup-sort-item .popup-switch {
      justify-content: flex-start;
      min-width: 0;
    }
  }
  .activity-item {
    margin-bottom: 10px;
    padding: 10px 12px;
    border: 1px solid var(--el-border-color);
    border-radius: 8px;
    background: var(--el-fill-color-blank);
  }
  .activity-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
  }
  .activity-header .title {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .activity-field {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
  }
  .activity-field .label {
    width: 56px;
    color: var(--el-text-color-secondary);
    flex: 0 0 auto;
  }
}
</style>
