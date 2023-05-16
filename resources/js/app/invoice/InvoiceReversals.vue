<template>
  <div class="app-container">
    <el-tabs v-model="activeActivity">
      <el-tab-pane label="UNTREATED INVOICES" name="untreated">
        <untreated-invoices :warehouses="warehouses" />
      </el-tab-pane>
      <el-tab-pane label="PARTIALLY TREATED INVOICES" name="partially_treated">
        <partially-treated-invoices :warehouses="warehouses" />
      </el-tab-pane>
    </el-tabs>
  </div>
</template>
<script>
import UntreatedInvoices from './partials/UntreatedInvoices';
import PartiallyTreatedInvoices from './partials/PartiallyTreatedInvoices';
// const deleteItemInStock = new Resource('stock/items-in-stock/delete');
export default {
  components: { UntreatedInvoices, PartiallyTreatedInvoices },
  data() {
    return {
      activeActivity: 'untreated',
      warehouses: [],

    };
  },
  computed: {
    params() {
      return this.$store.getters.params;
    },
  },
  created() {
    this.fetchNecessaryParams();
  },
  methods: {
    fetchNecessaryParams() {
      const app = this;
      app.$store.dispatch('app/setNecessaryParams');
      // const params = app.params;
      app.warehouses = app.params.warehouses;
      app.currency = app.params.currency;
    },
  },
};
</script>
