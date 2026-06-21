import { createApp } from 'vue';
import CustomerGroups from './components/CustomerGroups.vue';
import DailySettlementReport from './components/DailySettlementReport.vue';

const app = createApp({});

app.component('customer-groups', CustomerGroups);
app.component('daily-settlement-report', DailySettlementReport);

app.mount('#app');

