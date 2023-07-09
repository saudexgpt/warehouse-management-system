<template>
  <div class="app-container">
    <new-count v-if="page.option== 'add_new'" :page="page" />
    <div v-if="page.option=='list'">
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
          <el-button type="primary" round @click="fetchStockCount()">Fetch</el-button>

        </el-col>
      </el-row>
      <br>

      <v-client-table v-model="stock_counts" v-loading="load" :columns="columns" :options="options">
        <div slot="stock_balance" slot-scope="{row}">
          {{ row.stock_balance }} {{ row.item.package_type }}
          <br>
          <code v-html="showItemsInCartons(row.stock_balance, row.item.quantity_per_carton, row.item.package_type)" />
        </div>
        <div slot="count_quantity" slot-scope="{row}">
          <div v-if="row.count_quantity !== null">

            {{ row.count_quantity }} {{ row.item.package_type }}
            <br>
            <code v-html="showItemsInCartons(row.count_quantity, row.item.quantity_per_carton, row.item.package_type)" />
          </div>
          <div v-else>
            No count
          </div>
        </div>
        <div slot="difference" slot-scope="{row}">
          <div v-if="row.count_quantity !== null">

            {{ parseInt(row.count_quantity - row.stock_balance) }} {{ row.item.package_type }}
            <br>
            <code v-html="showItemsInCartons(parseInt(Math.abs(row.count_quantity - row.stock_balance)), row.item.quantity_per_carton, row.item.package_type)" />
          </div>
          <div v-else>
            No count
          </div>
        </div>
        <div slot="created_at" slot-scope="{row}">
          {{ moment(row.created_at).fromNow() }}
        </div>
        <div slot="action" slot-scope="props">
          <div v-if="props.row.is_warehouse_transfered === 0">
            <a v-if="checkPermission(['manage item stocks', 'update item stocks'])" class="btn btn-primary" @click="itemInStock=props.row; selected_row_index=props.index; page.option = 'edit_item'"><i class="fa fa-edit" /> </a>
          </div>

        </div>
      </v-client-table>
    </div>
  </div>
</template>
<script>
import moment from 'moment';
import { parseTime } from '@/utils';
import checkPermission from '@/utils/permission';
import showItemsInCartons from '@/utils/functions';
import checkRole from '@/utils/role';

