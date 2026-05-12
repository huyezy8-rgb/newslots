<template>
  <el-dialog v-model="visibleInner" title="用户详情" width="1000px" destroy-on-close>
    <div class="layout__body">
      <aside class="sider">
        <el-menu :default-active="active" class="sider-menu" @select="onSelect">
          <el-menu-item index="base">用户基础信息</el-menu-item>
          <el-menu-item index="recharge">充值记录</el-menu-item>
          <el-menu-item index="withdraw">提现记录</el-menu-item>
          <el-menu-item index="game">游戏记录</el-menu-item>
          <el-menu-item index="coin">资金流水</el-menu-item>
        </el-menu>
      </aside>
      <section class="content">
        <BaseInfo v-if="active==='base'" :user-id="userId" />
        <RechargeList v-else-if="active==='recharge'" :user-id="userId" />
        <WithdrawList v-else-if="active==='withdraw'" :user-id="userId" />
        <GameList v-else-if="active==='game'" :user-id="userId" />
        <CoinLogList v-else :user-id="userId" />
      </section>
    </div>
    <template #footer>
      <el-button type="primary" @click="visibleInner=false">关闭</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import BaseInfo from './detail/BaseInfo.vue'
import RechargeList from './detail/RechargeList.vue'
import WithdrawList from './detail/WithdrawList.vue'
import GameList from './detail/GameList.vue'
import CoinLogList from './detail/CoinLogList.vue'

const props = defineProps<{ visible: boolean; userId: number }>()
const emit = defineEmits<{ (e: 'update:visible', val: boolean): void }>()

const visibleInner = ref(props.visible)
watch(() => props.visible, v => visibleInner.value = v)
watch(visibleInner, v => emit('update:visible', v))

const active = ref('base')
const onSelect = (key: string) => { active.value = key }
</script>

<style scoped lang="scss">
.layout__body { display: flex; gap: 16px; }
.sider { width: 180px; }
.content { flex: 1; min-height: 400px; }
</style>


