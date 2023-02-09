<template>
  <div class="app-container">
    <el-row :gutter="10">
      <el-col :xs="24" :sm="8" :md="8">
        <label for="">Select Warehouse</label>
        <el-select v-model="form.warehouse_index" placeholder="Select Warehouse" class="span" filterable @input="getInvoices()">
          <el-option v-for="(warehouse, index) in warehouses" :key="index" :value="index" :label="warehouse.name" />

        </el-select>

      </el-col>

    </el-row>
    <br>
    <div class="box">
      <div class="box-header">
        <h4 class="box-title">{{ table_title }}</h4>
      </div>
      <div class="box-body">
        <el-row :gutter="20">
          <el-col :xs="24" :sm="12" :md="12">
            <el-input
              v-model="form.invoice_no"
              placeholder="Search by Invoice Number"
              style="width: 80%"
              class="filter-item"
            />
            <el-button
              type="primary"
              @click="getInvoices()"
            >Fetch</el-button>
          </el-col>
        </el-row>
        <v-client-table v-model="invoice_item_batches" :columns="columns" :options="options">
          <!-- <div slot="child_row" slot-scope="props">
            <aside>
              <legend>Delivery Details for Invoice No.: {{ props.row.invoice.invoice_number }}</legend>
              <v-client-table v-model="props.row.waybill_items" :columns="['waybill_no', 'quantity', 'supply_status', 'dispatchers', 'supply_date']">
                <div slot="waybill_no" slot-scope="{row}">
                  {{ row.waybill.waybill_no }}

                </div>
                <div slot="quantity" slot-scope="{row}">
                  {{ row.quantity }} {{ row.type }}

                </div>
                <div slot="supply_status" slot-scope="{row}">
                  {{ row.waybill.status }}

                </div>
                <div slot="dispatchers" slot-scope="{row}">
                  <div v-if="row.waybill.dispatcher">
                    <span v-for="(vehicle_driver, index) in row.waybill.dispatcher.vehicle.vehicle_drivers" :key="index">
                      {{ vehicle_driver.driver.user.name }}<br>
                    </span>
                  </div>
                  <div v-else>
                    Not assigned
                  </div>

                </div>
                <div slot="supply_date" slot-scope="{row}">
                  {{ moment(row.updated_at).format('MMM D, YYYY') }}
                </div>
              </v-client-table>
            </aside>

          </div> -->
          <!-- <div slot="batches" slot-scope="props">
            <div v-for="(invoice_batch, batch_index) in props.row.batches" :key="batch_index">
              <div v-if="invoice_batch.item_stock_batch">
                {{ invoice_batch.item_stock_batch.batch_no }}
              </div>, <br>
            </div>
          </div>
          <div slot="amount" slot-scope="props">
            {{ currency + Number(props.row.invoice.amount).toLocaleString() }}
          </div>
          <div slot="quantity" slot-scope="props">
            {{ props.row.quantity + ' ' + props.row.type }}
          </div>
          <div slot="quantity_supplied" slot-scope="props">
            {{ props.row.quantity_supplied + ' ' + props.row.type }}
          </div>
          <div slot="balance" slot-scope="props">
            {{ props.row.quantity - props.row.quantity_supplied + ' ' + props.row.type }}
          </div>
          <div slot="invoice.invoice_date" slot-scope="props">
            {{ moment(props.row.invoice.invoice_date).format('MMM D, YYYY') }}
          </div>
          <div slot="created_at" slot-scope="props">
            {{ moment(props.row.created_at).format('MMM D, YYYY') }}
          </div>
          <div slot="updated_at" slot-scope="props">
            {{ (props.row.delivery_status === 'delivered') ? moment(props.row.updated_at).format('MMM D, YYYY') : 'Pending' }}
          </div> -->
          <div slot="action" slot-scope="{row}">
            <el-button v-if="checkPermission(['manage invoice reversals'])" type="danger" round @click="reverseInvoice(row)">Reverse</el-button>
          </div>
          <div slot="created_at" slot-scope="props">
            {{ moment(props.row.created_at).format('lll') }}
          </div>
          <div slot="invoice_item.quantity" slot-scope="props">
            {{ props.row.invoice_item.quantity }}
          </div>
          <div slot="quantity" slot-scope="props" class="alert alert-danger">
            {{ props.row.quantity }}
          </div>
          <div slot="invoice_item.quantity_supplied" slot-scope="props">
            {{ props.row.invoice_item.quantity - props.row.quantity }}
          </div>
          <div slot="uom" slot-scope="props">
            {{ props.row.invoice_item.type }}
          </div>

        </v-client-table>

      </div>
      <pagination
        v-show="total > 0"
        :total="total"
        :page.sync="form.page"
        :limit.sync="form.limit"
        @pagination="getInvoices"
      />

    </div>
  </div>
