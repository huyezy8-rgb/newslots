import { ElNotification } from 'element-plus'
import { SYSTEM_ZINDEX } from '/@/stores/constant/common'

export interface CsvHeader {
    label: string
    prop: string
}

export function exportRowsToCsv(filename: string, headers: CsvHeader[], rows: anyObj[]) {
    if (!rows.length) {
        ElNotification({
            type: 'error',
            message: '无数据',
            zIndex: SYSTEM_ZINDEX,
        })
        return false
    }

    const csvRows = [headers.map((header) => escapeCsvValue(header.label))]
    rows.forEach((row) => {
        csvRows.push(headers.map((header) => escapeCsvValue(getValue(row, header.prop))))
    })

    const blob = new Blob(['\uFEFF' + csvRows.map((row) => row.join(',')).join('\n')], { type: 'text/csv;charset=utf-8;' })
    downloadBlob(blob, filename)
    return true
}

export function downloadBlob(blob: Blob, filename: string) {
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
}

function getValue(row: anyObj, prop: string) {
    if (Object.prototype.hasOwnProperty.call(row, prop)) {
        return row[prop]
    }

    return prop.split('.').reduce((value: any, key) => {
        if (value && Object.prototype.hasOwnProperty.call(value, key)) {
            return value[key]
        }
        return ''
    }, row)
}

function escapeCsvValue(value: any) {
    if (value === null || typeof value === 'undefined') {
        return ''
    }

    const text = typeof value === 'object' ? JSON.stringify(value) : String(value)
    return `"${text.replace(/"/g, '""')}"`
}
