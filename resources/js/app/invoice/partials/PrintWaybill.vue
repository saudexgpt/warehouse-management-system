<template>
  <div class="clear-margin">
    <!-- title row -->
    <div class="row">
      <div class="col-xs-12 page-header" align="center">
        <img src="svg/logo.png" alt="Company Logo" width="50">
        <span>
          <label>{{ companyName }}</label>
          <div class="pull-right no-print">
            <a v-if="checkPermission(['manage waybill'])" @click="doPrint();"><i class="el-icon-printer" /> Print Waybill</a>
          </div>
        </span>
      </div>
      <!-- /.col -->
    </div>
    <!-- info row -->
    <div class="row invoice-info" align="center">
      <span v-html="companyContact" />
      <legend>WAYBILL/DELIVERY NOTE</legend>

    </div>
    <!-- /.row -->
    <!-- Table row -->
    <div class="row">
      <div class="col-xs-8 table-responsive">
        <label>Customer Details</label>
        <address>
          <label>{{ waybill.waybill_items[0].invoice.customer.user.name.toUpperCase() }}</label><br>
          <!-- {{ (waybill.waybill_items[0].invoice.customer.type) ? waybill.waybill_items[0].invoice.customer.type.toUpperCase() : '' }}<br> -->
          Phone: {{ waybill.waybill_items[0].invoice.customer.user.phone }}<br>
          Email: {{ waybill.waybill_items[0].invoice.customer.user.email }}<br>
          {{ waybill.waybill_items[0].invoice.customer.user.address }}
        </address>
        <legend>Invoice Products</legend>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>S/N</th>
              <th>Invoice No.</th>
              <!-- <th>Customer</th> -->
              <th>Product</th>
              <th>Quantity</th>
              <th>Batch No.</th>
              <th>Expires</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(waybill_item, index) in waybill.waybill_items" :key="index">
              <template v-if="waybill_item.remitted > 0">

                <td>{{ index + 1 }}</td>
                <td>{{ waybill_item.invoice.invoice_number }}</td>
                <td>{{ waybill_item.item.name }}</td>
                <!-- <td>{{ waybill_item.item.description }}</td> -->
                <td>{{ waybill_item.remitted+' '+waybill_item.type }}<br>
                  <code v-html="showItemsInCartons(waybill_item.remitted, waybill_item.invoice_item.quantity_per_carton, waybill_item.type)" />
                </td>
                <td>
                  <div v-for="(batch, batch_index) in uniqueBatchNoAndExpiryDates(waybill_item.invoice_item.batches, 'batch')" :key="batch_index">
                    <small>{{ batch }},<br></small>
                  </div>
                </td>
                <td>
                  <div v-for="(expiry_date, expiry_date_index) in uniqueBatchNoAndExpiryDates(waybill_item.invoice_item.batches, 'expiry_date')" :key="expiry_date_index">
                    {{ moment(expiry_date).format('MMMM Do YYYY') }},<br>
                  </div>
                </td>
              <!-- <td align="right">{{ currency + Number(waybill_item.rate).toLocaleString() }}</td>
              <td>{{ waybill_item.type }}</td>
              <td align="right">{{ currency + Number(waybill_item.amount).toLocaleString() }}</td> -->
              </template>
            </tr>
            <!-- <tr>
              <td colspan="4" align="right"><label>Subtotal</label></td>
              <td align="right">{{ currency + Number(waybill.invoice.subtotal).toLocaleString() }}</td>
            </tr>
            <tr>
              <td colspan="4" align="right"><label>Discount</label></td>
              <td align="right">{{ currency + Number(waybill.invoice.discount).toLocaleString() }}</td>
            </tr>
            <tr>
              <td colspan="4" align="right"><label>Grand Total</label></td>
              <td align="right"><label style="color: green">{{ currency + Number(waybill.invoice.amount).toLocaleString() }}</label></td>
            </tr> -->
          </tbody>
        </table>
      </div>
      <div class="col-xs-4 table-responsive">
        <label>Waybill No.: {{ waybill.waybill_no }}</label>
        <label>Dispatched By.: {{ waybill.dispatch_company }}</label>
        <br>
        <label>Date:</label> {{ moment(waybill.created_at).format('MMMM Do YYYY') }}
        <table v-if="waybill.dispatcher" class="table table-bordered">
          <tbody>
            <tr>
              <td><label>Vehicle No.:</label> {{ waybill.dispatcher.vehicle.plate_no }}<br></td>
            </tr>
            <tr>
              <td>Dispatched By:</td>
            </tr>
            <tr v-for="(vehicle_driver, index) in waybill.dispatcher.vehicle.vehicle_drivers" :key="index">
              <td v-if="vehicle_driver.driver">
                <label>{{ vehicle_driver.type }} Dispatcher</label><br>
                <label>Name:</label> {{ vehicle_driver.driver.user.name }}<br>
                <label>Phone:</label> {{ vehicle_driver.driver.user.phone }}<br>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- /.col -->
    </div>
    <div class="row">
      <div class="col-xs-12">
        <span>Confirm the receipt of the above listed goods in good condition and complete</span><br>
        <span>Which are not returnable</span><br><br><br>
      </div>
      <div class="col-xs-7">
        <label>Name: ______________________________</label><br><br><br>
        <label>Sign: ______________________________</label>
      </div>
      <div class="col-xs-5">
        <label>Date: _______________________</label><br><br><br>
        <label>Time: _______________________</label>
      </div>
    </div>
  </div>
</template>

<script>
import moment from 'moment';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';
import showItemsInCartons from '@/utils/functions';
// import Watermark from '@/watermark';
export default {
  props: {
    waybill: {
      type: Object,
      default: () => ({}),
    },
    page: {
      type: Object,
      default: () => ({
        option: 'print_waybill',
      }),
    },
    companyName: {
      type: String,
      default: () => ('Warehouse Management System'),
    },
    companyContact: {
      type: String,
      default: () => (''),
    },
    currency: {
      type: String,
      default: () => ('₦'),
    },
  },
  data() {
    return {
      activeActivity: 'first',
    };
  },
  mounted() {
    // Watermark.set('Green Life Pharmaceutical Ltd.');
    this.doPrint();
  },
  methods: {
    checkPermission,
    checkRole,
    moment,
    showItemsInCartons,
    uniqueBatchNoAndExpiryDates(batches, option) {
      const batch_nos = [];
      const expiry_dates = [];
      batches.forEach(batch => {
        if (!batch_nos.includes(batch.item_stock_batch.batch_no)) {
          batch_nos.push(batch.item_stock_batch.batch_no);
        }
        if (!expiry_dates.includes(batch.item_stock_batch.expiry_date)) {
          expiry_dates.push(batch.item_stock_batch.expiry_date);
        }
      });
      if (option === 'batch') {
        return batch_nos;
      }
      return expiry_dates;
    },
    doPrint() {
      window.print();
    },
  },
};
</script>
<style>
@media print {
  * {
    -webkit-print-color-adjust: exact !important; /*Chrome, Safari */
    color-adjust: exact !important;  /*Firefox*/

  }
  .clear-margin {
    margin-top: -100px !important;
  }
}
</style>
