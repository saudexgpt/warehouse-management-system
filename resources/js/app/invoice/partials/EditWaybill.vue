<template>
  <div class="app-container">
    <div>
      <div class="box">
        <div class="box-header">
          <h4 class="box-title">Edit Waybill : {{ form.waybill_no }}</h4>
        </div>
        <div class="box-body">
          <el-form ref="form" :model="form" label-width="120px">
            <el-row :gutter="2" class="padded">
              <el-col>
                <div style="overflow: auto">
                  <label for="">Products</label>
                  <table v-loading="loading" class="table table-binvoiceed">
                    <thead>
                      <tr>
                        <th />
                        <th>Product</th>
                        <th>Invoice Quantity</th>
                        <th>Waybill Quantity</th>
                        <!-- <th>Batch(es)</th> -->
                        <th>New Waybill Quantity</th>
                      </tr>
                    </thead>
                    <tbody v-if="waybill_items.length > 0">
                      <tr>
                        <td colspan="4" />
                        <td><small>Must NOT be more than the maximum quantity</small></td>
                      </tr>
                      <tr
                        v-for="(waybill_item, index) in waybill_items"
                        :key="index"
                      >
                        <td>{{ index + 1 }}</td>
                        <td>{{ waybill_item.item.name }}</td>
                        <td>
                          {{ waybill_item.invoice_item.quantity }}
                          {{
                            formatPackageType(waybill_item.item.package_type)
                          }}
                        </td>
                        <td>
                          {{ waybill_item.quantity }}
                          {{
                            formatPackageType(waybill_item.item.package_type)
                          }}
                        </td>
                        <td>
                          <div>
                            <el-input-number
                              v-model="waybill_item.quantity"
                              placeholder="Set Quantity for Supply"
                              type="number"
                              :max="maximumQuantity(waybill_item.invoice_item, waybill_item)"
                              :min="0"
                              @input="checkForOverflow(waybill_item.quantity, index)"
                            />
                            <br>
                            <small>Maximum modifiable quantity: {{ maximumQuantity(waybill_item.invoice_item, waybill_item) }}</small>
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
              <el-col :xs="12" :sm="6" :md="4">
                <el-button
                  type="success"
                  :disabled="disabled"
                  @click="updateWaybill()"
                ><i class="el-icon-upload" />
                  Update Waybill
                </el-button>
              </el-col>
              <el-col :xs="12" :sm="6" :md="4">
                <el-button
                  type="warning"
                  @click="page.option = 'list'"
                ><i class="el-icon-close" />
                  Cancel
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
// const availableVehicles = new Resource('invoice/waybill/fetch-available-vehicles');
const updateWaybillResource = new Resource('invoice/waybill/update');
const fetchProductBatches = new Resource('stock/items-in-stock/product-batches');
export default {
  // name: 'GenerateWaybill',
  props: {
    waybill: {
      type: Object,
      default: () => ({}),
    },
    page: {
      type: Object,
      default: () => ({
        option: 'edit_waybill',
      }),
    },
    params: {
      type: Object,
      default: () => ({}),
    },
  },
  data() {
    return {
      form: {
        warehouse_id: '',
        waybill_no: '',
        dispatch_company: 'GREEN LIFE LOGISTICS',
        status: 'pending',
        invoice_ids: [],
        waybill_items: [],
      },
      invoices: [],
      selected_invoice: [],
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
  created() {
    this.form = this.waybill;
    this.waybill_items = this.waybill.waybill_items;
    this.processInvoiceItems();
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
    processInvoiceItems() {
      const app = this;
      app.waybill_items.forEach((waybill_item) => {
        waybill_item.old_quantity = waybill_item.quantity;
      });
    },
    checkForOverflow(new_quantity, index) {
      const app = this;
      const waybill_item = app.waybill_items[index];
      const product = app.waybill_items[index].item.name;
      const package_type = app.waybill_items[index].item.package_type;
      const max_quantity = app.maximumQuantity(waybill_item.invoice_item, waybill_item);
      if (new_quantity > max_quantity) {
        app.$alert('Make sure you DO NOT exceed ' + max_quantity + ' ' + package_type + ' for ' + product);
        app.waybill_items[index].quantity = max_quantity;
      }
    },
    maximumQuantity(invoice_item, waybill_item){
      var waybill_quantity = waybill_item.old_quantity;
      var order_quantity = invoice_item.quantity;
      var stocks = waybill_item.item.stocks;

      var total_batch_balance = parseInt(waybill_quantity);
      stocks.forEach((stock_batch) => {
        total_batch_balance +=
            parseInt(stock_batch.balance) -
            (parseInt(stock_batch.reserved_for_supply));
      });
      if (total_batch_balance > order_quantity) {
        return order_quantity;
      }
      return total_batch_balance;
    },
    updateWaybill() {
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
              const loader = updateWaybillResource.loaderShow();
              this.form.waybill_items = this.waybill_items;
              this.disabled = true;
              updateWaybillResource
                .update(this.form.id, this.form)
                .then((response) => {
                  if (response.status) {
                    this.error_message = response.status + response.message;
                  } else {
                    this.$message({
                      message: 'Waybill update successfully.',
                      type: 'success',
                      duration: 5 * 1000,
                    });
                    loader.hide();
                    this.$emit('update', response.warehouse);
                  }
                })
                .catch((error) => {
                  console.log(error.message);
                  this.disabled = false;
                })
                .finally(() => {
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

