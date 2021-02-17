<template>
  <div class="">
    <aside>
      <el-row :gutter="10">
        <el-col v-if="params.warehouses.length > 0" :xs="24" :sm="8" :md="8">
          <label for="">Select Warehouse</label>
          <el-select v-model="warehouse_index" placeholder="Select Warehouse" class="span" filterable @input="fetchItemStocks">
            <el-option value="all" label="All Warehouses" />
            <el-option v-for="(warehouse, index) in warehouses" :key="index" :value="index" :label="warehouse.name" />

          </el-select>

        </el-col>
        <!-- <el-col :xs="24" :sm="6" :md="6">
          <label for="">Filter by: </label>
          <el-select v-model="view_by" placeholder="Select Option" class="span" @input="fetchItemStocks">
            <el-option v-for="(view_option, index) in view_options" :key="index" :value="view_option.key" :label="view_option.name" />

          </el-select>

        </el-col> -->
        <el-col :xs="24" :sm="10" :md="10">
          <br>
          <el-popover
            placement="right"
            trigger="click"
          >
            <date-range-picker :from="$route.query.from" :to="$route.query.to" :panel="panel" :panels="panels" :submit-title="submitTitle" :future="future" @update="setDateRange" />
            <el-button id="pick_date" slot="reference" type="success">
              <i class="el-icon-date" /> Pick Date Range
            </el-button>
          </el-popover>
        </el-col>

      </el-row>
    </aside>
    <div class="box">
      <div class="box-header">
        <h4 class="box-title">{{ table_title }}</h4>
      </div>
      <div class="box-body">
        <el-button :loading="downloadLoading" style="margin:0 0 20px 20px;" type="primary" icon="document" @click="handleDownload">
          Export Excel
        </el-button>
        <v-client-table v-model="items_in_stock" :columns="columns" :options="options">
          <div slot="total_in" slot-scope="{row}" class="alert alert-info">
            {{ row['total_in'] }} {{ formatPackageType(row['package_type']) }}

          </div>
          <div slot="total_on_transit" slot-scope="{row}" class="alert alert-warning">
            {{ row['total_on_transit'] }} {{ formatPackageType(row['package_type']) }}

          </div>
          <div slot="total_delivered" slot-scope="{row}" class="alert alert-danger">
            {{ row['total_delivered'] }} {{ formatPackageType(row['package_type']) }}

          </div>
          <div slot="total_physical_count" slot-scope="{row}" class="alert alert-success">
            {{ row['total_physical_count'] }} {{ formatPackageType(row['package_type']) }}

          </div>
          <div slot="total_reserved" slot-scope="{row}" class="alert alert-primary">
            {{ row['total_reserved'] }} {{ formatPackageType(row['package_type']) }}

          </div>
          <!-- <div slot="updated_at" slot-scope="{row}">
        {{ moment(row.created_at).fromNow() }}

      </div> -->
        </v-client-table>
      </div>

    </div>

  </div>
