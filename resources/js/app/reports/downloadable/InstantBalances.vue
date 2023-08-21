<template>
  <div class="">
    <aside>
      <el-row :gutter="10">
        <el-col v-if="params.warehouses.length > 0" :xs="24" :sm="8" :md="8">
          <label for="">Select Warehouse</label>
          <el-select
            v-model="warehouse_index"
            placeholder="Select Warehouse"
            class="span"
            filterable
            @input="fetchItemStocks"
          >
            <el-option value="all" label="All Warehouses" />
            <el-option
              v-for="(warehouse, index) in warehouses"
              :key="index"
              :value="index"
              :label="warehouse.name"
            />

          </el-select>

        </el-col>
        <el-col :xs="24" :sm="68" :md="8">
          <label for="">Filter by Product: </label>
          <el-select
            v-model="form.item_id"
            placeholder="Select Product"
            filterable
            class="span"
          >
            <el-option value="all" label="All Products" />
            <el-option
              v-for="(item, item_index) in params.items"
              :key="item_index"
              :value="item.id"
              :label="item.name"
            >
              <span v-if="item.category_id !== 26">
                {{ item.name }}
              </span>
            </el-option>
          </el-select>
        </el-col>
        <el-col :xs="24" :sm="8" :md="8">
          <br>
          <el-popover
            placement="right"
            trigger="click"
          >
            <date-range-picker :from="$route.query.from" :to="$route.query.to" :panel="panel" :panels="panels" :submit-title="submitTitle" :future="future" @update="showCalendar" />
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
        <v-client-table ref="myTable" v-model="items_in_stock" :columns="columns" :options="options">
          <div slot="brought_forward" slot-scope="{row}" class="alert alert-info">
            {{ row['brought_forward'] }}

          </div>
          <div slot="quantity_in" slot-scope="{row}" class="alert alert-warning">
            {{ row['quantity_in'] }}

          </div>
          <div slot="quantity_out" slot-scope="{row}" class="alert alert-danger">
            {{ row['quantity_out'] }}

          </div>
          <div slot="quantity_expired" slot-scope="{row}" class="alert bg-black-active">
            {{ row['quantity_expired'] }}

          </div>
          <div slot="balance" slot-scope="{row}" class="alert alert-success">
            {{ row['balance'] }}

          </div>
          <div slot="uom" slot-scope="{row}">
            {{ formatPackageType(row['uom']) }}

          </div>
          <!-- <div slot="updated_at" slot-scope="{row}">
        {{ moment(row.created_at).fromNow() }}

      </div> -->
        </v-client-table>
      </div>
      <pagination
        v-show="total > 0"
        :total="total"
        :page.sync="form.page"
        :limit.sync="form.limit"
        @pagination="fetchItemStocks"
      />

    </div>

  </div>
</template>
<script>
import moment from 'moment';
import Pagination from '@/components/Pagination';
import Resource from '@/api/resource';
// const fetchWarehouse = new Resource('warehouse/fetch-warehouse');
const itemsInStock = new Resource('reports/instant-balances');
export default {
  // name: 'InstantBalances',
  components: {
    Pagination,
  },
  // props: {
  //   params: {
  //     type: Object,
  //     default: () => ([]),
  //   },
  // },
  data() {
    return {
      warehouses: [],
      items_in_stock: [],
      view_by: null,
      columns: ['product_name', 'warehouse', 'brought_forward', 'quantity_in', 'quantity_out', 'quantity_expired', 'balance', 'uom'],

      options: {
        headings: {
          'warehouse': 'WAREHOUSE',
          'product_name': 'PRODUCT',
          brought_forward: 'BROUGHT FORWARD',
          // total_out: 'Total Supplied',
          quantity_in: 'QTY IN',
          quantity_out: 'QTY OUT',
          quantity_expired: 'QTY EXPIRED',
          balance: 'BALANCE',
          uom: 'UOM',
        },
        // filterByColumn: true,
        pagination: {
          dropdown: true,
          chunk: 200,
        },
        perPage: 10,
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: ['warehouse', 'product_name', 'brought_forward', 'quantity_in', 'quantity_out', 'quantity_expired', 'balance'],
        filterable: [/* 'warehouse', 'product_name', 'brought_forward', 'quantity_in', 'quantity_out', 'quantity_expired', 'balance'*/],
      },
      page: {
        option: 'list',
      },
      downloadLoading: false,
      form: {
        warehouse_id: '',
        item_id: '',
        from: '',
        to: '',
        panel: '',
        page: 1,
        limit: 10,
      },
      total: 0,
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
  computed: {
    params() {
      return this.$store.getters.params;
    },
  },
  mounted() {
    this.warehouses = this.params.warehouses;
    this.warehouse_index = 0;
    this.form.warehouse_id = this.warehouses[0].id;
    // this.setDateRange('');
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
    format(date) {
      var month = date.toLocaleString('en-US', { month: 'short' });
      return month + ' ' + date.getDate() + ', ' + date.getFullYear();
    },
    showCalendar(values){
      document.getElementById('pick_date').click();
      this.setDateRange(values);
    },
    setDateRange(values){
      const app = this;
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
      const { limit, page } = param;
      app.options.perPage = limit;
      itemsInStock.list(param)
        .then(response => {
          app.items_in_stock = response.items_in_stock;
          app.items_in_stock.forEach((element, index) => {
            element['index'] = (page - 1) * limit + index + 1;
          });
          app.total = response.items.total;

          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error.message);
        });
    },
    async handleDownload() {
      // const filtered_string = this.$refs.myTable.$refs.table.query;
      this.downloadLoading = true;
      const param = this.form;
      param.is_download = 'yes';
      const { items_in_stock } = await itemsInStock.list(param);
      import('@/vendor/Export2Excel').then(excel => {
        const multiHeader = [[this.table_title, '', '', '', '', '', '', '', '']];
        const tHeader = ['PRODUCT', 'WAREHOUSE', 'BROUGHT FORWARD', 'QUANTITY IN', 'QUANTITY OUT', 'QUANTITY EXPIRED', 'BALANCE', 'UOM'];
        const filterVal = this.columns;
        const list = items_in_stock; // (filtered_string === '') ? this.items_in_stock : this.$refs.myTable.filteredData;
        const data = this.formatJson(filterVal, list);
        excel.export_json_to_excel({
          multiHeader,
          header: tHeader,
          data,
          filename: 'Instant_Balances',
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
