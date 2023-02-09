<template>
  <div class="app-container">
    <el-tabs v-model="activeActivity">
      <el-tab-pane label="UNTREATED INVOICES" name="untreated">
        <untreated-invoices />
      </el-tab-pane>
      <el-tab-pane label="PARTIALLY TREATED INVOICES" name="partially_treated">
        <partially-treated-invoices />
      </el-tab-pane>
    </el-tabs>
  </div>
</template>
<script>
import UntreatedInvoices from './partials/UntreatedInvoices';
import PartiallyTreatedInvoices from './partials/PartiallyTreatedInvoices';
// const deleteItemInStock = new Resource('stock/items-in-stock/delete');
export default {
  name: 'InvoiceReversals',
  components: { UntreatedInvoices, PartiallyTreatedInvoices },
  data() {
    return {
      activeActivity: 'untreated',

    };
  },
};
</script>
