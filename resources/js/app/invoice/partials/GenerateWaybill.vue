<template>
  <div class="app-container">
    <span v-if="params">
      <router-link v-if="checkPermission(['manage waybill'])" :to="{name:'Waybills'}" class="btn btn-default"> View Waybills</router-link>
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
                <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" filterable class="span" @change="fetchUndeliveredInvoices()">
                  <el-option v-for="(warehouse, warehouse_index) in params.warehouses" :key="warehouse_index" :value="warehouse.id" :label="warehouse.name" />

                </el-select>
              </el-col>
              <el-col :xs="24" :sm="12" :md="12">
                <label for="">Search Invoice</label>
                <el-select v-model="selected_invoice" placeholder="Select Invoice" filterable class="span" multiple @input="displayInvoiceitems()">
                  <el-option v-for="(invoice, invoice_index) in invoices" :key="invoice_index" :value="invoice_index" :label="invoice.invoice_number" />
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
                  <table class="table table-binvoiceed">
                    <thead>
                      <tr>
                        <th />
                        <th>Product</th>
                        <th>Order</th>
                        <th>Batch(es)</th>
                        <th>Supplied</th>
                        <th>Balance</th>
                        <th>To Supply</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td colspan="3" />
                        <td><label>BN / SUB-BN / Quantity</label></td>
                        <td colspan="3" />
                      </tr>
                      <tr v-for="(invoice_item, index) in invoice_items" :key="index">
                        <td>{{ index + 1 }}</td>
                        <td>{{ invoice_item.item.name }}</td>
                        <td>{{ invoice_item.quantity }} {{ formatPackageType(invoice_item.item.package_type) }}</td>
                        <td>
                          <span v-for="(batch, batch_index) in invoice_item.batches" :key="batch_index">
                            {{ batch.item_stock_batch.item_stock.batch_no + ' / ' + batch.item_stock_batch.batch_no + ' / ' + batch.quantity }}<br>
                          </span>
                        </td>
                        <td>{{ invoice_item.quantity_supplied+' ('+invoice_item.delivery_status+')' }}</td>
                        <td><div class="alert alert-danger">{{ invoice_item.quantity - invoice_item.quantity_supplied }}</div></td>
                        <td>
                          <div v-if="invoice_item.quantity - invoice_item.quantity_supplied > 0">
                            <el-select v-model="invoice_item.quantity_for_supply" placeholder="Set Quantity for Supply" filterable class="span">
                              <el-option v-for="(quantity, quantity_index) in invoice_item.quantity - invoice_item.quantity_supplied" :key="quantity_index" :value="quantity" :label="quantity" />
                            </el-select>
                          </div>
                        </td>
                      </tr>
                    </tbody>

                  </table>
                </div>
              </el-col>
            </el-row>
            <el-row>
              <el-form ref="form" :rules="rules" :model="form" label-position="left" label-width="130px" style="max-width: 600px;">
                <el-form-item label="Waybill No." prop="waybill_no">
                  <el-input v-model="form.waybill_no" required />
                </el-form-item>
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
                <el-button type="success" @click="generateWaybill()"><i class="el-icon-plus" />
                  Generate Waybill
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
import moment from 'moment';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';

import Resource from '@/api/resource';
// const createInvoice = new Resource('invoice/general/store');
const necessaryParams = new Resource('fetch-necessary-params');
const unDeliveredInvoices = new Resource('invoice/waybill/undelivered-invoices');
// const availableVehicles = new Resource('invoice/waybill/fetch-available-vehicles');
const storeWaybillResource = new Resource('invoice/waybill/store');
export default {
  name: 'AddNewInvoice',

  data() {
    return {
      params: {},
      form: {
        warehouse_id: '',
        waybill_no: '',
        status: 'pending',
        invoice_ids: [],
      },
      invoices: [],
      selected_invoice: [],
      invoice_items: [],
      waybill_items: [],
      available_vehicles: [],
      rules: {
        // vehicle_id: [{ required: true, message: 'Vehicle is required', trigger: 'change' }],
        waybill_no: [{ required: true, message: 'Waybill Number is required', trigger: 'blur' }],
      },
    };
  },
  mounted() {
    this.fetchNecessaryParams();
  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    fetchUndeliveredInvoices(index) {
      const app = this;
      var form = app.form;
      unDeliveredInvoices.list(form)
        .then(response => {
          app.invoices = response.invoices;
          // app.fetchAvailableDrivers();
        });
    },
    fetchNecessaryParams() {
      const app = this;
      necessaryParams.list()
        .then(response => {
          app.params = response.params;
        });
    },
    displayInvoiceitems() {
      const app = this;
      var selected_invoice = app.selected_invoice;
      var invoice_items = [];
      var invoice_ids = [];
      for (let index = 0; index < selected_invoice.length; index++) {
        const element = selected_invoice[index];
        invoice_items.push(...app.invoices[element].invoice_items);
        invoice_ids.push(app.invoices[element].id);
      }
      app.invoice_items = invoice_items;
      app.form.invoice_ids = invoice_ids;
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
      this.$refs['form'].validate((valid) => {
        if (valid) {
          const loader = storeWaybillResource.loaderShow();
          this.form.invoice_items = this.invoice_items;
          storeWaybillResource
            .store(this.form)
            .then(response => {
              if (response.status) {
                this.error_message = response.status + response.message;
              } else {
                this.$message({
                  message: 'Waybill created successfully.',
                  type: 'success',
                  duration: 5 * 1000,
                });
                loader.hide();
                this.$router.replace('waybill');
              }
            })
            .catch(error => {
              console.log(error.message);
            })
            .finally(() => {
              this.creatingWaybill = false;
            });
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },
    formatPackageType(type){
      // var formated_type = type + 's';
      // if (type === 'Box') {
      //   formated_type = type + 'es';
      // }
      return type;
    },

  },
};
</script>

