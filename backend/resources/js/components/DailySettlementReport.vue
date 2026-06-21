<template>
  <div class="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">
            日结算报表
          </h1>
          <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm mt-1">
            查看每日订单、收支和利润统计
          </p>
        </div>
        <button
          @click="exportReport"
          :disabled="exporting"
          class="inline-flex items-center gap-2 px-5 py-2 bg-[#1b1b18] text-white rounded-sm border border-[#1b1b18] hover:bg-black transition-all dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:border-[#eeeeec] dark:hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          {{ exporting ? '导出中...' : '导出CSV' }}
        </button>
      </div>

      <div class="bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-sm p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
          <div class="flex-1 min-w-[160px]">
            <label class="block text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-1">
              开始日期
            </label>
            <input
              v-model="filters.date_from"
              type="date"
              class="w-full px-3 py-2 bg-[#FDFDFC] dark:bg-[#0a0a0a] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm text-sm text-[#1b1b18] dark:text-[#EDEDEC] focus:outline-none focus:border-[#1b1b18] dark:focus:border-[#eeeeec]"
              @change="fetchReport"
            />
          </div>
          <div class="flex-1 min-w-[160px]">
            <label class="block text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-1">
              结束日期
            </label>
            <input
              v-model="filters.date_to"
              type="date"
              class="w-full px-3 py-2 bg-[#FDFDFC] dark:bg-[#0a0a0a] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm text-sm text-[#1b1b18] dark:text-[#EDEDEC] focus:outline-none focus:border-[#1b1b18] dark:focus:border-[#eeeeec]"
              @change="fetchReport"
            />
          </div>
          <div class="flex-1 min-w-[160px]">
            <label class="block text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-1">
              订单类型
            </label>
            <select
              v-model="filters.type"
              class="w-full px-3 py-2 bg-[#FDFDFC] dark:bg-[#0a0a0a] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm text-sm text-[#1b1b18] dark:text-[#EDEDEC] focus:outline-none focus:border-[#1b1b18] dark:focus:border-[#eeeeec]"
              @change="fetchReport"
            >
              <option value="">全部</option>
              <option value="supplier_purchase">采购订单</option>
              <option value="distributor_order">分销订单</option>
              <option value="agent_order">代理订单</option>
            </select>
          </div>
          <div class="flex gap-2">
            <button
              @click="setQuickDate(7)"
              :class="[
                'px-4 py-2 text-sm rounded-sm border transition-colors',
                quickDate === 7
                  ? 'bg-[#1b1b18] text-white border-[#1b1b18] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:border-[#eeeeec]'
                  : 'border-[#e3e3e0] dark:border-[#3E3E3A] text-[#1b1b18] dark:text-[#EDEDEC] hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a]'
              ]"
            >
              近7天
            </button>
            <button
              @click="setQuickDate(30)"
              :class="[
                'px-4 py-2 text-sm rounded-sm border transition-colors',
                quickDate === 30
                  ? 'bg-[#1b1b18] text-white border-[#1b1b18] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:border-[#eeeeec]'
                  : 'border-[#e3e3e0] dark:border-[#3E3E3A] text-[#1b1b18] dark:text-[#EDEDEC] hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a]'
              ]"
            >
              近30天
            </button>
            <button
              @click="setQuickDate(90)"
              :class="[
                'px-4 py-2 text-sm rounded-sm border transition-colors',
                quickDate === 90
                  ? 'bg-[#1b1b18] text-white border-[#1b1b18] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:border-[#eeeeec]'
                  : 'border-[#e3e3e0] dark:border-[#3E3E3A] text-[#1b1b18] dark:text-[#EDEDEC] hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a]'
              ]"
            >
              近90天
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="p-12 text-center">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-[#e3e3e0] dark:border-[#3E3E3A] border-t-[#1b1b18] dark:border-t-[#eeeeec]"></div>
        <p class="mt-4 text-[#706f6c] dark:text-[#A1A09A]">加载中...</p>
      </div>

      <template v-else>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div class="bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-sm p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm">总订单数</p>
                <p class="text-2xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC] mt-1">
                  {{ summary?.total_orders || 0 }}
                </p>
                <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mt-1">
                  日均 {{ summary?.avg_daily_orders || 0 }} 单
                </p>
              </div>
              <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                  <line x1="3" y1="6" x2="21" y2="6"></line>
                  <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-sm p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm">销售金额</p>
                <p class="text-2xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC] mt-1">
                  ¥{{ formatNumber(summary?.total_sales_amount || 0) }}
                </p>
                <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mt-1">
                  采购 ¥{{ formatNumber(summary?.total_purchase_amount || 0) }}
                </p>
              </div>
              <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-600 dark:text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="1" x2="12" y2="23"></line>
                  <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-sm p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm">收入</p>
                <p class="text-2xl font-semibold text-green-600 dark:text-green-400 mt-1">
                  ¥{{ formatNumber(summary?.total_income || 0) }}
                </p>
                <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mt-1">
                  支出 ¥{{ formatNumber(summary?.total_expense || 0) }}
                </p>
              </div>
              <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-sm p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm">净利润</p>
                <p :class="[
                  'text-2xl font-semibold mt-1',
                  (summary?.net_profit || 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-[#f53003] dark:text-[#FF4433]'
                ]">
                  ¥{{ formatNumber(summary?.net_profit || 0) }}
                </p>
                <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mt-1">
                  未收 ¥{{ formatNumber(summary?.total_unpaid_amount || 0) }}
                </p>
              </div>
              <div :class="[
                'w-12 h-12 rounded-full flex items-center justify-center',
                (summary?.net_profit || 0) >= 0 ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'
              ]">
                <svg xmlns="http://www.w3.org/2000/svg" :class="[
                  'w-6 h-6',
                  (summary?.net_profit || 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-[#f53003] dark:text-[#FF4433]'
                ]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                  <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <div class="bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-sm p-4">
            <h3 class="text-lg font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-4">订单统计</h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">已完成订单</span>
                <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">{{ summary?.total_completed_orders || 0 }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">待处理订单</span>
                <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">{{ summary?.total_pending_orders || 0 }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">采购订单</span>
                <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">{{ summary?.total_purchase_orders || 0 }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">分销商订单</span>
                <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">{{ summary?.total_distributor_orders || 0 }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">代理商订单</span>
                <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">{{ summary?.total_agent_orders || 0 }}</span>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-sm p-4">
            <h3 class="text-lg font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-4">支付方式统计</h3>
            <div class="space-y-4">
              <div v-for="(amount, method) in paymentMethods" :key="method" class="relative">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">{{ methodLabels[method] }}</span>
                  <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">¥{{ formatNumber(amount) }}</span>
                </div>
                <div class="w-full h-2 bg-[#e3e3e0] dark:bg-[#3E3E3A] rounded-full overflow-hidden">
                  <div
                    :class="[
                      'h-full rounded-full transition-all',
                      methodColors[method]
                    ]"
                    :style="{ width: getPaymentPercentage(amount) + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-sm overflow-hidden">
          <div class="p-4 border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
            <h3 class="text-lg font-medium text-[#1b1b18] dark:text-[#EDEDEC]">每日明细</h3>
          </div>

          <div v-if="dailyData.length === 0" class="p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto text-[#e3e3e0] dark:text-[#3E3E3A] mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <p class="text-[#706f6c] dark:text-[#A1A09A]">暂无数据</p>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-[#e3e3e0] dark:border-[#3E3E3A] bg-[#FDFDFC] dark:bg-[#0a0a0a]">
                  <th class="px-4 py-3 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider sticky left-0 bg-[#FDFDFC] dark:bg-[#0a0a0a]">
                    日期
                  </th>
                  <th class="px-4 py-3 text-center text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                    订单数
                  </th>
                  <th class="px-4 py-3 text-center text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                    已完成
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                    总金额
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                    销售金额
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                    已收
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                    收入
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                    支出
                  </th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                    净额
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
                <tr
                  v-for="day in dailyData"
                  :key="day.date"
                  class="hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] transition-colors cursor-pointer"
                  @click="toggleDayDetail(day.date)"
                >
                  <td class="px-4 py-3 sticky left-0 bg-white dark:bg-[#161615]">
                    <div class="flex items-center gap-2">
                      <svg v-if="expandedDays.includes(day.date)" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#706f6c] dark:text-[#A1A09A]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                      </svg>
                      <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#706f6c] dark:text-[#A1A09A]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                      </svg>
                      <div>
                        <div class="text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC]">
                          {{ day.date }}
                        </div>
                        <div class="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                          {{ getDayName(day.day_of_week) }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC]">
                      {{ day.orders.total }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="text-sm text-green-600 dark:text-green-400">
                      {{ day.orders.completed }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span class="text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC]">
                      ¥{{ formatNumber(day.amounts.total) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span class="text-sm text-[#1b1b18] dark:text-[#EDEDEC]">
                      ¥{{ formatNumber(day.amounts.sales) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span class="text-sm text-blue-600 dark:text-blue-400">
                      ¥{{ formatNumber(day.amounts.paid) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span class="text-sm text-green-600 dark:text-green-400">
                      ¥{{ formatNumber(day.payments.income) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span class="text-sm text-[#f53003] dark:text-[#FF4433]">
                      ¥{{ formatNumber(day.payments.expense) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span :class="[
                      'text-sm font-medium',
                      day.payments.net >= 0 ? 'text-green-600 dark:text-green-400' : 'text-[#f53003] dark:text-[#FF4433]'
                    ]">
                      ¥{{ formatNumber(day.payments.net) }}
                    </span>
                  </td>
                </tr>
                <tr v-if="expandedDays.includes(day.date)" v-for="day in dailyData" :key="'detail-' + day.date">
                  <td colspan="9" class="px-4 py-4 bg-[#FDFDFC] dark:bg-[#0a0a0a]">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                      <div>
                        <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mb-1">订单明细</p>
                        <div class="text-sm text-[#1b1b18] dark:text-[#EDEDEC] space-y-1">
                          <p>采购: {{ day.orders.purchase }}</p>
                          <p>分销: {{ day.orders.distributor }}</p>
                          <p>代理: {{ day.orders.agent }}</p>
                          <p>待处理: {{ day.orders.pending }}</p>
                        </div>
                      </div>
                      <div>
                        <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mb-1">金额明细</p>
                        <div class="text-sm text-[#1b1b18] dark:text-[#EDEDEC] space-y-1">
                          <p>采购: ¥{{ formatNumber(day.amounts.purchase) }}</p>
                          <p>销售: ¥{{ formatNumber(day.amounts.sales) }}</p>
                          <p>已收: ¥{{ formatNumber(day.amounts.paid) }}</p>
                          <p>未收: ¥{{ formatNumber(day.amounts.unpaid) }}</p>
                        </div>
                      </div>
                      <div>
                        <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mb-1">支付方式</p>
                        <div class="text-sm text-[#1b1b18] dark:text-[#EDEDEC] space-y-1">
                          <p>现金: ¥{{ formatNumber(day.payments.by_method.cash) }}</p>
                          <p>银行: ¥{{ formatNumber(day.payments.by_method.bank_transfer) }}</p>
                          <p>支付宝: ¥{{ formatNumber(day.payments.by_method.alipay) }}</p>
                          <p>微信: ¥{{ formatNumber(day.payments.by_method.wechat) }}</p>
                        </div>
                      </div>
                      <div>
                        <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mb-1">收支汇总</p>
                        <div class="text-sm text-[#1b1b18] dark:text-[#EDEDEC] space-y-1">
                          <p>收入: ¥{{ formatNumber(day.payments.income) }}</p>
                          <p>支出: ¥{{ formatNumber(day.payments.expense) }}</p>
                          <p class="font-medium">净额: <span :class="day.payments.net >= 0 ? 'text-green-600 dark:text-green-400' : 'text-[#f53003] dark:text-[#FF4433]'">¥{{ formatNumber(day.payments.net) }}</span></p>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>

      <div
        v-if="toast.show"
        :class="[
          'fixed bottom-6 right-6 z-50 px-4 py-3 rounded-sm shadow-lg flex items-center gap-3 transition-all transform',
          toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-[#f53003] text-white'
        ]"
      >
        <svg v-if="toast.type === 'success'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="15" y1="9" x2="9" y2="15"></line>
          <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>
        <span>{{ toast.message }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(false);
const exporting = ref(false);
const summary = ref(null);
const dailyData = ref([]);
const expandedDays = ref([]);
const quickDate = ref(30);

const filters = ref({
  date_from: '',
  date_to: '',
  type: '',
});

const toast = ref({
  show: false,
  message: '',
  type: 'success'
});

const methodLabels = {
  cash: '现金',
  bank_transfer: '银行转账',
  alipay: '支付宝',
  wechat: '微信支付'
};

const methodColors = {
  cash: 'bg-yellow-500',
  bank_transfer: 'bg-blue-500',
  alipay: 'bg-blue-600',
  wechat: 'bg-green-500'
};

const paymentMethods = computed(() => {
  return summary.value?.payment_methods || {
    cash: 0,
    bank_transfer: 0,
    alipay: 0,
    wechat: 0
  };
});

const getPaymentPercentage = (amount) => {
  const total = Object.values(paymentMethods.value).reduce((sum, val) => sum + val, 0);
  if (total === 0) return 0;
  return Math.round((amount / total) * 100);
};

const formatNumber = (num) => {
  return parseFloat(num).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
};

const getDayName = (dayOfWeek) => {
  const days = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
  return days[dayOfWeek];
};

const setQuickDate = (days) => {
  quickDate.value = days;
  const today = new Date();
  const pastDate = new Date();
  pastDate.setDate(today.getDate() - days + 1);

  filters.value.date_to = today.toISOString().split('T')[0];
  filters.value.date_from = pastDate.toISOString().split('T')[0];

  fetchReport();
};

const toggleDayDetail = (date) => {
  const index = expandedDays.value.indexOf(date);
  if (index > -1) {
    expandedDays.value.splice(index, 1);
  } else {
    expandedDays.value.push(date);
  }
};

const fetchReport = async () => {
  loading.value = true;
  quickDate.value = null;
  try {
    const params = { ...filters.value };
    Object.keys(params).forEach(key => {
      if (!params[key]) delete params[key];
    });

    const response = await axios.get('/api/reports/daily-settlement', { params });
    summary.value = response.data.summary;
    dailyData.value = response.data.daily_data;
  } catch (error) {
    console.error('Error fetching report:', error);
    showToast('加载报表失败', 'error');
  } finally {
    loading.value = false;
  }
};

const exportReport = async () => {
  exporting.value = true;
  try {
    const params = { ...filters.value, format: 'csv' };
    Object.keys(params).forEach(key => {
      if (!params[key]) delete params[key];
    });

    const response = await axios.get('/api/reports/daily-settlement/export', {
      params,
      responseType: 'blob'
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `daily_settlement_${filters.value.date_from || 'all'}_to_${filters.value.date_to || 'today'}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    showToast('导出成功', 'success');
  } catch (error) {
    console.error('Error exporting report:', error);
    showToast('导出失败', 'error');
  } finally {
    exporting.value = false;
  }
};

const showToast = (message, type = 'success') => {
  toast.value = {
    show: true,
    message,
    type
  };

  setTimeout(() => {
    toast.value.show = false;
  }, 3000);
};

onMounted(() => {
  setQuickDate(30);
});
</script>
