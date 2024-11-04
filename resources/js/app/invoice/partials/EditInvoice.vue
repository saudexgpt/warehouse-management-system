<template>
  <div class>
    <div>
      <div v-if="params" class="box">
        <div class="box-header">
          <h4 class="box-title">Edit Invoice</h4>
        </div>
        <div class="box-body">
          <el-form ref="form" :model="form" label-width="120px">
            <el-row :gutter="5" class="padded">
              <el-col :xs="24" :sm="12" :md="12">
                <label for>Invoice Number</label>
                <p>{{ form.invoice_number }}</p>
                <!-- <el-input v-model="form.invoice_number" type="text" /> -->
                <label for>Warehouse</label>
                <!-- <p v-if="form.warehouse">{{ form.warehouse.name }}</p> -->
                <el-select
                  v-model="form.warehouse_id"
                  placeholder="Select Warehouse"
                  filterable
                  class="span"
                  @input="show_product_list = true"
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
                <label for>Customer</label>
                <el-select
                  v-model="form.customer_id"
                  placeholder="Select Customer"
                  filterable
                  class="span"
                >
                  <el-option
                    v-for="(customer, customer_index) in customers"
                    :key="customer_index"
                    :value="customer.id"
                    :label="(customer.user) ? customer.user.name : ''"
                  />
                </el-select>
                <label for>Invoice Date</label>
                <el-date-picker
                  v-model="form.invoice_date"
                  type="date"
                  placeholder="Invoice Date"
                  style="width: 100%;"
                  format="yyyy/MM/dd"
                  value-format="yyyy-MM-dd"
                  :picker-options="pickerOptions"
                />
                <p>{{ form.invoice_date }}</p>
              </el-col>
            </el-row>
            <div v-if="invoice.waybill_items.length < 1 || invoice.status === 'pending'">
              <el-row :gutter="2" class="padded">
                <el-col>
                  <div v-loading="load" style="overflow: auto">
                    <label for>PRODUCT DETAILS</label>
                    <table class="table table-binvoiceed">
                      <thead>
                        <tr>
                          <th />
                          <th>Product</th>
                          <th>Quantity</th>
                          <th>Rate</th>
                          <th>Per</th>
                          <th>Amount</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(invoice_item, index) in invoice_items" :key="index">
                          <td>
                            <span>
                              <a
                                class="btn btn-danger btn-flat fa fa-trash"
                                @click="removeLine(index)"
                              />
                              <a
                                v-if="index + 1 === invoice_items.length"
                                class="btn btn-info btn-flat fa fa-plus"
                                @click="addLine(index)"
                              />
                            </span>
                          </td>
                          <td>
                            <span v-if="invoice_item.item">{{ invoice_item.item.name }}</span>
                            <el-select
                              v-model="invoice_item.item"
                              value-key="id"
                              filterable
                              placeholder="Change"
                              @change="fetchItemDetails(index)"
                            >
                              <el-option
                                v-for="(item, item_index) in params.items"
                                :key="item_index"
                                :value="item"
                                :label="item.name"
                              />
                            </el-select>
                            <div v-if="params.enable_stock_quantity_check_when_raising_invoice === 'yes'" v-loading="invoice_item.load">
                              <div>
                                <!-- <br><small class="label label-primary">Physical Stock: {{ invoice_item.total_stocked }} {{ invoice_item.type }}</small>

                                <br><small class="label label-warning">Total Pending Invoice: {{ invoice_item.total_invoiced_quantity }} {{ invoice_item.type }}</small> -->

                                <br><small class="label label-success">Available for Order: {{ invoice_item.stock_balance }} {{ invoice_item.type }}</small>
                              </div>
                            </div>
                          </td>
                          <td>
                            <el-input
                              v-model="invoice_item.quantity"
                              type="number"
                              outline
                              placeholder="Quantity"
                              min="1"
                              @input="calculateTotal(index); calculateNoOfCartons(index); checkStockBalance(index)"
                            />
                            <br>
                            <div>
                              <div v-if="params.enable_stock_quantity_check_when_raising_invoice === 'yes' && invoice_item.stock_balance < 1" class="label label-danger">You cannot raise invoice for this product due to insufficient stock</div>
                            </div>
                            <br><code v-html="showItemsInCartons(invoice_item.quantity, invoice_item.quantity_per_carton, invoice_item.type)" />
                          </td>
                          <td>
                            <!-- {{ currency }} {{ Number(invoice_item.rate).toLocaleString() }}
                            <br>
                            <el-switch
                              v-model="invoice_item.is_promo"
                              active-text="Is Promo"
                              inactive-text="Not Promo"
                              @change="setItemAsPromo(index, invoice_item.is_promo);"
                            /> -->
                            <el-input
                              v-if="checkPermission(['update invoice product price'])"
                              v-model="invoice_item.rate"
                              type="number"
                              outline
                              @input="calculateTotal(index)"
                            />
                            <span v-else>{{ currency }} {{ Number(invoice_item.rate).toLocaleString() }}</span>
                          </td>
                          <td>{{ invoice_item.type }}</td>
                          <td align="right">
                            <el-input v-model="invoice_item.amount" type="hidden" outline />
                            {{ Number(invoice_item.amount).toLocaleString() }}
                          </td>
                        </tr>
                        <tr v-if="fill_fields_error">
                          <td colspan="5">
                            <label
                              class="label label-danger"
                            >Please fill all empty fields before adding another row</label>
                          </td>
                        </tr>
                        <tr>
                          <td colspan="5" align="right">
                            <label>Subtotal</label>
                          </td>
                          <td align="right">{{ Number(form.subtotal).toLocaleString() }}</td>
                        </tr>
                        <tr>
                          <td colspan="5" align="right">
                            <el-dropdown
                              class="avatar-container right-menu-item hover-effect"
                              trigger="click"
                            >
                              <div class="avatar-wrapper" style="color: brown">
                                <label style="cursor:pointer">Add Discount</label>
                              </div>
                              <el-dropdown-menu slot="dropdown" style="padding: 10px;">
                                <el-input
                                  v-model="discount_rate"
                                  type="number"
                                  min="0"
                                  style="width: 50%;"
                                  @input="calculateTotal(null)"
                                />% of Subtotal
                                <el-dropdown-item divided>Enter Discount percentage</el-dropdown-item>
                              </el-dropdown-menu>
                            </el-dropdown>
                          </td>
                          <td align="right">{{ Number(form.discount).toLocaleString() }}</td>
                        </tr>
                        <tr>
                          <td colspan="5" align="right">
                            <label>Grand Total</label>
                          </td>
                          <td align="right">
                            <label style="color: green">{{ Number(form.amount).toLocaleString() }}</label>
                          </td>
                        </tr>
                        <tr>
                          <td align="right">Notes</td>
                          <td colspan="5">
                            <textarea
                              v-model="form.notes"
                              class="form-control"
                              rows="5"
                              placeholder="Type extra note on this invoice here..."
                            />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </el-col>
              </el-row>
            </div>
            <div v-else>
              <el-row :gutter="2" class="padded">
                <el-col>
                  <div style="overflow: auto">
                    <label for>Products</label>
                    <table class="table table-binvoiceed">
                      <thead>
                        <tr>
                          <th />
                          <th>Product</th>
                          <th>Quantity</th>
                          <th>Rate</th>
                          <th>Per</th>
                          <th>Amount</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(invoice_item, index) in invoice_items" :key="index">
                          <td>{{ index + 1 }}</td>
                          <td>
                            <span v-if="invoice_item.item">{{ invoice_item.item.name }}</span>
                          </td>
                          <td>{{ invoice_item.quantity }}({{ invoice_item.no_of_cartons }} CTN)</td>
                          <td>{{ currency }} {{ Number(invoice_item.rate).toLocaleString() }}</td>
                          <td>{{ invoice_item.type }}</td>
                          <td align="right">{{ currency }}{{ Number(invoice_item.amount).toLocaleString() }}</td>
                        </tr>
                        <tr v-if="fill_fields_error">
                          <td colspan="5">
                            <label
                              class="label label-danger"
                            >Please fill all empty fields before adding another row</label>
                          </td>
                        </tr>
                        <tr>
                          <td colspan="5" align="right">
                            <label>Subtotal</label>
                          </td>
                          <td align="right">{{ currency }}{{ Number(form.subtotal).toLocaleString() }}</td>
                        </tr>
                        <tr>
                          <td colspan="5" align="right">
                            <label>Grand Total</label>
                          </td>
                          <td align="right">
                            <label style="color: green">{{ currency }}{{ Number(form.amount).toLocaleString() }}</label>
                          </td>
                        </tr>
                        <tr>
                          <td align="right">Notes</td>
                          <td colspan="5">{{ form.notes }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </el-col>
              </el-row>
            </div>
            <el-row :gutter="2" class="padded">
              <el-col :xs="24" :sm="6" :md="6">
                <el-button :disabled="disable_submit" type="success" @click="updateInvoice">
                  <i class="el-icon-edit" />
                  Update Invoice
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
import showItemsInCartons from '@/utils/functions';
import Resource from '@/api/resource';
const editInvoice = new Resource('invoice/general/update');
const necessaryParams = new Resource('fetch-necessary-params');
const getCustomers = new Resource('fetch-customers');
const fetchProductBatches = new Resource('stock/items-in-stock/product-batches');
export default {
  // name: 'AddNewInvoice',
  props: {
    invoice: {
      type: Object,
      default: () => ({}),
    },
    page: {
      type: Object,
      default: () => ({
        option: 'edit_invoice',
      }),
    },
    params: {
      type: Object,
      default: () => ({}),
    },
  },
  data() {
    return {
      load: false,
      pickerOptions: {
        disabledDate(date) {
          var d = new Date(); // today
          d.setDate(d.getDate()); // one year from now
          return date > d;
        },
      },
      currency: '₦',
      customers: [],
      customer_types: [],
      items_in_stock_dialog: false,
      dialogFormVisible: false,
      userCreating: false,
      fill_fields_error: false,
      show_product_list: false,
      disable_submit: false,
      batches_of_items_in_stock: [],
      form: {
        warehouse_id: '',
        customer_id: '',
        invoice_number: '',
        status: 'pending',
        invoice_date: '',
        subtotal: 0,
        discount: 0,
        amount: 0,
        notes: '',
        invoice_items: [
          {
            item_id: '',
            quantity: 1,
            rate: null,
            tax: null,
            type: '',
            total: 0,
            batches: [],
            batches_of_items_in_stock: [],
          },
        ],
      },
      empty_form: {
        warehouse_id: '',
        customer_id: '',
        invoice_number: '',
        status: 'pending',
        invoice_date: '',
        subtotal: 0,
        discount: 0,
        amount: 0,
        notes: '',
        invoice_items: [
          {
            item_id: '',
            quantity: 1,
            type: '',
            rate: null,
            amount: 0,
          },
        ],
      },
      invoice_items: [],
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
        customer_type_id: [
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
  watch: {
    invoice_items() {
      this.blockRemoval = this.invoice_items.length <= 1;
    },
  },
  mounted() {
    this.form = this.invoice;
    this.setInvoiceItemsProps();
    this.fetchCustomers();
    // this.addLine();
  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    showItemsInCartons,
    setInvoiceItemsProps() {
      const app = this;
      app.load = true;
      const editInvoiceItems = new Resource('invoice/general/edit');
      editInvoiceItems.get(app.invoice.id).then((response) => {
        app.load = false;
        const invoice_items = response.invoice_items;
        // let index = 0;
        invoice_items.forEach(invoice_item => {
          invoice_item.load = false;
          invoice_item.item_rate = invoice_item.rate;
          invoice_item.stock_balance = (invoice_item.item.stocks.length > 0) ? invoice_item.item.stocks[0].total_balance : 0;
          // app.setProductBatches(index, item.warehouse_id, item.item_id);
          // index++;
        });
        app.invoice_items = invoice_items;
      });
    },
    rowIsEmpty() {
      this.fill_fields_error = false;
      const checkEmptyLines = this.invoice_items.filter(
        (detail) =>
          detail.item_id === '' ||
          detail.quantity === '' ||
          detail.rate === null ||
          detail.tax === null ||
          detail.total === 0,
      );
      if (checkEmptyLines.length >= 1) {
        this.fill_fields_error = true;
        // this.invoice_items[index].seleted_category = true;
        return true;
      }
      false;
    },
    stockIsZero() {
      let isZero = 0;
      this.invoice_items.forEach(item => {
        const stock_balance = parseInt(item.stock_balance);
        const quantity = parseInt(item.quantity);
        if (stock_balance < 1) {
          isZero += 1;
        }
        if (stock_balance < quantity) {
          isZero += 1;
        }
      });
      console.log(isZero);
      return isZero;
    },
    addLine(index) {
      if (this.rowIsEmpty() && this.invoice_items.length > 0) {
        return;
      } else {
        // if (this.invoice_items.length > 0)
        //     this.invoice_items[index].grade = '';

        this.invoice_items.push({
          item: null,
          item_id: '',
          load: false,
          quantity: 1,
          type: '',
          item_rate: null,
          rate: null,
          amount: 0,
          total_stocked: null,
          total_invoiced_quantity: null,
          stock_balance: null,
        });
      }
    },
    removeLine(index) {
      this.fill_fields_error = false;
      if (!this.blockRemoval) {
        this.invoice_items.splice(index, 1);
        this.calculateTotal(null);
      }
    },
    fetchNecessaryParams() {
      const app = this;
      necessaryParams.list().then((response) => {
        app.params = response.params;
      });
    },
    fetchCustomers() {
      const app = this;
      getCustomers.list().then((response) => {
        app.customers = response.customers;
        app.customer_types = response.customer_types;
      });
    },
    updateInvoice() {
      const app = this;
      var form = app.form;
      if (app.params.enable_stock_quantity_check_when_raising_invoice === 'yes') {
        if (app.stockIsZero() > 0) {
          app.$alert('Please remove all entries with insufficient stock');
          return;
        }
      }
      if (this.rowIsEmpty()) {
        app.$alert('Please fill in all fields on each row');
        return;
      }
      const checkEmptyFields =
        form.warehouse_id === '' ||
        form.customer_id === '' ||
        form.invoice_date === '' ||
        form.currency_id === '' ||
        form.invoice_number === '';
      if (!checkEmptyFields) {
        app.load = true;
        form.invoice_items = app.invoice_items;
        editInvoice
          .update(form.id, form)
          .then((response) => {
            app.$message({
              message: 'Invoice Updated Successfully!!!',
              type: 'success',
            });
            // app.form = app.empty_form;
            app.$emit('update', response.invoice);
            app.load = false;
            app.page.option = 'list';
          })
          .catch((error) => {
            app.load = false;
            console.log(error.message);
          });
      } else {
        app.$alert('Please fill the form fields completely');
      }
    },
    fetchItemDetails(index) {
      const app = this;
      const item = app.invoice_items[index].item;
      app.invoice_items[index].item_rate = item.price.sale_price;
      app.invoice_items[index].rate = item.price.sale_price;
      app.invoice_items[index].item_id = item.id;
      app.invoice_items[index].item = item;
      app.invoice_items[index].type = item.package_type;
      app.invoice_items[index].quantity_per_carton = item.quantity_per_carton;
      app.invoice_items[index].no_of_cartons = 0;
      app.invoice_items[index].quantity = 1;

      if (app.params.enable_stock_quantity_check_when_raising_invoice === 'yes') {
        app.setProductBatches(index, app.form.warehouse_id, item.id);
      }
      app.calculateTotal(index);
    },
    setProductBatches(index, warehouse_id, item_id) {
      const app = this;
      const param = {
        warehouse_id: warehouse_id,
        item_id: item_id,
      };
      app.invoice_items[index].load = true;
      app.disable_submit = false;
      fetchProductBatches.list(param).then((response) => {
        app.invoice_items[index].load = false;
        const total_stocked = (response.total_balance) ? parseInt(response.total_balance.total_balance) : 0;
        const total_invoiced_quantity = (response.total_invoiced_quantity) ? parseInt(response.total_invoiced_quantity.total_invoiced) : 0;

        app.invoice_items[index].total_stocked = total_stocked;
        app.invoice_items[index].total_invoiced_quantity = total_invoiced_quantity;
        const stock_balance = parseInt(total_stocked - total_invoiced_quantity);

        app.invoice_items[index].stock_balance = (stock_balance < 0) ? 0 : stock_balance;

        if (stock_balance < 1) {
          app.disable_submit = true;
        }
        app.calculateTotal(index);
        app.invoice_items.unshift({});
        setTimeout(() => {
          app.invoice_items.splice(0, 1);
        }, 100);
      }).catch(error => {
        app.invoice_items[index].load = false;
        console.log(error);
      });
    },
    calculateNoOfCartons(index) {
      const app = this;
      if (index !== null) {
        const item = app.invoice_items[index].item;
        const quantity = app.invoice_items[index].quantity;
        const quantity_per_carton = item.quantity_per_carton;
        if (quantity_per_carton > 0) {
          const no_of_cartons = quantity / quantity_per_carton;
          app.invoice_items[index].no_of_cartons = no_of_cartons; // + parseFloat(tax);
        }
      }
    },
    calculateTotal(index) {
      const app = this;
      // Get total amount for this item without tax
      if (index !== null) {
        const quantity = app.invoice_items[index].quantity;
        const unit_rate = app.invoice_items[index].rate;
        app.invoice_items[index].amount = parseFloat(
          quantity * unit_rate,
        ).toFixed(2); // + parseFloat(tax);
      }

      // we now calculate the running total of items invoiceed for with tax //////////
      // let total_tax = 0;
      let subtotal = 0;
      for (let count = 0; count < app.invoice_items.length; count++) {
        // const tax_rate = app.invoice_items[count].tax;
        // const quantity = app.invoice_items[count].quantity;
        // const unit_rate = app.invoice_items[count].rate;
        // total_tax += parseFloat(tax_rate * quantity * unit_rate);
        subtotal += parseFloat(app.invoice_items[count].amount);
      }
      // app.form.tax = total_tax.toFixed(2);
      app.form.subtotal = subtotal.toFixed(2);
      app.form.discount = parseFloat(
        (app.discount_rate / 100) * subtotal,
      ).toFixed(2);
      // subtract discount
      app.form.amount = parseFloat(subtotal - app.form.discount).toFixed(2);
    },
    checkStockBalance(index) {
      const app = this;
      // Get total amount for this item without tax
      if (app.params.enable_stock_quantity_check_when_raising_invoice === 'yes') {
        if (index !== null) {
          const invoice_item = app.invoice_items[index];
          const item = app.invoice_items[index].item;
          const quantity = invoice_item.quantity;
          const available_stock = invoice_item.total_stocked - invoice_item.total_invoiced_quantity;
          app.disable_submit = false;
          if (quantity > available_stock) {
            app.disable_submit = true;
            app.$alert(`${item} stock balance is less than ${quantity}. Please enter a value within range`);
            app.invoice_items[index].quantity = 0;
            app.calculateTotal(index);
          }
        }
      }
    },
    setItemAsPromo(index, value) {
      const app = this;
      const item_rate = app.invoice_items[index].item_rate;
      app.invoice_items[index].rate = item_rate;
      if (value === true) {
        app.invoice_items[index].rate = 0;
      }
      app.calculateTotal(index);
    },
  },
};
</script>

