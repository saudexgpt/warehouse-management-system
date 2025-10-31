<template>
  <div class="app-container">
    <div>
      <div v-if="params" class="box">
        <div class="box-header">
          <h4 class="box-title">Add Returned Products</h4>
          <span class="pull-right">
            <a class="btn btn-danger" @click="page.option = 'list'"> Back</a>
          </span>
          <!-- <span class="pull-right">
            <a
              v-if="checkPermission(['create invoice']) && upload_type ==='normal'"
              class="btn btn-success"
              @click="upload_type ='bulk'"
            >Bulk Upload</a>
            <a
              v-if="checkPermission(['create invoice']) && upload_type ==='bulk'"
              class="btn btn-primary"
              @click="upload_type ='normal'"
            >Normal Upload</a>
            <router-link
              v-if="checkPermission(['view invoice'])"
              :to="{name:'Invoices'}"
              class="btn btn-danger"
            >Cancel</router-link>
            <el-button
              type="primary"
              @click="loadOfflineData()"
            >Load Unsaved Invoice</el-button>

          </span> -->
        </div>
        <div class="box-body">
          <el-form ref="form" v-loading="loadForm" :model="form" label-width="120px">
            <el-row :gutter="5" class="padded">
              <el-col :xs="24" :sm="12" :md="12">
                <label for>Select Warehouse</label>
                <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" filterable class="span">
                  <el-option
                    v-for="(warehouse, index) in params.warehouses"
                    :key="index"
                    :value="warehouse.id"
                    :label="warehouse.name"
                    :disabled="warehouse.id !== 7"
                  />

                </el-select>
              </el-col>
              <el-col :xs="24" :sm="12" :md="12">
                <label for>
                  Select Customer
                </label>
                <el-select
                  v-model="selectedCustomer"
                  placeholder="Select Customer"
                  filterable
                  value-key="id"
                  class="span"
                  @input="show_product_list = true"
                >
                  <el-option
                    v-for="(customer, customer_index) in customers"
                    :key="customer_index"
                    :value="customer"
                    :label="(customer.user) ? customer.user.name : ''"
                  />
                </el-select>
                <!-- <el-select
                  v-model="form.customer_id"
                  placeholder="Select Customer"
                  filterable
                  class="span"
                  @input="show_product_list = true"
                >
                  <el-option
                    v-for="(customer, customer_index) in customers"
                    :key="customer_index"
                    :value="customer.id"
                    :label="(customer.user) ? customer.user.name : ''"
                  />
                </el-select> -->
                <label for>Returned Date</label>
                <el-date-picker
                  v-model="form.date_returned"
                  type="date"
                  placeholder="Returned Date"
                  style="width: 100%;"
                  format="yyyy/MM/dd"
                  value-format="yyyy-MM-dd"
                  :picker-options="pickerOptions"
                />
              </el-col>
            </el-row>
            <div v-if="show_product_list">
              <el-row :gutter="2" class="padded">
                <el-col>
                  <div style="overflow: auto">
                    <label for>Products</label>
                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th />
                          <th>Choose Product</th>
                          <th>Batch No & Expiry Date</th>
                          <th>Quantity</th>
                          <th>Reason for return</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(invoice_item, index) in returns_items" :key="index">
                          <td>
                            <span v-if="!can_submit">
                              <a
                                class="btn btn-danger btn-flat fa fa-trash"
                                @click="removeLine(index)"
                              />
                              <a
                                v-if="index + 1 === returns_items.length"
                                class="btn btn-info btn-flat fa fa-plus"
                                @click="addLine(index)"
                              />
                            </span>
                            <span v-else>
                              {{ index + 1 }}
                            </span>
                          </td>
                          <td>
                            <el-select
                              v-model="invoice_item.item"
                              value-key="id"
                              placeholder="Select Product"
                              filterable
                              class="span"
                              :disabled="can_submit"
                              @input="fetchDeliveredInvoices($event.id, index);"
                            >
                              <el-option
                                v-for="(item, item_index) in params.items"
                                :key="item_index"
                                :value="item"
                                :label="item.name"
                                :disabled="item.enabled === 0"
                              />
                            </el-select>
                          </td>
                          <td v-loading="invoice_item.load">
                            <el-select
                              v-model="invoice_item.selectedBatch"
                              value-key="id"
                              placeholder="Select Batch"
                              filterable
                              class="span"
                              :disabled="can_submit"
                              @input="invoice_item.invoice_no = $event.invoice_no; invoice_item.batch_no = $event.batch_no; invoice_item.expiry_date = $event.expiry_date; invoice_item.price = $event.price; invoice_item.max_quantity = $event.max_quantity; invoice_item.dispatched_product_id = $event.dispatched_product_id; invoice_item.batch_id = $event.id"
                            >
                              <el-option
                                v-for="(batch, batch_index) in invoice_item.batches"
                                :key="batch_index"
                                :value="batch"
                                :label="`${batch.batch_no} | ${batch.expiry_date}`"
                              />
                            </el-select>
                            <el-tag>Invoice No: {{ invoice_item.invoice_no }}</el-tag><br>
                            <el-tag>Batch No: {{ invoice_item.batch_no }}</el-tag><br>
                            <el-tag>Expiry Date: {{ invoice_item.expiry_date }}</el-tag><br>
                            <el-tag>Price: {{ invoice_item.price }}</el-tag>
                          </td>
                          <td>
                            <el-input v-model="invoice_item.quantity" type="text" placeholder="Quantity" class="span" @input="calculateNoOfCartons(index);">
                              <template slot="append">{{ invoice_item.type }}</template>
                            </el-input>
                            <!-- <el-input
                              v-model="invoice_item.quantity"
                              type="number"
                              outline
                              placeholder="Quantity"
                              min="0"
                              :disabled="can_submit"
                              @input="calculateNoOfCartons(index);"
                            /> -->
                            <br><code v-html="showItemsInCartons(invoice_item.quantity, invoice_item.quantity_per_carton, invoice_item.type)" />
                            <br>
                            <el-tag type="danger">Maximum Returnable Quantity:
                              <span v-html="showItemsInCartons(invoice_item.max_quantity, invoice_item.quantity_per_carton, invoice_item.type)" />
                            </el-tag>
                          </td>
                          <td>
                            <el-select v-model="invoice_item.reason" placeholder="Select Reason" filterable class="span">
                              <el-option v-for="(reason, reason_index) in params.product_return_reasons" :key="reason_index" :value="reason" :label="reason" />

                            </el-select>
                            <div v-if="invoice_item.reason === 'Others'">
                              <label for="">Specify Other Reasons</label>
                              <el-input v-model="invoice_item.other_reason" type="text" placeholder="Specify" class="span" />
                            </div>
                          </td>
                        </tr>
                        <tr v-if="fill_fields_error">
                          <td colspan="5">
                            <label
                              class="label label-danger"
                            >Please fill all empty fields before adding another row</label>
                          </td>
                        </tr>
                        <!-- <tr>
                          <td align="right">Notes</td>
                          <td colspan="5">
                            <textarea
                              v-model="form.notes"
                              class="form-control"
                              rows="3"
                              placeholder="Type extra note on this invoice here..."
                            />
                          </td>
                        </tr> -->
                      </tbody>
                    </table>
                  </div>
                </el-col>
              </el-row>
              <el-row :gutter="2" class="padded">
                <el-col :xs="24" :sm="24" :md="24">
                  <div align="center">
                    <el-button type="success" @click="submitNewInvoice">
                      <i class="el-icon-plus" />
                      Submit Entry
                    </el-button>
                    <!-- <div v-if="can_submit">

                      <el-button type="success" @click="submitNewInvoice">
                        <i class="el-icon-plus" />
                        Submit Invoice
                      </el-button>
                      <el-button type="danger" @click="can_submit = false">
                        <i class="el-icon-edit" />
                        Alter Entry
                      </el-button>
                    </div>
                    <el-button v-else :loading="loadPreview" type="primary" @click="checkProductsQuantityInStock">
                      <i class="el-icon-file" />
                      Preview Entry
                    </el-button> -->
                  </div>
                </el-col>
              </el-row>
            </div>
          </el-form>
          <add-new-customer
            :dialog-form-visible="dialogFormVisible"
            :params="params"
            @created="onCreateUpdate"
            @close="dialogFormVisible=false"
          />

        </div>
      </div>
    </div>

  </div>
