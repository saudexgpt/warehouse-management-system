<template>
  <div class="app-container">
    <div>
      <div class="box">
        <div class="box-header">
          <h4 class="box-title">Edit Waybill : {{ form.waybill_no }}</h4>
        </div>
        <div v-loading="load" class="box-body">
          <!-- <h4 class="alert alert-danger">Items on this Waybill can only be edited once</h4> -->
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
                        <th>Batches</th>
                        <th>New Waybill Quantity</th>
                      </tr>
                    </thead>
                    <tbody v-if="waybill_items.length > 0">
                      <!-- <tr>
                        <td colspan="4" />
                        <td><small>Must NOT be more than the maximum quantity</small></td>
                      </tr> -->
                      <tr
                        v-for="(waybill_item, index) in waybill_items"
                        :key="index"
                      >
                        <td>{{ index + 1 }}</td>
                        <td>{{ waybill_item.invoice_item.item.name }}</td>
                        <td>
                          {{ waybill_item.invoice_item.quantity }}
                          {{
                            formatPackageType(waybill_item.invoice_item.item.package_type)
                          }}
                        </td>
                        <td>
                          {{ waybill_item.quantity }}
                          {{
                            formatPackageType(waybill_item.invoice_item.item.package_type)
                          }}
                        </td>
                        <td>
                          <el-button
                            round
                            type="primary"
                            @click="selectProductBatch(index, waybill_item)"
                          >Select Batches</el-button>
                        </td>
                        <td>
                          <div>
                            {{ waybill_item.quantity_for_supply }}
                            <!-- <el-input-number
                              v-model="waybill_item.quantity"
                              placeholder="Set Quantity for Supply"
                              type="number"
                              :max="maximumQuantity(waybill_item.invoice_item, waybill_item)"
                              :min="0"
                              @input="checkForOverflow(waybill_item.quantity, index)"
                            /> -->
                            <!-- <br>
                            <small>Maximum modifiable quantity: {{ maximumQuantity(waybill_item.invoice_item, waybill_item) }}</small> -->
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
        <el-dialog
          v-if="selected_waybill_item !== null"
          title="Set Product Batch Quantity to be supplied. Consider First In First Out (FIFO) Principle"
          :visible.sync="showBatchSelection"
          :close-on-click-modal="false"
          :close-on-press-escape="false"
          :show-close="false"
        >
          <div class="form-container">
            <label>Total batch quantity should not be more than {{ selected_waybill_item.supply_bal }} {{ selected_waybill_item.invoice_item.item.package_type }} for {{ selected_waybill_item.invoice_item.item.name }}</label>
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
                <tr v-for="(stock, stock_index) in selected_waybill_item.batches" :key="stock_index">
                  <td>{{ stock.batch_no }}</td>
                  <td>{{ stock.expiry_date }}</td>
                  <td>{{ stock.balance }} {{ selected_waybill_item.invoice_item.item.package_type }}</td>
                  <td><input v-model="stock.supply_quantity" type="number" @input="setSupplyQuantity(selected_waybill_item, stock_index)"></td>
                </tr>
                <tr>
                  <td colspan="4" align="right">
                    <h4>
                      Order Quantity: {{ selected_waybill_item.supply_bal }}<br>
                      Total Supply Quantity: {{ total_supplied }}<br>
                      Remaining Quantity: {{ selected_waybill_item.supply_bal - total_supplied }}
                    </h4>
                  </td>
                </tr>
                <tr>
                  <th colspan="4" align="right">
                    <aside>
                      <!-- <el-button round type="danger" @click="showBatchSelection = false">Cancel</el-button> -->
                      <el-button round type="primary" style="width: 100%" @click="updateQuantityForSupply(selected_waybill_item)">Continue</el-button>
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
</template>

<script>
// import moment from 'moment';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';

