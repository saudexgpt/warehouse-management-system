<template>
  <div class="app-container">
    <span v-if="params">
      <router-link
        v-if="checkPermission(['manage waybill'])"
        :to="{ name: 'Waybills' }"
        class="btn btn-default no-print"
      >
        View Waybills</router-link>
    </span>
    <div>
      <div v-if="params" class="box">
        <div class="box-header no-print">
          <h4 class="box-title">Generate Waybill</h4>
        </div>
        <div class="box-body">
          <el-form ref="form" v-model="form" label-width="120px">
            <el-row :gutter="10" class="padded">
              <el-col :xs="24" :sm="24" :md="8">
                <label for="">Select Warehouse</label>
                <el-select
                  v-model="form.warehouse_id"
                  placeholder="Select Warehouse"
                  filterable
                  class="span"
                  @change="fetchUndeliveredInvoices()"
                >
                  <el-option
                    v-for="(warehouse, warehouse_index) in params.warehouses"
                    :key="warehouse_index"
                    :value="warehouse.id"
                    :label="warehouse.name"
                  />
                </el-select>
                <label for="">Search Invoice</label><br>
                <small>(Only confirmed invoice by auditors will be displayed for waybilling)</small>
                <el-input v-model="searchString" placeholder="Search Invoice" @input="searchInvoice">
                  <el-button slot="append" icon="el-icon-search" @click="searchRemoteInvoice(true)" />
                </el-input>
                <div v-loading="loadInvoices" style="height: 450px; overflow: auto; background: #f0f0f0; margin-top: 10px;">
                  <div>
                    <el-checkbox-group v-model="selected_invoice" @change="displayInvoiceitems">
                      <el-checkbox
                        v-for="(invoice, invoice_index) in filtered_invoices"
                        :key="invoice_index"
                        :label="invoice.id"
                        border
                      >{{ (invoice.customer) ? invoice.invoice_number + ' [' + invoice.customer.user.name + '] '
                        : invoice.invoice_number }}</el-checkbox>
                    </el-checkbox-group>
                  </div>
                </div>
              </el-col>
              <el-col v-loading="loading" :xs="24" :sm="24" :md="16">
                <el-tabs>
                  <el-tab-pane label="Draft For Loading">
                    <!-- <keep-alive> -->
                    <waybill-draft v-if="invoice_items.length > 0" :invoice-items="invoice_items" />
                    <!-- </keep-alive> -->
                  </el-tab-pane>
                  <el-tab-pane label="Final Waybill for Supply">
                    <!-- <keep-alive> -->
                    <div v-if="invoice_items.length > 0" style="overflow: auto">
                      <h4>This document is to carry exact information of items that has already been loaded in a truck and ready for supply. DO NOT Submit until you are done loading.</h4>
                      <table class="table table-binvoiceed">
                        <thead>
                          <tr>
                            <th />
                            <th>Product</th>
                            <th>Order</th>
                            <!-- <th>Specify Batch(es)</th> -->
                            <th>Supplied</th>
                            <th>Balance</th>
                            <th>Batches</th>
                            <th>To Supply</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr
                            v-for="(invoice_item, index) in invoice_items"
                            :key="index"
                          >
                            <td>{{ index + 1 }}</td>
                            <td>
                              {{ invoice_item.item.name }}
                              <div>
                                <br><small class="label label-primary">Physical Stock: {{ invoice_item.physical_stock }} {{ invoice_item.item.package_type }}</small>

                                <br><small class="label label-danger">Total Reserved: {{ invoice_item.reserved_for_supply }} {{ invoice_item.item.package_type }}</small>

                                <br><small class="label label-success">Total Available: {{ invoice_item.total_batch_balance }} {{ invoice_item.item.package_type }}</small>
                              </div>

                            </td>
                            <td>
                              {{ invoice_item.quantity }}
                              {{
                                formatPackageType(invoice_item.item.package_type)
                              }}
                            </td>

                            <td>
                              {{
                                invoice_item.quantity_supplied +
                                  ' (' +
                                  invoice_item.delivery_status +
                                  ')'
                              }}
                            </td>
                            <td>
                              <div class="alert alert-danger">
                                {{
                                  invoice_item.supply_bal
                                }}
                              </div>
                            </td>
                            <td>
                              <el-button
                                round
                                type="primary"
                                @click="selectProductBatch(index, invoice_item)"
                              >Select Batches</el-button>
                              <!-- <el-select
                                v-model="invoice_item.batches"
                                placeholder="Specify product batch for this supply"
                                filterable
                                class="span"
                                multiple
                                collapse-tags
                              >
                                <el-option
                                  v-for="(batch, batch_index) in invoice_item.item
                                    .batches"
                                  :key="batch_index"
                                  :value="batch.id"
                                  :label="
                                    batch.batch_no + ' | ' + batch.expiry_date
                                  "
                                >
                                  <span style="float: left">{{
                                    batch.batch_no + ' | ' + batch.expiry_date
                                  }}</span>
                                  <span
                                    style="
                                  float: right;
                                  color: #8492a6;
                                  font-size: 13px;
                                "
                                  >&nbsp;({{
                                    batch.balance - batch.reserved_for_supply
                                  }})</span>
                                </el-option>
                              </el-select> -->
                            </td>
                            <td>
                              <div>
                                {{ invoice_item.quantity_for_supply }}
                                <!-- <input
                                  v-model="invoice_item.quantity_for_supply"
                                  class="form-control"
                                  placeholder="Set Quantity for Supply"
                                  type="number"
                                  :max="invoice_item.supply_bal"
                                  min="0"
                                  @blur="checkForOverflow(invoice_item.supply_bal, index)"
                                > -->
                                <!-- <el-select
                              v-model="invoice_item.quantity_for_supply"
                              placeholder="Set Quantity for Supply"
                              filterable
                              class="span"
                            >
                              <el-option value="0" label="0" />
                              <el-option
                                v-for="(quantity,
                                quantity_index) in invoice_item.supply_bal"
                                :key="quantity_index"
                                :value="quantity"
                                :label="quantity"
                              />
                            </el-select> -->
                              </div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <el-row v-if="form.waybill_no" :gutter="2" class="padded">
                      <el-col :xs="24" :sm="6" :md="6">
                        <!-- <el-input v-model="form.waybill_no" required readonly /> -->
                        <el-button
                          type="success"
                          :disabled="disabled"
                          @click="generateWaybill()"
                        ><i class="el-icon-upload" />
                          Submit
                        </el-button>
                      </el-col>
                    </el-row>
                    <!-- </keep-alive> -->
                  </el-tab-pane>
                </el-tabs>

              </el-col>
            </el-row>
          </el-form>
          <el-dialog
            v-if="selected_invoice_item !== null"
            title="Set Product Batch Quantity to be supplied. Consider First In First Out (FIFO) Principle"
            :visible.sync="showBatchSelection"
            :close-on-click-modal="false"
            :close-on-press-escape="false"
            :show-close="false"
          >
            <div class="form-container">
              <label>Total batch quantity should not be more than {{ selected_invoice_item.quantity - selected_invoice_item.quantity_supplied }} {{ selected_invoice_item.item.package_type }} for {{ selected_invoice_item.item.name }}</label>
              <table class="table table-binvoiceed">
                <thead>
                  <tr>
                    <th>Batch No</th>
                    <th>Expiry Date</th>
                    <th>Balance</th>
                    <th>Enter Quantity</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(stock, stock_index) in selected_invoice_item.batches" :key="stock_index">
                    <template v-if="stock.balance > 0">

                      <td>{{ stock.batch_no }}</td>
                      <td>{{ stock.expiry_date }}</td>
                      <td>{{ stock.balance }} {{ selected_invoice_item.item.package_type }}</td>
                      <td><input v-model="stock.supply_quantity" type="number" @input="setSupplyQuantity(selected_invoice_item, stock_index)"></td>
                    </template>
                  </tr>
                  <tr>
                    <td colspan="4" align="right">
                      <h4>
                        Order Quantity: {{ selected_invoice_item.supply_bal }}<br>
                        Total Supply Quantity: {{ total_supplied }}<br>
                        Remaining Quantity: {{ selected_invoice_item.supply_bal - total_supplied }}
                      </h4>
                    </td>
                  </tr>
                  <tr>
                    <th colspan="4" align="right">
                      <aside>
                        <!-- <el-button round type="danger" @click="showBatchSelection = false">Cancel</el-button> -->
                        <el-button round type="primary" style="width: 100%" @click="updateQuantityForSupply(selected_invoice_item)">Continue</el-button>
                      </aside>
                    </th>
                  </tr>
                </tbody>
              </table>
            </div>
          </el-dialog>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
