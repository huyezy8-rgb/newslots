<template>
    <div @click="enableEdit" style="position: relative; display: inline-block; width: 100%;">
        <el-input
            v-if="field.prop"
            :model-value="inputValue"
            :placeholder="field.edit?.placeholder || ''"
            :type="inputType"
            :min="field.edit?.min"
            :max="field.edit?.max"
            :step="field.edit?.step"
            :maxlength="field.edit?.maxlength"
            :disabled="loading || !editing"
            :readonly="!editing"
            @input="onInput"
            @change="onChange"
            @blur="onBlur"
            clearable
        />
        <!-- 点击编辑的图标，点击图标也启用编辑 -->
        <el-icon
            @click.stop="enableEdit"
            style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); cursor: pointer;"
        >
            <Edit />
        </el-icon>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, defineProps, inject } from 'vue'
import { Edit } from '@element-plus/icons-vue'
import type { TableColumnCtx } from 'element-plus'
import type baTableClass from '/@/utils/baTable'

interface Props {
    row: Record<string, any>
    field: {
        prop: string
        edit?: {
            type?: string
            min?: number
            max?: number
            step?: number
            precision?: number
            placeholder?: string
            maxlength?: number
        }
    }
    column: TableColumnCtx<Record<string, any>>
    index: number
}

const props = defineProps<Props>()

const baTable = inject('baTable') as baTableClass

const inputValue = ref(props.row[props.field.prop] ?? '')
const inputType = props.field.edit?.type === 'number' ? 'number' : 'text'
const loading = ref(false)

// 新增编辑状态
const editing = ref(false)

watch(
    () => props.row[props.field.prop],
    (newVal) => {
        inputValue.value = newVal ?? ''
    }
)

const enableEdit = () => {
    if (!loading.value) {
        editing.value = true
    }
}

const onInput = (val: string | number) => {
    inputValue.value = val
}

const onChange = async (val: string | number) => {
    if (loading.value) return
    loading.value = true

    try {
        await baTable.api.postData('edit', {
            [baTable.table.pk!]: props.row[baTable.table.pk!],
            [props.field.prop]: val,
        })
        props.row[props.field.prop] = val
        baTable.onTableAction('field-change', { value: val, ...props })
    } catch (error) {
        inputValue.value = props.row[props.field.prop] ?? ''
        console.error('输入框更新失败:', error)
    } finally {
        loading.value = false
        editing.value = false // 结束编辑
    }
}

const onBlur = (e: Event) => {
    // 失焦自动提交并退出编辑状态
    onChange(inputValue.value)
}
</script>