</template>

<script>
import moment from 'moment';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';
import showItemsInCartons from '@/utils/functions';
import AddNewCustomer from '@/app/users/AddNewCustomer';
import Resource from '@/api/resource';
const checkProductsInStock = new Resource('invoice/general/check-product-quantity-in-stock');
export default {
  // name: 'CreateInvoice',
  components: { AddNewCustomer },
  props: {
    page: {
      type: Object,
      default: () => ({
        option: 'add_new',
      }),
    },
  },
  data() {
    return {
      pickerOptions: {
        disabledDate(date) {
          var d = new Date(); // today
          d.setDate(d.getDate()); // one year from now
          return date > d;
        },
      },
      currency: '₦',
      upload_type: 'normal',
      // customers: [],
      // customer_types: [],
      items_in_stock_dialog: false,
      dialogFormVisible: false,
      userCreating: false,
      loadPreview: false,
      fill_fields_error: false,
      show_product_list: false,
      loadForm: false,
      batches_of_items_in_stock: [],
      disable_submit: false,
      can_submit: false,
      selectedCustomer: '',
      dispatched_products: [],
      form: {
        warehouse_id: 7,
        customer_id: '',
        customer_name: '',
        status: 'pending',
        date_returned: new Date(),
        notes: '',
        returns_items: [
          {
            item: null,
            load: false,
            item_id: '',
            quantity: '',
            price: '',
            batch_no: '',
            batches: [],
            expiry_date: '',
            date_returned: '',
            reason: null,
            other_reason: null,
            type: '',
          },
        ],
      },
      empty_form: {
        warehouse_id: 7,
        customer_id: '',
        customer_name: '',
        status: 'pending',
        date_returned: new Date(),
        notes: '',
        returns_items: [
          {
            item: null,
            load: false,
            item_id: '',
            quantity: '',
            batch_no: '',
            batches: [],
            expiry_date: '',
            date_returned: '',
            reason: null,
            other_reason: null,
            type: '',
          },
        ],
      },
      returns_items: [],
      newCustomer: {
        name: '',
        email: null,
        phone: null,
        address: '',
        role: 'customer',
        customer_type_id: '',
        password: '',
        confirmPassword: '',
      },
      rules: {
        customer_type: [
          {
            required: true,
            message: 'Customer Type is required',
            trigger: 'change',
          },
        ],
        name: [
          { required: true, message: 'Name is required', trigger: 'blur' },
        ],
        // email: [
        //   { required: true, message: 'Email is required', trigger: 'blur' },
        //   { type: 'email', message: 'Please input correct email address', trigger: ['blur', 'change'] },
        // ],
        // phone: [{ required: true, message: 'Phone is required', trigger: 'blur' }],
      },
      discount_rate: 0,
    };
  },
  computed: {
    params() {
      return this.$store.getters.params;
    },
    customers() {
      return this.$store.getters.customers;
    },
    customer_types() {
      return this.$store.getters.customer_types;
    },
    unsavedReturns() {
      return this.$store.getters.unsavedReturns;
    },
  },
  watch: {
    returns_items() {
      this.blockRemoval = this.returns_items.length <= 1;
    },
  },
  mounted() {
    // this.loadOfflineData();
    this.fetchNecessaryParams();
    this.fetchCustomers();
    this.addLine();
  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    showItemsInCartons,
    loadOfflineData() {
      this.loadForm = true;
      this.$store.dispatch('returns/loadOfflineReturns').then(() => {
        this.form = this.unsavedReturns;
        this.returns_items = this.form.returns_items;
        if (this.form.warehouse_id !== '') {
          this.show_product_list = true;
        }
        this.loadForm = false;
      });
    },
    rowIsEmpty() {
      this.fill_fields_error = false;
      const checkEmptyLines = this.returns_items.filter(
        (detail) =>
          detail.item_id === '' ||
          detail.quantity === '' ||
          detail.batch_no === '' ||
          detail.expiry_date === '' ||
          detail.reason === null,
      );
      if (checkEmptyLines.length >= 1) {
        this.fill_fields_error = true;
        // this.returns_items[index].seleted_category = true;
        return true;
      }
      false;
    },
    addLine(index) {
      if (this.rowIsEmpty() && this.returns_items.length > 0) {
        return;
      } else {
        // if (this.returns_items.length > 0)
        //     this.returns_items[index].grade = '';
        this.returns_items.push({
          item: null,
          load: false,
          item_id: '',
          price: 0.00,
          quantity: 0,
          batches: [],
          batch_id: null,
          dispatched_product_id: null,
          batch_no: '',
          expiry_date: '',
          date_returned: '',
          reason: null,
          other_reason: null,
          max_quantity: 0,
          showMaxQuantity: false,
          invoice_no: '',
          type: '',
        });
        const unsavedReturns = this.form;
        unsavedReturns.returns_items = this.returns_items;
        this.$store.dispatch('returns/saveUnsavedReturns', unsavedReturns);
      }
    },
    removeLine(detailId) {
      this.fill_fields_error = false;
      if (!this.blockRemoval) {
        this.returns_items.splice(detailId, 1);
        // this.calculateTotal(null);
        const unsavedReturns = this.form;
        unsavedReturns.returns_items = this.returns_items;
        this.$store.dispatch('returns/saveUnsavedReturns', unsavedReturns);
      }
    },
    fetchNecessaryParams() {
      const app = this;
      app.$store.dispatch('app/setNecessaryParams');
    },
    fetchCustomers() {
      const app = this;
      app.$store.dispatch('customer/fetch');
    },
    checkProductsQuantityInStock() {
      const app = this;
      const form = app.form;
      form.returns_items = app.returns_items;
      app.loadPreview = true;
      checkProductsInStock
        .store(form)
        .then((response) => {
          app.can_submit = response.can_submit;
          app.returns_items = response.returns_items;
          app.loadPreview = false;
        })
        .catch((error) => {
          app.loadPreview = false;
          console.log(error.message);
        });
    },
    submitNewInvoice() {
      const app = this;
      if (this.rowIsEmpty()) {
        app.$alert('Please fill in all fields on each row');
        return;
      }
      // let overflowCount = 0;
      // app.returns_items.forEach(element => {
      //   if (app.isQuantityOverflow(element.quantity, element.max_quantity)) {
      //     element.showMaxQuantity = true;
      //     overflowCount++;
      //   }
      // });
      // if (overflowCount > 0) {
      //   app.$alert('Please ensure you do not exceed the maximum returnable quantity for each row');
      //   return;
      // }
      var form = app.form;
      const checkEmptyFields =
        form.warehouse_id === '' ||
        app.selectedCustomer === '' ||
        form.date_returned === '';
      if (!checkEmptyFields) {
        app.loadForm = true;
        form.returns_items = app.returns_items;
        form.customer_id = app.selectedCustomer.id;
        form.customer_name = app.selectedCustomer.user.name;
        app.disable_submit = true;
        const createInvoice = new Resource('stock/returns/store');
        createInvoice
          .store(form)
          .then((response) => {
            app.$message({
              message: 'Returns Created Successfully!!!',
              type: 'success',
            });
            const warehouse_id = app.form.warehouse_id;
            app.form = app.empty_form;
            app.form.warehouse_id = warehouse_id;
            app.returns_items = app.form.returns_items;

            // persist it
            const unsavedReturns = this.form;
            app.$store.dispatch('returns/saveUnsavedReturns', unsavedReturns);
            app.$emit('update', response);
            app.loadForm = false;
          })
          .catch((error) => {
            app.loadForm = false;
            console.log(error.message);
          });
      } else {
        alert('Please fill the form fields completely');
      }
    },

    onCreateUpdate(created_row) {
      const app = this;
      app.customers.push(created_row);
    },
    fetchDeliveredInvoices(itemId, index) {
      const app = this;
      app.fetchItemDetails(index);
      const form = { item_id: itemId, customer_id: app.selectedCustomer.id };
      const fetchDeliveredInvoicesResource = new Resource('stock/returns/fetch-delivered-invoices');
      fetchDeliveredInvoicesResource
        .list(form)
        .then((response) => {
          const dispatched_products = response.dispatched_products;
          app.setProductBatches(dispatched_products, index);
        })
        .catch((error) => {
          app.loadForm = false;
          console.log(error.message);
        });
    },
    fetchItemDetails(index) {
      const app = this;
      const item = app.returns_items[index].item;
      app.returns_items[index].item_rate = item.price.sale_price;
      app.returns_items[index].rate = item.price.sale_price;
      app.returns_items[index].item_id = item.id;
      app.returns_items[index].type = item.package_type;
      app.returns_items[index].quantity_per_carton = item.quantity_per_carton;
      app.returns_items[index].no_of_cartons = 0;
      app.returns_items[index].quantity = 1;
    },
    setProductBatches(dispatchedProducts, index) {
      const app = this;
      app.returns_items[index].batches = batches;
      app.returns_items[index].selectedBatch = null;
      app.returns_items[index].batch_no = '';
      app.returns_items[index].batch_id = null;
      app.returns_items[index].dispatched_product_id = null;
      app.returns_items[index].expiry_date = '';
      app.returns_items[index].max_quantity = 0;
      app.returns_items[index].invoice_no = '';
      app.returns_items[index].showMaxQuantity = false;
      const batches = [];
      dispatchedProducts.forEach(element => {
        batches.push({
          id: element.item_stock_sub_batch_id,
          batch_id: element.item_stock_sub_batch_id,
          dispatched_product_id: element.id,
          batch_no: element.batch_no,
          expiry_date: element.expiry_date,
          price: element.rate,
          invoice_no: element.invoice_number,
          max_quantity: (element.quantity_returned) ? parseInt(element.quantity_supplied) - parseInt(element.quantity_returned) : parseInt(element.quantity_supplied),
        });
      });
      app.returns_items[index].batches = batches;
    },
    isQuantityOverflow(quantity, maxQuantity) {
      return quantity > maxQuantity;
    },
    calculateNoOfCartons(index) {
      const app = this;
      if (index !== null) {
        const quantity = app.returns_items[index].quantity;
        const quantity_per_carton = app.returns_items[index].quantity_per_carton;
        if (quantity_per_carton > 0) {
          const no_of_cartons = quantity / quantity_per_carton;
          app.returns_items[index].no_of_cartons = no_of_cartons; // + parseFloat(tax);
        }
      }
    },
    checkStockBalance(index) {
      const app = this;
      // Get total amount for this item without tax
      if (app.params.enable_stock_quantity_check_when_raising_invoice === 'yes') {
        // if (index !== null) {
        //   const invoice_item = app.returns_items[index];
        //   const item = app.returns_items[index].item;
        //   const quantity = invoice_item.quantity;
        //   const available_stock = invoice_item.total_stocked - invoice_item.total_invoiced_quantity;
        //   app.disable_submit = false;
        //   if (quantity > available_stock) {
        //     app.disable_submit = true;
        //     app.$alert(`${item} stock balance is less than ${quantity}. Please enter a value within range`);
        //     app.returns_items[index].quantity = 0;
        //     app.calculateTotal(index);
        //   }
        // }
      }
    },
    calculateTotal(index) {
      const app = this;
      // Get total amount for this item without tax
      if (index !== null) {
        const quantity = app.returns_items[index].quantity;
        const unit_rate = app.returns_items[index].rate;
        app.returns_items[index].amount = parseFloat(
          quantity * unit_rate,
        ).toFixed(2); // + parseFloat(tax);
      }

      // we now calculate the running total of items invoiceed for with tax //////////
      // let total_tax = 0;
      let subtotal = 0;
      for (let count = 0; count < app.returns_items.length; count++) {
        // const tax_rate = app.returns_items[count].tax;
        // const quantity = app.returns_items[count].quantity;
        // const unit_rate = app.returns_items[count].rate;
        // total_tax += parseFloat(tax_rate * quantity * unit_rate);
        subtotal += parseFloat(app.returns_items[count].amount);
      }
      // app.form.tax = total_tax.toFixed(2);
      app.form.subtotal = subtotal.toFixed(2);
      app.form.discount = parseFloat(
        (app.discount_rate / 100) * subtotal,
      ).toFixed(2);
      // subtract discount
      app.form.amount = parseFloat(subtotal - app.form.discount).toFixed(2);
    },

  },
};
</script>

