<template>
    <div>
        {{ !cellValue ? '-' : (field.useTimezone === false ? formatRaw(cellValue, field.timeFormat ?? 'YYYY-MM-DD HH:mm:ss') : formatWithTimezone(cellValue, field.timeFormat ?? 'YYYY-MM-DD HH:mm:ss')) }}
    </div>
    
</template>

<script setup lang="ts">
import { TableColumnCtx } from 'element-plus'
import { getCellValue } from '/@/components/table/index'
import { formatWithTimezone } from '/@/utils/dayjs'
import dayjs from 'dayjs'

interface Props {
    row: TableRow
    field: TableColumn
    column: TableColumnCtx<TableRow>
    index: number
}

const props = defineProps<Props>()

const cellValue = getCellValue(props.row, props.field, props.column, props.index)

const formatRaw = (val: any, fmt: string) => {
    let processed = val
    if (typeof val === 'number' && val.toString().length === 10) {
        processed = val * 1000
    }
    return dayjs(processed).format(fmt)
}
</script>
