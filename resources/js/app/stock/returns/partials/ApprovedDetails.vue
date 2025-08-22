<template>
  <div class="app-container">
    <el-button
      type="primary"
      icon="el-icon-printer"
      @click="doPrint(returnData.id)"
    >Print</el-button>
    <div :id="returnData.id" class="box padded">
      <div>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>
                <label>Customer Name</label>
                <address>
                  <label>{{ returnData.customer_name.toUpperCase() }}</label>
                </address>
              </th>
              <th>
                <label>Returns No.: {{ returnData.returns_no }}</label>
                <br>
                <label>Date:</label>
                {{
                  moment(returnData.date_returned).format('MMMM Do YYYY')
                }}
                <br>
              </th>
              <th>
                <label>Stocked By</label>
                <address>
                  <strong>{{ returnData.stocker.name }}</strong>
                </address>
              </th>
              <th>
                <h3>Total Amount: {{ currency + totalAmount.toLocaleString() }}</h3>
              </th>
            </tr>
          </thead>
        </table>
      </div>
      <v-client-table v-model="products" :columns="columns" :options="options">
        <div slot="price" slot-scope="{row}">
          <!-- {{ row.quantity }} -->
          {{ currency + Number(row.price).toLocaleString() }}

        </div>
        <div slot="total" slot-scope="{row}">
          <!-- {{ row.quantity }} -->
          {{ currency + (Number(row.quantity) * Number(row.price)).toLocaleString() }}

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
        <!-- <div slot="confirmer.name" slot-scope="{row}">
          <div :id="row.id">
            <div v-if="row.confirmed_by == null">
              <a v-if="checkPermission(['audit confirm actions', 'manage returned products']) && row.stocked_by !== userId" class="btn btn-success" title="Click to confirm" @click="confirmReturnedItem(row.id);"><i class="fa fa-check" /> </a>
            </div>
            <div v-else>
              {{ row.confirmer.name }}
            </div>
          </div>
        </div> -->
        <div slot="auditor">
          <div>
            {{ (returnData.auditor) ? returnData.auditor.name : '' }}
          </div>
        </div>

      </v-client-table>

    </div>

  </div>
</template>
<script>
import { mapGetters } from 'vuex';
import moment from 'moment';
import { parseTime } from '@/utils';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';
import Resource from '@/api/resource';
// import Vue from 'vue';
// const necessaryParams = new Resource('fetch-necessary-params');
// const fetchWarehouse = new Resource('warehouse/fetch-warehouse');
const returnedProducts = new Resource('stock/returns');
const approveReturnedProducts = new Resource('stock/returns/approve-products');
const confirmItemReturned = new Resource('audit/confirm/returned-products');
export default {
  name: 'Returns',
  props: {
    products: {
      type: Array,
      required: true,
    },
    returnData: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      activeActivity: 'unapproved',
      load: false,
      dialogVisible: false,
      downloadLoading: false,
      warehouses: [],
      columns: ['auditor', /* 'confirmer.name',*/ 'item.name', 'batch_no', 'price', 'quantity', 'total', 'quantity_approved', 'reason', 'expiry_date'],

      options: {
        headings: {
          'auditor': 'Audited By',
          // 'confirmer.name': 'Confirmed By',
          'stocker.name': 'Stocked By',
          'item.name': 'Product',
          price: 'Unit price',
          batch_no: 'Batch No.',
          expiry_date: 'Expiry Date',
          quantity: 'QTY',
          quantity_approved: 'QTY Approved',

          // id: 'S/N',
        },
        pagination: {
          dropdown: true,
          chunk: 20,
        },
        filterByColumn: false,
        texts: {
          filter: 'Search:',
        },
        filter: false,
      },
      page: {
        option: 'list',
      },
      form: {
        warehouse_id: 7,
        warehouse_index: '',
        from: '',
        to: '',
        panel: '',
        status: 'pending',
        page: 1,
        limit: 10,
        keyword: '',
      },
      currency: '',
      totalAmount: 0,
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
  computed: {
    params() {
      return this.$store.getters.params;
    },
    ...mapGetters([
      'userId',
    ]),
  },
  mounted() {
    this.currency = this.params.currency;
    this.calculateTotalAmount();
    // this.getWarehouse();
    // this.fetchNecessaryParams();
  },
  beforeDestroy() {

  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    calculateTotalAmount() {
      let total = 0;
      this.products.forEach(product => {
        total += Number(product.price) * Number(product.quantity);
      });
      this.totalAmount = total;
    },
    doPrint(elementId) {
      var prtContent = document.getElementById(elementId);
      var WinPrint = window.open('', '', 'left=0,top=0,width=800,height=600,toolbar=0,scrollbars=1,status=0');
      WinPrint.document.write(prtContent.innerHTML);
      WinPrint.document.close();
      WinPrint.focus();
      WinPrint.print();
      WinPrint.close();
      // var printContent = document.getElementById(elementId).innerHTML;
      // var originalContent = document.body.innerHTML;

      // document.body.innerHTML = printContent; // Replace body content with the section
      // window.print(); // Open the print dialog
      // document.body.innerHTML = originalContent; // Restore the original content
    },
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
      const { limit, page } = app.form;
      app.page.option = 'list';
      const param = app.form;
      returnedProducts.list(param)
        .then(response => {
          app.products = response.products;
          app.products = response.products.data;
          app.products.forEach((element, index) => {
            element['index'] = (page - 1) * limit + index + 1;
          });
          app.total = response.products.total;
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
      app.$emit('update');
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
    //         app.products.splice(row.index - 1, 1);
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
    openDialog(product, selected_row_index, show = true) {
      const app = this;
      app.approvalForm.product_details = product;
      app.selected_row_index = selected_row_index;
      app.dialogVisible = show;
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
              // app.products[app.selected_row_index - 1] = response.returned_product;
              // app.fetchItemStocks();
              app.$message('Action Successful');
              app.$emit('update');
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
      const { products } = await returnedProducts.list(param);
      import('@/vendor/Export2Excel').then(excel => {
        const multiHeader = [[this.table_title, '', '', '', '', '', '', '', '', '']];
        const tHeader = [
          'STOCKED BY',
          'CUSTOMER NAME',
          'PRODUCT',
          'PRICE',
          'BATCH NO.',
          'QUANTITY',
          'QUANTITY APPROVED',
          'REASON',
          'EXPIRY DATE',
          'DATE RETURNED',
        ];
        const filterVal = [
          'stocker.name', 'customer_name', 'item.name', 'price', 'batch_no', 'quantity', 'quantity_approved', 'reason', 'expiry_date', 'date_returned',
        ];
        const list = products;
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