// import moment from 'moment';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';
import WaybillDraft from './WaybillDraft.vue';
import Resource from '@/api/resource';
// const createInvoice = new Resource('invoice/general/store');
// const necessaryParams = new Resource('fetch-necessary-params');
const unDeliveredInvoices = new Resource('invoice/waybill/undelivered-invoices');
// const availableVehicles = new Resource('invoice/waybill/fetch-available-vehicles');
const storeWaybillResource = new Resource('invoice/waybill/store');
export default {
  name: 'GenerateWaybill',
  components: {
    WaybillDraft,
  },
  data() {
    return {
      form: {
        warehouse_id: '',
        waybill_no: '',
        dispatch_company: 'GREEN LIFE LOGISTICS',
        status: 'pending',
        invoice_ids: [],
      },
      invoices: [],
      filtered_invoices: [],
      selected_invoice: [],
      invoice_items: [],
      waybill_items: [],
      available_vehicles: [],
      rules: {
        // vehicle_id: [{ required: true, message: 'Vehicle is required', trigger: 'change' }],
        waybill_no: [
          {
            required: true,
            message: 'Waybill Number is required',
            trigger: 'blur',
          },
        ],
      },
      loading: false,
      loadInvoices: false,
      loadBatch: false,
      disabled: false,
      showBatchSelection: false,
      selected_index: null,
      selected_invoice_item: null,
      total_supplied: 0,
      searchString: '',
    };
  },
  computed: {
    params() {
      return this.$store.getters.params;
    },
  },
  created() {
    // this.fetchUndeliveredInvoices();
    this.fetchNecessaryParams();
  },
  methods: {
    // moment,
    checkPermission,
    checkRole,
    searchInvoice(str) {
      const app = this;
      const query = str.toLowerCase();
      const key = 'invoice_number';

      const invoices = [...new Map(app.invoices.map(item => [item[key], item])).values()];
      // var new_filter = [];
      // eslint-disable-next-line no-array-constructor
      // const new_filter = new Array();
      if (query && query.trim() !== '') {
        const new_filter = invoices.filter(invoice => invoice.invoice_number.toLowerCase().indexOf(query) > -1);

        this.filtered_invoices = new_filter;
        if (new_filter.length < 1) {
          app.searchRemoteInvoice(false);
        }
        // });
      } else {
        // if nothing is typed, restore the full list
        this.filtered_invoices = invoices;
      }
    },
    selectProductBatch(index, invoice_item) {
      const app = this;
      app.showBatchSelection = true;
      app.selected_index = index;
      app.selected_invoice_item = invoice_item;
      app.total_supplied = invoice_item.total_supplied;
    },
    setSupplyQuantity(selectedInvoiceItem, stockIndex) {
      const app = this;
      const for_supply = selectedInvoiceItem.supply_bal;
      let total_supply_quantity = 0;
      let count = 0;
      app.selected_invoice_item.batches.forEach(stock => {
        if (count === stockIndex){
          const balance = parseInt(stock.balance);
          const supply_quantity = parseInt(stock.supply_quantity);
          // check whether batch balance is less than inputed quantity
          if (balance < supply_quantity) {
            app.$alert(`Batch quantity should not be more than ${supply_quantity}`);
            stock.supply_quantity = parseInt(balance);
          }
          // check whether total supply quantity is more than order quantity
          if ((parseInt(total_supply_quantity) + parseInt(stock.supply_quantity)) > for_supply) {
            app.$alert(`Total supply quantity is more than Order quantity of ${for_supply}. Kindly correct.`);
            stock.supply_quantity = 0;
          }
        }
        total_supply_quantity += parseInt(stock.supply_quantity);
        count++;
      });
      app.total_supplied = parseInt(total_supply_quantity);
      app.selected_invoice_item.total_supplied = parseInt(total_supply_quantity);
      if (app.selected_invoice_item.total_supplied > for_supply) {
        app.$alert(`Total supply quantity is more than Order quantity of ${for_supply}. Kindly correct.`);
      }
      // this.selected_invoice_item = null;
      // setTimeout(() => {
      //   this.selected_invoice_item = invoice_item;
      // }, 100);
    },
    updateQuantityForSupply(selectedInvoiceItem) {
      const app = this;
      const for_supply = selectedInvoiceItem.supply_bal;
      const total_supply_quantity = app.total_supplied;
      if (total_supply_quantity > for_supply) {
        app.$alert(`Total supply quantity is more than Order quantity of ${for_supply}. Please fix to continue.`);
      } else {
        selectedInvoiceItem.quantity_for_supply = total_supply_quantity;
        app.selected_invoice_item = selectedInvoiceItem;
        app.invoice_items[app.selected_index] = app.selected_invoice_item;
        app.showBatchSelection = false;
      }
    },
    // setProductBatches(batchId, quantity) {
    //   // const app = this;
    //   // const invoice_item = app.selected_invoice_item;
    // },
    fetchUndeliveredInvoices(index) {
      const app = this;
      var form = app.form;
      app.loadInvoices = true;
      // const loading = unDeliveredInvoices.loaderShow();
      unDeliveredInvoices.list(form).then((response) => {
        app.invoices = response.invoices.data;
        app.filtered_invoices = app.invoices;
        app.form.waybill_no = response.waybill_no;
        app.loadInvoices = false;
        // loading.hide();
        // app.fetchAvailableDrivers();
      });
    },
    searchRemoteInvoice(load) {
      const app = this;
      if (app.filtered_invoices.length < 1) {
        var form = app.form;
        app.loadInvoices = load;
        const unDeliveredInvoices = new Resource('invoice/waybill/undelivered-invoices-search');
        const param = {
          warehouse_id: form.warehouse_id,
          invoice_no: app.searchString,
        };
        unDeliveredInvoices.list(param).then((response) => {
          app.invoices = response.invoices.concat(app.invoices);

          const key = 'invoice_number';
          const filtered_invoices = response.invoices.concat(app.filtered_invoices);
          app.filtered_invoices = [...new Map(filtered_invoices.map(item => [item[key], item])).values()];
          app.loadInvoices = false;
        // loading.hide();
        // app.fetchAvailableDrivers();
        });
      }
    },
    fetchNecessaryParams() {
      const app = this;
      app.$store.dispatch('app/setNecessaryParams');
    },

    displayInvoiceitems(value) {
      const app = this;
      app.invoice_items = [];
      app.form.invoice_ids = [];
      let invoice_ids = [];
      const selected_invoice_ids = value;
      if (selected_invoice_ids.length > 0) {
        invoice_ids = value;
        const unDeliveredInvoices = new Resource('invoice/waybill/waybill-selected-invoices');
        const param = {
          warehouse_id: app.form.warehouse_id,
          invoice_ids: selected_invoice_ids,
        };
        app.loading = true;
        unDeliveredInvoices.list(param).then((response) => {
          const invoice_items = response.invoice_items;
          invoice_items.forEach((invoice_item) => {
            var total_batch_balance = 0;
            var reserved_for_supply = 0;
            var physical_stock = 0;

            var supply_bal = invoice_item.quantity - invoice_item.quantity_supplied - invoice_item.quantity_reversed;
            var stocks = invoice_item.item.stocks;
            const batches = [];
            stocks.forEach((stock_batch) => {
              batches.push({
                batch_no: stock_batch.batch_no,
                expiry_date: stock_batch.expiry_date,
                balance: stock_batch.balance - stock_batch.reserved_for_supply,
                invoice_item_id: invoice_item.id,
                supply_quantity: 0,

              });
              total_batch_balance +=
                parseInt(stock_batch.balance) -
                parseInt(stock_batch.reserved_for_supply);
              reserved_for_supply += parseInt(stock_batch.reserved_for_supply);

              physical_stock += parseInt(stock_batch.balance);
            });
            invoice_item.batches = batches;
            invoice_item.quantity_for_supply = 0;
            invoice_item.total_supplied = 0;
            if (supply_bal > total_batch_balance) {
              invoice_item.supply_bal = total_batch_balance;
            } else {
              invoice_item.supply_bal = supply_bal;
            }
            invoice_item.total_batch_balance = total_batch_balance;
            invoice_item.reserved_for_supply = reserved_for_supply;
            invoice_item.physical_stock = physical_stock;
          });
          app.invoice_items = invoice_items;
          app.form.invoice_ids = invoice_ids;
          app.loading = false;
          // loading.hide();
          // app.fetchAvailableDrivers();
        });
      }
    },
    displayInvoiceitemsOLD(value) {
      const app = this;
      var selected_invoice = value;
      var invoice_items = [];
      var invoice_ids = [];
      selected_invoice.forEach(invoice => {
        invoice_items.push(...invoice.invoice_items);
        invoice_ids.push(invoice.id);
      });
      invoice_items.forEach((invoice_item) => {
        var total_batch_balance = 0;
        var reserved_for_supply = 0;
        var physical_stock = 0;

        var supply_bal = invoice_item.quantity - invoice_item.quantity_supplied;
        var stocks = invoice_item.item.stocks;
        const batches = [];
        stocks.forEach((stock_batch) => {
          batches.push({
            batch_no: stock_batch.batch_no,
            expiry_date: stock_batch.expiry_date,
            balance: stock_batch.balance - stock_batch.reserved_for_supply,
            invoice_item_id: invoice_item.id,
            supply_quantity: 0,

          });
          total_batch_balance +=
            parseInt(stock_batch.balance) -
            parseInt(stock_batch.reserved_for_supply);
          reserved_for_supply += parseInt(stock_batch.reserved_for_supply);

          physical_stock += parseInt(stock_batch.balance);
        });
        invoice_item.batches = batches;
        invoice_item.supply_bal = supply_bal;
        invoice_item.quantity_for_supply = 0;
        invoice_item.total_supplied = 0;
        if (supply_bal > total_batch_balance) {
          invoice_item.supply_bal = total_batch_balance;
        }
        invoice_item.total_batch_balance = total_batch_balance;
        invoice_item.reserved_for_supply = reserved_for_supply;
        invoice_item.physical_stock = physical_stock;
      });
      app.invoice_items = invoice_items;
      app.form.invoice_ids = invoice_ids;
      // app.loading = false;
    },
    checkForOverflow(limit, index) {
      const app = this;
      const value = app.invoice_items[index].quantity_for_supply;
      const product = app.invoice_items[index].item.name;
      const package_type = app.invoice_items[index].item.package_type;
      if (value > limit) {
        app.invoice_items[index].quantity_for_supply = limit;
        app.$alert('Make sure you DO NOT exceed ' + limit + ' ' + package_type + ' for ' + product);
      }
    },
    // fetchAvailableDrivers(){
    //   const app = this;
    //   var form = app.form;
    //   availableVehicles
    //     .list(form)
    //     .then(response => {
    //       app.available_vehicles = response.available_vehicles;
    //     });
    // },
    generateWaybill() {
      const invoice_items = this.invoice_items;

      if (invoice_items.length < 1) {
        this.$alert('Please select at least one invoice to generate a waybill');
        return false;
      }
      let itemQuantityCheck = 0;
      invoice_items.forEach(element => {
        itemQuantityCheck += parseInt(element.quantity_for_supply);
        if (element.supply_bal < element.quantity_for_supply) {
          this.$alert('To continue, kindly reduce ' + element.item.name + ' quantity to ' + element.supply_bal);
          return false;
        }
      });
      if (itemQuantityCheck < 1) {
        this.$alert('You are not permitted to generate empty waybill. Kindly enter at least one product quantity');
        return false;
      }

      this.$confirm(
        'Check through your selection before submitting. Continue?',
        'Warning',
        {
          confirmButtonText: 'Yes, Submit',
          cancelButtonText: 'Cancel',
          type: 'warning',
        },
      )
        .then(() => {
          // const loading = storeWaybillResource.loaderShow();
          this.loading = true;
          this.form.invoice_items = invoice_items;
          this.disabled = true;
          storeWaybillResource
            .store(this.form)
            .then((response) => {
              if (response.status) {
                this.error_message = response.status + response.message;
              } else {
                this.$message({
                  message: 'Waybill created successfully.',
                  type: 'success',
                  duration: 5 * 1000,
                });
                this.$router.replace('waybill');
              }
              this.loading = false;
            })
            .catch((error) => {
              this.loading = false;
              console.log(error.message);
              this.disabled = false;
            })
            .finally(() => {
              this.loading = false;
              this.creatingWaybill = false;
              this.disabled = false;
            });
        })
        .catch(() => {
          // this.$message({
          //   type: 'info',
          //   message: 'Delete canceled',
          // });
        });
    },
    formatPackageType(type) {
      // var formated_type = type + 's';
      // if (type === 'Box') {
      //   formated_type = type + 'es';
      // }
      return type;
    },
  },
};
</script>

