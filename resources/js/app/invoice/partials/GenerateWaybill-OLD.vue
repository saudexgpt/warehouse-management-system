<template>
  <div class="app-container">
    <span v-if="params">
      <router-link
        v-if="checkPermission(['manage waybill'])"
        :to="{ name: 'Waybills' }"
        class="btn btn-default"
      >
        View Waybills</router-link>
    </span>
    <div>
      <div v-if="params" class="box">
        <div class="box-header">
          <h4 class="box-title">Generate Waybill</h4>
        </div>
        <div class="box-body">
          <el-form ref="form" :model="form" label-width="120px">
            <el-row :gutter="5" class="padded">
              <el-col :xs="24" :sm="12" :md="12">
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
              </el-col>
              <el-col :xs="24" :sm="12" :md="12">
                <label for="">Search Invoice</label>
                <small>(Only confirmed invoice by auditors will be displayed for waybilling)</small>
                <el-select
                  v-model="selected_invoice"
                  placeholder="Select Invoice"
                  filterable
                  class="span"
                  multiple
                  collapse-tags
                  @input="displayInvoiceitems()"
                >
                  <el-option
                    v-for="(invoice, invoice_index) in invoices"
                    :key="invoice_index"
                    :value="invoice_index"
                    :label="
                      invoice.customer
                        ? invoice.customer.user.name +
                          '[ ' +
                          invoice.invoice_number +
                          '] '
                        : invoice.invoice_number
                    "
                  />
                </el-select>
              </el-col>
              <!-- <el-col :xs="24" :sm="2" :md="2">
                <br>
                <el-button type="success" @click="displayInvoiceitems()"><i class="el-icon-plus" />
                  Generate Waybill
                </el-button>
              </el-col> -->
            </el-row>
            <el-row :gutter="2" class="padded">
              <el-col>
                <div style="overflow: auto">
                  <label for="">Products</label>
                  <table v-loading="loading" class="table table-binvoiceed">
                    <thead>
                      <tr>
                        <th />
                        <th>Product</th>
                        <th>Order</th>
                        <th>Stocks</th>
                        <!-- <th>Specify Batch(es)</th> -->
                        <th>Supplied</th>
                        <th>Balance</th>
                        <th>To Supply</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td colspan="3" />
                        <td><label>BN | Exp Date (Quantity)</label></td>
                        <td colspan="3" />
                      </tr>
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
                          <el-select
                            v-model="invoice_item.batches"
                            placeholder="Specify product batch for this supply"
                            filterable
                            class="span"
                            multiple
                            collapse-tags
                          >
                            <el-option
                              v-for="(batch, batch_index) in invoice_item.item
                                .stocks"
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
                          </el-select>
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
                              invoice_item.quantity -
                                invoice_item.quantity_supplied
                            }}
                          </div>
                        </td>
                        <td>
                          <div v-if="invoice_item.supply_bal > 0">
                            <input
                              v-model="invoice_item.quantity_for_supply"
                              class="form-control"
                              placeholder="Set Quantity for Supply"
                              type="number"
                              :max="invoice_item.supply_bal"
                              min="0"
                              @blur="checkForOverflow(invoice_item.supply_bal, index)"
                            >
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
              </el-col>
            </el-row>
            <el-row>
              <el-form
                ref="form"
                :rules="rules"
                :model="form"
                label-position="left"
                label-width="130px"
                style="max-width: 600px"
              >
                <el-form-item label="Waybill No." prop="waybill_no">
                  <el-input v-model="form.waybill_no" required readonly />
                </el-form-item>
                <!-- <el-form-item v-else label="Waybill No." prop="waybill_no">
                  <el-input v-model="form.waybill_no" required />
                </el-form-item> -->
                <!-- <el-form-item v-if="available_vehicles.length > 0" label="Dispatch Vehicle" prop="vehicle_id">
                  <el-select v-model="form.vehicle_id" placeholder="Select Vehicle" filterable class="span">
                    <el-option v-for="(vehicle, index) in available_vehicles" :key="index" :value="vehicle.id" :label="vehicle.plate_no.toUpperCase()" />
                  </el-select>
                </el-form-item>
                <el-form-item v-else>
                  <span class="label label-danger">No vehicles available</span>
                </el-form-item> -->
              </el-form>
            </el-row>
            <el-row v-if="form.waybill_no" :gutter="2" class="padded">
              <el-col :xs="24" :sm="6" :md="6">
                <el-button
                  type="success"
                  :disabled="disabled"
                  @click="generateWaybill()"
                ><i class="el-icon-upload" />
                  Submit
                </el-button>
              </el-col>
            </el-row>
          </el-form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