</template>
<script>
import moment from 'moment';
import Resource from '@/api/resource';
// const fetchWarehouse = new Resource('warehouse/fetch-warehouse');
const itemsInStock = new Resource('reports/instant-balances');
export default {
  name: 'DownloadReports',
  props: {
    params: {
      type: Object,
      default: () => ([]),
    },
  },
  data() {
    return {
      warehouses: [],
      items_in_stock: [],
      view_by: null,
      columns: ['product_name', 'warehouse', 'total_in', /* 'total_out',*/ 'total_on_transit', 'total_delivered', 'total_physical_count', 'total_reserved'],

      options: {
        headings: {
          'warehouse': 'Warehouse',
          'product_name': 'Product',
          total_in: 'Quantity Stocked',
          // total_out: 'Total Supplied',
          total_on_transit: 'In Transit',
          total_delivered: 'Supplied',
          total_physical_count: 'Main Balance',
          total_reserved: 'Reserved for Supply',

          // id: 'S/N',
        },
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: ['warehouse', 'product_name', 'total_in', 'total_on_transit', 'total_delivered', 'total_physical_count', 'total_reserved'],
        filterable: ['warehouse', 'product_name', 'total_in', 'total_on_transit', 'total_delivered', 'total_physical_count', 'total_reserved'],
      },
      page: {
        option: 'list',
      },
      downloadLoading: false,
      form: {
        warehouse_id: '',
        from: '',
        to: '',
        panel: '',
      },
      submitTitle: 'Fetch Report',
      panel: 'month',
      future: false,
      panels: ['range', 'week', 'month', 'quarter', 'year'],
      show_calendar: false,
      table_title: '',
      selected_row_index: '',
      warehouse_index: '',
      view_options: [
        { key: 'product', name: 'Products' },
        { key: 'batch', name: 'Batches' },
        { key: 'sub_batch', name: 'Sub Batches' },
      ],
    };
  },

  mounted() {
    this.warehouses = this.params.warehouses;
    this.warehouse_index = 0;
    this.form.warehouse_id = this.warehouses[0].id;
    this.setDateRange('');
  },
  beforeDestroy() {

  },
  methods: {
    moment,
    formatPackageType(type){
      // var formated_type = type + 's';
      // if (type === 'Box') {
      //   formated_type = type + 'es';
      // }
      return type;
    },
    showCalendar(){
      this.show_calendar = !this.show_calendar;
    },
    format(date) {
      var month = date.toLocaleString('en-US', { month: 'short' });
      return month + ' ' + date.getDate() + ', ' + date.getFullYear();
    },
    setDateRange(values){
      const app = this;
      document.getElementById('pick_date').click();
      let panel = 'month';
      let from = app.format(new Date(app.moment().clone().startOf('month').format('YYYY-MM-DD hh:mm')));
      let to = app.format(new Date(app.moment().clone().endOf('month').format('YYYY-MM-DD hh:mm')));
      if (values !== '') {
        to = this.format(new Date(values.to));
        from = this.format(new Date(values.from));
        panel = values.panel;
      }
      app.form.from = from;
      app.form.to = to;
      app.form.panel = panel;
      app.fetchItemStocks();
      document.getElementById('pick_date').click();
    },
    fetchItemStocks() {
      const app = this;
      app.show_progress = true;
      if (app.warehouse_index === 'all') {
        app.form.warehouse_id = 'all';
        app.title = app.table_title = 'List of Products in all warehouses from: ' + app.form.from + ' to: ' + app.form.to;
      } else {
        app.form.warehouse_id = app.warehouses[app.warehouse_index].id;

        app.table_title = 'List of Products in ' + app.warehouses[app.warehouse_index].name + ' from: ' + app.form.from + ' to: ' + app.form.to;
      }

      const loader = itemsInStock.loaderShow();
      const param = app.form;
      param.view_by = app.view_by;
      itemsInStock.list(param)
        .then(response => {
          app.items_in_stock = response.items_in_stock;

          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error.message);
        });
    },
    handleDownload() {
      this.downloadLoading = true;
      import('@/vendor/Export2Excel').then(excel => {
        const multiHeader = [[this.table_title, '', '', '', '', '', '', '', '']];
        const tHeader = ['PRODUCT', 'WAREHOUSE', 'QUANTITY STOCKED', 'IN TRANSIT', 'SUPPLIED', 'MAIN BALANCE', 'RESERVED FOR SUPPLY'];
        const filterVal = this.columns;
        const list = this.items_in_stock;
        const data = this.formatJson(filterVal, list);
        excel.export_json_to_excel({
          multiHeader,
          header: tHeader,
          data,
          filename: 'Inbounds',
          autoWidth: true,
          bookType: 'csv',
        });
        this.downloadLoading = false;
      });
    },
    formatJson(filterVal, jsonData) {
      return jsonData.map(v => filterVal.map(j => {
        return v[j];
      }));
    },
  },
};
</script>
