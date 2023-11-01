<template>
  <div class="box">
    <div class="box-header">
      <h4 class="box-title">Add Product to Stock</h4>
      <span class="pull-right">
        <a class="btn btn-danger" @click="page.option = 'list'"> Back</a>
        <a class="btn btn-success" @click="bulkUpload = true"> Bulk Upload</a>
      </span>
    </div>
    <div class="box-body">
      <div v-if="bulkUpload">
        <a class="btn btn-default" @click="bulkUpload = false"> Cancel</a>
        <bulk-upload :items-in-stock="itemsInStock" :params="params" :bulk-upload="bulkUpload" :page="page" />
      </div>
      <div v-else>
        <aside>
          <el-form ref="form" :model="form" label-width="120px">
            <el-row :gutter="5" class="padded">
              <el-col :xs="24" :sm="12" :md="12">
                <label for="">Select Warehouse</label>
                <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" filterable class="span">
                  <el-option v-for="(warehouse, index) in params.warehouses" :key="index" :value="warehouse.id" :label="warehouse.name" :disabled="warehouse.id === 7" />

                </el-select>

                <!-- <label for="">Select Product</label>
                <el-select v-model="form.item_id" placeholder="Select Product" filterable class="span">
                  <el-option v-for="(item, index) in params.items" :key="index" :value="item.id" :label="item.name" />

                </el-select> -->
              </el-col>
              <!-- <el-col :xs="24" :sm="12" :md="12">
                <label for="">Batch No.</label>
                <el-input v-model="form.batch_no" placeholder="Batch No." class="span" />
                <label for="">Goods Received Number (GRN)</label>
                <el-input v-model="form.goods_received_note" placeholder="GRN" class="span" />

              </el-col> -->
            </el-row>
          </el-form>
        </aside>
        <el-row v-if="form.warehouse_id !== ''" :gutter="2" class="padded">
          <el-col>
            <div style="overflow: auto">
              <label for="">Sub Batches</label>
              <table class="table table-binvoiceed">
                <thead>
                  <tr>
                    <th />
                    <th>Product</th>
                    <th>Batch No.</th>
                    <th>GRN</th>
                    <th>Quantity</th>
                    <th>Expiry Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(sub_batch, index) in sub_batches" :key="index">
                    <td>
                      <span>
                        <a v-if="sub_batches.length > 1" class="btn btn-danger btn-flat fa fa-trash" @click="removeLine(index)" />
                        <a class="btn btn-info btn-flat fa fa-plus" @click="addLine()" />
                      </span>
                    </td>
                    <td>
                      <el-select
                        v-model="sub_batch.item"
                        value-key="id"
                        placeholder="Select Product"
                        filterable
                        class="span"
                        @input="fetchItemDetails(index)"
                      >
                        <el-option
                          v-for="(item, item_index) in params.items"
                          :key="item_index"
                          :value="item"
                          :label="item.name"
                        />

                      </el-select>
                    </td>
                    <td>
                      <el-input v-model="sub_batch.batch_no" type="text" outline placeholder="Batch No." />
                    </td>
                    <td>
                      <el-input v-model="sub_batch.goods_received_note" type="text" outline placeholder="GRN" />
                    </td>
                    <td>
                      <el-input v-model="sub_batch.quantity" type="number" outline placeholder="Quantity" min="1">
                        <span slot="append">{{ sub_batch.type }}</span>
                      </el-input>
                      <br><code v-html="showItemsInCartons(sub_batch.quantity, sub_batch.quantity_per_carton, sub_batch.type)" />
                    </td>
                    <td>
                      <el-date-picker v-model="sub_batch.expiry_date" type="date" outline format="yyyy/MM/dd" value-format="yyyy-MM-dd" />
                    </td>
                  </tr>
                  <tr v-if="fill_fields_error">
                    <td colspan="6"><label class="label label-danger">Please fill all empty fields before adding another row</label></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </el-col>
          <el-col :xs="24" :sm="24" :md="24">
            <el-button type="success" @click="addProductToStock"><i class="el-icon-upload" />
              Submit
            </el-button>
          </el-col>
        </el-row>
      </div>
    </div>
  </div>
</template>

<script>
import moment from 'moment';
import Resource from '@/api/resource';
import showItemsInCartons from '@/utils/functions';
import BulkUpload from './BulkUpload';
const createProduct = new Resource('stock/items-in-stock/store');

export default {
  name: 'AddNewProduct',
  components: { BulkUpload },
  props: {
    params: {
      type: Object,
      default: () => ({}),
    },
    itemsInStock: {
      type: Array,
      default: () => ([]),
    },

    page: {
      type: Object,
      default: () => ({
        option: 'add_new',
      }),
    },

  },
  data() {
    return {
      bulkUpload: false,
      fill_fields_error: false,
      form: {
        warehouse_id: '',
        item_id: '',
        quantity: '',
        goods_received_note: '',
        batch_no: '',
        sub_batches: [
          {
            quantity: '',
            batch_no: '',
            item_id: '',
            expiry_date: '',
            goods_received_note: null,
          },
        ],

      },
      empty_form: {
        warehouse_id: '',
        item_id: '',
        quantity: '',
        goods_received_note: '',
        batch_no: '',
        sub_batches: [
          {
            quantity: '',
            batch_no: '',
            item_id: '',
            expiry_date: '',
            goods_received_note: null,
          },
        ],

      },
      sub_batches: [],

    };
  },
  watch: {
    sub_batches() {
      this.blockRemoval = this.sub_batches.length <= 1;
    },

  },
  mounted() {
    this.addLine();
  },
  methods: {
    moment,
    showItemsInCartons,
    fetchItemDetails(index) {
      const app = this;
      const item = app.sub_batches[index].item;
      app.sub_batches[index].item_id = item.id;
      app.sub_batches[index].type = item.package_type;
      app.sub_batches[index].quantity_per_carton = item.quantity_per_carton;
    },
    isRowEmpty() {
      const checkEmptyLines = this.sub_batches.filter(detail => detail.quantity === '' || detail.batch_no === '' || detail.expiry_date === '' || detail.item_id === '' || detail.goods_received_note === '');

      if (checkEmptyLines.length) {
        return true;
      }
      return false;
    },
    addLine() {
      this.fill_fields_error = false;
      if (this.isRowEmpty()) {
        this.fill_fields_error = true;
        return;
      } else {
        this.sub_batches.push({
          item: null,
          item_id: '',
          quantity: '',
          batch_no: '',
          expiry_date: '',
          goods_received_note: '',
        });
      }
    },
    removeLine(detailId) {
      this.fill_fields_error = false;
      if (!this.blockRemoval) {
        this.sub_batches.splice(detailId, 1);
      }
    },
    addProductToStock() {
      const app = this;
      if (this.isRowEmpty()) {
        app.$alert('Please fill all empty fields before you submit');
        return;
      }
      const load = createProduct.loaderShow();
      var form = app.form;
      // form.expiry_date = app.moment(form.expiry_date).format('LLL');
      form.sub_batches = app.sub_batches;
      createProduct.store(form)
        .then(response => {
          app.form = app.empty_form;
          app.sub_batches = [{
            quantity: '',
            batch_no: '',
            expiry_date: '',
            goods_received_note: null,
          }];
          app.$message({ message: 'New Product Added Successfully!!!', type: 'success' });
          app.itemsInStock.push(response.item_in_stock);
          app.$emit('update', response);
          load.hide();
        })
        .catch(error => {
          load.hide();
          alert(error.message);
        });
    },

  },
};
</script>