// import moment from 'moment';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';

import Resource from '@/api/resource';
// const createInvoice = new Resource('invoice/general/store');
// const necessaryParams = new Resource('fetch-necessary-params');
const unDeliveredInvoices = new Resource('invoice/waybill/undelivered-invoices');
// const availableVehicles = new Resource('invoice/waybill/fetch-available-vehicles');
const storeWaybillResource = new Resource('invoice/waybill/store');
const fetchProductBatches = new Resource('stock/items-in-stock/product-batches');
export default {
  // name: 'GenerateWaybill',

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
      selected_invoice: [],
      invoice_items: [],
      waybill_items: [],
      available_vehicles: [],
      batches_of_items_in_stock: [],
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
      disabled: false,
    };
  },
  computed: {
    params() {
      return this.$store.getters.params;
    },
  },
  created() {
    this.fetchNecessaryParams();
  },
  methods: {
    // moment,
    checkPermission,
    checkRole,
    setProductBatches(item_id) {
      const app = this;
      const param = {
        warehouse_id: app.form.warehouse_id,
        item_id: item_id,
      };
      fetchProductBatches.list(param).then((response) => {
        return response.batches_of_items_in_stock;
      });
    },
    fetchUndeliveredInvoices(index) {
      const app = this;
      var form = app.form;
      app.loading = true;
      // const loading = unDeliveredInvoices.loaderShow();
      unDeliveredInvoices.list(form).then((response) => {
        app.invoices = response.invoices;
        app.form.waybill_no = response.waybill_no;
        app.loading = false;
        // loading.hide();
        // app.fetchAvailableDrivers();
      });
    },
    fetchNecessaryParams() {
      const app = this;
      app.$store.dispatch('app/setNecessaryParams');
    },
    displayInvoiceitems() {
      const app = this;
      var selected_invoice = app.selected_invoice;
      var invoice_items = [];
      var invoice_ids = [];
      // app.loading = true;
      for (let index = 0; index < selected_invoice.length; index++) {
        const element = selected_invoice[index];
        invoice_items.push(...app.invoices[element].invoice_items);
        invoice_ids.push(app.invoices[element].id);
      }
      // console.log(invoice_items);
      invoice_items.forEach((invoice_item) => {
        var total_batch_balance = 0;
        var reserved_for_supply = 0;
        var physical_stock = 0;

        var supply_bal = invoice_item.quantity - invoice_item.quantity_supplied;
        var stocks = invoice_item.item.stocks;

        stocks.forEach((stock_batch) => {
          total_batch_balance +=
            parseInt(stock_batch.balance) -
            parseInt(stock_batch.reserved_for_supply);
          reserved_for_supply += parseInt(stock_batch.reserved_for_supply);

          physical_stock += parseInt(stock_batch.balance);
        });

        invoice_item.supply_bal = supply_bal;
        invoice_item.quantity_for_supply = supply_bal;
        if (supply_bal > total_batch_balance) {
          invoice_item.supply_bal = total_batch_balance;
          invoice_item.quantity_for_supply = total_batch_balance;
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
      invoice_items.forEach(element => {
        if (element.supply_bal < element.quantity_for_supply) {
          this.$alert('To continue, kindly reduce ' + element.item.name + ' quantity to ' + element.supply_bal);
          return false;
        }
      });
      this.$refs['form'].validate((valid) => {
        if (valid) {
          this.$confirm(
            'Cross check your selection before submitting. Continue?',
            'Warning',
            {
              confirmButtonText: 'OK',
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
        } else {
          console.log('error submit!!');
          return false;
        }
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

