<template>
  <div class="app-container">
    <div>
      <div v-if="params" class="box">
        <div class="box-header">
          <h4 class="box-title">List of Returned Products</h4>
          <span class="pull-right no-print">
            <a class="btn btn-danger" @click="page.option = 'list'"> Back</a>
            <a class="btn btn-info" @click="doPrint();"><i class="el-icon-printer" /> Print</a>
          </span>
        </div>
        <div class="box-body">
          <table class="table">
            <thead>
              <tr>
                <th>
                  <label>Customer Name</label>
                  <address>
                    <label>{{ selectedCustomer.user.name.toUpperCase() }}</label>
                  </address>
                </th>
                <th>
                  <label>Returns No.: {{ returnedProduct.returns_no }}</label>
                  <br>
                  <label>Date:</label>
                  {{
                    moment(returnedProduct.date_returned).format('MMMM Do YYYY')
                  }}
                  <br>
                </th>
                <th>
                  <label>Returned By</label>
                  <address>
                    <strong>{{ returnedProduct.stocker.name }}</strong>
                  </address>
                </th>
                <th>
                  <h3>Total Amount: {{ currency + totalAmount.toLocaleString() }}</h3>
                </th>
              </tr>
            </thead>
          </table>
          <el-form ref="form" v-loading="loadForm" :model="form" label-width="120px">
            <div>
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
                            {{ index + 1 }}
                          </td>
                          <td>
                            {{ invoice_item.item ? invoice_item.item.name : '' }}
                          </td>
                          <td v-loading="invoice_item.load">
                            <p>Batch No: {{ invoice_item.batch_no }}</p>
                            <p>Expiry Date: {{ invoice_item.expiry_date }}</p>
                          </td>
                          <td>
                            <p>Quantity: {{ invoice_item.quantity }}{{ invoice_item.type }}</p>
                            <p>Quantity in CTNs: <span v-html="showItemsInCartons(invoice_item.quantity, invoice_item.quantity_per_carton, invoice_item.type)" /></p>
                          </td>
                          <td>
                            {{ invoice_item.reason === 'Others' ? invoice_item.other_reason : invoice_item.reason }}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </el-col>
              </el-row>
            </div>
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
const checkProductsInStock = new Resource('invoice/general/check-product-quantity-in-stock');
export default {
  // name: 'CreateInvoice',
  props: {
    returnedProduct: {
      type: Object,
      default: () => ({}),
    },
    selectedCustomer: {
      type: Object,
      default: () => ({}),
    },
    page: {
      type: Object,
      default: () => ({
        option: 'edit_returns',
      }),
    },
  },
  data() {
    return {
      auditCheckForm: {
        comment: '',
      },
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
      dispatched_products: [],
      form: {
        warehouse_id: 7,
        customer_id: '',
        customer_name: '',
        status: 'pending',
        date_returned: '',
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
        date_returned: '',
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
      totalAmount: 0,
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
    this.loadData();
    this.fetchNecessaryParams();
    this.fetchCustomers();
    // this.addLine();
  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    showItemsInCartons,
    calculateTotalAmount() {
      let total = 0;
      this.returns_items.forEach(product => {
        total += Number(product.price) * Number(product.quantity);
      });
      this.totalAmount = total;
    },
    doPrint() {
      window.print();
    },
    submitAuditorComment(id, status) {
      const app = this;
      if (status === 'confirmed') {
        let overflowCount = 0;
        app.returns_items.forEach(element => {
          if (app.isQuantityOverflow(element.quantity, element.max_quantity)) {
            element.showMaxQuantity = true;
            overflowCount++;
          }
        });
        if (overflowCount > 0) {
          app.$alert('Please ensure you do not exceed the maximum returnable quantity for each row');
          return;
        }
      }

      const { comment } = app.auditCheckForm;
      if (comment !== '') {
        app.dialogVisible = false;
        app.load = true;
        const approveReturnedProducts = new Resource('stock/returns/auditor-comment');
        approveReturnedProducts.update(id, { comment, approval_status: status })
          .then(response => {
            // app.products[app.selected_row_index - 1] = response.returned_product;
            app.fetchItemStocks();
            app.$message('Action Successful');
            app.$emit('update');
            app.load = false;
          })
          .catch(error => {
            app.load = false;
            console.log(error.message);
          });
      } else {
        app.$alert('Please give a comment to proceed');
        return;
      }
    },
    loadData() {
      this.form = this.returnedProduct;
      this.returns_items = this.returnedProduct.products;
      if (this.returns_items.length < 1) {
        this.addLine();
        return;
      }
      for (let index = 0; index < this.returns_items.length; index++) {
        const element = this.returns_items[index];
        const quantity = element.quantity;
        const selectedBatch = {
          id: element.item_stock_sub_batch_id,
          batch_id: element.item_stock_sub_batch_id,
          dispatched_product_id: element.dispatched_product_id,
          batch_no: element.batch_no,
          expiry_date: element.expiry_date,
          price: element.price,
          invoice_no: element.invoice_no,
          max_quantity: element.max_returnable_quantity,
        };
        this.fetchInvoicesForBatchNo(element.item_id, index, quantity, selectedBatch, element.batch_no);
      }
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
        const id = this.returns_items[detailId].id;
        const unsavedReturns = this.form;
        unsavedReturns.returns_items = this.returns_items;
        this.$store.dispatch('returns/saveUnsavedReturns', unsavedReturns);
        this.deleteEntry(id);
        this.loadData();
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
    deleteEntry(id) {
      const deleteInvoice = new Resource('stock/returns/delete');
      deleteInvoice.destroy(id)
        .then(response => {})
        .catch(error => {
          alert(error.message);
        });
    },
    submitNewInvoice() {
      const app = this;
      if (this.rowIsEmpty()) {
        app.$alert('Please fill in all fields on each row');
        return;
      }
      let overflowCount = 0;
      app.returns_items.forEach(element => {
        if (app.isQuantityOverflow(element.quantity, element.max_quantity)) {
          element.showMaxQuantity = true;
          overflowCount++;
        }
      });
      if (overflowCount > 0) {
        app.$alert('Please ensure you do not exceed the maximum returnable quantity for each row');
        return;
      }
      var form = app.form;
      const checkEmptyFields =
        form.warehouse_id === '' ||
        app.selectedCustomer === '' ||
        form.date_returned === '';
      if (!checkEmptyFields) {
        app.loadForm = true;
        form.products = app.returns_items;
        form.customer_id = app.selectedCustomer.id;
        form.customer_name = app.selectedCustomer.user.name;
        app.disable_submit = true;
        const createInvoice = new Resource('stock/returns/update');
        createInvoice
          .update(form.id, form)
          .then((response) => {
            app.$message({ message: 'Product Updated Successfully!!!', type: 'success' });

            app.$emit('update', response.returned_product);
            app.page.option = 'list';// return to list of items

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
    fetchInvoicesForBatchNo(itemId, index, quantity, selectedBatch, batch_no) {
      const app = this;
      app.fetchItemDetails(index, quantity);
      const form = { batch_no, item_id: itemId, customer_id: app.selectedCustomer.id };
      const fetchDeliveredInvoicesResource = new Resource('stock/returns/fetch-invoices-for-batch');
      fetchDeliveredInvoicesResource
        .list(form)
        .then((response) => {
          const dispatched_products = response.dispatched_products;
          app.dispatched_products = response.dispatched_products;
          app.setProductBatches(dispatched_products, index, selectedBatch);
        })
        .catch((error) => {
          app.loadForm = false;
          console.log(error.message);
        });
    },
    fetchItemDetails(index, quantity = 1) {
      const app = this;
      const item = app.returns_items[index].item;
      app.returns_items[index].item_id = item.id;
      app.returns_items[index].type = item.package_type;
      app.returns_items[index].quantity_per_carton = item.quantity_per_carton;
      app.returns_items[index].no_of_cartons = 0;
      app.returns_items[index].quantity = quantity;
    },
    setProductBatches(dispatchedProducts, index, selectedBatch = null) {
      const app = this;
      const batches = [selectedBatch];
      app.returns_items[index].batches = batches;
      app.returns_items[index].selectedBatch = selectedBatch;
      app.returns_items[index].batch_no = (selectedBatch !== null) ? selectedBatch.batch_no : '';
      app.returns_items[index].batch_id = (selectedBatch !== null) ? selectedBatch.batch_id : null;
      app.returns_items[index].dispatched_product_id = (selectedBatch !== null) ? selectedBatch.dispatched_product_id : null;
      app.returns_items[index].expiry_date = (selectedBatch !== null) ? selectedBatch.expiry_date : '';
      app.returns_items[index].max_quantity = (selectedBatch !== null) ? selectedBatch.max_quantity : 0;
      app.returns_items[index].invoice_no = (selectedBatch !== null) ? selectedBatch.invoice_no : '';
      app.returns_items[index].showMaxQuantity = false;

      this.calculateTotalAmount();
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