</template>
<script>
import moment from 'moment';
import Pagination from '@/components/Pagination';
import Resource from '@/api/resource';
import checkPermission from '@/utils/permission';
// const deleteItemInStock = new Resource('stock/items-in-stock/delete');
export default {
  name: 'UnsuppliedInvoices',
  components: { Pagination },
  data() {
    return {
      activeTab: 'Invoice',
      warehouses: [],
      invoice_item_batches: [],
      invoice_statuses: [],
      currency: '',
      columns: ['action', 'invoice_item.item.name', 'invoice.invoice_number', 'invoice.customer.user.name', 'invoice_item.quantity', 'invoice_item.quantity_supplied', 'quantity', 'uom', 'created_at'],

      options: {
        headings: {
          'invoice_item.item.name': 'Product',
          'invoice.invoice_number': 'Invoice No.',
          'invoice.customer.user.name': 'Customer Name',
          'invoice_item.quantity': 'Invoiced Quantity',
          'invoice_item.quantity_supplied': 'Quantity Supplied',
          quantity: 'Unsupplied Waybill Quantity',
          uom: 'UOM',
          'invoice_item.warehouse.name': 'Concerned Warehouse',
          // 'invoice.customer.user.name': 'Customer',
          // 'invoice.invoice_number': 'Invoice',
          // 'batches': 'Batch Nos.',
          // 'item.name': 'Product',
          // 'quantity_supplied': 'Supplied',
          // 'invoice.invoice_date': 'Invoice Date',
          // 'created_at': 'Date Saved',
          // 'delivery_status': 'Status',
          // 'updated_at': 'Delivery Date',

          // id: 'S/N',
        },
        pagination: {
          dropdown: true,
          chunk: 100,
        },
        perPage: 100,
        filterByColumn: true,
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: ['invoice.invoice_number', 'invoice_item.item.name', 'invoice.customer.user.name', 'created_at'],
        filterable: ['invoice.invoice_number', 'invoice_item.item.name', 'invoice.customer.user.name', 'created_at'],
      },
      page: {
        option: 'list',
      },
      form: {
        warehouse_index: '',
        warehouse_id: 'all',
        invoice_no: '',
        page: 1,
        limit: 100,
        keyword: '',
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
    params() {
      return this.$store.getters.params;
    },
  },
  mounted() {
    this.fetchNecessaryParams();
  },
  beforeDestroy() {

  },
  methods: {
    moment,
    checkPermission,
    showCalendar(){
      this.show_calendar = !this.show_calendar;
    },
    fetchNecessaryParams() {
      const app = this;
      app.warehouses = app.params.warehouses;
      app.form.warehouse_index = 0;
      app.form.warehouse_id = app.warehouses[0].id;
      app.invoice_statuses = app.params.invoice_statuses;
      app.currency = app.params.currency;
      app.getInvoices();
    },
    getInvoices() {
      const app = this;
      const outboundReport = new Resource('reports/tabular/all-partially-treated-invoices');
      const loader = outboundReport.loaderShow();
      const { limit, page } = this.form;
      const param = app.form;
      outboundReport.list(param)
        .then(response => {
          this.invoice_item_batches = response.invoice_item_batches.data;
          this.invoice_item_batches.forEach((element, index) => {
            element['index'] = (page - 1) * limit + index + 1;
          });
          this.total = response.invoice_item_batches.total;
          app.table_title = 'Partially Treated Invoices';
          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error.message);
        });
    },
    reverseInvoice(entry) {
      const app = this;
      if (confirm(`Are you sure you want to reverse ${entry.quantity} ${entry.invoice_item.type} of ${entry.invoice_item.item.name} from invoice ${entry.invoice.invoice_number}? This cannot be undone`)) {
        const reverseInvoiceResource = new Resource('invoice/general/reverse-partially-treated-invoice-item');
        const loader = reverseInvoiceResource.loaderShow();
        reverseInvoiceResource.update(entry.invoice_item_id)
          .then(response => {
            app.$message('Reversal Action Successful');
            app.getInvoices();
            loader.hide();
          })
          .catch(error => {
            loader.hide();
            console.log(error.message);
          });
      }
    },
  },
};
</script>
