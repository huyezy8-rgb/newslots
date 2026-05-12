<template>
    <div class="activity-config-container">
        <ContentWrap title="270首充活动配置" v-loading="loading">
            <!-- 页面头部信息 -->
            <div class="page-header">
                <div class="header-info">
                    <div class="header-icon-wrapper">
                        <el-icon class="header-icon"><Present /></el-icon>
                        <div class="icon-badge">270%</div>
                    </div>
                    <div class="header-text">
                        <h2>270首充活动配置</h2>
                        <p>配置首充活动的各项参数，包括奖励策略、金额配置、任务设置等</p>
                        <div class="header-stats">
                            <div class="stat-item">
                                <span class="stat-label">奖励比例</span>
                                <span class="stat-value">{{ formData.reward_percent }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    <el-button type="primary" @click="submit" :loading="submitting" :icon="Check">
                        {{ submitting ? '保存中...' : '保存配置' }}
                    </el-button>
                    <el-button type="info" @click="previewConfig" :icon="View">预览配置</el-button>
                    <el-button type="warning" @click="resetForm" :icon="Refresh">重置表单</el-button>
                </div>
            </div>

            <div class="config-content">
                <el-form ref="formRef" :model="formData" :rules="rules" label-width="160px" @submit.prevent status-icon class="config-form">
                    <!-- 基本信息 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><Document /></el-icon>
                            <span>基本信息</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('basic')">
                                    <el-icon><ArrowDown v-if="!expandedSections.basic" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.basic }">
                    <el-form-item label="活动标题" prop="title">
                                <el-input 
                                    v-model="formData.title" 
                                    placeholder="请输入活动标题"
                                    maxlength="50"
                                    show-word-limit
                                    clearable
                                    class="enhanced-input"
                                >
                                    <template #prefix>
                                        <el-icon><Edit /></el-icon>
                                    </template>
                                </el-input>
                    </el-form-item>

                    <el-form-item label="活动说明" prop="context">
                                <el-input 
                                    v-model="formData.context" 
                                    type="textarea" 
                                    :rows="4" 
                                    placeholder="请输入活动说明内容"
                                    maxlength="500"
                                    show-word-limit
                                    resize="vertical"
                                    class="enhanced-textarea"
                                />
                    </el-form-item>
                        </div>
                    </div>

                    <!-- 奖励策略配置 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><Money /></el-icon>
                            <span>奖励策略配置</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('reward')">
                                    <el-icon><ArrowDown v-if="!expandedSections.reward" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.reward }">
                    <el-form-item label="启用充值奖励" prop="enable_reward">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <el-switch 
                                            v-model="formData.enable_reward" 
                                            :active-value="1" 
                                            :inactive-value="0"
                                            active-text="启用"
                                            inactive-text="禁用"
                                            size="large"
                                        />
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>{{ formData.enable_reward ? '用户可参与首充活动' : '用户无法参与首充活动' }}</span>
                                    </div>
                                </div>
                    </el-form-item>

                    <el-form-item label="奖励策略" prop="reward_strategy">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <el-select 
                                            v-model="formData.reward_strategy" 
                                            placeholder="请选择奖励策略" 
                                            class="enhanced-select"
                                            @change="handleStrategyChange"
                                            clearable
                                        >
                                            <el-option label="固定金额 (fixed)" value="fixed">
                                                <div class="option-content">
                                                    <span class="option-label">固定金额</span>
                                                    <span class="option-desc">用户获得固定金额的奖励</span>
                                                </div>
                                            </el-option>
                                            <el-option label="区间随机 (range)" value="range">
                                                <div class="option-content">
                                                    <span class="option-label">区间随机</span>
                                                    <span class="option-desc">在指定范围内随机发放奖励</span>
                                                </div>
                                            </el-option>
                                            <el-option label="百分比 (percent)" value="percent">
                                                <div class="option-content">
                                                    <span class="option-label">百分比</span>
                                                    <span class="option-desc">按充值金额的百分比发放奖励</span>
                                                </div>
                                            </el-option>
                        </el-select>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><Lightning /></el-icon>
                                        <span>选择适合的奖励策略，影响用户获得的奖励金额</span>
                                    </div>
                                </div>
                    </el-form-item>

                    <!-- 奖励值配置 -->
                    <el-form-item label="奖励值配置" prop="reward_value" v-if="formData.reward_strategy">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <div class="reward-value-config">
                        <template v-if="formData.reward_strategy === 'range'">
                                    <div class="range-config">
                                        <div class="input-group">
                                            <label class="input-label">最小值</label>
                                            <el-input 
                                                v-model.number="rewardValueFields.min" 
                                                placeholder="请输入最小值" 
                                                class="reward-input" 
                                                type="number"
                                                :min="0"
                                                :max="rewardValueFields.max || 999999"
                                                @input="validateRangeInput"
                                                clearable
                                            >
                                                <template #append>元</template>
                                            </el-input>
                                        </div>
                                        
                                        <div class="range-separator">
                                            <el-icon><ArrowRight /></el-icon>
                                            <span>至</span>
                                        </div>
                                        
                                        <div class="input-group">
                                            <label class="input-label">最大值</label>
                                            <el-input 
                                                v-model.number="rewardValueFields.max" 
                                                placeholder="请输入最大值" 
                                                class="reward-input" 
                                                type="number"
                                                :min="rewardValueFields.min || 0"
                                                @input="validateRangeInput"
                                                clearable
                                            >
                                                <template #append>元</template>
                                            </el-input>
                                        </div>
                                    </div>
                                    
                                    <!-- 范围预览和统计 -->
                                    <div class="range-preview">
                                        <div class="preview-card">
                                            <div class="preview-header">
                                                <el-icon><TrendCharts /></el-icon>
                                                <span>奖励范围预览</span>
                                            </div>
                                            <div class="preview-content">
                                                <div class="range-display">
                                                    <span class="range-min">{{ rewardValueFields.min || 0 }}</span>
                                                    <div class="range-bar">
                                                        <div class="range-fill" :style="{ width: rangePercentage + '%' }"></div>
                                                    </div>
                                                    <span class="range-max">{{ rewardValueFields.max || 0 }}</span>
                                                </div>
                                                <div class="range-stats">
                                                    <div class="stat-item">
                                                        <span class="stat-label">平均奖励:</span>
                                                        <span class="stat-value">{{ averageReward }}元</span>
                                                    </div>
                                                    <div class="stat-item">
                                                        <span class="stat-label">奖励范围:</span>
                                                        <span class="stat-value">{{ rewardRange }}元</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="range-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>用户将获得 {{ rewardValueFields.min || 0 }} - {{ rewardValueFields.max || 0 }} 元之间的随机奖励</span>
                                    </div>
                                    
                                    <!-- 快速设置按钮 -->
                                    <div class="quick-settings">
                                        <span class="quick-label">快速设置:</span>
                                        <el-button-group>
                                            <el-button size="small" @click="setQuickRange('low')">小额 (5-20元)</el-button>
                                            <el-button size="small" @click="setQuickRange('medium')">中额 (20-50元)</el-button>
                                            <el-button size="small" @click="setQuickRange('high')">大额 (50-200元)</el-button>
                                        </el-button-group>
                                    </div>
                        </template>
                                
                        <template v-else-if="formData.reward_strategy === 'fixed'">
                                    <div class="fixed-config">
                                        <div class="input-group">
                                            <label class="input-label">固定奖励金额</label>
                                            <el-input 
                                                v-model.number="rewardValueFields.fixed" 
                                                placeholder="请输入固定奖励金额" 
                                                class="reward-input" 
                                                type="number"
                                                :min="0"
                                                :max="999999"
                                                @input="validateFixedInput"
                                                clearable
                                            >
                                                <template #append>元</template>
                                            </el-input>
                                        </div>
                                        
                                        <!-- 固定金额预览 -->
                                        <div class="fixed-preview">
                                            <div class="preview-card">
                                                <div class="preview-header">
                                                    <el-icon><Money /></el-icon>
                                                    <span>固定奖励预览</span>
                                                </div>
                                                <div class="preview-content">
                                                    <div class="fixed-display">
                                                        <div class="amount-display">
                                                            <span class="amount-value">{{ rewardValueFields.fixed || 0 }}</span>
                                                            <span class="amount-unit">元</span>
                                                        </div>
                                                        <div class="amount-desc">所有用户都将获得此固定金额</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="fixed-tip">
                                            <el-icon><InfoFilled /></el-icon>
                                            <span>所有用户都将获得 {{ rewardValueFields.fixed || 0 }} 元的固定奖励</span>
                                        </div>
                                        
                                        <!-- 快速设置按钮 -->
                                        <div class="quick-settings">
                                            <span class="quick-label">快速设置:</span>
                                            <el-button-group>
                                                <el-button size="small" @click="setQuickFixed(10)">10元</el-button>
                                                <el-button size="small" @click="setQuickFixed(20)">20元</el-button>
                                                <el-button size="small" @click="setQuickFixed(50)">50元</el-button>
                                                <el-button size="small" @click="setQuickFixed(100)">100元</el-button>
                                            </el-button-group>
                                        </div>
                                    </div>
                        </template>
                                
                        <template v-else-if="formData.reward_strategy === 'percent'">
                                    <div class="percent-config">
                                        <div class="input-group">
                                            <label class="input-label">奖励百分比</label>
                                            <el-input 
                                                v-model.number="rewardValueFields.percent" 
                                                placeholder="请输入奖励百分比" 
                                                class="reward-input" 
                                                type="number"
                                                :min="0"
                                                :max="100"
                                                @input="validatePercentInput"
                                                clearable
                                            >
                                                <template #append>%</template>
                                            </el-input>
                                        </div>
                                        
                                        <!-- 百分比预览 -->
                                        <div class="percent-preview">
                                            <div class="preview-card">
                                                <div class="preview-header">
                                                    <el-icon><TrendCharts /></el-icon>
                                                    <span>百分比奖励预览</span>
                                                </div>
                                                <div class="preview-content">
                                                    <div class="percent-examples">
                                                        <div class="example-item">
                                                            <span class="example-label">充值100元:</span>
                                                            <span class="example-value">{{ (100 * (rewardValueFields.percent || 0) / 100).toFixed(2) }}元奖励</span>
                                                        </div>
                                                        <div class="example-item">
                                                            <span class="example-label">充值500元:</span>
                                                            <span class="example-value">{{ (500 * (rewardValueFields.percent || 0) / 100).toFixed(2) }}元奖励</span>
                                                        </div>
                                                        <div class="example-item">
                                                            <span class="example-label">充值1000元:</span>
                                                            <span class="example-value">{{ (1000 * (rewardValueFields.percent || 0) / 100).toFixed(2) }}元奖励</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="percent-tip">
                                            <el-icon><InfoFilled /></el-icon>
                                            <span>用户将获得充值金额 {{ rewardValueFields.percent || 0 }}% 的奖励</span>
                                        </div>
                                        
                                        <!-- 快速设置按钮 -->
                                        <div class="quick-settings">
                                            <span class="quick-label">快速设置:</span>
                                            <el-button-group>
                                                <el-button size="small" @click="setQuickPercent(10)">10%</el-button>
                                                <el-button size="small" @click="setQuickPercent(20)">20%</el-button>
                                                <el-button size="small" @click="setQuickPercent(50)">50%</el-button>
                                                <el-button size="small" @click="setQuickPercent(100)">100%</el-button>
                                            </el-button-group>
                                        </div>
                                    </div>
                        </template>
                                        </div>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>配置奖励的具体数值，影响用户获得的奖励金额</span>
                                    </div>
                                </div>
                    </el-form-item>
                        </div>
                    </div>

                    <!-- 充值金额配置 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><Money /></el-icon>
                            <span>充值金额配置</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('amount')">
                                    <el-icon><ArrowDown v-if="!expandedSections.amount" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.amount }">
                    <el-form-item label="充值金额配置" prop="amount_list">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <div class="config-header">
                                            <el-button type="primary" @click="addAmountItem" size="small" :icon="Plus">
                                                新增金额项
                                            </el-button>
                        </div>

                        <div class="amount-list-container">
                            <div v-for="(item, index) in amountListFields" :key="item.id || index" class="amount-item">
                                <el-input
                                    v-model.number="item.amount"
                                    placeholder="金额"
                                    type="number"
                                                    class="enhanced-input"
                                    :min="0"
                                    @change="validateAmountItem(item)"
                                >
                                    <template #append>元</template>
                                </el-input>

                                                <el-checkbox v-model="item.recommend" class="recommend-checkbox">推荐</el-checkbox>

                                <el-input
                                    v-model.number="item.reward_percent"
                                    placeholder="奖励百分比"
                                    type="number"
                                                    class="enhanced-input"
                                    :min="0"
                                    :max="100"
                                    @change="validateAmountItem(item)"
                                >
                                    <template #append>%</template>
                                </el-input>

                                                <el-button type="danger" :icon="Delete" @click="removeAmountItem(index)" circle plain class="delete-btn" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>配置用户可选择的充值金额和对应的奖励比例</span>
                            </div>
                        </div>
                    </el-form-item>
                        </div>
                    </div>

                    <!-- 支付通道配置 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><CreditCard /></el-icon>
                            <span>支付通道配置</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('channels')">
                                    <el-icon><ArrowDown v-if="!expandedSections.channels" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.channels }">
                    <el-form-item label="支付通道配置" prop="pay_channels">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <div class="config-header">
                                            <el-button type="primary" @click="addPayChannel" size="small" :icon="Plus">
                                                新增通道
                                            </el-button>
                        </div>

                        <div class="channel-list-container">
                            <div v-for="(item, index) in payChannelsFields" :key="item.id || index" class="channel-item">
                                                <el-input v-model="item.channel" placeholder="通道标识" class="enhanced-input" />

                                <el-input
                                    v-model.number="item.reward_percent"
                                    placeholder="奖励百分比"
                                    type="number"
                                                    class="enhanced-input"
                                    :min="0"
                                    :max="100"
                                >
                                    <template #append>%</template>
                                </el-input>

                                                <el-button type="danger" :icon="Delete" @click="removePayChannel(index)" circle plain class="delete-btn" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>配置不同支付通道的奖励比例</span>
                            </div>
                        </div>
                    </el-form-item>
                        </div>
                    </div>

                    <!-- 奖励比例配置 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><Money /></el-icon>
                            <span>奖励比例配置</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('reward_percent')">
                                    <el-icon><ArrowDown v-if="!expandedSections.reward_percent" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.reward_percent }">
                    <el-form-item label="活动奖励比例" prop="reward_percent">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <el-input 
                                            v-model.number="formData.reward_percent" 
                                            placeholder="请输入活动奖励比例" 
                                            type="number" 
                                            :min="0" 
                                            :max="1000"
                                            class="enhanced-input"
                                        >
                            <template #append>%</template>
                        </el-input>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>设置首充活动的总奖励比例，最高1000%</span>
                                    </div>
                                </div>
                    </el-form-item>

                    <el-form-item label="立即到账比例" prop="lg_reward_percent">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <el-input 
                                            v-model.number="formData.lg_reward_percent" 
                                            placeholder="请输入立即到账比例" 
                                            type="number" 
                                            :min="0" 
                                            :max="100"
                                            class="enhanced-input"
                                        >
                            <template #append>%</template>
                        </el-input>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>设置立即到账的奖励比例，剩余部分通过任务获得</span>
                                    </div>
                                </div>
                    </el-form-item>
                        </div>
                    </div>

                    <!-- 每日奖励比例 (固定6天) -->
                    <!-- 每日奖励配置 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><Calendar /></el-icon>
                            <span>每日奖励配置</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('daily')">
                                    <el-icon><ArrowDown v-if="!expandedSections.daily" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.daily }">
                    <el-form-item label="每日领取比例" prop="day_reward_percent">
                                <div class="setting-item">
                                    <div class="setting-control">
                        <div class="day-reward-list">
                            <div v-for="(percent, index) in dayRewardPercentFields" :key="index" class="day-reward-item">
                                                <div class="day-info">
                                <span class="day-label">第{{ index + 1 }}天</span>
                                                    <span class="day-desc">签到奖励</span>
                                                </div>
                                <el-input
                                    v-model.number="dayRewardPercentFields[index]"
                                    placeholder="比例"
                                    type="number"
                                    :min="0"
                                    :max="100"
                                                    class="enhanced-input"
                                                    @change="validateDayRewardPercent(index)"
                                >
                                    <template #append>%</template>
                                </el-input>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>配置连续6天的每日签到奖励比例</span>
                            </div>
                        </div>
                    </el-form-item>
                        </div>
                    </div>

                    <!-- 投注奖励配置 -->
                    <!-- 投注累计奖励配置 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><Trophy /></el-icon>
                            <span>投注累计奖励配置</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('bet')">
                                    <el-icon><ArrowDown v-if="!expandedSections.bet" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.bet }">
                    <el-form-item label="投注累计奖励" prop="bet_sum_reward">
                                <div class="setting-item">
                                    <div class="setting-control">
                        <div class="bet-reward-container">
                                            <div class="reward-item">
                                                <span class="reward-label">每投注</span>
                                                <el-input v-model.number="betSumRewardFields.base" placeholder="基数" type="number" class="enhanced-input">
                                <template #append>次</template>
                            </el-input>
                                            </div>

                                            <div class="reward-item">
                                                <span class="reward-label">奖励</span>
                                                <el-input v-model.number="betSumRewardFields.reward" placeholder="奖励金额" type="number" class="enhanced-input">
                                <template #append>元</template>
                            </el-input>
                                            </div>

                                            <div class="reward-item">
                                                <span class="reward-label">最多</span>
                            <el-input
                                v-model.number="betSumRewardFields.max_reward_percent"
                                placeholder="最高奖励比例"
                                type="number"
                                                    class="enhanced-input"
                            >
                                <template #append>%</template>
                            </el-input>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>配置投注累计奖励的触发条件和奖励金额</span>
                                    </div>
                        </div>
                    </el-form-item>
                        </div>
                    </div>

                    <!-- 投注任务奖励配置 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><Aim /></el-icon>
                            <span>投注任务奖励配置</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('bet_task')">
                                    <el-icon><ArrowDown v-if="!expandedSections.bet_task" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.bet_task }">
                    <el-form-item label="投注任务奖励" prop="bet_test_reward">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <div class="bet-task-container">
                                            <div class="task-item">
                                                <span class="task-label">投注金额</span>
                                                <span class="task-desc">达到</span>
                                                <el-input v-model.number="betTestRewardFields.multiple" placeholder="充值金额倍数" type="number" class="enhanced-input">
                                <template #append>倍</template>
                            </el-input>
                                            </div>

                                            <div class="task-item">
                                                <span class="task-label">奖励</span>
                                                <el-input v-model.number="betTestRewardFields.reward_percent" placeholder="奖励比例" type="number" class="enhanced-input">
                                <template #append>%</template>
                            </el-input>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>配置投注任务奖励的触发条件和奖励比例</span>
                                    </div>
                        </div>
                    </el-form-item>
                        </div>
                    </div>

                    <!-- 弹窗倒计时配置 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><Timer /></el-icon>
                            <span>弹窗倒计时配置</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('countdown')">
                                    <el-icon><ArrowDown v-if="!expandedSections.countdown" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.countdown }">
                    <el-form-item label="弹窗倒计时" prop="countdown_seconds">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <el-input 
                                            v-model.number="formData.countdown_seconds" 
                                            placeholder="请输入弹窗倒计时秒数" 
                                            type="number" 
                                            class="enhanced-input"
                                        >
                            <template #append>秒</template>
                        </el-input>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>弹窗显示时长，建议设置为7200秒（2小时）</span>
                                    </div>
                                </div>
                    </el-form-item>
                        </div>
                    </div>

                    <!-- 任务配置 -->
                    <div class="form-section">
                        <div class="section-title">
                            <el-icon><Timer /></el-icon>
                            <span>任务配置</span>
                            <div class="section-actions">
                                <el-button size="small" type="text" @click="toggleSection('task')">
                                    <el-icon><ArrowDown v-if="!expandedSections.task" /><ArrowUp v-else /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        
                        <div class="section-content" :class="{ 'collapsed': !expandedSections.task }">
                    <el-form-item label="任务有效期" prop="task_valid_days">
                                <div class="setting-item">
                                    <div class="setting-control">
                                        <el-input 
                                            v-model.number="formData.task_valid_days" 
                                            placeholder="请输入奖励任务有效期天数" 
                                            type="number"
                                            class="enhanced-input"
                                        >
                            <template #append>天</template>
                        </el-input>
                                    </div>
                                    <div class="setting-tip">
                                        <el-icon><InfoFilled /></el-icon>
                                        <span>设置用户完成任务的有效期，建议7-30天</span>
                                    </div>
                                </div>
                    </el-form-item>
                        </div>
                    </div>

                </el-form>

                <!-- 表单操作按钮 -->
                <div class="form-actions">
                    <el-button type="primary" @click="submit" :loading="submitting" :icon="Check" size="large">
                        {{ submitting ? '保存中...' : '保存配置' }}
                    </el-button>
                    <el-button @click="resetForm" :icon="Refresh" size="large">重置表单</el-button>
                    <el-button type="info" @click="previewConfig" :icon="View" size="large">预览配置</el-button>
                </div>

                <!-- 配置预览区域 -->
                <div class="config-preview">
                    <div class="preview-header">
                        <div class="preview-title">
                            <el-icon><View /></el-icon>
                            <span>配置预览</span>
                        </div>
                        <div class="preview-actions">
                            <el-button type="text" @click="togglePreview" :icon="expandedSections.preview ? ArrowUp : ArrowDown">
                                {{ expandedSections.preview ? '收起' : '展开' }}
                            </el-button>
                        </div>
                    </div>
                    
                    <div class="preview-content" :class="{ 'collapsed': !expandedSections.preview }">
                        <!-- 快速概览 -->
                        <div class="quick-overview">
                            <div class="overview-cards">
                                <div class="overview-card">
                                    <div class="card-icon">
                                        <el-icon><Money /></el-icon>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-title">金额配置</div>
                                        <div class="card-value">{{ amountListFields.length }}项</div>
                                    </div>
                                </div>
                                <div class="overview-card">
                                    <div class="card-icon">
                                        <el-icon><CreditCard /></el-icon>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-title">支付通道</div>
                                        <div class="card-value">{{ payChannelsFields.length }}个</div>
                                    </div>
                                </div>
                                <div class="overview-card">
                                    <div class="card-icon">
                                        <el-icon><Calendar /></el-icon>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-title">每日奖励</div>
                                        <div class="card-value">{{ dayRewardPercentFields.length }}天</div>
                                    </div>
                                </div>
                                <div class="overview-card">
                                    <div class="card-icon">
                                        <el-icon><Trophy /></el-icon>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-title">奖励比例</div>
                                        <div class="card-value">{{ formData.reward_percent || 0 }}%</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 详细配置 -->
                        <div class="detailed-config">
                            <el-tabs v-model="activePreviewTab" class="preview-tabs">
                                <el-tab-pane label="金额配置" name="amount">
                                    <div class="config-detail">
                                        <div class="detail-header">
                                            <el-icon><Money /></el-icon>
                                            <span>充值金额配置</span>
                                        </div>
                                        <div class="detail-content">
                                            <div v-if="amountListFields.length === 0" class="empty-state">
                                                <el-icon><InfoFilled /></el-icon>
                                                <span>暂无金额配置</span>
                                            </div>
                                            <div v-else class="config-list">
                                                <div v-for="(item, index) in amountListFields" :key="index" class="config-item">
                                                    <div class="item-label">金额: {{ item.amount }}元</div>
                                                    <div class="item-value">奖励: {{ item.reward_percent }}%</div>
                                                    <div v-if="item.recommend" class="item-badge">推荐</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </el-tab-pane>
                                
                                <el-tab-pane label="支付通道" name="channels">
                                    <div class="config-detail">
                                        <div class="detail-header">
                                            <el-icon><CreditCard /></el-icon>
                                            <span>支付通道配置</span>
                                        </div>
                                        <div class="detail-content">
                                            <div v-if="payChannelsFields.length === 0" class="empty-state">
                                                <el-icon><InfoFilled /></el-icon>
                                                <span>暂无支付通道配置</span>
                                            </div>
                                            <div v-else class="config-list">
                                                <div v-for="(item, index) in payChannelsFields" :key="index" class="config-item">
                                                    <div class="item-label">通道: {{ item.channel }}</div>
                                                    <div class="item-value">奖励: {{ item.reward_percent }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </el-tab-pane>
                                
                                <el-tab-pane label="每日奖励" name="daily">
                                    <div class="config-detail">
                                        <div class="detail-header">
                                            <el-icon><Calendar /></el-icon>
                                            <span>每日奖励配置</span>
                                        </div>
                                        <div class="detail-content">
                                            <div v-if="dayRewardPercentFields.length === 0" class="empty-state">
                                                <el-icon><InfoFilled /></el-icon>
                                                <span>暂无每日奖励配置</span>
                                            </div>
                                            <div v-else class="config-list">
                                                <div v-for="(item, index) in dayRewardPercentFields" :key="index" class="config-item">
                                                    <div class="item-label">第{{ index + 1 }}天</div>
                                                    <div class="item-value">奖励: {{ item }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </el-tab-pane>
                                
                                <el-tab-pane label="JSON格式" name="json">
                                    <div class="config-detail">
                                        <div class="detail-header">
                                            <el-icon><Document /></el-icon>
                                            <span>JSON格式预览</span>
                                        </div>
                                        <div class="detail-content">
                                            <div class="json-sections">
                        <div class="json-section">
                            <h5>金额配置:</h5>
                            <pre>{{ prettyPrintJSON(amountListFields) }}</pre>
                        </div>
                        <div class="json-section">
                            <h5>支付通道配置:</h5>
                            <pre>{{ prettyPrintJSON(payChannelsFields) }}</pre>
                        </div>
                        <div class="json-section">
                            <h5>每日奖励比例:</h5>
                            <pre>{{ prettyPrintJSON(dayRewardPercentFields) }}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </el-tab-pane>
                            </el-tabs>
                        </div>
                    </div>
                </div>
            </div>
        </ContentWrap>

        <!-- 配置预览对话框 -->
        <el-dialog
            v-model="showPreviewDialog"
            title="配置预览"
            width="80%"
            :close-on-click-modal="false"
        >
            <div class="preview-content">
                <div class="preview-section">
                    <h3>基本信息</h3>
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="活动标题">{{ previewConfigData.title || '未设置' }}</el-descriptions-item>
                        <el-descriptions-item label="奖励策略">{{ previewConfigData.reward_strategy || '未设置' }}</el-descriptions-item>
                        <el-descriptions-item label="充值奖励">{{ previewConfigData.enable_reward ? '启用' : '禁用' }}</el-descriptions-item>
                        <el-descriptions-item label="弹窗倒计时">{{ previewConfigData.countdown_seconds }}秒</el-descriptions-item>
                    </el-descriptions>
                </div>

                <div class="preview-section">
                    <h3>奖励配置</h3>
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="活动奖励比例">{{ previewConfigData.reward_percent }}%</el-descriptions-item>
                        <el-descriptions-item label="立即到账比例">{{ previewConfigData.lg_reward_percent || 0 }}%</el-descriptions-item>
                        <el-descriptions-item label="弹窗倒计时">{{ previewConfigData.countdown_seconds }}秒</el-descriptions-item>
                        <el-descriptions-item label="任务有效期">{{ previewConfigData.task_valid_days }}天</el-descriptions-item>
                    </el-descriptions>
                </div>

                <div class="preview-section">
                    <h3>详细配置</h3>
                    <el-tabs>
                        <el-tab-pane label="金额配置" name="amount">
                            <pre>{{ prettyPrintJSON(previewConfigData.amount_list) }}</pre>
                        </el-tab-pane>
                        <el-tab-pane label="支付通道" name="channels">
                            <pre>{{ prettyPrintJSON(previewConfigData.pay_channels) }}</pre>
                        </el-tab-pane>
                        <el-tab-pane label="每日奖励" name="daily">
                            <pre>{{ prettyPrintJSON(previewConfigData.day_reward_percent) }}</pre>
                        </el-tab-pane>
                        <el-tab-pane label="投注奖励" name="bet">
                            <pre>{{ prettyPrintJSON(previewConfigData.bet_sum_reward) }}</pre>
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
            
            <template #footer>
                <el-button @click="showPreviewDialog = false">关闭</el-button>
                <el-button type="primary" @click="submit">保存配置</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, reactive, computed } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus'
import { Calendar, Trophy, Aim, Timer, InfoFilled, Present, View, Refresh, Document, Money, Lightning, Plus, ArrowRight, TrendCharts, Check, Edit, ArrowDown, ArrowUp, CreditCard, Delete } from '@element-plus/icons-vue'
import { baTableApi } from '/@/api/common'

interface ActivityConfig {
    id: number
    title: string
    context: string
    amount_list: string
    pay_channels: string
    enable_reward: 0 | 1
    reward_strategy: string
    reward_value: string | null
    update_time: number | null
    countdown_seconds: number
    task_valid_days: number
    popup_enabled: 0 | 1
    daily_trigger_limit: number
    reward_percent: number
    lg_reward_percent: number | null
    day_reward_percent: string | null
    bet_sum_reward: string | null
    bet_test_reward: string | null
}

interface AmountItem {
    id?: number
    amount: number
    recommend: boolean
    reward_percent: number
}

interface PayChannel {
    id?: string
    channel: string
    reward_percent: number
}

interface BetSumReward {
    base: number
    reward: number
    max_reward_percent: number
}

interface BetTestReward {
    multiple: number
    reward_percent: number
}

const api = new baTableApi('/admin/activity.first_deposit_270/')
const formRef = ref<FormInstance>()
const submitting = ref(false)
const loading = ref(false)

const formData = reactive<ActivityConfig>({
    id: 1,
    title: '',
    context: '',
    amount_list: '[]',
    pay_channels: '[]',
    enable_reward: 0,
    reward_strategy: '',
    reward_value: null,
    update_time: null,
    countdown_seconds: 7200,
    task_valid_days: 7,
    popup_enabled: 1,
    daily_trigger_limit: 1,
    reward_percent: 270,
    lg_reward_percent: null,
    day_reward_percent: null,
    bet_sum_reward: null,
    bet_test_reward: null,
})

const rewardValueFields = reactive<Record<string, any>>({})
const amountListFields = ref<AmountItem[]>([])
const payChannelsFields = ref<PayChannel[]>([])
const dayRewardPercentFields = ref<number[]>(Array(6).fill(0))
const betSumRewardFields = reactive<BetSumReward>({
    base: 100,
    reward: 2,
    max_reward_percent: 50,
})
const betTestRewardFields = reactive<BetTestReward>({
    multiple: 60,
    reward_percent: 80,
})

// 新增响应式数据
const showPreviewDialog = ref(false)
const previewConfigData = ref<any>({})

// 区块展开/收起状态
const expandedSections = reactive<Record<string, boolean>>({
    basic: true,
    reward: true,
    amount: true,
    channels: true,
    reward_percent: true,
    task: true,
    daily: true,
    bet: true,
    bet_task: true,
    countdown: true,
    preview: true
})

const activePreviewTab = ref('amount')

// 新增计算属性
const rangePercentage = computed(() => {
    const min = rewardValueFields.min || 0
    const max = rewardValueFields.max || 0
    if (max === 0) return 0
    return Math.min((min / max) * 100, 100)
})

const averageReward = computed(() => {
    const min = rewardValueFields.min || 0
    const max = rewardValueFields.max || 0
    return ((min + max) / 2).toFixed(2)
})

const rewardRange = computed(() => {
    const min = rewardValueFields.min || 0
    const max = rewardValueFields.max || 0
    return (max - min).toFixed(2)
})

const rules = {
    title: [{ required: true, message: '请输入活动标题', trigger: 'blur' }],
    context: [{ required: true, message: '请输入活动说明内容', trigger: 'blur' }],
    reward_strategy: [{ required: false, message: '请选择奖励策略', trigger: 'change' }],
    enable_reward: [{ required: true, message: '请设置开关', trigger: 'change' }],
    reward_value: [
        {
            validator: (rule: any, value: string | null, callback: any) => {
                if (!formData.reward_strategy) return callback()
                try {
                    const val = value ? JSON.parse(value) : {}
                    if (formData.reward_strategy === 'range' && (!val.min || !val.max)) {
                        callback(new Error('必须填写最小值和最大值'))
                    } else if (formData.reward_strategy === 'fixed' && !val.fixed) {
                        callback(new Error('必须填写固定值'))
                    } else if (formData.reward_strategy === 'percent' && !val.percent) {
                        callback(new Error('必须填写百分比值'))
                    } else {
                        callback()
                    }
                } catch {
                    callback(new Error('JSON格式错误'))
                }
            },
            trigger: 'blur',
        },
    ],
    amount_list: [
        {
            validator: (rule: any, value: string, callback: any) => {
                if (amountListFields.value.length === 0) {
                    callback(new Error('至少需要配置一个金额项'))
                    return
                }
                const hasError = amountListFields.value.some((item) => item.amount <= 0 || item.reward_percent < 0 || item.reward_percent > 100)
                hasError ? callback(new Error('请检查金额项配置')) : callback()
            },
            trigger: 'blur',
        },
    ],
    pay_channels: [
        {
            validator: (rule: any, value: string, callback: any) => {
                if (payChannelsFields.value.length === 0) {
                    callback(new Error('至少需要配置一个支付通道'))
                    return
                }
                const hasError = payChannelsFields.value.some((item) => !item.channel || item.reward_percent < 0 || item.reward_percent > 100)
                hasError ? callback(new Error('请检查支付通道配置')) : callback()
            },
            trigger: 'blur',
        },
    ],
    reward_percent: [
        { required: true, message: '请输入活动奖励比例', trigger: 'blur' },
        {
            validator: (rule: any, value: number, callback: any) => {
                if (value < 0) {
                    callback(new Error('比例不能为负数'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
    lg_reward_percent: [
        {
            validator: (rule: any, value: number, callback: any) => {
                if (value < 0) {
                    callback(new Error('比例不能为负数'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
    day_reward_percent: [
        {
            validator: (rule: any, value: string | null, callback: any) => {
                if (dayRewardPercentFields.value.some((percent) => percent < 0 || percent > 100)) {
                    callback(new Error('每日比例必须在0-100之间'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
    countdown_seconds: [
        {
            validator: (rule: any, value: number, callback: any) => {
                if (value < 0) {
                    callback(new Error('倒计时不能为负数'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
    task_valid_days: [
        {
            validator: (rule: any, value: number, callback: any) => {
                if (value < 1 || value > 30) {
                    callback(new Error('有效期必须在1-30天之间'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
    daily_trigger_limit: [
        {
            validator: (rule: any, value: number, callback: any) => {
                if (value < 1 || value > 10) {
                    callback(new Error('限制必须在1-10之间'))
                } else {
                    callback()
                }
            },
            trigger: 'blur',
        },
    ],
}

const previewData = computed(() => ({
    ...formData,
    reward_value: filteredRewardValue.value,
    amount_list: amountListFields.value,
    pay_channels: payChannelsFields.value,
    day_reward_percent: dayRewardPercentFields.value,
    bet_sum_reward: betSumRewardFields,
    bet_test_reward: betTestRewardFields,
}))

const filteredRewardValue = computed(() => {
    if (formData.reward_strategy === 'range') {
        return { min: rewardValueFields.min, max: rewardValueFields.max }
    } else if (formData.reward_strategy === 'fixed') {
        return { fixed: rewardValueFields.fixed }
    } else if (formData.reward_strategy === 'percent') {
        return { percent: rewardValueFields.percent }
    }
    return null
})

const getDefaultAmountItem = (): AmountItem => {
    const lastAmount = amountListFields.value.length > 0 ? amountListFields.value[amountListFields.value.length - 1].amount : 0
    return {
        amount: lastAmount + 100,
        recommend: false,
        reward_percent: 0,
    }
}

const addAmountItem = () => amountListFields.value.push(getDefaultAmountItem())
const removeAmountItem = (index: number) => amountListFields.value.splice(index, 1)

const validateAmountItem = (item: AmountItem) => {
    if (item.amount < 0) {
        item.amount = 0
        ElMessage.warning('金额不能为负数')
    }
    if (item.reward_percent < 0) {
        item.reward_percent = 0
    } else if (item.reward_percent > 100) {
        item.reward_percent = 100
    }
}

const validateDayRewardPercent = (index: number) => {
    if (dayRewardPercentFields.value[index] < 0) {
        dayRewardPercentFields.value[index] = 0
    } else if (dayRewardPercentFields.value[index] > 100) {
        dayRewardPercentFields.value[index] = 100
    }
}

const addPayChannel = () => {
    payChannelsFields.value.push({
        channel: '',
        reward_percent: 0,
    })
}
const removePayChannel = (index: number) => payChannelsFields.value.splice(index, 1)

const objToArray = (obj: any) => {
    if (!obj) return []
    if (Array.isArray(obj)) return obj
    return Object.values(obj)
}

watch(
    () => [formData.reward_strategy, formData.reward_value],
    () => {
        try {
            const parsed = formData.reward_value ? JSON.parse(formData.reward_value) : {}
            Object.assign(rewardValueFields, parsed)
        } catch {
            Object.keys(rewardValueFields).forEach((k) => delete rewardValueFields[k])
        }
    },
    { immediate: true }
)

watch(
    () => formData.amount_list,
    () => {
        try {
            const parsed = JSON.parse(formData.amount_list || '[]')
            amountListFields.value = objToArray(parsed).map((item: any) => ({
                amount: Number(item.amount) || 0,
                recommend: !!item.recommend,
                reward_percent: Number(item.reward_percent) || 0,
            }))
        } catch {
            amountListFields.value = []
        }
    },
    { immediate: true }
)

watch(
    () => formData.pay_channels,
    () => {
        try {
            const parsed = JSON.parse(formData.pay_channels || '[]')
            payChannelsFields.value = objToArray(parsed).map((item: any) => ({
                channel: item.channel || '',
                reward_percent: Number(item.reward_percent) || 0,
            }))
        } catch {
            payChannelsFields.value = []
        }
    },
    { immediate: true }
)

watch(
    () => formData.day_reward_percent,
    (newVal) => {
        try {
            if (!newVal) {
                dayRewardPercentFields.value = Array(6).fill(0)
                return
            }

            const parsed = typeof newVal === 'string' ? JSON.parse(newVal) : newVal
            const sourceArray = Array.isArray(parsed) ? parsed : []

            dayRewardPercentFields.value = Array(6)
                .fill(0)
                .map((_, i) => {
                    const val = sourceArray[i]
                    return val !== undefined && val !== null ? Number(val) : 0
                })
        } catch (e) {
            console.error('解析day_reward_percent错误:', e)
            dayRewardPercentFields.value = Array(6).fill(0)
        }
    },
    { immediate: true }
)

watch(
    () => formData.bet_sum_reward,
    () => {
        try {
            const parsed = formData.bet_sum_reward ? JSON.parse(formData.bet_sum_reward) : {}
            Object.assign(betSumRewardFields, {
                base: Number(parsed.base) || 100,
                reward: Number(parsed.reward) || 2,
                max_reward_percent: Number(parsed.max_reward_percent) || 50,
            })
        } catch {
            Object.assign(betSumRewardFields, {
                base: 100,
                reward: 2,
                max_reward_percent: 50,
            })
        }
    },
    { immediate: true }
)

watch(
    () => formData.bet_test_reward,
    () => {
        try {
            const parsed = formData.bet_test_reward ? JSON.parse(formData.bet_test_reward) : {}
            Object.assign(betTestRewardFields, {
                multiple: Number(parsed.multiple) || 60,
                reward_percent: Number(parsed.reward_percent) || 80,
            })
        } catch {
            Object.assign(betTestRewardFields, {
                multiple: 60,
                reward_percent: 80,
            })
        }
    },
    { immediate: true }
)

watch(
    rewardValueFields,
    () => {
        formData.reward_value = formData.reward_strategy ? JSON.stringify(filteredRewardValue.value) : null
    },
    { deep: true }
)

watch(
    amountListFields,
    () => {
        formData.amount_list = JSON.stringify(amountListFields.value)
    },
    { deep: true }
)

watch(
    payChannelsFields,
    () => {
        formData.pay_channels = JSON.stringify(payChannelsFields.value)
    },
    { deep: true }
)

watch(
    dayRewardPercentFields,
    () => {
        formData.day_reward_percent = JSON.stringify(dayRewardPercentFields.value)
    },
    { deep: true }
)

watch(
    betSumRewardFields,
    () => {
        formData.bet_sum_reward = JSON.stringify(betSumRewardFields)
    },
    { deep: true }
)

watch(
    betTestRewardFields,
    () => {
        formData.bet_test_reward = JSON.stringify(betTestRewardFields)
    },
    { deep: true }
)

const submit = async () => {
    try {
        await formRef.value?.validate()
        submitting.value = true

        const postData = {
            ...formData,
            reward_value: formData.reward_strategy ? JSON.stringify(filteredRewardValue.value) : null,
            amount_list: JSON.stringify(amountListFields.value),
            pay_channels: JSON.stringify(payChannelsFields.value),
            day_reward_percent: JSON.stringify(dayRewardPercentFields.value),
            bet_sum_reward: JSON.stringify(betSumRewardFields),
            bet_test_reward: JSON.stringify(betTestRewardFields),
            update_time: Math.floor(Date.now() / 1000),
        }

        const res = await api.postData('edit', postData)
        if (res.code === 1) {
            ElMessage.success('保存成功')
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (error) {
        console.error('提交错误:', error)
        if (!(error as any).response) {
            ElMessage.error('提交配置失败，请检查表单')
        }
    } finally {
        submitting.value = false
    }
}

const handleError = (error: unknown) => {
    if (error instanceof Error) {
        ElMessage.error(`操作失败: ${error.message}`)
    } else if (typeof error === 'string') {
        ElMessage.error(error)
    } else {
        ElMessage.error('发生未知错误')
    }
    console.error(error)
}

const getInfo = async () => {
    loading.value = true
    try {
        const res = await api.edit({ id: 1 })
        if (res.code === 1) {
            const row = res.data.row

            // 处理day_reward_percent数据
            // 初始化默认值
            dayRewardPercentFields.value = Array(6).fill(0)

            if (row.day_reward_percent) {
                console.log('原始day_reward_percent:', row.day_reward_percent, typeof row.day_reward_percent)

                try {
                    // 1. 解析数据
                    let parsed
                    if (typeof row.day_reward_percent === 'string') {
                        try {
                            parsed = JSON.parse(row.day_reward_percent)
                        } catch (jsonError) {
                            parsed = row.day_reward_percent
                        }
                    } else {
                        parsed = row.day_reward_percent
                    }

                    console.log('解析后的parsed:', parsed, '是否为数组:', Array.isArray(parsed))

                    // 2. 处理数组或类数组对象
                    if (Array.isArray(parsed) || (typeof parsed === 'object' && parsed !== null)) {
                        // 转换类数组对象为真数组
                        const sourceArray = Array.isArray(parsed) ? parsed : Object.values(parsed)

                        dayRewardPercentFields.value = Array(6)
                            .fill(0)
                            .map((_, i) => {
                                const val = sourceArray[i]
                                console.log(`第${i + 1}天值:`, val)
                                const numVal = Number(val)
                                return !isNaN(numVal) ? Math.max(0, Math.min(100, numVal)) : 0
                            })
                    }
                    // 3. 处理单个数值
                    else if (!isNaN(Number(parsed))) {
                        const numVal = Math.min(100, Math.max(0, Number(parsed)))
                        dayRewardPercentFields.value[0] = numVal
                    }
                    // 4. 无法识别的格式
                    else {
                        console.warn('无法识别的day_reward_percent格式:', parsed)
                    }
                } catch (e) {
                    console.error('解析day_reward_percent错误:', e)
                }
            }

            console.log('最终dayRewardPercentFields:', dayRewardPercentFields.value)

            Object.assign(formData, {
                id: row.id || 1,
                title: row.title || '',
                context: row.context || '',
                amount_list: typeof row.amount_list === 'object' ? JSON.stringify(row.amount_list) : row.amount_list || '[]',
                pay_channels: typeof row.pay_channels === 'object' ? JSON.stringify(row.pay_channels) : row.pay_channels || '[]',
                enable_reward: Number(row.enable_reward) || 0,
                reward_strategy: row.reward_strategy || '',
                reward_value: typeof row.reward_value === 'object' ? JSON.stringify(row.reward_value) : row.reward_value || null,
                countdown_seconds: Number(row.countdown_seconds) || 7200,
                task_valid_days: Number(row.task_valid_days) || 7,
                popup_enabled: Number(row.popup_enabled) || 0,
                daily_trigger_limit: Number(row.daily_trigger_limit) || 1,
                reward_percent: Number(row.reward_percent) || 270,
                lg_reward_percent: row.lg_reward_percent !== null ? Number(row.lg_reward_percent) : null,
                day_reward_percent: row.day_reward_percent || null,
                bet_sum_reward: typeof row.bet_sum_reward === 'object' ? JSON.stringify(row.bet_sum_reward) : row.bet_sum_reward || null,
                bet_test_reward: typeof row.bet_test_reward === 'object' ? JSON.stringify(row.bet_test_reward) : row.bet_test_reward || null,
            })
        } else {
            ElMessage.error(res.msg || '加载配置失败')
        }
    } catch (error) {
        handleError(error)
    } finally {
        loading.value = false
    }
}

onMounted(getInfo)

const prettyPrintJSON = (obj: any) => {
    try {
        return JSON.stringify(obj, null, 2)
    } catch {
        return '{}'
    }
}

// 新增方法
const handleStrategyChange = (value: string) => {
    // 清空之前的奖励值配置
    Object.keys(rewardValueFields).forEach(key => {
        delete rewardValueFields[key]
    })
    
    // 根据策略类型初始化默认值
    if (value === 'fixed') {
        rewardValueFields.fixed = 10
    } else if (value === 'range') {
        rewardValueFields.min = 5
        rewardValueFields.max = 20
    } else if (value === 'percent') {
        rewardValueFields.percent = 10
    }
}


const previewConfig = () => {
    previewConfigData.value = {
        ...formData,
        reward_value: filteredRewardValue.value,
        amount_list: amountListFields.value,
        pay_channels: payChannelsFields.value,
        day_reward_percent: dayRewardPercentFields.value,
        bet_sum_reward: betSumRewardFields,
        bet_test_reward: betTestRewardFields,
    }
    showPreviewDialog.value = true
}

const resetForm = () => {
    ElMessageBox.confirm(
        '确定要重置表单吗？这将清空所有已填写的内容。',
        '确认重置',
        {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning',
        }
    ).then(() => {
        // 重置表单数据
        Object.assign(formData, {
            id: 1,
            title: '',
            context: '',
            amount_list: '[]',
            pay_channels: '[]',
            enable_reward: 0,
            reward_strategy: '',
            reward_value: null,
            update_time: null,
            countdown_seconds: 7200,
            task_valid_days: 7,
            popup_enabled: 1,
            daily_trigger_limit: 1,
            reward_percent: 270,
            lg_reward_percent: null,
            day_reward_percent: null,
            bet_sum_reward: null,
            bet_test_reward: null,
        })
        
        // 重置其他数据
        amountListFields.value = []
        payChannelsFields.value = []
        dayRewardPercentFields.value = Array(6).fill(0)
        
        Object.assign(betSumRewardFields, {
            base: 100,
            reward: 2,
            max_reward_percent: 50,
        })
        
        Object.assign(betTestRewardFields, {
            multiple: 60,
            reward_percent: 80,
        })
        
        ElMessage.success('表单已重置')
    }).catch(() => {
        // 用户取消重置
    })
}

// 新增验证方法
const validateRangeInput = () => {
    const min = rewardValueFields.min || 0
    const max = rewardValueFields.max || 0
    
    if (min > max && max > 0) {
        ElMessage.warning('最小值不能大于最大值')
        rewardValueFields.min = max
    }
    
    if (min < 0) {
        rewardValueFields.min = 0
    }
    
    if (max < 0) {
        rewardValueFields.max = 0
    }
}

const validateFixedInput = () => {
    const value = rewardValueFields.fixed || 0
    
    if (value < 0) {
        rewardValueFields.fixed = 0
        ElMessage.warning('奖励金额不能为负数')
    }
    
    if (value > 999999) {
        rewardValueFields.fixed = 999999
        ElMessage.warning('奖励金额不能超过999999元')
    }
}

const validatePercentInput = () => {
    const value = rewardValueFields.percent || 0
    
    if (value < 0) {
        rewardValueFields.percent = 0
        ElMessage.warning('百分比不能为负数')
    }
    
    if (value > 100) {
        rewardValueFields.percent = 100
        ElMessage.warning('百分比不能超过100%')
    }
}

// 新增快速设置方法
const setQuickRange = (type: string) => {
    switch (type) {
        case 'low':
            rewardValueFields.min = 5
            rewardValueFields.max = 20
            break
        case 'medium':
            rewardValueFields.min = 20
            rewardValueFields.max = 50
            break
        case 'high':
            rewardValueFields.min = 50
            rewardValueFields.max = 200
            break
    }
    ElMessage.success(`已设置为${type === 'low' ? '小额' : type === 'medium' ? '中额' : '大额'}奖励范围`)
}

const setQuickFixed = (value: number) => {
    rewardValueFields.fixed = value
    ElMessage.success(`已设置为固定奖励${value}元`)
}

const setQuickPercent = (value: number) => {
    rewardValueFields.percent = value
    ElMessage.success(`已设置为奖励比例${value}%`)
}

// 新增方法
const toggleSection = (section: string) => {
    expandedSections[section] = !expandedSections[section]
}

const togglePreview = () => {
    expandedSections.preview = !expandedSections.preview
}

const expandAllSections = () => {
    Object.keys(expandedSections).forEach(key => {
        expandedSections[key] = true
    })
}

const collapseAllSections = () => {
    Object.keys(expandedSections).forEach(key => {
        expandedSections[key] = false
    })
}
</script>

<style scoped>
/* 活动配置容器 */
.activity-config-container {
    background: #f5f7fa;
    min-height: 100vh;
    padding: 20px;
}

/* 页面头部 */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding: 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    color: white;
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    pointer-events: none;
}

.header-info {
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
    z-index: 1;
}

.header-icon-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.header-icon {
    font-size: 48px;
    color: #ffd700;
    filter: drop-shadow(0 3px 6px rgba(255, 215, 0, 0.4));
    font-weight: bold;
}

.icon-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ff4757;
    color: white;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(255, 71, 87, 0.4);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.header-text h2 {
    margin: 0 0 8px 0;
    font-size: 32px;
    font-weight: 800;
    text-shadow: 0 3px 6px rgba(0, 0, 0, 0.2);
    letter-spacing: 0.5px;
}

.header-text p {
    margin: 0 0 12px 0;
    font-size: 16px;
    opacity: 0.95;
    line-height: 1.6;
    font-weight: 500;
}

.header-stats {
    display: flex;
    gap: 24px;
    margin-top: 8px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
    font-weight: 600;
}

.stat-value {
    font-size: 18px;
    font-weight: 700;
    color: #ffd700;
}

.header-actions {
    display: flex;
    gap: 12px;
    position: relative;
    z-index: 1;
}

.header-actions .el-button {
    font-weight: 600;
    font-size: 14px;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.header-actions .el-button {
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.header-actions .el-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

/* 表单区块 */
.form-section {
    background: #ffffff;
    border-radius: 16px;
    padding: 0;
    margin-bottom: 24px;
    border: 1px solid #e9ecef;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.form-section:hover {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 22px 28px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-bottom: 1px solid #e9ecef;
    font-size: 20px;
    font-weight: 700;
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    letter-spacing: 0.3px;
}

.section-title .el-icon {
    margin-right: 12px;
    color: #ffd700;
    font-size: 22px;
    filter: drop-shadow(0 1px 2px rgba(255, 215, 0, 0.3));
}

.section-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-content {
    padding: 24px;
    transition: all 0.3s ease;
    overflow: hidden;
}

.section-content.collapsed {
    max-height: 0;
    padding: 0 24px;
    opacity: 0;
}

/* 增强的输入框样式 */
.enhanced-input {
    border-radius: 8px;
    transition: all 0.3s ease;
}

.enhanced-input:focus-within {
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
}

.enhanced-textarea {
    border-radius: 8px;
    transition: all 0.3s ease;
}

.enhanced-textarea:focus-within {
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
}

/* 统一设置项样式 */
.setting-item {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.setting-control {
    display: flex;
    align-items: center;
    gap: 12px;
}

.setting-tip {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #e1f3d8;
    border: 1px solid #b3e19d;
    border-radius: 8px;
    font-size: 14px;
    color: #529b2e;
    font-weight: 500;
}

.setting-tip .el-icon {
    color: #67c23a;
    font-size: 16px;
    font-weight: bold;
}

/* 增强的选择器样式 */
.enhanced-select {
    border-radius: 8px;
    transition: all 0.3s ease;
}

.enhanced-select:focus-within {
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
}

/* 配置头部样式 */
.config-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding: 12px 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.config-header .el-button {
    border-radius: 6px;
    font-weight: 500;
}



/* 选项内容 */
.option-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.option-label {
    font-weight: 600;
    color: #303133;
}

.option-desc {
    font-size: 12px;
    color: #909399;
}

/* 奖励值配置 */
.reward-value-config {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.range-config {
    display: flex;
    align-items: center;
    gap: 12px;
}

.range-separator {
    font-weight: 600;
    color: #606266;
}

.range-tip,
.fixed-tip,
.percent-tip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #e1f3d8;
    border: 1px solid #b3e19d;
    border-radius: 6px;
    font-size: 12px;
    color: #529b2e;
}

.range-tip .el-icon,
.fixed-tip .el-icon,
.percent-tip .el-icon {
    color: #67c23a;
}

/* 预览对话框 */
.preview-content {
    max-height: 60vh;
    overflow-y: auto;
}

.preview-section {
    margin-bottom: 24px;
}

.preview-section h3 {
    margin-bottom: 16px;
    color: #303133;
    font-size: 16px;
    font-weight: 600;
}

.preview-section pre {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 12px;
    font-size: 12px;
    line-height: 1.5;
    overflow-x: auto;
}

/* 奖励值配置增强样式 */
.input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.input-label {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
}

.range-config {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    margin-bottom: 16px;
}

.range-separator {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 8px 0;
    color: #606266;
    font-weight: 600;
}

.range-separator .el-icon {
    font-size: 16px;
}

/* 范围预览卡片 */
.range-preview,
.fixed-preview,
.percent-preview {
    margin: 16px 0;
}

.preview-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
}

.preview-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: #e9ecef;
    font-weight: 600;
    color: #495057;
}

.preview-header .el-icon {
    color: #6c757d;
}

.preview-content {
    padding: 16px;
}

/* 范围显示 */
.range-display {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.range-min,
.range-max {
    font-size: 18px;
    font-weight: 600;
    color: #303133;
    min-width: 60px;
    text-align: center;
}

.range-bar {
    flex: 1;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    position: relative;
    overflow: hidden;
}

.range-fill {
    height: 100%;
    background: linear-gradient(90deg, #67c23a 0%, #409eff 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.range-stats {
    display: flex;
    justify-content: space-between;
    gap: 16px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.stat-label {
    font-size: 12px;
    color: #909399;
}

.stat-value {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
}

/* 固定金额显示 */
.fixed-display {
    text-align: center;
}

.amount-display {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 4px;
    margin-bottom: 8px;
}

.amount-value {
    font-size: 32px;
    font-weight: 700;
    color: #67c23a;
}

.amount-unit {
    font-size: 18px;
    color: #909399;
}

.amount-desc {
    font-size: 14px;
    color: #606266;
}

/* 百分比示例 */
.percent-examples {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.example-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.example-label {
    font-size: 14px;
    color: #606266;
}

.example-value {
    font-size: 14px;
    font-weight: 600;
    color: #67c23a;
}

/* 快速设置 */
.quick-settings {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.quick-label {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
    white-space: nowrap;
}

.el-button-group .el-button {
    border-radius: 4px;
}

.el-button-group .el-button:first-child {
    border-top-left-radius: 4px;
    border-bottom-left-radius: 4px;
}

.el-button-group .el-button:last-child {
    border-top-right-radius: 4px;
    border-bottom-right-radius: 4px;
}

.config-content {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.config-form {
    padding: 24px;
    background: #fff;
}

/* 金额配置容器 */
.amount-list-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* 支付通道容器 */
.channel-list-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* 配置区块标题 */
.section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
}

.section-title .el-icon {
    font-size: 18px;
}

/* 每日奖励比例列表 */

.day-reward-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}

.day-reward-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #fff;
    border: 2px solid #e4e7ed;
    border-radius: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.day-reward-item:hover {
    border-color: #409eff;
    box-shadow: 0 4px 12px rgba(64, 158, 255, 0.15);
    transform: translateY(-2px);
}

.day-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 80px;
}

.day-label {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
}

.day-desc {
    font-size: 12px;
    color: #909399;
}

.day-reward-input {
    flex: 1;
    max-width: 120px;
}

/* 投注奖励配置 */

.bet-reward-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.reward-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #fff;
    border: 2px solid #e4e7ed;
    border-radius: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.reward-item:hover {
    border-color: #67c23a;
    box-shadow: 0 4px 12px rgba(103, 194, 58, 0.15);
    transform: translateY(-2px);
}

.reward-label {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
    min-width: 60px;
}

/* 投注任务奖励配置 */

.bet-task-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.task-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #fff;
    border: 2px solid #e4e7ed;
    border-radius: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.task-item:hover {
    border-color: #e6a23c;
    box-shadow: 0 4px 12px rgba(230, 162, 60, 0.15);
    transform: translateY(-2px);
}

.task-label {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
    min-width: 60px;
}

.task-desc {
    font-size: 12px;
    color: #909399;
}

.task-input {
    flex: 1;
    max-width: 150px;
}

/* 倒计时配置 */


/* 单个配置项 */
.amount-item,
.channel-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #fafbfc;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.amount-item:hover,
.channel-item:hover {
    background: #f0f2f5;
    border-color: #c0c4cc;
}

/* 输入框样式 */
.amount-input {
    width: 180px;
}

.channel-input {
    width: 200px;
}

.percent-input {
    width: 150px;
}

.reward-input {
    width: 120px;
}

.strategy-select {
    width: 200px;
}

/* 推荐复选框 */
.recommend-checkbox {
    margin: 0 12px;
}

/* 删除按钮 */
.delete-btn {
    margin-left: auto;
    flex-shrink: 0;
}

/* 按钮间距 */
.mb-3 {
    margin-bottom: 12px;
}

/* 表单操作按钮区域 */
.form-actions {
    display: flex;
    justify-content: center;
    gap: 16px;
    padding: 24px;
    margin-top: 20px;
    background: #fafbfc;
    border-top: 1px solid #e4e7ed;
    border-radius: 0 0 8px 8px;
}

.form-actions .el-button {
    min-width: 120px;
    height: 44px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.form-actions .el-button--primary {
    background: linear-gradient(135deg, #409eff 0%, #66b3ff 100%);
    border: none;
    box-shadow: 0 4px 12px rgba(64, 158, 255, 0.3);
}

.form-actions .el-button--primary:hover {
    background: linear-gradient(135deg, #337ecc 0%, #5ba0f2 100%);
    box-shadow: 0 6px 16px rgba(64, 158, 255, 0.4);
    transform: translateY(-2px);
}

.form-actions .el-button:not(.el-button--primary) {
    background: #fff;
    border: 2px solid #e4e7ed;
    color: #606266;
}

.form-actions .el-button:not(.el-button--primary):hover {
    border-color: #409eff;
    color: #409eff;
    background: #f0f9ff;
    transform: translateY(-1px);
}

/* 配置预览区域 */
.config-preview {
    margin-top: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #e4e7ed;
}

.preview-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 18px;
    font-weight: 600;
    color: #303133;
}

.preview-title .el-icon {
    font-size: 20px;
    color: #409eff;
}

.preview-actions .el-button {
    color: #606266;
    font-size: 14px;
}

.preview-content {
    padding: 24px;
    transition: all 0.3s ease;
}

.preview-content.collapsed {
    display: none;
}

/* 快速概览 */
.quick-overview {
    margin-bottom: 24px;
}

.overview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.overview-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #f8f9fa;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.overview-card:hover {
    background: #e9ecef;
    border-color: #409eff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(64, 158, 255, 0.15);
}

.card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #409eff 0%, #66b3ff 100%);
    border-radius: 8px;
    color: #fff;
    font-size: 18px;
}

.card-content {
    flex: 1;
}

.card-title {
    font-size: 14px;
    color: #606266;
    margin-bottom: 4px;
}

.card-value {
    font-size: 18px;
    font-weight: 600;
    color: #303133;
}

/* 详细配置 */
.detailed-config {
    margin-top: 24px;
}

.preview-tabs {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
}

.config-detail {
    padding: 20px;
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 16px;
    font-weight: 600;
    color: #303133;
}

.detail-header .el-icon {
    color: #409eff;
}

.detail-content {
    min-height: 120px;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 40px;
    color: #909399;
    font-size: 14px;
}

.empty-state .el-icon {
    font-size: 24px;
}

.config-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 12px;
}

.config-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px;
    background: #f8f9fa;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    position: relative;
}

.item-label {
    font-size: 14px;
    color: #606266;
    font-weight: 500;
}

.item-value {
    font-size: 16px;
    color: #303133;
    font-weight: 600;
}

.item-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #f56c6c;
    color: #fff;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 500;
}

/* JSON格式预览 */
.json-sections {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.json-section {
    background: #f8f9fa;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    padding: 16px;
}

.json-section h5 {
    margin: 0 0 12px 0;
    color: #409eff;
    font-size: 14px;
    font-weight: 600;
}

.json-section pre {
    margin: 0;
    padding: 12px;
    background: #fff;
    border-radius: 4px;
    overflow-x: auto;
    color: #606266;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', Consolas, monospace;
    font-size: 13px;
    line-height: 1.6;
}


/* 平板设备优化 */
@media (max-width: 1024px) and (min-width: 769px) {
    .page-header {
        padding: 24px;
    }

    .header-text h2 {
        font-size: 28px;
    }

    .header-text p {
        font-size: 15px;
    }

    .section-title {
        padding: 20px 24px;
        font-size: 18px;
    }

    .section-content {
        padding: 18px;
    }

    .day-reward-list {
        grid-template-columns: repeat(2, 1fr);
    }

    .bet-reward-container,
    .bet-task-container {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 16px;
    }

    .reward-item,
    .task-item {
        flex: 1;
        min-width: 200px;
    }
}

/* 移动端表单布局优化 */
@media (max-width: 768px) {
    /* Element Plus 表单项移动端布局 */
    .el-form-item {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        margin-bottom: 20px;
    }

    .el-form-item__label {
        width: 100% !important;
        text-align: left !important;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 14px;
        color: #303133;
        padding: 0 !important;
        line-height: 1.4;
    }

    .el-form-item__content {
        width: 100% !important;
        margin-left: 0 !important;
        flex: 1;
    }

    /* 输入框全宽显示 */
    .el-input,
    .el-select,
    .el-textarea {
        width: 100%;
    }

    .el-input__wrapper,
    .el-select__wrapper {
        width: 100%;
    }

    /* 开关组件移动端优化 */
    .el-switch {
        align-self: flex-start;
        margin-top: 4px;
    }

    /* 复选框移动端优化 */
    .el-checkbox {
        margin-top: 8px;
    }

    /* 按钮组移动端优化 */
    .el-button-group {
        width: 100%;
        display: flex;
    }

    .el-button-group .el-button {
        flex: 1;
    }
    .activity-config-container {
        padding: 10px;
    }

    .config-form {
        padding: 16px;
    }

    .section-title {
        font-size: 14px;
        padding: 10px 12px;
    }

    .day-reward-list {
        grid-template-columns: 1fr;
    }

    .day-reward-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .day-info {
        min-width: auto;
    }

    .day-reward-input {
        max-width: 100%;
        width: 100%;
    }

    .bet-reward-container,
    .bet-task-container {
        gap: 12px;
    }

    .reward-item,
    .task-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .reward-label,
    .task-label {
        min-width: auto;
    }

    .reward-input,
    .task-input {
        width: 100%;
        max-width: 100%;
    }

    .countdown-input {
        max-width: 100%;
        width: 100%;
    }

    .amount-item,
    .channel-item {
        flex-wrap: wrap;
    }

    .amount-input,
    .channel-input,
    .percent-input,
    .reward-input {
        width: 100%;
    }

    .delete-btn {
        margin-left: 0;
        margin-top: 8px;
    }

    .recommend-checkbox {
        margin: 8px 0;
    }

    .json-preview {
        padding: 16px;
    }

    /* 新增响应式样式 */
    .page-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
        padding: 20px;
    }

    .header-info {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }

    .header-icon-wrapper {
        align-self: center;
    }

    .header-icon {
        font-size: 40px;
    }

    .icon-badge {
        font-size: 12px;
        padding: 4px 8px;
        top: -6px;
        right: -6px;
    }

    .header-text h2 {
        font-size: 24px;
        margin-bottom: 6px;
    }

    .header-text p {
        font-size: 14px;
        margin-bottom: 10px;
    }

    .header-stats {
        justify-content: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .stat-item {
        flex-direction: column;
        gap: 4px;
    }

    .stat-label {
        font-size: 12px;
    }

    .stat-value {
        font-size: 16px;
    }

    .header-actions {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .header-actions .el-button {
        flex: 1;
        min-width: 120px;
        font-size: 13px;
        padding: 10px 16px;
        flex-wrap: wrap;
    }

    .section-title {
        padding: 16px 20px;
        font-size: 16px;
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }

    .section-title .el-icon {
        font-size: 18px;
        margin-right: 0;
        margin-bottom: 4px;
    }

    .section-actions {
        align-self: center;
    }

    .section-content {
        padding: 16px;
    }

    .setting-item {
        gap: 8px;
    }

    .setting-control {
        flex-direction: column;
        gap: 8px;
    }

    .setting-tip {
        font-size: 13px;
        padding: 8px 12px;
    }

    /* 自定义组件移动端优化 */
    .day-reward-item {
        flex-direction: column;
        gap: 8px;
        padding: 12px;
        align-items: stretch;
    }

    .day-info {
        text-align: center;
        margin-bottom: 8px;
    }

    .day-label {
        font-size: 15px;
        font-weight: 700;
    }

    .day-desc {
        font-size: 13px;
        margin-top: 2px;
    }

    .day-reward-input {
        width: 100%;
        max-width: none;
    }

    /* 投注奖励配置移动端优化 */
    .bet-reward-container,
    .bet-task-container {
        gap: 12px;
    }

    .reward-item,
    .task-item {
        flex-direction: column;
        gap: 6px;
        text-align: center;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .reward-label,
    .task-label {
        font-size: 13px;
        font-weight: 600;
        color: #495057;
    }

    .task-desc {
        font-size: 12px;
        color: #6c757d;
        margin: 2px 0;
    }

    .reward-input,
    .task-input {
        width: 100%;
        max-width: none;
    }

    /* 金额配置移动端优化 */
    .amount-item,
    .channel-item {
        flex-direction: column;
        gap: 8px;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
        margin-bottom: 12px;
    }

    .amount-input,
    .channel-input,
    .percent-input {
        width: 100%;
    }

    .recommend-checkbox {
        margin: 8px 0;
        align-self: center;
    }

    .delete-btn {
        align-self: center;
        margin-top: 8px;
    }

    .range-config {
        flex-direction: column;
        align-items: stretch;
    }

    .input-group {
        flex-direction: column;
        gap: 8px;
    }

    .input-label {
        font-size: 13px;
        font-weight: 600;
        text-align: center;
        margin-bottom: 4px;
    }

    .range-separator {
        display: none;
    }

    .range-preview,
    .fixed-preview,
    .percent-preview {
        margin-top: 12px;
    }

    .preview-card {
        padding: 12px;
    }

    .preview-header {
        font-size: 14px;
        margin-bottom: 8px;
    }

    .preview-content {
        font-size: 13px;
    }

    .quick-settings {
        margin-top: 12px;
    }

    .quick-label {
        font-size: 13px;
        margin-bottom: 8px;
        text-align: center;
    }

    .el-button-group {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .el-button-group .el-button {
        flex: 1;
        min-width: 60px;
        font-size: 12px;
        padding: 6px 8px;
    }

    .range-separator {
        text-align: center;
        padding: 8px 0;
    }

    .preview-content {
        max-height: 50vh;
    }

    .header-actions .el-button {
        flex: 1;
        min-width: 120px;
    }

    .range-stats {
        flex-direction: column;
        gap: 8px;
    }

    .quick-settings {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }

    .quick-label {
        text-align: center;
    }

    .el-button-group {
        width: 100%;
    }

    .el-button-group .el-button {
        flex: 1;
    }
}

@media (max-width: 480px) {
    /* 小屏设备表单进一步优化 */
    .el-form-item {
        margin-bottom: 16px;
    }

    .el-form-item__label {
        font-size: 13px;
        margin-bottom: 6px;
        font-weight: 700;
    }

    .el-form-item__content {
        margin-left: 0 !important;
    }

    /* 输入框样式优化 */
    .el-input__wrapper {
        min-height: 44px;
        font-size: 16px;
    }

    .el-textarea__inner {
        min-height: 80px;
        font-size: 16px;
    }

    /* 选择器优化 */
    .el-select {
        width: 100%;
    }

    .el-select__wrapper {
        min-height: 44px;
    }

    /* 开关组件优化 */
    .el-switch {
        transform: scale(1.1);
        margin-top: 6px;
    }

    .el-switch__label {
        font-size: 14px;
        font-weight: 600;
    }

    /* 复选框优化 */
    .el-checkbox {
        transform: scale(1.05);
        margin-top: 6px;
    }

    .el-checkbox__label {
        font-size: 14px;
        font-weight: 600;
    }

    .activity-config-container {
        padding: 8px;
    }

    .page-header {
        padding: 16px;
        margin-bottom: 12px;
    }

    .header-icon {
        font-size: 36px;
    }

    .icon-badge {
        font-size: 10px;
        padding: 3px 6px;
        top: -4px;
        right: -4px;
    }

    .header-text h2 {
        font-size: 20px;
        margin-bottom: 4px;
    }

    .header-text p {
        font-size: 12px;
        margin-bottom: 8px;
        line-height: 1.4;
    }

    .header-stats {
        gap: 12px;
    }

    .stat-label {
        font-size: 11px;
    }

    .stat-value {
        font-size: 14px;
    }

    .header-actions {
        flex-direction: column;
        gap: 8px;
    }

    .header-actions .el-button {
        width: 100%;
        font-size: 12px;
        padding: 8px 12px;
    }

    .section-title {
        padding: 12px 16px;
        font-size: 14px;
    }

    .section-title .el-icon {
        font-size: 16px;
    }

    .section-content {
        padding: 12px;
    }

    .setting-tip {
        font-size: 12px;
        padding: 6px 10px;
    }

    .form-section {
        padding: 12px;
        margin-bottom: 16px;
    }

    .day-reward-list {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .day-reward-item {
        flex-direction: column;
        gap: 8px;
        padding: 12px;
    }

    .day-info {
        text-align: center;
    }

    .bet-reward-container,
    .bet-task-container {
        gap: 12px;
    }

    .reward-item,
    .task-item {
        flex-direction: column;
        gap: 6px;
        text-align: center;
    }

    .reward-label,
    .task-label {
        font-size: 13px;
    }

    .enhanced-input,
    .enhanced-select {
        width: 100%;
    }

    .config-header {
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }

    .config-header .el-button {
        width: 100%;
    }

    .config-form {
        padding: 12px;
    }
}

/* 触摸设备优化 */
@media (hover: none) and (pointer: coarse) {
    .form-section {
        margin-bottom: 20px;
    }

    .section-title {
        padding: 18px 20px;
        font-size: 17px;
    }

    .section-title .el-button {
        padding: 8px 12px;
        font-size: 14px;
    }

    .setting-tip {
        padding: 10px 12px;
        font-size: 14px;
    }

    .enhanced-input,
    .enhanced-select {
        min-height: 44px;
        font-size: 16px;
    }

    .el-button {
        min-height: 44px;
        font-size: 16px;
    }

    .el-switch {
        transform: scale(1.2);
    }

    .el-checkbox {
        transform: scale(1.1);
    }

    .delete-btn {
        min-width: 44px;
        min-height: 44px;
    }

    /* 表单操作按钮移动端适配 */
    .form-actions {
        flex-direction: column;
        gap: 12px;
        padding: 16px;
    }

    .form-actions .el-button {
        width: 100%;
        min-width: auto;
        height: 48px;
        font-size: 16px;
    }

    /* 配置预览移动端适配 */
    .preview-header {
        flex-direction: column;
        gap: 12px;
        padding: 16px;
        text-align: center;
    }

    .preview-title {
        font-size: 16px;
    }

    .overview-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .overview-card {
        padding: 12px;
    }

    .card-icon {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }

    .card-title {
        font-size: 13px;
    }

    .card-value {
        font-size: 16px;
    }

    .config-list {
        grid-template-columns: 1fr;
    }

    .config-item {
        padding: 10px;
    }

    .detail-header {
        font-size: 15px;
    }

    .config-detail {
        padding: 16px;
    }
}
</style>
