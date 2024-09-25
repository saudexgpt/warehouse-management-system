<template>
  <div class="app-container">
    <el-row :gutter="10">
      <el-col :xs="24" :sm="8" :md="8">
        <label for="">Select Warehouse</label>
        <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" class="span" filterable @input="getInvoices()">
          <el-option v-for="(warehouse, index) in warehouses" :key="index" :value="warehouse.id" :label="warehouse.name" />

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
        <span class="pull-right">

          <el-button :loading="downloadLoading" style="margin:0 0 20px 20px;" type="primary" icon="document" @click="handleDownload">
            Export Excel
          </el-button>
        </span>
      </div>
      <div class="box-body">
        <el-row>
          <el-col :md="6">

            <el-checkbox
              v-model="checkAll"
              :indeterminate="isIndeterminate"
              border
              @change="handleCheckAllChange"
            >Check all</el-checkbox>
          </el-col>
          <el-col :md="6">

            <el-button :loading="archiving" type="danger" icon="document" @click="archiveSelection">
              Archive
            </el-button>
          </el-col>
        </el-row>

        <v-client-table v-model="invoices" :columns="columns" :options="options">
          <div slot="action" slot-scope="{row}">
            <el-checkbox-group v-model="checkedInvoices" @change="handleCheckedCitiesChange">
              <el-checkbox :label="row.id" border>{{ row.id }}</el-checkbox>
            </el-checkbox-group>
          </div>
          <div slot="created_at" slot-scope="props">
            {{ moment(props.row.created_at).format('lll') }}
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
const outboundReport = new Resource('invoice/general/fetch-pending-invoices');
// const deleteItemInStock = new Resource('stock/items-in-stock/delete');
export default {
  name: 'ArchiveInvoices',
  components: { Pagination },
  data() {
    return {
      checkAll: false,
      isIndeterminate: true,
      activeTab: 'Invoice',
      invoices: [],
      invoice_statuses: [],
      currency: '',
      columns: ['action', 'invoice_number', 'created_at'],

      options: {
        headings: {
          'invoice_number': 'Invoice No.',
        },
        pagination: {
          dropdown: true,
          chunk: 100,
        },
        perPage: 100,
        filterByColumn: true,
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: ['invoice_number', 'created_at'],
        filterable: ['invoice_number', 'created_at'],
      },
      page: {
        option: 'list',
      },
      form: {
        warehouse_id: '',
        page: 1,
        limit: 100,
        keyword: '',
        from: '',
        to: '',
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
      archiving: false,
      filename: 'Untreated Invoices',
      checkedInvoices: [],

    };
  },
  computed: {
    warehouses() {
      return this.$store.getters.params.warehouses;
    },
  },
  beforeDestroy() {

  },
  methods: {
    moment,
    checkPermission,
    handleCheckAllChange(val) {
      const invoices = this.invoices;
      const checkedInvoices = [];
      invoices.forEach(invoice => {
        checkedInvoices.push(invoice.id);
      });
      this.checkedInvoices = val ? checkedInvoices : [];

      this.isIndeterminate = false;
    },
    handleCheckedCitiesChange(value) {
      const checkedCount = value.length;
      this.checkAll = checkedCount === this.invoices.length;
      this.isIndeterminate = checkedCount > 0 && checkedCount < this.invoices.length;
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
          this.invoices = response.invoices.data;
          this.invoices.forEach((element, index) => {
            element['index'] = (page - 1) * limit + index + 1;
          });
          this.total = response.invoices.total;
          app.table_title = 'Untreated Invoices';
          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error.message);
        });
    },
    archiveSelection() {
      const app = this;
      if (confirm(`Are you sure you want to archive the selected invoices? This cannot be undone`)) {
        const archiveInvoiceResource = new Resource('invoice/general/archive-invoices');
        app.archiving = true;
        archiveInvoiceResource.store({ invoice_ids: app.checkedInvoices })
          .then(response => {
            app.$message('Invoices Archived Successfully');
            app.getInvoices();
            app.archiving = false;
          })
          .catch(error => {
            app.archiving = false;
            console.log(error.message);
          });
      }
    },
    async handleDownload() {
      this.downloadLoading = true;
      const param = this.form;
      param.is_download = 'yes';
      const { invoices } = await outboundReport.list(param);
      import('@/vendor/Export2Excel').then(excel => {
        const multiHeader = [[this.table_title, '', '', '', '', '', '', '', '', '', '', '', '', '', '']];
        const tHeader = ['Product', 'Invoice No.', 'Customer', 'Concerned Warehouse', 'Quantity', 'Quantity Supplied', 'Unsupplied', 'UOM', 'Created At'];
        const filterVal = ['item.name', 'invoice.invoice_number', 'invoice.customer.user.name', 'warehouse.name', 'quantity', 'quantity_supplied', 'balance', 'uom', 'created_at'];
        const list = invoices;
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
        if (j === 'warehouse.name') {
          return v['warehouse']['name'];
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
        if (j === 'uom') {
          return v['type'];
        }
        if (j === 'balance') {
          return parseInt(v['quantity'] - v['quantity_supplied'] - v['quantity_reversed']);
        }
        return v[j];
      }));
    },
  },
};
</script>