import Resource from '@/api/resource';
// const availableVehicles = new Resource('invoice/waybill/fetch-available-vehicles');
const updateWaybillResource = new Resource('invoice/waybill/update');
export default {
  // name: 'GenerateWaybill',
  props: {
    waybillId: {
      type: Number,
      default: () => null,
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
      load: false,
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
      waybill: {},
      loading: false,
      disabled: false,
      selected_waybill_item: null,
      showBatchSelection: false,
      selected_index: null,
      total_supplied: 0,
    };
  },
  created() {
    this.fetchWaybill();
  },
  methods: {
    // moment,
    checkPermission,
    checkRole,
    selectProductBatch(index, waybill_item) {
      const app = this;
      app.showBatchSelection = true;
      app.selected_index = index;
      app.selected_waybill_item = waybill_item;
      app.total_supplied = waybill_item.total_supplied;
    },
    setSupplyQuantity(selectedWaybillItem, stockIndex) {
      const app = this;
      const for_supply = selectedWaybillItem.supply_bal;
      let total_supply_quantity = 0;
      let count = 0;
      app.selected_waybill_item.batches.forEach(stock => {
        if (count === stockIndex) {
          const balance = parseInt(stock.balance);
          stock.supply_quantity = (stock.supply_quantity !== null && stock.supply_quantity !== '') ? parseInt(stock.supply_quantity) : 0;
          // check whether batch balance is less than inputed quantity
          if (balance < stock.supply_quantity) {
            app.$alert(`Batch quantity should not be more than ${stock.supply_quantity}`);
            stock.supply_quantity = parseInt(balance);
          }
          // check whether total supply quantity is more than order quantity
          if ((parseInt(total_supply_quantity) + parseInt(stock.supply_quantity)) > for_supply) {
            app.$alert(`Total supply quantity is more than Order quantity of ${for_supply}. Kindly correct.`);
            stock.supply_quantity = stock.old_supply_quantity;
          }
        }
        total_supply_quantity += parseInt(stock.supply_quantity);
        count++;
      });
      app.total_supplied = parseInt(total_supply_quantity);
      app.selected_waybill_item.total_supplied = parseInt(total_supply_quantity);
      if (app.selected_waybill_item.total_supplied > for_supply) {
        app.$alert(`Total supply quantity is more than Order quantity of ${for_supply}. Kindly correct.`);
      }
    },
    updateQuantityForSupply(selectedWaybillItem) {
      const app = this;
      const for_supply = selectedWaybillItem.supply_bal;
      const total_supply_quantity = app.total_supplied;
      if (total_supply_quantity > for_supply) {
        app.$alert(`Total supply quantity is more than Order quantity of ${for_supply}. Please fix to continue.`);
      } else {
        selectedWaybillItem.quantity_for_supply = total_supply_quantity;
        app.selected_waybill_item = selectedWaybillItem;
        app.waybill_items[app.selected_index] = app.selected_waybill_item;
        app.showBatchSelection = false;
      }
    },
    fetchWaybill() {
      const app = this;
      app.load = true;
      const waybillResource = new Resource('invoice/waybill/edit');
      waybillResource.get(app.waybillId)
        .then(response => {
          app.load = false;
          app.waybill = response.waybill;

          app.form = response.waybill;
          app.waybill_items = response.waybill.waybill_items;
          app.processWaybillItems();
        });
    },
    processWaybillItems() {
      const app = this;
      app.waybill_items.forEach((waybill_item) => {
        waybill_item.old_quantity = waybill_item.quantity;
        const invoice_item = waybill_item.invoice_item;
        var supply_bal = invoice_item.quantity - (invoice_item.quantity_supplied - waybill_item.quantity);
        var waybill_item_batches = waybill_item.batches;
        const item_stocks = waybill_item.invoice_item.item.stocks;
        const batches = [];
        const item_stock_batch_nos = [];
        waybill_item_batches.forEach((invoice_item_batch) => {
          if (invoice_item_batch.item_stock_batch) {
            const supply_quantity = invoice_item_batch.quantity_total;
            item_stock_batch_nos.push(invoice_item_batch.item_stock_batch.batch_no);
            batches.push({
              waybill_batch_id: invoice_item_batch.id,
              batch_no: invoice_item_batch.item_stock_batch.batch_no,
              expiry_date: invoice_item_batch.item_stock_batch.expiry_date,
              balance: invoice_item_batch.item_stock_batch.balance - (invoice_item_batch.item_stock_batch.reserved_for_supply - supply_quantity),
              supply_quantity: supply_quantity,
              old_supply_quantity: supply_quantity,

            });
          }
        });
        item_stocks.forEach(item_stock => {
          if (!item_stock_batch_nos.includes(item_stock.batch_no)) {
            batches.push({
              batch_no: item_stock.batch_no,
              expiry_date: item_stock.expiry_date,
              balance: item_stock.total_balance,
              supply_quantity: 0,
              old_supply_quantity: 0,

            });
          }
        });
        waybill_item.batches = batches;
        waybill_item.supply_bal = supply_bal;
        waybill_item.quantity_for_supply = waybill_item.quantity;
        waybill_item.total_supplied = waybill_item.quantity;
      });
    },
    // checkForOverflow(new_quantity, index) {
    //   const app = this;
    //   const waybill_item = app.waybill_items[index];
    //   const product = app.waybill_items[index].item.name;
    //   const package_type = app.waybill_items[index].item.package_type;
    //   const max_quantity = app.maximumQuantity(waybill_item.invoice_item, waybill_item);
    //   if (new_quantity > max_quantity) {
    //     app.$alert('Make sure you DO NOT exceed ' + max_quantity + ' ' + package_type + ' for ' + product);
    //     app.waybill_items[index].quantity = max_quantity;
    //   }
    // },
    maximumQuantity(invoice_item, waybill_item){
      var waybill_quantity = waybill_item.old_quantity;
      var order_quantity = invoice_item.quantity - (invoice_item.quantity_supplied - waybill_quantity);
      // var order_quantity = invoice_item.quantity - waybill_quantity;
      var stocks = waybill_item.invoice_item.item.stocks;

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
              this.loading = true;
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
                    // loader.hide();
                    this.$emit('update', response.warehouse);
                  }
                  this.loading = false;
                })
                .catch((error) => {
                  console.log(error.message);
                  this.disabled = false;
                  this.loading = false;
                })
                .finally(() => {
                  this.creatingWaybill = false;
                  this.disabled = false;
                  this.loading = false;
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

