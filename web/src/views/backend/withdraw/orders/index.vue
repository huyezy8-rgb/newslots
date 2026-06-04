<template>
  <div class="default-main ba-table-box">
      <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

      <TableHeader
          :buttons="['refresh', 'export', 'comSearch', 'quickSearch', 'columnDisplay']"
          :quick-search-placeholder="t('Quick search placeholder', { fields: t('withdraw.orders.quick Search Fields') })"
      />

      <Table ref="tableRef">
          <template #account_info_solt>
              <el-table-column :label="t('withdraw.orders.account_info')" width="280">
                  <template #default="scope">
                      <div style="text-align: left">
                          <p><strong>钱包类型:</strong> <el-tag size="small">{{ scope.row.wallet_type }}</el-tag></p>
                          <p><strong>账户姓名:</strong> {{ scope.row.account_info.name }}</p>
                          <p><strong>账户:</strong> {{ scope.row.account_info.account_name }}</p>
                          <p v-if="scope.row.account_info.bank_name"><strong>银行:</strong> {{ scope.row.account_info.bank_name }}</p>
                      </div>
                  </template>
              </el-table-column>
          </template>

          <template #withdraw_info_solt>
              <el-table-column :label="t('withdraw.orders.amount')" width="280">
                  <template #default="scope">
                      <div style="text-align: left">
                          <p><strong>总金额:</strong> {{ scope.row.amount }}</p>
                          <p><strong>实际到账:</strong> {{ scope.row.real_amount }}</p>
                          <p><strong>手续费:</strong> {{ scope.row.fee }}</p>
                      </div>
                  </template>
              </el-table-column>
          </template>
      </Table>

  </div>
</template>

