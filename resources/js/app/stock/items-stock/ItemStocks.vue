<template>
  <div class="app-container">
    <!-- <item-details v-if="page.option== 'view_details'" :item-in-stock="itemInStock" :page="page" /> -->
    <add-new v-if="page.option== 'add_new'" :items-in-stock="items_in_stock" :params="params" :page="page" />
    <edit-item v-if="page.option=='edit_item'" :items-in-stock="items_in_stock" :item-in-stock="itemInStock" :params="params" :page="page" @update="onEditUpdate" />
    <div v-if="page.option=='list'">
      <el-row :gutter="10">
        <el-col :xs="24" :sm="12" :md="12">
          <label for="">Select Warehouse</label>
          <el-select v-model="form.warehouse_index" placeholder="Select Warehouse" class="span" filterable @input="fetchItemStocks">
            <el-option v-for="(warehouse, index) in warehouses" :key="index" :value="index" :label="warehouse.name" />

          </el-select>

        </el-col>
        <!-- <el-col :xs="24" :sm="12" :md="12">
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
        </el-col> -->
      </el-row>
      <br>

      <el-tabs v-model="activeActivity">
        <el-tab-pane v-if="form.warehouse_id !== 8" label="UNEXPIRED PRODUCTS" name="unexpired">
          <div class="box-header">
            <h4 class="box-title">{{ table_title }}</h4>

            <span class="pull-right">
              <a v-if="checkPermission(['manage item stocks', 'create item stocks'])" class="btn btn-info" @click="page.option = 'add_new'"> Add New</a>
            </span>

          </div>
          <div class="box-body">
            <div v-if="items_in_stock.length > 0" class="pull-left">
              <el-button :loading="downloadLoading" style="margin:0 0 20px 20px;" type="primary" icon="document" @click="handleDownload">
                Export Excel
              </el-button>
            </div>

            <v-client-table v-model="items_in_stock" :columns="columns" :options="options">
              <div slot="quantity" slot-scope="{row}" class="alert alert-info">
                {{ row.quantity }} {{ formatPackageType(row.item.package_type) }}
                <br><small v-html="showItemsInCartons(row.quantity, row.item.quantity_per_carton)" />
              </div>
              <div slot="in_transit" slot-scope="{row}" class="alert alert-warning">
                {{ row.in_transit }} {{ formatPackageType(row.item.package_type) }}
                <br><small v-html="showItemsInCartons(row.in_transit, row.item.quantity_per_carton)" />
              </div>
              <div slot="supplied" slot-scope="{row}" class="alert alert-danger">
                {{ row.supplied }} {{ formatPackageType(row.item.package_type) }}
                <br><small v-html="showItemsInCartons(row.supplied, row.item.quantity_per_carton)" />
              </div>
              <div slot="reserved_for_supply" slot-scope="{row}" class="alert alert-default">
                <a @click="showReservationTransactions(row)">
                  {{ row.reserved_for_supply }} {{ formatPackageType(row.item.package_type) }}
                </a>
                <br><small v-html="showItemsInCartons(row.reserved_for_supply, row.item.quantity_per_carton)" />
              </div>
              <div slot="in_stock" slot-scope="{row}" class="alert alert-primary">
                {{ row.balance }} {{ formatPackageType(row.item.package_type) }}
                <br><small v-html="showItemsInCartons(row.balance, row.item.quantity_per_carton)" />
              </div>
              <div slot="balance" slot-scope="{row}" class="alert alert-success">
                {{ (row.balance - row.reserved_for_supply) }} {{ formatPackageType(row.item.package_type) }}
                <br><small v-html="showItemsInCartons(row.balance - row.reserved_for_supply, row.item.quantity_per_carton)" />
              </div>
              <div slot="expiry_date" slot-scope="{row}" :class="expiryFlag(moment(row.expiry_date).format('x'))">
                <span>
                  {{ moment(row.expiry_date).fromNow() }}
                </span>
              </div>
              <div slot="created_at" slot-scope="{row}">
                {{ moment(row.created_at).fromNow() }}
              </div>
              <div slot="confirmer.name" slot-scope="{row}">
                <div :id="row.id">
                  <div v-if="row.confirmed_by == null">
                    <a v-if="checkPermission(['audit confirm actions'])" title="Click to confirm" class="btn btn-warning" @click="confirmItemStocked(row.id);"><i class="fa fa-check" /> </a>
                  </div>
                  <div v-else>
                    {{ row.confirmer.name }}
                  </div>
                </div>
              </div>
              <div slot="action" slot-scope="props">
                <div v-if="props.row.is_warehouse_transfered === 0">
                  <a v-if="checkPermission(['manage item stocks', 'update item stocks'])" class="btn btn-primary" @click="itemInStock=props.row; selected_row_index=props.index; page.option = 'edit_item'"><i class="fa fa-edit" /> </a>
                  <a v-if="checkPermission(['manage item stocks', 'delete item stocks']) && props.row.reserved_for_supply == 0 && props.row.in_transit == 0 && props.row.balance == 0" class="btn btn-danger" @click="confirmDelete(props.index, props)"><i class="fa fa-trash" /> </a>
                  <el-dropdown
                    class="avatar-container right-menu-item hover-effect"
                    trigger="click"
                  >
                    <el-tooltip content="Request for Quantity Update" placement="top">
                      <el-button class="btn btn-warning" icon="el-icon-document" />
                    </el-tooltip>
                    <el-dropdown-menu slot="dropdown" style="padding: 10px;">
                      Quantity update request for: <br> {{ props.row.item.name }} ({{ props.row.batch_no }})<br>
                      <el-input
                        v-model="new_stock_quantity"
                        placeholder="Enter new stock quantity"
                        type="number"
                        min="0"
                        style="width: 100%;"
                      />
                      <a class="btn btn-success" @click="createTicket(props.row)">Submit</a>
                      <el-dropdown-item divided>Close</el-dropdown-item>
                    </el-dropdown-menu>
                  </el-dropdown>

                </div>
                <!-- <div v-else>
                  <a v-if="checkPermission(['manage item stocks', 'update item stocks'])" class="btn btn-dark"><i class="fa fa-edit" /> </a>
                  <a v-if="checkPermission(['manage item stocks', 'delete item stocks'])" class="btn btn-dark"><i class="fa fa-trash" /> </a>
                </div> -->

                <!-- <a class="btn btn-default" @click="itemInStock=props.row; page.option = 'view_details'"><i class="fa fa-eye" /> </a> -->
                <!-- <a class="btn btn-warning" @click="itemInStock=props.row; selected_row_index=props.index; page.option = 'edit_item'"><i class="fa fa-edit" /> </a>
            <a class="btn btn-danger" @click="confirmDelete(props)"><i class="fa fa-trash" /> </a> -->

              </div>
            </v-client-table>

          </div>
        </el-tab-pane>
        <el-tab-pane label="EXPIRED PRODUCTS" name="expired">
          <div class="box-header">
            <h4 class="box-title">{{ expired_title }}</h4>

          </div>
          <div class="box-body">
            <div v-if="expired_products.length > 0" class="pull-left">
              <el-button :loading="downloadLoading" style="margin:0 0 20px 20px;" type="primary" icon="document" @click="handleDownload2">
                Export Excel
              </el-button>
            </div>

            <v-client-table v-model="expired_products" :columns="expired_columns" :options="expired_options">
              <!-- <div slot="quantity" slot-scope="{row}" class="alert alert-info">
                {{ row.quantity }} {{ formatPackageType(row.item.package_type) }}

              </div>
              <div slot="destroyed" slot-scope="{row}" class="alert alert-warning">
                {{ row.destroyed }} {{ formatPackageType(row.item.package_type) }}

              </div> -->
              <!-- <div slot="expired" slot-scope="{row}" class="alert alert-danger">
                {{ row.expired }} {{ formatPackageType(row.item.package_type) }}

              </div> -->
              <div slot="balance" slot-scope="{row}" class="alert alert-danger">
                {{ row.balance - row.reserved_for_supply }} {{ formatPackageType(row.item.package_type) }}

              </div>
              <div slot="expiry_date" slot-scope="{row}">
                {{ row.expiry_date }}
              </div>
              <div v-if="form.warehouse_id !== 8" slot="action" slot-scope="props">
                <el-dropdown
                  class="avatar-container right-menu-item hover-effect"
                  trigger="click"
                >
                  <el-tooltip content="Move to Expired Warehouse" placement="top">
                    <el-button class="btn btn-danger" icon="el-icon-position" round />
                  </el-tooltip>
                  <el-dropdown-menu slot="dropdown" style="padding: 10px;">
                    Confirm movement of  {{ props.row.balance - props.row.reserved_for_supply }} {{ formatPackageType(props.row.item.package_type) }} of <br>{{ props.row.item.name }} to Expired Warehouse?<br>
                    <a class="btn btn-primary" @click="moveExpiredProduct(props.index, props.row)">Yes, Move</a>
                    <el-dropdown-item :id="props.index" divided>Cancel</el-dropdown-item>
                  </el-dropdown-menu>
                </el-dropdown>
              </div>
            </v-client-table>

          </div>
        </el-tab-pane>
      </el-tabs>
    </div>
    <show-item-reservation-transactions
      :dialog-form-visible="dialogFormVisible"
      :transactions="transactions"
      :title="transaction_title"
      @close="dialogFormVisible=false"
    />
    <!-- <create-issue-ticket
      :ticket-form-visible="ticketFormVisible"
      :issue-details="issue_details"
      @close="ticketFormVisible=false"
    /> -->
  </div>
