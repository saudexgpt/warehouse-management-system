<template>
  <div class="app-container">
    <!-- <el-button
      :loading="downloadLoading"
      style="margin:0 0 20px 20px;"
      type="primary"
      icon="document"
      @click="handleDownload"
    >Export Excel</el-button> -->
    <el-popover
      placement="right"
      trigger="click"
    >
      <date-range-picker :from="$route.query.from" :to="$route.query.to" :panel="panel" :panels="panels" :submit-title="submitTitle" :future="future" @update="setDateRange" />
      <el-button id="pick_outbound_date2" slot="reference" type="success">
        <i class="el-icon-date" /> Export Excel
      </el-button>
    </el-popover>
    <v-client-table v-model="returned_products" :columns="columns" :options="options">
      <div slot="child_row" slot-scope="{row}">
        <Details :products="row.products" :return-data="row" @update="fetchItemStocks" />

      </div>
      <div slot="quantity" slot-scope="{row}" class="alert alert-warning">
        <!-- {{ row.quantity }} -->
        {{ row.quantity }} {{ row.item.package_type }}

      </div>
      <div slot="quantity_approved" slot-scope="{row}" class="alert alert-info">
        <!-- {{ row.quantity_approved }} -->
        {{ row.quantity_approved }} {{ row.item.package_type }}

      </div>
      <div slot="expiry_date" slot-scope="{row}" :class="'alert alert-'+ expiryFlag(moment(row.expiry_date).format('x'))">
        <span>
          {{ moment(row.expiry_date).calendar() }}
        </span>
      </div>
      <div slot="created_at" slot-scope="{row}">
        {{ moment(row.created_at).calendar() }}

      </div>
      <div slot="confirmer.name" slot-scope="{row}">
        <div :id="row.id">
          <div v-if="row.confirmed_by == null">
            <a v-if="checkPermission(['audit confirm actions']) && row.stocked_by !== userId" class="btn btn-success" title="Click to confirm" @click="confirmReturnedItem(row.id);"><i class="fa fa-check" /> </a>
          </div>
          <div v-else>
            {{ row.confirmer.name }}
          </div>
        </div>
      </div>

    </v-client-table>
    <el-row :gutter="20">
      <pagination
        v-show="total > 0"
        :total="total"
        :page.sync="form.page"
        :limit.sync="form.limit"
        @pagination="fetchItemStocks"
      />
    </el-row>
  </div>
</template>
<script>
import moment from 'moment';
import { parseTime } from '@/utils';
import Pagination from '@/components/Pagination';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';
import Details from './partials/ApprovedDetails';

import Resource from '@/api/resource';
// import Vue from 'vue';
// const necessaryParams = new Resource('fetch-necessary-params');
// const fetchWarehouse = new Resource('warehouse/fetch-warehouse');
const returnedProducts = new Resource('stock/returns/approved');
export default {
  name: 'Returns',
  components: {
    Pagination,
    Details,
  },
  data() {
    return {
      load: false,
      dialogVisible: false,
      downloadLoading: false,
      warehouses: [],
      returned_products: [],
      columns: ['returns_no', 'stocker.name', 'customer_name', 'date_returned'],

      options: {
        headings: {
          returns_no: 'Return No.',
          'confirmer.name': 'Confirmed By',
          'stocker.name': 'Stocked By',
          'item.name': 'Product',
          batch_no: 'Batch No.',
          expiry_date: 'Expiry Date',
          quantity: 'QTY',
          quantity_approved: 'QTY Approved',
          date_returned: 'Date Returned',

          // id: 'S/N',
        },
        pagination: {
          dropdown: true,
          chunk: 20,
        },
        filterByColumn: true,
        texts: {
          filter: 'Search:',
        },
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: ['returns_no', 'date_returned'],
        filterable: ['returns_no', 'date_returned'],
      },
      page: {
        option: 'list',
      },
      submitTitle: 'Fetch Report',
      panel: 'month',
      future: false,
      panels: ['range', 'week', 'month', 'quarter', 'year'],
      show_calendar: false,
      // params: {},
      form: {
        warehouse_id: 7,
        page: 1,
        limit: 10,
        from: '',
        to: '',
        panel: '',
      },
      total: 0,
      in_warehouse: '',
      returnedProduct: {},
      selected_row_index: '',
      approvalForm: {
        approved_quantity: null,
        product_details: '',
      },
      product_expiry_date_alert_in_months: 9, // defaults to 9 months

    };
  },
  mounted() {
    // this.getWarehouse();
    this.fetchItemStocks();
  },
  beforeDestroy() {

  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    fetchItemStocks() {
      const app = this;
      const { limit, page } = app.form;
      app.options.perPage = limit;
      const loader = returnedProducts.loaderShow();

      const param = app.form;
      returnedProducts.list(param)
        .then(response => {
          app.returned_products = response.returned_products.data;
          app.returned_products.forEach((element, index) => {
            element['index'] = (page - 1) * limit + index + 1;
          });
          app.total = response.returned_products.total;
          // app.in_warehouse = 'in ' + app.warehouses[param.warehouse_index].name;
          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error.message);
        });
    },
    format(date) {
      var month = date.toLocaleString('en-US', { month: 'short' });
      return month + ' ' + date.getDate() + ', ' + date.getFullYear();
    },
    setDateRange(values){
      const app = this;
      document.getElementById('pick_outbound_date2').click();
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
      app.handleDownload();
    },
    formatPackageType(type){
      var formated_type = type + 's';
      if (type === 'Box') {
        formated_type = type + 'es';
      }
      return formated_type;
    },
    async handleDownload() {
      this.downloadLoading = true;
      const param = this.form;
      param.is_download = 'yes';
      const { returned_products } = await returnedProducts.list(param);
      import('@/vendor/Export2Excel').then(excel => {
        const multiHeader = [[this.table_title, '', '', '', '', '', '', '', '', '']];
        const tHeader = [
          'STOCKED BY',
          'CUSTOMER NAME',
          'PRODUCT',
          'BATCH NO.',
          'QUANTITY',
          'QUANTITY APPROVED',
          'REASON',
          'EXPIRY DATE',
          'DATE RETURNED',
        ];
        const filterVal = [
          'stocker.name', 'customer_name', 'item.name', 'batch_no', 'quantity', 'quantity_approved', 'reason', 'expiry_date', 'date_returned',
        ];
        const list = returned_products;
        const data = this.formatJson(filterVal, list);
        excel.export_json_to_excel({
          multiHeader,
          header: tHeader,
          data,
          filename: 'UNAPPROVED RETURNS',
          autoWidth: true,
          bookType: 'csv',
        });
        this.downloadLoading = false;
      });
    },
    formatJson(filterVal, jsonData) {
      return jsonData.map(v =>
        filterVal.map(j => {
          if (j === 'stocker.name') {
            return (v['stocker']) ? v['stocker']['name'] : '';
          }
          if (j === 'item.name') {
            return (v['item']) ? v['item']['name'] : '';
          }
          if (j === 'expiry_date') {
            return parseTime(v[j]);
          }
          if (j === 'date_returned') {
            return parseTime(v[j]);
          }
          return v[j];
        }),
      );
    },
  },
};
</script>
<style rel="stylesheet/scss" lang="scss" scoped>
.alert {
  padding: 5px;
  margin: -5px;
  text-align: right;
}
td {
  padding: 0px !important;
}

</style>
