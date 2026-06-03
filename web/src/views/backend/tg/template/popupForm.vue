<template>
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        width="72%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate }}
            </div>
        </template>

        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div class="ba-operate-form" :style="config.layout.shrink ? '' : 'width: calc(100% - ' + baTable.form.labelWidth! / 2 + 'px)'">
                <el-form
                    v-if="!baTable.form.loading"
                    ref="formRef"
                    @submit.prevent=""
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >
                    <FormItem label="模板标题" type="string" v-model="baTable.form.items!.title" prop="title" />
                    <el-form-item label="文案内容" prop="content">
                        <el-input v-model="baTable.form.items!.content" type="textarea" :rows="9" resize="vertical" />
                    </el-form-item>
                    <el-form-item label="媒体类型" prop="media_type">
                        <el-select v-model="baTable.form.items!.media_type" class="w100">
                            <el-option label="纯文字" value="none" />
                            <el-option label="图片" value="image" />
                            <el-option label="GIF" value="gif" />
                            <el-option label="视频" value="video" />
                        </el-select>
                    </el-form-item>
                    <FormItem v-if="baTable.form.items!.media_type !== 'none'" label="媒体地址" type="string" v-model="baTable.form.items!.media_url" prop="media_url" />
                    <el-form-item label="按钮配置" prop="buttons_json">
                        <div class="tg-buttons-editor">
                            <div v-for="(button, index) in buttonList" :key="index" class="tg-button-row">
                                <el-input v-model="button.text" placeholder="按钮文字" />
                                <el-input v-model="button.url" placeholder="请输入完整链接，例如 https://example.com" />
                                <el-button @click="moveButton(index, -1)" :disabled="index === 0">上移</el-button>
                                <el-button @click="moveButton(index, 1)" :disabled="index === buttonList.length - 1">下移</el-button>
                                <el-button type="danger" @click="removeButton(index)">删除</el-button>
                            </div>
                            <el-button type="primary" @click="addButton">新增按钮</el-button>
                            <div class="form-tip">
                                URL 必须以 http://、https:// 或 tg:// 开头，支持变量：{code}、{amount}、{amount_min}、{amount_max}、{expire_hours}、{max_users}、{claim_count}、{left_count}
                            </div>
                        </div>
                    </el-form-item>
                    <FormItem label="备注" type="textarea" v-model="baTable.form.items!.remark" prop="remark" :input-attr="{ rows: 3 }" />
                    <FormItem label="是否启用" type="radio" v-model="baTable.form.items!.is_enabled" prop="is_enabled" :input-attr="{ content: { 0: '否', 1: '是' } }" />
                    <FormItem label="是否默认" type="radio" v-model="baTable.form.items!.is_default" prop="is_default" :input-attr="{ content: { 0: '否', 1: '是' } }" />
                </el-form>
            </div>
        </el-scrollbar>

        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">取消</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="submit" type="primary">保存</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormItemRule } from 'element-plus'
import { ElMessage } from 'element-plus'
import { inject, nextTick, reactive, ref, useTemplateRef, watch } from 'vue'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

type TgButton = { text: string; url: string }

const config = useConfig()
const formRef = useTemplateRef('formRef')
const baTable = inject('baTable') as baTableClass
const buttonList = ref<TgButton[]>([])

const parseButtonList = (raw: any): TgButton[] => {
    if (Array.isArray(raw)) {
        return raw.map((item) => ({ text: String(item?.text || ''), url: String(item?.url || '') }))
    }
    if (typeof raw === 'string' && raw.trim()) {
        try {
            const parsed = JSON.parse(raw)
            return Array.isArray(parsed) ? parsed.map((item) => ({ text: String(item?.text || ''), url: String(item?.url || '') })) : []
        } catch {
            return []
        }
    }
    return []
}

watch(
    () => [baTable.form.operate, baTable.form.items?.id],
    async () => {
        await nextTick()
        buttonList.value = parseButtonList(baTable.form.items?.buttons_json)
    },
    { immediate: true }
)

const validateButtonUrl = (text: string, url: string) => {
    if (!/^(https?:\/\/|tg:\/\/)/i.test(url.trim())) {
        throw new Error(`按钮“${text}”的链接不是有效URL，请填写 http:// 或 https:// 开头的链接。`)
    }
}

const buildButtonsJson = () => {
    const normalized: TgButton[] = []
    for (const button of buttonList.value) {
        const text = String(button.text || '').trim()
        const url = String(button.url || '').trim()
        if (!text && !url) {
            continue
        }
        if (text && !url) {
            throw new Error(`按钮“${text}”链接不能为空`)
        }
        if (!text && url) {
            throw new Error('按钮文字不能为空')
        }
        validateButtonUrl(text, url)
        normalized.push({ text, url })
    }
    return JSON.stringify(normalized)
}

const addButton = () => {
    buttonList.value.push({ text: '', url: '' })
}

const removeButton = (index: number) => {
    buttonList.value.splice(index, 1)
}

const moveButton = (index: number, offset: number) => {
    const target = index + offset
    if (target < 0 || target >= buttonList.value.length) return
    const item = buttonList.value.splice(index, 1)[0]
    buttonList.value.splice(target, 0, item)
}

const submit = () => {
    try {
        baTable.form.items!.buttons_json = buildButtonsJson()
        baTable.onSubmit(formRef.value)
    } catch (err: any) {
        ElMessage.error(err?.message || '保存失败')
    }
}

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    title: [buildValidatorData({ name: 'required', title: '模板标题' })],
    content: [buildValidatorData({ name: 'required', title: '文案内容' })],
})
</script>

<style scoped lang="scss">
.w100 {
    width: 100%;
}

.tg-buttons-editor {
    width: 100%;
}

.tg-button-row {
    display: grid;
    grid-template-columns: minmax(120px, 180px) minmax(260px, 1fr) auto auto auto;
    gap: 8px;
    margin-bottom: 8px;
}

.form-tip {
    margin-top: 6px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 18px;
}
</style>
