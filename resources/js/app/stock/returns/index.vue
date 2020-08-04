<template>
  <div class="app-container">
    <!-- <item-details v-if="page.option== 'view_details'" :item-in-stock="returnedProduct" :page="page" /> -->
    <add-new-returns v-if="page.option== 'add_new'" :returned-products="returned_products" :params="params" :page="page" />

    <edit-returns v-if="page.option== 'edit_returns'" :returned-product="returnedProduct" :params="params" :page="page" @update="onEditUpdate" />
    <div v-if="page.option=='list'" class="box">
      <div class="box-header">
        <h4 class="box-title">List of Returned Products {{ in_warehouse }}</h4>

        <span class="pull-right">
          <a v-if="checkPermission(['manage returned products'])" class="btn btn-info" @click="page.option = 'add_new'"> Add New</a>
        </span>

      </div>
      <div class="box-body">
        <el-col :xs="24" :sm="12" :md="12">
          <label for="">Select Warehouse</label>
          <el-select v-model="form.warehouse_index" placeholder="Select Warehouse" class="span" filterable @input="fetchItemStocks">
            <el-option v-for="(warehouse, index) in warehouses" :key="index" :value="index" :label="warehouse.name" />

          </el-select>

        </el-col>
        <br><br><br><br>
        <v-client-table v-model="returned_products" :columns="columns" :options="options">
          <div slot="quantity" slot-scope="{row}" class="alert alert-info">
            {{ row.quantity }}
            <!-- {{ row.quantity }} {{ formatPackageType(row.item.package_type) }} -->

          </div>
          <div slot="in_transit" slot-scope="{row}" class="alert alert-warning">
            {{ row.in_transit }} {{ formatPackageType(row.item.package_type) }}

          </div>
          <div slot="supplied" slot-scope="{row}" class="alert alert-danger">
            {{ row.supplied }} {{ formatPackageType(row.item.package_type) }}

          </div>
          <div slot="balance" slot-scope="{row}" class="alert alert-success">
            {{ row.balance }} {{ formatPackageType(row.item.package_type) }}

          </div>
          <div slot="created_at" slot-scope="{row}">
            {{ moment(row.created_at).fromNow() }}

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
          <div slot="action" slot-scope="props">
            <a v-if="checkPermission(['manage returned products'])" class="btn btn-primary" @click="returnedProduct=props.row; selected_row_index=props.index; page.option = 'edit_returns'"><i class="fa fa-edit" /> </a>
            <!-- <a v-if="checkPermission(['manage item stocks', 'delete item stocks'])" class="btn btn-danger" @click="confirmDelete(props)"><i class="fa fa-trash" /> </a>
            <a v-else class="btn btn-grey"><i class="fa fa-trash" /></a> -->

            <!-- <a class="btn btn-default" @click="returnedProduct=props.row; page.option = 'view_details'"><i class="fa fa-eye" /> </a> -->
            <!-- <a class="btn btn-warning" @click="returnedProduct=props.row; selected_row_index=props.index; page.option = 'edit_item'"><i class="fa fa-edit" /> </a>
            <a class="btn btn-danger" @click="confirmDelete(props)"><i class="fa fa-trash" /> </a> -->
          </div>

        </v-client-table>

      </div>

    </div>

  </div>
</template>
<script>
import { mapGetters } from 'vuex';
import moment from 'moment';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';

import AddNewReturns from './partials/AddNewReturns';
import EditReturns from './partials/EditReturns';
import Resource from '@/api/resource';
// import Vue from 'vue';
const necessaryParams = new Resource('fetch-necessary-params');
// const fetchWarehouse = new Resource('warehouse/fetch-warehouse');
const returnedProducts = new Resource('stock/returns');
const confirmItemReturned = new Resource('audit/confirm/returned-products');
export default {
  components: { AddNewReturns, EditReturns },
  data() {
    return {
      warehouses: [],
      returned_products: [],
      columns: ['action', 'confirmer.name', 'stocker.name', 'customer_name', 'item.name', 'batch_no', 'quantity', 'expiry_date', 'reason', 'date_returned'],

      options: {
        headings: {
          'confirmer.name': 'Confirmed By',
          'stocker.name': 'Stocked By',
          'item.name': 'Product',
          batch_no: 'Batch No.',
          expiry_date: 'Expiry Date',
          quantity: 'Quantity',
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
      params: {},
      form: {
        warehouse_index: '',
        warehouse_id: '',
      },
      in_warehouse: '',
      returnedProduct: {},
      selected_row_index: '',

    };
  },
  computed: {
    ...mapGetters([
      'userId',
    ]),
  },
  mounted() {
    // this.getWarehouse();
    this.fetchNecessaryParams();
  },
  beforeDestroy() {

  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    fetchNecessaryParams() {
      const app = this;
      necessaryParams.list()
        .then(response => {
          app.params = response.params;
          app.warehouses = response.params.warehouses;
          if (app.warehouses.length > 0) {
            app.form.warehouse_id = app.warehouses[0];
            app.form.warehouse_index = 0;
            app.fetchItemStocks();
          }
        });
    },
    confirmReturnedItem(id) {
      // const app = this;
      const form = { id: id };
      const message = 'Click okay to confirm action';
      if (confirm(message)) {
        confirmItemReturned.update(id, form)
          .then(response => {
            if (response.confirmed === 'success'){
              document.getElementById(id).innerHTML = response.confirmed_by;
            }
          });
      }
    },
    fetchItemStocks() {
      const app = this;
      const loader = returnedProducts.loaderShow();

      const param = app.form;
      param.warehouse_id = app.warehouses[param.warehouse_index].id;
      returnedProducts.list(param)
        .then(response => {
          app.returned_products = response.returned_products;
          app.in_warehouse = 'in ' + app.warehouses[param.warehouse_index].name;
          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error.message);
        });
    },

    onEditUpdate(updated_row) {
      const app = this;
      // app.returned_products.splice(app.returnedProduct.index-1, 1);
      app.returned_products[app.selected_row_index - 1] = updated_row;
    },
    // confirmDelete(props) {
    //   // this.loader();

    //   const row = props.row;
    //   const app = this;
    //   const message = 'This delete action cannot be undone. Click OK to confirm';
    //   if (confirm(message)) {
    //     deleteItemInStock.destroy(row.id, row)
    //       .then(response => {
    //         app.returned_products.splice(row.index - 1, 1);
    //         this.$message({
    //           message: 'Item has been deleted',
    //           type: 'success',
    //         });
    //       })
    //       .catch(error => {
    //         console.log(error);
    //       });
    //   }
    // },
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