import NewCount from './NewCount';
import Resource from '@/api/resource';
export default {
  name: 'ViewStockCount',
  components: { NewCount },
  data() {
    return {
      pickerOptions: {
        disabledDate(date) {
          var d = new Date(); // today
          d.setDate(d.getDate()); // one year from now
          return date > d;
        },
      },
      stock_counts: [],
      columns: ['action', 'item.name', 'stock_balance', 'count_quantity', 'difference', 'date', /* 'batch_no', 'expiry_date', */'created_at', 'counter.name'],

      options: {
        headings: {
          'counter.name': 'Counted By',
          'item.name': 'Product',
          batch_no: 'Batch No.',
          stock_balance: 'System Balance',
          count_quantity: 'Count Quantity',
          created_at: 'Created',

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
        filterable: ['item.name', 'created_at'],
      },
      page: {
        option: 'list',
      },
      form: {
        warehouse_id: '',
        date: '',
      },
      load: false,

    };
  },
  computed: {
    params() {
      return this.$store.getters.params;
    },
  },
  created() {
    // this.getWarehouse();
    this.fetchNecessaryParams();
  },
  beforeDestroy() {

  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    showItemsInCartons,
    fetchNecessaryParams() {
      const app = this;
      app.$store.dispatch('app/setNecessaryParams');
    },
    fetchStockCount() {
      const app = this;
      app.load = true;

      const param = app.form;
      const stockCountResource = new Resource('stock/count');
      stockCountResource.list(param)
        .then(response => {
          app.stock_counts = response.stock_counts;
          app.load = false;
        })
        .catch(error => {
          app.load = false;
          console.log(error.message);
        });
    },
    handleDownload() {
      this.downloadLoading = true;
      import('@/vendor/Export2Excel').then(excel => {
        const multiHeader = [[this.table_title, '', '', '', '', '', '', '', '', '', '', '', '']];
        const tHeader = ['Confirmed By', 'Product', 'Batch No.', 'Expires', 'Quantity', 'In Transit', 'Supplied', 'Physical Stock', 'Reserved for Supply', 'Main Balance', 'Created', 'Stocked By'];
        const filterVal = ['confirmer.name', 'item.name', 'batch_no', 'expiry_date', 'quantity', 'in_transit', 'supplied', 'in_stock', 'reserved_for_supply', 'balance', 'created_at', 'stocker.name'];
        const list = this.stock_counts;
        const data = this.formatJson(filterVal, list);
        excel.export_json_to_excel({
          multiHeader,
          header: tHeader,
          data,
          filename: this.table_title,
          autoWidth: true,
          bookType: 'csv',
        });
        this.downloadLoading = false;
      });
    },
    formatJson(filterVal, jsonData) {
      return jsonData.map(v => filterVal.map(j => {
        if (j === 'confirmer.name') {
          if (v['confirmer'] != null) {
            return v['confirmer']['name'];
          }
        }
        if (j === 'item.name') {
          if (v['item'] != null) {
            return v['item']['name'];
          }
        }
        if (j === 'expiry_date') {
          return parseTime(v['expiry_date']);
        }
        if (j === 'created_at') {
          return parseTime(v['created_at']);
        }
        if (j === 'stocker.name') {
          if (v['stocker'] != null) {
            return v['stocker']['name'];
          }
        }
        if (j === 'quantity') {
          if (v['item'] != null) {
            return v['quantity'] + ' ' + v['item']['package_type'];
          } else {
            return v['quantity'];
          }
        }
        if (j === 'in_transit') {
          if (v['item'] != null) {
            return v['in_transit'] + ' ' + v['item']['package_type'];
          } else {
            return v['in_transit'];
          }
        }
        if (j === 'reserved_for_supply') {
          if (v['item'] != null) {
            return v['reserved_for_supply'] + ' ' + v['item']['package_type'];
          } else {
            return v['reserved_for_supply'];
          }
        }
        if (j === 'supplied') {
          if (v['item'] != null) {
            return v['supplied'] + ' ' + v['item']['package_type'];
          } else {
            return v['supplied'];
          }
        }
        if (j === 'in_stock') {
          if (v['item'] != null) {
            return v['balance'] + ' ' + v['item']['package_type'];
          } else {
            return v['balance'];
          }
        }
        if (j === 'balance') {
          if (v['item'] != null) {
            return (v['balance'] - v['reserved_for_supply']) + ' ' + v['item']['package_type'];
          } else {
            return v['balance'] - v['reserved_for_supply'];
          }
        }
        return v[j];
      }));
    },
  },
};
</script>
<style rel="stylesheet/scss" lang="scss" scoped>
.alert {
  padding: 5px;
  margin: -5px;
  text-align: right;
}
td {
  padding: 0px !important;
}
.alert-bg{
		color: #fff;
		padding: 10px;
		display: inline-block;
		border-radius: 5px;
    -webkit-animation: blinkingBackground 1s infinite;  /* Safari 4+ */
    -moz-animation: blinkingBackground 1s infinite;  /* Fx 5+ */
    -o-animation: blinkingBackground 1s infinite;  /* Opera 12+ */
    animation: blinkingBackground 1s infinite;  /* IE 10+, Fx 29+ */
	}
	@keyframes blinkingBackground{
		0%, 79%		{ background-color: #33d45b;}
		80%, 100%	{ background-color: #000000;}
	}
  .okay-bg{
		color: #fff;
		padding: 10px;
		display: inline-block;
		border-radius: 5px;
		background-color: #33d45b;
	}
  .expired-bg{
		color: #fff;
		padding: 10px;
		display: inline-block;
		border-radius: 5px;
		background-color: #000000;
	}
</style>
