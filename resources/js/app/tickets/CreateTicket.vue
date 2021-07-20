<template>
  <el-dialog :title="issueDetails.title" :visible.sync="ticketFormVisible" :before-close="closeModal">
    <div class="form-container">
      <aside>
        <el-row :gutter="5" class="padded">
          <el-col :xs="24" :sm="12" :md="12">
            <label for="">Old Quantity Stocked: </label>
            <el-input
              v-model="form.quantity"
              placeholder="Quantity"
              class="span"
              readonly
            />
            <label for="">New Quantity</label>
            <el-input
              v-model="form.new_quantity"
              placeholder="Quantity"
              class="span"
            />
          </el-col>
          <el-col :xs="24" :sm="12" :md="12">
            <label for="">Description</label>
            <el-input
              v-model="form.details"
              placeholder="Enter Details Here..."
              class="span"
              type="textarea"
              readonly
            />
          </el-col>
        </el-row>
        <el-row :gutter="2" class="padded">
          <el-col :xs="24" :sm="6" :md="6">
            <el-button
              type="success"
              @click="editProduct"
            ><svg-icon icon-class="edit" />
              Update
            </el-button>
          </el-col>
        </el-row>
      </aside>
    </div>
  </el-dialog>
</template>
<script>
import moment from 'moment';
export default {
  props: {
    ticketFormVisible: {
      type: Boolean,
      default: () => (false),
    },
    issueDetails: {
      type: Object,
      default: () => ({}),
    },
  },
  data() {
    return {
      form: {
        new_quantity: '',
        quantity: '',
        expiry_date: '',
        batch_no: '',
      },
    };
  },
  mounted() {
    this.form = this.issueDetails.details;
  },
  methods: {
    moment,
    closeModal(){
      this.$emit('close', false);
    },
    editProduct(){

    },
  },
};
</script>
