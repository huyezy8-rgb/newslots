<template>
  <div class="account-detail">
    <el-card class="layout">
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
    </el-card>
  </div>
  
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import BaseInfo from './detail/BaseInfo.vue'
import RechargeList from './detail/RechargeList.vue'
import WithdrawList from './detail/WithdrawList.vue'
import GameList from './detail/GameList.vue'
import CoinLogList from './detail/CoinLogList.vue'

const route = useRoute()
const userId = Number(route.params.id)
const active = ref('base')

const onSelect = (key: string) => {
  active.value = key
}
</script>

<style scoped lang="scss">
.account-detail {
  .layout__body {
    display: flex;
    gap: 16px;
    max-width: 100%;
    overflow: hidden;
  }
  .sider {
    flex: 0 0 180px;
    width: 180px;
  }
  .content {
    flex: 1;
    min-width: 0;
    min-height: 400px;
    overflow-x: auto;
  }
}
</style>

