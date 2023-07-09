<template>
  <div class="app-container">
    <div>
      <el-row :gutter="10">
        <el-col :xs="24" :sm="12" :md="12">
          <label for="">Select Warehouse</label>
          <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" class="span" filterable>
            <el-option v-for="(warehouse, index) in params.warehouses" :key="index" :value="warehouse.id" :label="warehouse.name" />

          </el-select>

        </el-col>
        <el-col :xs="24" :sm="10" :md="10">
          <label for="">Select Date</label><br>
          <el-date-picker
            v-model="form.date"
            type="month"
            placeholder="Pick a month"
            format="yyyy-MM"
            value-format="yyyy-MM"
            :picker-options="pickerOptions"
          />
          <el-button type="primary" round @click="prepareStockCount()">Continue</el-button>

        </el-col>
      </el-row>
      <br>

      <v-client-table
        v-model="stock_counts"
        v-loading="load"
        element-loading-text="Preparing products for stock count..."
        :columns="columns"
        :options="options"
      >
        <div slot="count_quantity" slot-scope="{row}">
          <span>
            <input v-model="row.count_quantity" class="form-control" type="number" placeholder="Enter quantity of products counted" @input="convertQuantity(row, $event)" @change="countStock(row.id, $event)">
          </span>
          <code :id="row.id" v-html="showItemsInCartons(row.count_quantity, row.item.quantity_per_carton, row.item.package_type)" />
        </div>
      </v-client-table>
    </div>
  </div>
</template>
<script>
import moment from 'moment';
import checkPermission from '@/utils/permission';
import showItemsInCartons from '@/utils/functions';
import checkRole from '@/utils/role';
import Resource from '@/api/resource';
export default {
  name: 'NewCount',
  data() {
    return {
      pickerOptions: {
        disabledDate(date) {
          var d = new Date(); // today
          // d.setDate(d.getMonth());
          const month1 = date.getMonth();
          const month2 = d.getMonth();
          return month1 !== month2;
        },
      },
      stock_counts: [],
      columns: ['item.name', 'count_quantity'],

      options: {
        headings: {
          'item.name': 'Product',
          count_quantity: 'Count Quantity',

          // id: 'S/N',
        },
        pagination: {
          dropdown: true,
          chunk: 10,
        },
        filterByColumn: true,
        texts: {
          filter: 'Search:',
        },
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: ['item.name'],
        filterable: ['item.name'],
      },
      page: {
        option: 'list',
      },
      form: {
        warehouse_id: '',
        date: new Date(),
      },
      load: false,

    };
  },
  computed: {
    params() {
      return this.$store.getters.params;
    },
  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    showItemsInCartons,
    prepareStockCount() {
      const app = this;
      app.load = true;

      const param = app.form;
      const stockCountResource = new Resource('stock/count/prepare');
      stockCountResource.store(param)
        .then(response => {
          app.stock_counts = response.stock_counts;
          app.load = false;
        })
        .catch(error => {
          app.load = false;
          console.log(error.message);
        });
    },
    convertQuantity(row, event) {
      const app = this;
      const quantity = event.target.value;
      const conversion = app.showItemsInCartons(quantity, row.item.quantity_per_carton, row.item.package_type);
      document.getElementById(row.id).innerHTML = conversion;
    },
    countStock(id, event) {
      const quantity = event.target.value;
      if (quantity !== null && quantity !== 'null') {
        const param = { count_quantity: quantity };
        const stockCountResource = new Resource('stock/count/save-count');
        stockCountResource.update(id, param)
          .then(response => {
          // app.stock_counts = response.stock_counts;
          // app.load = false;
          })
          .catch(error => {
          // app.load = false;
            console.log(error.message);
          });
      }
    },
  },
};
</script>
