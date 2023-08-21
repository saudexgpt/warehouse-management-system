<template>
  <div class="app-container">
    <v-client-table v-model="returned_products" :columns="columns" :options="options">
      <div slot="quantity" slot-scope="{row}" class="alert alert-warning">
        <!-- {{ row.quantity }} -->
        {{ row.quantity }} {{ row.item.package_type }}

      </div>
      <div slot="quantity_approved" slot-scope="{row}" class="alert alert-info">
        <!-- {{ row.quantity_approved }} -->
        {{ row.quantity_approved }} {{ row.item.package_type }}

      </div>
      <div slot="expiry_date" slot-scope="{row}">
        <span>
          {{ moment(row.expiry_date).calendar() }}
        </span>
      </div>
      <div slot="created_at" slot-scope="{row}">
        {{ moment(row.created_at).calendar() }}

      </div>
      <!-- <div slot="confirmer.name" slot-scope="{row}">
        <div :id="row.id">
          {{ row.confirmer.name }}
        </div>
      </div> -->
      <!-- <div slot="action" slot-scope="props">
        <span>
          <a v-if="checkPermission(['manage returned products'])" class="btn btn-primary" @click="returnedProduct=props.row; selected_row_index=props.index; page.option = 'edit_returns'"><i class="fa fa-edit" /> </a>

          <a v-if="checkPermission(['approve returned products']) && parseInt(props.row.quantity) > parseInt(props.row.quantity_approved)" class="btn btn-default" @click="openDialog(props.row, props.index)"><i class="fa fa-check" /> </a>
        </span>
      </div> -->

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
import Pagination from '@/components/Pagination';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';

import Resource from '@/api/resource';
// import Vue from 'vue';
// const necessaryParams = new Resource('fetch-necessary-params');
// const fetchWarehouse = new Resource('warehouse/fetch-warehouse');
const returnedProducts = new Resource('stock/returns/approved');
export default {
  name: 'Returns',
  components: {
    Pagination,
  },
  data() {
    return {
      load: false,
      dialogVisible: false,
      warehouses: [],
      returned_products: [],
      columns: ['stocker.name', 'customer_name', 'item.name', 'batch_no', 'quantity', 'quantity_approved', 'reason', 'expiry_date', 'date_returned'],

      options: {
        headings: {
          // 'confirmer.name': 'Confirmed By',
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
        sortable: ['item.name', 'batch_no', 'expiry_date', 'date_returned'],
        filterable: ['item.name', 'batch_no', 'expiry_date', 'date_returned'],
      },
      page: {
        option: 'list',
      },
      // params: {},
      form: {
        warehouse_id: 7,
        page: 1,
        limit: 10,
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
    formatPackageType(type){
      var formated_type = type + 's';
      if (type === 'Box') {
        formated_type = type + 'es';
      }
      return formated_type;
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
