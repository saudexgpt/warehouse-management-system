<template>
  <el-card>
    <el-alert :closable="false" type="error"><h4>Set your preferences to get the specific parameters for your query</h4></el-alert>
    <hr>
    <el-row :gutter="10">
      <el-col :xs="24" :sm="12" :md="12">
        <label for="">Select Warehouse</label>
        <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" class="span" filterable @input="setStrParam">
          <!-- <el-option value="all" label="All" /> -->
          <el-option v-for="(warehouse, index) in params.warehouses" :key="index" :value="warehouse.id" :label="warehouse.name" />

        </el-select>

      </el-col>
      <el-col :xs="24" :sm="12" :md="12">
        <br>
        <el-popover
          placement="right"
          trigger="click"
        >
          <date-range-picker :from="$route.query.from" :to="$route.query.to" :panel="panel" :panels="panels" :submit-title="submitTitle" :future="future" @update="setDateRange" />
          <el-button id="pick_outbound_date" slot="reference" type="success">
            <i class="el-icon-date" /> Pick Date Range
          </el-button>
        </el-popover>
      </el-col>

    </el-row>
    <hr>
    <el-row>
      <el-col :span="24">
        <div>
          <span>
            <h3>Customers List</h3>
            <el-input :value="`${baseUrl}/report/customers`" class="form-control" readonly>
              <template #append>
                <el-button @click="copyToClipboard(`${baseUrl}/report/customers`)">Copy API</el-button>
              </template>
            </el-input>

          </span>
          <hr>
        </div>
      </el-col>
      <el-col :span="24">
        <div>
          <span>
            <h3>Instant Balances</h3>
            <el-input :value="`${baseUrl}/report/instant-balances/${strParams}`" class="form-control" readonly>
              <template #append>
                <el-button @click="copyToClipboard(`${baseUrl}/report/instant-balances/${strParams}`)">Copy API</el-button>
              </template>
            </el-input>

          </span>
          <hr>
        </div>
      </el-col>
      <el-col :span="24">
        <div>
          <span>
            <h3>All Generated Invoices</h3>
            <el-input :value="`${baseUrl}/report/all-generated-invoices/${strParams}`" class="form-control" readonly>
              <template #append>
                <el-button @click="copyToClipboard(`${baseUrl}/report/all-generated-invoices/${strParams}`)">Copy API</el-button>
              </template>
            </el-input>

          </span>
          <hr>
        </div>
      </el-col>
      <el-col :span="24">
        <div>
          <span>
            <h3>Unsupplied Invoices</h3>
            <el-input :value="`${baseUrl}/report/unsupplied-invoices/${strParams}`" class="form-control" readonly>
              <template #append>
                <el-button @click="copyToClipboard(`${baseUrl}/report/unsupplied-invoices/${strParams}`)">Copy API</el-button>
              </template>
            </el-input>

          </span>
          <hr>
        </div>
      </el-col>
    </el-row>
  </el-card>
</template>
<script>
import moment from 'moment';
export default {
  name: 'APIs',
  components: {},
  data() {
    return {
      strParams: '',
      form: {
        warehouse_id: 1,
        from: '',
        to: '',
        is_download: 'yes',
      },
      total: 0,
      submitTitle: 'Fetch Report',
      panel: 'month',
      future: false,
      panels: ['range', 'week', 'month', 'quarter', 'year'],
      show_calendar: false,
      table_title: '',
      in_warehouse: '',
      invoice: {},
      selected_row_index: '',
      downloadLoading: false,
      filename: 'Unsupplied Invoices',

    };
  },
  computed: {
    baseUrl() {
      return this.$store.getters.baseUrl;
    },
    params() {
      return this.$store.getters.params;
    },
  },
  methods: {
    moment,
    format(date) {
      var month = date.toLocaleString('en-US', { month: 'short' });
      return month + ' ' + date.getDate() + ', ' + date.getFullYear();
    },
    setDateRange(values){
      const app = this;
      document.getElementById('pick_outbound_date').click();
      let panel = app.panel;
      let from = app.week_start;
      let to = app.week_end;
      if (values !== '') {
        to = this.format(new Date(values.to));
        from = this.format(new Date(values.from));
        panel = values.panel;
      }
      app.form.from = from;
      app.form.to = to;
      app.form.panel = panel;
      app.setStrParam();
    },
    setStrParam(){
      const app = this;
      app.strParams = `?from=${app.form.from}&to=${app.form.to}&warehouse_id=${app.form.warehouse_id}&is_download=${app.form.is_download}`;
    },
    copyToClipboard(text) {
      navigator.clipboard.writeText(text);
      this.$message('API link copied to clipboard');
    },
  },
};
</script>

