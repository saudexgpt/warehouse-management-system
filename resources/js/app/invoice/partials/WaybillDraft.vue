<template>
  <div>
    <h4>This document is to help load the truck with products listed below. When the truck is loaded, proceed to generate the final waybill for supply based on what was loaded.</h4>
    <el-button class="no-print" type="danger" @click="print()">Print Draft For Loading</el-button>
    <div class="watermark">
      Draft copy. Not for supply!!!
    </div>
    <table class="table table-binvoiceed">
      <thead>
        <tr>
          <th />
          <th>Product</th>
          <th>Order</th>
          <th>Supplied</th>
          <th>Balance</th>
          <th>Stock Info</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(invoice_item, index) in invoiceItems"
          :key="index"
        >
          <td>{{ index + 1 }}</td>
          <td>
            {{ invoice_item.item.name }}

          </td>
          <td>
            {{ invoice_item.quantity }}
            {{
              invoice_item.item.package_type
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
                invoice_item.quantity -
                  invoice_item.quantity_supplied
              }}
            </div>
          </td>
          <td>
            <div>
              <small>Physical Stock: {{ invoice_item.physical_stock }} {{ invoice_item.item.package_type }}</small>

              <br><small>Total Reserved: {{ invoice_item.reserved_for_supply }} {{ invoice_item.item.package_type }}</small>

              <br><small>Total Available: {{ invoice_item.total_batch_balance }} {{ invoice_item.item.package_type }}</small>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
<script>
export default {
  props: {
    invoiceItems: {
      type: Array,
      default: () => ([]),
    },
  },
  methods: {
    print() {
      window.print();
    },
  },
};
</script>
<style scoped>
.watermark {
    font-size: 48px;
    font-weight: 600;
    opacity: 0.5;
    color: BLACK;
    position: fixed;
    top: auto;
    transform: rotate(45deg);
}
.watermark {
    display: none;
  }
@media print {
  .watermark {
    display: block;
  }
}
</style>