<script setup lang="ts">
import { onMounted, provide, reactive, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { baTableApi } from '/@/api/common'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { ElMessageBox } from 'element-plus'
import { useAdminInfo } from '/@/stores/adminInfo'
import createAxios from '/@/utils/axios'
const adminInfo = useAdminInfo()

defineOptions({ name: 'withdraw/orders' })

const { t } = useI18n()
const tableRef = useTemplateRef('tableRef')
const withdrawTypeOptions = reactive<Record<string, string>>({})

// 修改 pass 和 reject 按钮的类型
const pass: OptButton = {
  render: 'tipButton',
  name: 'pass',
  title: '通过',
  text: '通过',
  type: 'success',
  icon: 'fa fa-check-circle',
  class: 'table-row-info',
  click: async (row: TableRow) => {
    try {
      await ElMessageBox.confirm(t('withdraw.orders.passConfirm') || '确认通过该提现订单？', '提示', {
        confirmButtonText: t('Confirm') || '确认',
        cancelButtonText: t('Cancel') || '取消',
        type: 'warning',
      })
        await baTable.api.postData('pass', { id: row.id })

        baTable.onTableHeaderAction('refresh', {})
    } catch (err) {
        console.error(err)
    }

  },
  disabled: () => false,
    display: (row: TableRow) => {
        return row.status === 0 || row.status === 4
    }
}

const reject: OptButton = {
  render: 'tipButton',
  name: 'reject',
  title: '驳回',
  text: '驳回',
  type: 'danger',
  icon: 'fa fa-ban',
  class: 'table-row-info',
  click: async (row: TableRow) => {
    try {
        await ElMessageBox.confirm(t('withdraw.orders.rejectConfirm') || '确认驳回该提现订单？', '提示', {
            confirmButtonText: t('Confirm') || '确认',
            cancelButtonText: t('Cancel') || '取消',
            type: 'warning',
        })
      await baTable.api.postData('reject', {
        id: row.id,
      })
      baTable.onTableHeaderAction('refresh', {})
    } catch (err) {
        console.error(err)
    }

  },
  disabled: () => false,
    display: (row: TableRow) => {
        return row.status === 0 || row.status === 4
    }

}

// 合并操作按钮
const canManualTestpay = (row: TableRow) => {
    return row.status === 1 && String(row.pay_type).toLowerCase() === 'testpay'
}

const testpaySuccess: OptButton = {
  render: 'tipButton',
  name: 'testpaySuccess',
  title: 'TestPay成功',
  text: 'TestPay成功',
  type: 'success',
  icon: 'fa fa-check',
  class: 'table-row-info',
  click: async (row: TableRow) => {
    try {
        await ElMessageBox.confirm('确认模拟该 TestPay 提现打款成功？', '提示', {
            confirmButtonText: t('Confirm') || '确认',
            cancelButtonText: t('Cancel') || '取消',
            type: 'warning',
        })
        await baTable.api.postData('testpayManual', {
            id: row.id,
            status: 'success',
        })
        baTable.onTableHeaderAction('refresh', {})
    } catch (err) {
        console.error(err)
    }
  },
  disabled: () => false,
  display: (row: TableRow) => {
      return canManualTestpay(row)
  },
}

const testpayFail: OptButton = {
  render: 'tipButton',
  name: 'testpayFail',
  title: 'TestPay失败',
  text: 'TestPay失败',
  type: 'danger',
  icon: 'fa fa-times',
  class: 'table-row-info',
  click: async (row: TableRow) => {
    try {
        await ElMessageBox.confirm('确认模拟该 TestPay 提现打款失败？', '提示', {
            confirmButtonText: t('Confirm') || '确认',
            cancelButtonText: t('Cancel') || '取消',
            type: 'warning',
        })
        await baTable.api.postData('testpayManual', {
            id: row.id,
            status: 'fail',
        })
        baTable.onTableHeaderAction('refresh', {})
    } catch (err) {
        console.error(err)
    }
  },
  disabled: () => false,
  display: (row: TableRow) => {
      return canManualTestpay(row)
  },
}

const optButtons: OptButton[] = [pass, reject, testpaySuccess, testpayFail]

const baTable = new baTableClass(
  new baTableApi('/admin/withdraw.Orders/'),
  {
      pk: 'id',
      showComSearch: true,
      column: [
          { type: 'selection', align: 'left', operator: false },
          { label: t('withdraw.orders.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
          { label: t('withdraw.orders.channel_id'), prop: 'channel_id', align: 'center', operator: adminInfo.isAdminChannelId == null ? '=' : false, operatorPlaceholder: t('Fuzzy query') },
          { label: t('withdraw.orders.user_id'), prop: 'user_id', align: 'center', operator: 'LIKE', operatorPlaceholder: t('Fuzzy query'), sortable: true },
          { label: t('withdraw.orders.platform_order_no'), prop: 'platform_order_no', align: 'center', width: 180, operator: 'LIKE', operatorPlaceholder: t('Fuzzy query') },
          { label: t('withdraw.orders.order_no'), prop: 'order_no', align: 'center', width: 180, operator: 'LIKE', operatorPlaceholder: t('Fuzzy query') },
          { label: t('withdraw.orders.channel_order_no'), prop: 'channel_order_no', align: 'center', width: 180, operator: 'LIKE', operatorPlaceholder: t('Fuzzy query') },
          {
              label: "提现方式",
              prop: 'pay_type',
              align: 'center',
              width: 100,
              operator: 'eq',
              operatorPlaceholder: t('Please select field', { field: '提现方式' }),
              comSearchRender: 'select',
              replaceValue: withdrawTypeOptions,
          },
          {
              label: t('withdraw.orders.amount'),
              prop: 'amount',
              align: 'center',
              operator: 'RANGE',
              sortable: false,
              render: 'slot',
              slotName: 'withdraw_info_solt',
          },
          {
              label: t('withdraw.orders.account_info'),
              prop: 'account_info',
              align: 'center',
              operator: 'LIKE',
              operatorPlaceholder: t('Fuzzy query'),
              render: 'slot',
              slotName: 'account_info_solt',
          },
          {
              label: t('withdraw.orders.status'),
              prop: 'status',
              align: 'center',
              operator: 'RANGE',
              render: 'tags',
              formatter(row, column, cellValue, index) {
                  const map: Record<string, string> = {
                      0: '待审核',
                      1: '已通过',
                      2: '已打款',
                      3: '已驳回',
                      4: '打款失败',
                  }
                  return map[String(cellValue)] || cellValue
              },
          },
          {
              label: t('withdraw.orders.create_time'),
              prop: 'create_time',
              align: 'center',
              render: 'datetime',
              operator: 'RANGE',
              sortable: 'custom',
              width: 160,
              timeFormat: 'YYYY-MM-DD HH:mm:ss',
          },
          {
              label: t('withdraw.orders.update_time'),
              prop: 'update_time',
              align: 'center',
              render: 'datetime',
              operator: 'RANGE',
              sortable: 'custom',
              width: 160,
              timeFormat: 'YYYY-MM-DD HH:mm:ss',
          },
          {
              label: t('Operate'),
              align: 'center',
              width: 260,
              render: 'buttons',
              buttons: optButtons,
              operator: false,
          },
      ],
      dblClickNotEditColumn: ['all'],
  },
  {
      defaultItems: { wallet_type: 'withdraw_wallet' },
  }
)

provide('baTable', baTable)

const loadWithdrawTypeOptions = () => {
    createAxios<Record<string, string>>({
        url: '/admin/payment.Methods/withdrawOptions',
        method: 'get',
    }).then((res) => {
        Object.keys(withdrawTypeOptions).forEach((key) => delete withdrawTypeOptions[key])
        Object.assign(withdrawTypeOptions, res.data)
    })
}

onMounted(() => {
  loadWithdrawTypeOptions()
  baTable.table.ref = tableRef.value
  baTable.mount()
  baTable.getData()?.then(() => {
      baTable.initSort()
      baTable.dragSort()
  })
})
</script>

<style scoped lang="scss"></style>
