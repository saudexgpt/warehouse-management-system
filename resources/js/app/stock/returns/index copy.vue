<template>
  <div class="app-container">
    <!-- <item-details v-if="page.option== 'view_details'" :item-in-stock="returnedProduct" :page="page" /> -->
    <add-new-returns v-if="page.option== 'add_new'" :returned-products="returned_products" :params="params" :page="page" @update="fetchItemStocks" />

    <edit-returns v-if="page.option== 'edit_returns'" :returned-product="returnedProduct" :params="params" :page="page" @update="onEditUpdate" />
    <div v-if="page.option=='list'" class="box">
      <div class="box-header">
        <h4 class="box-title">List of Returned Products {{ in_warehouse }}</h4>

        <span class="pull-right">
          <a v-if="checkPermission(['manage returned products'])" class="btn btn-info" @click="page.option = 'add_new'"> Add New</a>
        </span>

      </div>
      <div v-loading="load" class="box-body">
        <el-col :xs="24" :sm="12" :md="12">
          <label for="">Select Warehouse</label>
          <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" class="span" filterable @input="fetchItemStocks">
            <el-option
              v-for="(warehouse, index) in warehouses"
              :key="index"
              :value="warehouse.id"
              :label="warehouse.name"
              :disabled="warehouse.id !== 7"
            />

          </el-select>

        </el-col>
        <br><br><br><br>
        <el-tabs v-model="activeActivity">
          <el-tab-pane label="Unapproved" name="unapproved">
            <el-button
              :loading="downloadLoading"
              style="margin:0 0 20px 20px;"
              type="primary"
              icon="document"
              @click="handleDownload"
            >Export Excel</el-button>
            <v-client-table v-model="returned_products" :columns="columns" :options="options">
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
              <div slot="action" slot-scope="props">
                <span>
                  <a v-if="checkPermission(['manage returned products'])" class="btn btn-primary" @click="returnedProduct=props.row; selected_row_index=props.index; page.option = 'edit_returns'"><i class="fa fa-edit" /> </a>

                  <a v-if="checkPermission(['approve returned products']) && parseInt(props.row.quantity) > parseInt(props.row.quantity_approved)" class="btn btn-default" @click="openDialog(props.row, props.index)"><i class="fa fa-check" /> </a>
                </span>
              </div>

            </v-client-table>
          </el-tab-pane>
          <el-tab-pane label="Approved" name="approved">
            <approved-returned-products />
          </el-tab-pane>
        </el-tabs>

      </div>
      <el-dialog
        title="Confirm Quantity for Approval"
        :visible.sync="dialogVisible"
        width="20%"
      >
        <el-input v-model="approvalForm.approved_quantity" type="number" placeholder="Enter quantity for approval" />
        <span slot="footer" class="dialog-footer">
          <el-button round @click="dialogVisible = false; approvalForm.approved_quantity = null">Cancel</el-button>
          <el-button round type="primary" @click="approveProduct(); ">Approve</el-button>
        </span>
      </el-dialog>

    </div>

  </div>
</template>
<script>
import { mapGetters } from 'vuex';
import moment from 'moment';
import { parseTime } from '@/utils';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';

import ApprovedReturnedProducts from './ApprovedReturnedProducts';
import AddNewReturns from './partials/AddNewReturns';
import EditReturns from './partials/EditReturns';
import Resource from '@/api/resource';
// import Vue from 'vue';
// const necessaryParams = new Resource('fetch-necessary-params');
// const fetchWarehouse = new Resource('warehouse/fetch-warehouse');
const returnedProducts = new Resource('stock/returns');
const approveReturnedProducts = new Resource('stock/returns/approve-products');
const confirmItemReturned = new Resource('audit/confirm/returned-products');
export default {
  name: 'Returns',
  components: { ApprovedReturnedProducts, AddNewReturns, EditReturns },
  data() {
    return {
      activeActivity: 'unapproved',
      load: false,
      dialogVisible: false,
      downloadLoading: false,
      warehouses: [],
      returned_products: [],
      columns: ['action', 'confirmer.name', 'stocker.name', 'customer_name', 'item.name', 'batch_no', 'quantity', 'quantity_approved', 'reason', 'expiry_date', 'date_returned'],

      options: {
        headings: {
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
        sortable: ['item.name', 'batch_no', 'expiry_date', 'date_returned'],
        filterable: ['item.name', 'batch_no', 'expiry_date', 'date_returned'],
      },
      page: {
        option: 'list',
      },
      // params: {},
      form: {
        warehouse_index: '',
        // warehouse_id: '',
        warehouse_id: 7,
      },
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
  computed: {
    params() {
      return this.$store.getters.params;
    },
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
      app.$store.dispatch('app/setNecessaryParams');
      const params = app.params;
      app.warehouses = params.warehouses;
      if (app.warehouses.length > 0) {
        app.form.warehouse_id = 7;
        app.fetchItemStocks();
      }
      // necessaryParams.list()
      //   .then(response => {
      //     app.params = response.params;
      //     app.warehouses = response.params.warehouses;
      //     if (app.warehouses.length > 0) {
      //       app.form.warehouse_id = app.warehouses[0];
      //       app.form.warehouse_index = 0;
      //       app.fetchItemStocks();
      //     }
      //   });
    },
    confirmReturnedItem(id) {
      const app = this;
      const form = { id: id };
      const message = 'Click okay to confirm action';
      if (confirm(message)) {
        app.load = true;
        confirmItemReturned.update(id, form)
          .then(response => {
            app.load = false;
            if (response.confirmed === 'success'){
              document.getElementById(id).innerHTML = response.confirmed_by;
            }
          });
      }
    },
    fetchItemStocks() {
      const app = this;
      const loader = returnedProducts.loaderShow();
      app.page.option = 'list';
      const param = app.form;
      returnedProducts.list(param)
        .then(response => {
          app.returned_products = response.returned_products;
          // app.in_warehouse = 'in ' + app.warehouses[param.warehouse_index].name;
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
    expiryFlag(date){
      const product_expiry_date_alert = this.product_expiry_date_alert_in_months;
      const min_expiration = parseInt(product_expiry_date_alert * 30 * 24 * 60 * 60 * 1000); // we use 30 days for one month to calculate
      const today = parseInt(this.moment().valueOf()); // Unix Timestamp (miliseconds) 1.6.0+
      if (parseInt(date) - today <= min_expiration) {
        // console.log(parseInt(date) - today);
        return 'danger'; // flag expiry date as red
      }
      return 'success'; // flag expiry date as green
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
    openDialog(product, selected_row_index){
      const app = this;
      app.approvalForm.product_details = product;
      app.selected_row_index = selected_row_index;
      app.dialogVisible = true;
    },
    approveProduct(){
      const app = this;

      const param = app.approvalForm;
      const balance = parseInt(app.approvalForm.product_details.quantity) - parseInt(app.approvalForm.product_details.quantity_approved);
      if (parseInt(param.approved_quantity) <= balance) {
        if (parseInt(param.approved_quantity) > 0) {
          app.dialogVisible = false;
          app.load = true;
          approveReturnedProducts.store(param)
            .then(response => {
              // app.returned_products[app.selected_row_index - 1] = response.returned_product;
              app.fetchItemStocks();
              app.load = false;
            })
            .catch(error => {
              app.load = false;
              console.log(error.message);
            });
        } else {
          app.$alert('Please enter a value greater than zero');
          return;
        }
      } else {
        app.$alert('Approved Quantity MUST NOT be greater than ' + balance);
        return;
      }
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