</template>
<script>
import moment from 'moment';
import { parseTime } from '@/utils';
import checkPermission from '@/utils/permission';
import showItemsInCartons from '@/utils/functions';
import checkRole from '@/utils/role';

import AddNew from './partials/AddNew';
import EditItem from './partials/EditItem';
import ShowItemReservationTransactions from './ShowItemReservationTransactions';
// import CreateIssueTicket from '@/app/tickets/CreateTicket';
// import ItemDetails from './partials/ItemDetails';
import Resource from '@/api/resource';
// import Vue from 'vue';
// const necessaryParams = new Resource('fetch-necessary-params');
// const fetchWarehouse = new Resource('warehouse/fetch-warehouse');
const itemsInStock = new Resource('stock/items-in-stock');
const deleteItemInStock = new Resource('stock/items-in-stock/delete');
const confirmItemInStock = new Resource('audit/confirm/items-in-stock');
export default {
  name: 'ItemStocks',
  components: { AddNew, EditItem, ShowItemReservationTransactions },
  data() {
    return {
      activeActivity: 'unexpired',
      warehouses: [],
      items_in_stock: [],
      expired_products: [],
      columns: ['action', 'confirmer.name', 'item.name', 'batch_no', 'expiry_date', 'quantity', 'in_transit', 'supplied', /* 'expired',*/ 'in_stock', 'reserved_for_supply', 'balance', 'created_at', 'stocker.name'],

      options: {
        headings: {
          'confirmer.name': 'Confirmed By',
          'stocker.name': 'Stocked By',
          'item.name': 'Product',
          batch_no: 'Batch No.',
          quantity: 'QTY Stocked',
          in_transit: 'On Trans',
          supplied: 'Supplied',
          reserved_for_supply: 'For Supply',
          in_stock: 'PHYS. Stock',
          balance: 'Main Bal.',
          expiry_date: 'Expires',
          created_at: 'Created',

          // id: 'S/N',
        },
        pagination: {
          dropdown: true,
          chunk: 10,
        },
        filterByColumn: true,
        texts: {
          filter: 'Search:',
        },
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: ['item.name', 'batch_no', 'expiry_date'/* 'item.name', 'batch_no', 'quantity', 'in_transit', 'supplied', 'balance', 'expiry_date', 'created_at'*/],
        filterable: ['stocker.name', 'item.name', 'batch_no', 'expiry_date', 'created_at'],
      },
      expired_columns: ['item.name', 'batch_no', 'balance', /* 'destroyed', 'balance', */'expiry_date', 'action'],

      expired_options: {
        headings: {
          'item.name': 'Product',
          batch_no: 'Batch No.',
          balance: 'Balance',

          // id: 'S/N',
        },
        pagination: {
          dropdown: true,
          chunk: 10,
        },
        filterByColumn: true,
        texts: {
          filter: 'Search:',
        },
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: [/* 'item.name', 'batch_no', 'expiry_date', 'quantity', 'in_transit', 'supplied', 'balance', 'expiry_date', 'created_at'*/],
        filterable: ['item.name', 'batch_no', 'balance', 'expiry_date'],
      },
      page: {
        option: 'list',
      },
      form: {
        warehouse_id: '',
        warehouse_index: '',
        from: '',
        to: '',
        panel: 'month',
      },
      submitTitle: 'Fetch Report',
      panel: 'month',
      future: false,
      panels: ['range', 'week', 'month', 'quarter', 'year'],
      show_calendar: false,
      in_warehouse: '',
      itemInStock: {},
      selected_row_index: '',
      product_expiry_date_alert_in_months: 9, // defaults to 9 months
      downloadLoading: false,
      filename: 'Products in Stock',
      table_title: '',
      expired_title: '',
      dialogFormVisible: false,
      ticketFormVisible: false,
      transactions: [],
      transaction_title: '',
      new_stock_quantity: null,

    };
  },
  computed: {
    params() {
      return this.$store.getters.params;
    },
  },
  created() {
    // this.getWarehouse();
    this.fetchNecessaryParams();
  },
  beforeDestroy() {

  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    showItemsInCartons,
    showReservationTransactions(item_stock){
      const transactionResource = new Resource('reports/reserved-product-transactions');
      const loader = transactionResource.loaderShow();
      this.transaction_title = 'Reservations of ' + item_stock.item.name + ' (' + item_stock.batch_no + ')';
      transactionResource.get(item_stock.id)
        .then((response) => {
          this.transactions = response;
          this.dialogFormVisible = true;
          loader.hide();
        });
    },
    createTicket(item_stock) {
      const new_stock_quantity = this.new_stock_quantity;
      if (new_stock_quantity > 0) {
        const title = 'Quantity update request for ' + item_stock.item.name + ' with batch no ' + item_stock.batch_no;
        const details = 'Please sir,  kindly update the quantity of ' + item_stock.item.name + ' with batch no ' + item_stock.batch_no + ' from ' + item_stock.quantity + ' to ' + new_stock_quantity;
        const createTicket = new Resource('ticket/create-ticket');
        const param = {
          title: title,
          details: details,
          table_name: 'item_stock_sub_batches',
          table_id: item_stock.id,
          new_quantity: new_stock_quantity,
          old_quantity: item_stock.quantity,
        };
        const loader = createTicket.loaderShow();
        createTicket.store(param)
          .then(() => {
            loader.hide();
            this.new_stock_quantity = null;
          });
      }
      // this.issue_details.title = 'Request for Update on Product Stocked Quantity';
      // this.issue_details.details = item_stock;
      // console.log(item_stock);
      // this.ticketFormVisible = true;
    },
    showCalendar(values){
      document.getElementById('pick_date').click();
      this.setDateRange(values);
    },
    format(date) {
      var month = date.toLocaleString('en-US', { month: 'short' });
      return month + ' ' + date.getDate() + ', ' + date.getFullYear();
    },
    setDateRange(values){
      const app = this;
      document.getElementById('pick_date').click();
      let panel = app.panel;
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
    fetchNecessaryParams() {
      const app = this;
      app.$store.dispatch('app/setNecessaryParams');
      const params = app.params;
      app.warehouses = params.warehouses;
      if (app.warehouses.length > 0) {
        app.form.warehouse_id = app.warehouses[0];
        app.form.warehouse_index = 0;

        const from = app.format(new Date(app.moment().clone().startOf('month').format('YYYY-MM-DD hh:mm')));
        const to = app.format(new Date(app.moment().clone().endOf('month').format('YYYY-MM-DD hh:mm')));

        app.form.from = from;
        app.form.to = to;
        app.fetchItemStocks();
      }
      app.product_expiry_date_alert_in_months = params.product_expiry_date_alert;
    },
    // fetchNecessaryParams() {
    //   const app = this;
    //   const loader = necessaryParams.loaderShow();
    //   necessaryParams.list()
    //     .then(response => {
    //       const params = response.params;
    //       app.$store.dispatch('app/setNecessaryParams', params);
    //       app.warehouses = params.warehouses;
    //       if (app.warehouses.length > 0) {
    //         app.form.warehouse_id = app.warehouses[0];
    //         app.form.warehouse_index = 0;

    //         const from = app.format(new Date(app.moment().clone().startOf('month').format('YYYY-MM-DD hh:mm')));
    //         const to = app.format(new Date(app.moment().clone().endOf('month').format('YYYY-MM-DD hh:mm')));

    //         app.form.from = from;
    //         app.form.to = to;
    //         app.fetchItemStocks();
    //         loader.hide();
    //       }
    //       app.product_expiry_date_alert_in_months = params.product_expiry_date_alert;
    //     });
    // },
    confirmItemStocked(stock_id) {
      // const app = this;
      const form = { id: stock_id };
      const message = 'Click okay to confirm action';
      if (confirm(message)) {
        confirmItemInStock.update(stock_id, form)
          .then(response => {
            if (response.confirmed === 'success'){
              document.getElementById(stock_id).innerHTML = response.confirmed_by;
            }
          });
      }
    },
    fetchItemStocks() {
      const app = this;
      const loader = itemsInStock.loaderShow();

      const param = app.form;
      param.warehouse_id = app.warehouses[param.warehouse_index].id;
      if (param.warehouse_id === 8){
        app.activeActivity = 'expired';
      }
      itemsInStock.list(param)
        .then(response => {
          app.items_in_stock = response.items_in_stock;
          app.expired_products = response.expired_products;
          // app.in_warehouse = 'in ' + app.warehouses[param.warehouse_index].name;
          app.table_title = 'Unexpired Products in ' + app.warehouses[param.warehouse_index].name;/* + ' from: ' + app.form.from + ' to: ' + app.form.to;*/
          app.expired_title = 'Expired Products in ' + app.warehouses[param.warehouse_index].name;/* + ' from: ' + app.form.from + ' to: ' + app.form.to;*/
          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error.message);
        });
    },

    onEditUpdate(updated_row) {
      const app = this;
      // app.items_in_stock.splice(app.itemInStock.index-1, 1);
      app.items_in_stock[app.selected_row_index - 1] = updated_row;
    },
    confirmDelete(index, props) {
      // this.loader();
      // alert(props.index);
      const app = this;
      const message = 'This delete action cannot be undone. Click OK to confirm';
      if (confirm(message)) {
        deleteItemInStock.destroy(props.row.id, props.row)
          .then(response => {
            app.items_in_stock.splice(index - 1, 1);
            this.$message({
              message: 'Item has been deleted',
              type: 'success',
            });
          })
          .catch(error => {
            console.log(error);
          });
      }
    },
    expiryFlag(date){
      const product_expiry_date_alert = this.product_expiry_date_alert_in_months;
      const min_expiration = parseInt(product_expiry_date_alert * 30 * 24 * 60 * 60 * 1000); // we use 30 days for one month to calculate
      const today = parseInt(this.moment().valueOf()); // Unix Timestamp (miliseconds) 1.6.0+
      if (parseInt(date) < today) {
        // console.log(parseInt(date) - today);
        return 'expired-bg'; // flag expiry date as red
      }
      if (parseInt(date) - today <= min_expiration) {
        // console.log(parseInt(date) - today);
        return 'alert-bg'; // flag expiry date as red
      }
      return 'okay-bg'; // flag expiry date as green
    },
    moveExpiredProduct(index, data) {
      const app = this;
      document.getElementById(index).click();
      // const loader = itemsInStock.loaderShow();
      app.expired_products.splice(index - 1, 1);
      const moveExpiredProduct = new Resource('stock/items-in-stock/move-expired-products');
      const param = {
        id: data.id,
        item_id: data.item_id,
        quantity: data.balance - data.reserved_for_supply,
        batch_no: data.batch_no,
        goods_received_note: data.goods_received_note,
        expiry_date: data.expiry_date,
      };
      moveExpiredProduct.store(param)
        .then(() => {
          this.$message({
            message: 'Product moved successfully',
            type: 'success',
          });
          // loader.hide();
        })
        .catch(error => {
          // loader.hide();
          console.log(error.message);
        });
    },
    formatPackageType(type){
      // var formated_type = type + 's';
      // if (type === 'Box') {
      //   formated_type = type + 'es';
      // }
      return type;
    },
    handleDownload() {
      this.downloadLoading = true;
      import('@/vendor/Export2Excel').then(excel => {
        const multiHeader = [[this.table_title, '', '', '', '', '', '', '', '', '', '', '', '']];
        const tHeader = ['Confirmed By', 'Product', 'Batch No.', 'Expires', 'Quantity', 'In Transit', 'Supplied', 'Physical Stock', 'Reserved for Supply', 'Main Balance', 'Created', 'Stocked By'];
        const filterVal = ['confirmer.name', 'item.name', 'batch_no', 'expiry_date', 'quantity', 'in_transit', 'supplied', 'in_stock', 'reserved_for_supply', 'balance', 'created_at', 'stocker.name'];
        const list = this.items_in_stock;
        const data = this.formatJson(filterVal, list);
        excel.export_json_to_excel({
          multiHeader,
          header: tHeader,
          data,
          filename: this.table_title,
          autoWidth: true,
          bookType: 'csv',
        });
        this.downloadLoading = false;
      });
    },
    handleDownload2() {
      this.downloadLoading = true;
      import('@/vendor/Export2Excel').then(excel => {
        const multiHeader = [[this.expired_title, '', '', '', '']];
        const tHeader = ['Product', 'Batch No.', 'Quantity', 'Expiry Date'];
        const filterVal = ['item.name', 'batch_no', 'quantity', 'expiry_date'];
        const list = this.items_in_stock;
        const data = this.formatJson(filterVal, list);
        excel.export_json_to_excel({
          multiHeader,
          header: tHeader,
          data,
          filename: this.expired_title,
          autoWidth: true,
          bookType: 'csv',
        });
        this.downloadLoading = false;
      });
    },
    formatJson(filterVal, jsonData) {
      return jsonData.map(v => filterVal.map(j => {
        if (j === 'confirmer.name') {
          if (v['confirmer'] != null) {
            return v['confirmer']['name'];
          }
        }
        if (j === 'item.name') {
          if (v['item'] != null) {
            return v['item']['name'];
          }
        }
        if (j === 'expiry_date') {
          return parseTime(v['expiry_date']);
        }
        if (j === 'created_at') {
          return parseTime(v['created_at']);
        }
        if (j === 'stocker.name') {
          if (v['stocker'] != null) {
            return v['stocker']['name'];
          }
        }
        if (j === 'quantity') {
          if (v['item'] != null) {
            return v['quantity'] + ' ' + v['item']['package_type'];
          } else {
            return v['quantity'];
          }
        }
        if (j === 'in_transit') {
          if (v['item'] != null) {
            return v['in_transit'] + ' ' + v['item']['package_type'];
          } else {
            return v['in_transit'];
          }
        }
        if (j === 'reserved_for_supply') {
          if (v['item'] != null) {
            return v['reserved_for_supply'] + ' ' + v['item']['package_type'];
          } else {
            return v['reserved_for_supply'];
          }
        }
        if (j === 'supplied') {
          if (v['item'] != null) {
            return v['supplied'] + ' ' + v['item']['package_type'];
          } else {
            return v['supplied'];
          }
        }
        if (j === 'in_stock') {
          if (v['item'] != null) {
            return v['balance'] + ' ' + v['item']['package_type'];
          } else {
            return v['balance'];
          }
        }
        if (j === 'balance') {
          if (v['item'] != null) {
            return (v['balance'] - v['reserved_for_supply']) + ' ' + v['item']['package_type'];
          } else {
            return v['balance'] - v['reserved_for_supply'];
          }
        }
        return v[j];
      }));
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
.alert-bg{
		color: #fff;
		padding: 10px;
		display: inline-block;
		border-radius: 5px;
    -webkit-animation: blinkingBackground 1s infinite;  /* Safari 4+ */
    -moz-animation: blinkingBackground 1s infinite;  /* Fx 5+ */
    -o-animation: blinkingBackground 1s infinite;  /* Opera 12+ */
    animation: blinkingBackground 1s infinite;  /* IE 10+, Fx 29+ */
	}
	@keyframes blinkingBackground{
		0%, 79%		{ background-color: #33d45b;}
		80%, 100%	{ background-color: #000000;}
	}
  .okay-bg{
		color: #fff;
		padding: 10px;
		display: inline-block;
		border-radius: 5px;
		background-color: #33d45b;
	}
  .expired-bg{
		color: #fff;
		padding: 10px;
		display: inline-block;
		border-radius: 5px;
		background-color: #000000;
	}
</style>
