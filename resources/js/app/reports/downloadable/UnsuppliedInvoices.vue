<template>
  <div class="">
    <el-row :gutter="10">
      <el-col :xs="24" :sm="8" :md="8">
        <label for="">Select Warehouse</label>
        <el-select v-model="form.warehouse_index" placeholder="Select Warehouse" class="span" filterable>
          <el-option v-for="(warehouse, index) in warehouses" :key="index" :value="index" :label="warehouse.name" />

        </el-select>

      </el-col>
      <el-col :xs="24" :sm="10" :md="10">
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
    <br>
    <div class="box">
      <div class="box-header">
        <h4 class="box-title">{{ table_title }}</h4>
      </div>
      <div class="box-body">
        <div>
          <label class="radio-label" style="padding-left:0;">Filename: </label>
          <el-input v-model="filename" :placeholder="$t('excel.placeholder')" style="width:340px;" prefix-icon="el-icon-document" />
          <el-button :loading="downloadLoading" style="margin:0 0 20px 20px;" type="primary" icon="document" @click="handleDownload">
            Export Excel
          </el-button>
        </div>
        <v-client-table v-model="invoice_items" :columns="columns" :options="options">
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
          <div slot="rate" slot-scope="props">
            {{ currency + Number(props.row.rate).toLocaleString() }}
          </div>
          <div slot="amount" slot-scope="props">
            {{ currency + Number(props.row.amount).toLocaleString() }}
          </div>
          <div slot="created_at" slot-scope="props">
            {{ moment(props.row.created_at).format('ll') }}
          </div>
          <div slot="unsupplied" slot-scope="props" class="alert alert-danger">
            {{ props.row.unsupplied }}
          </div>
          <!-- <div slot="date_waybilled" slot-scope="props">
            {{ moment(props.row.date_waybilled).format('ll') }}
          </div>
          <div slot="invoice_delay_before_waybilled_in_days" slot-scope="props">
            {{ moment(props.row.invoice_delay_before_waybilled_in_days) + ' days' }}
          </div> -->

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
const outboundReport = new Resource('reports/tabular/unsupplied');
// const deleteItemInStock = new Resource('stock/items-in-stock/delete');
export default {
  name: 'UnsuppliedInvoices',
  components: { Pagination },
  props: {
    params: {
      type: Object,
      default: () => ([]),
    },
  },
  data() {
    return {
      activeTab: 'Invoice',
      warehouses: [],
      invoice_items: [],
      invoice_statuses: [],
      currency: '',
      columns: ['product', 'invoice_number', 'customer', 'warehouse', 'quantity', 'quantity_supplied', 'unsupplied', 'uom', 'rate', 'amount', 'created_at'],

      options: {
        headings: {
          // quantity_reversed: 'Reversed',
          date_waybilled: 'Waybilled',
          invoice_delay_before_waybilled_in_days: 'Delay',
        },
        pagination: {
          dropdown: true,
          chunk: 100,
        },
        perPage: 100,
        filterByColumn: true,
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: ['invoice_number', 'product', 'customer', 'created_at'],
        filterable: ['invoice_number', 'product', 'customer', 'created_at'],
      },
      page: {
        option: 'list',
      },
      form: {
        warehouse_index: '',
        warehouse_id: 'all',
        from: '',
        to: '',
        panel: '',
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

  created() {
    this.fetchNecessaryParams();
  },
  beforeDestroy() {

  },
  methods: {
    moment,
    showCalendar(){
      this.show_calendar = !this.show_calendar;
    },
    dateDiff(a, b){
      return a.diff(b, 'days');
    },
    fetchNecessaryParams() {
      const app = this;
      app.warehouses = app.params.warehouses;
      app.form.warehouse_index = 0;
      app.form.warehouse_id = app.warehouses[0].id;
      app.invoice_statuses = app.params.invoice_statuses;
      app.currency = app.params.currency;
    },
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
      app.getInvoices();
    },
    getInvoices() {
      const app = this;
      const loader = outboundReport.loaderShow();
      const { limit, page } = this.form;
      const param = app.form;
      outboundReport.list(param)
        .then(response => {
          this.invoice_items = response.invoice_items.data;
          this.invoice_items.forEach((element, index) => {
            element['index'] = (page - 1) * limit + index + 1;
          });
          this.total = response.invoice_items.total;
          app.table_title = 'Unsupplied Invoices from: ' + app.form.from + ' to: ' + app.form.to;
          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error.message);
        });
    },
    async handleDownload() {
      this.downloadLoading = true;
      const param = this.form;
      param.is_download = 'yes';
      const { invoice_items } = await outboundReport.list(param);
      import('@/vendor/Export2Excel').then(excel => {
        const multiHeader = [[this.table_title, '', '', '', '', '', '', '', '', '', '', '', '', '', '']];
        const tHeader = ['Product', 'Invoice No.', 'Customer', 'Concerned Warehouse', 'Quantity', 'Quantity Supplied', 'Unsupplied', 'UOM', 'Rate', 'Amount', 'Created At'];
        const filterVal = this.columns;
        const list = invoice_items;
        const data = this.formatJson(filterVal, list);
        excel.export_json_to_excel({
          multiHeader,
          header: tHeader,
          data,
          filename: this.filename,
          autoWidth: true,
          bookType: 'csv',
        });
        this.downloadLoading = false;
      });
    },
    formatJson(filterVal, jsonData) {
      return jsonData.map(v => filterVal.map(j => {
        if (j === 'created_at') {
          return moment(v['created_at']).format('ll');
        }
        if (j === 'item.name') {
          return v['item']['name'];
        }
        if (j === 'invoice.invoice_number') {
          return v['invoice']['invoice_number'];
        }
        if (j === 'invoice.customer.user.name') {
          return v['invoice']['customer']['user']['name'];
        }
        if (j === 'waybilled') {
          return (v['first_waybill_item']) ? moment(v['first_waybill_item']['created_at']).format('ll') : '-';
        }
        if (j === 'delay') {
          return (v['first_waybill_item']) ? this.dateDiff(moment(v['first_waybill_item']['created_at']), moment(v['created_at'])) + ' days' : '-';
        }
        if (j === 'uom') {
          return v['type'];
        }
        if (j === 'balance') {
          return parseInt(v['quantity'] - v['quantity_supplied']);
        }
        if (j === 'rate') {
          return this.currency + Number(v['rate']).toLocaleString();
        }
        if (j === 'amount') {
          return this.currency + Number(v['amount']).toLocaleString();
        }
        return v[j];
      }));
    },
  },
};
</script>
