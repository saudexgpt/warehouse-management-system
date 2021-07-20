<template>
  <div class="app-container">
    <div class="box">
      <div class="box-header">
        <h4 class="box-title">Issue Tickets</h4>
      </div>
      <div class="box-body">
        <v-client-table v-model="tickets" :columns="columns" :options="options">
          <div slot="action" slot-scope="props">
            <div v-if="props.row.status === 'pending'">
              <a title="Click to approve" class="btn btn-primary" @click="approveRequest(props.row.id);">Approve</a>
              <a title="Click to reject" class="btn btn-danger" @click="rejectRequest(props.index, props.row.id);">Reject</a>
            </div>
          </div>
        </v-client-table>

      </div>

    </div>

  </div>
</template>
<script>
import Resource from '@/api/resource';
const ticketsResource = new Resource('ticket');
const approveTicketsResource = new Resource('ticket/approve-ticket');
const rejectTicketsResource = new Resource('ticket/delete-ticket');
export default {
  name: 'Tickets',
  data() {
    return {
      tickets: [],
      columns: ['action', 'ticket_no', 'title', 'details', 'raised_by.name', 'status'],

      options: {
        headings: {
          'raised_by.name': 'Request by',
          // id: 'S/N',
        },
        // editableColumns:['name', 'category.name', 'sku'],
        sortable: ['ticket_no', 'raised_by.name', 'status'],
        filterable: ['ticket_no', 'details', 'raised_by.name', 'status'],
      },
      page: {
        option: 'list',
      },
      item: {

      },
      params: [],
      selected_row_index: '',
      downloadLoading: false,

    };
  },

  mounted() {
    this.fetchTickets();
  },
  beforeDestroy() {

  },
  methods: {
    fetchTickets() {
      const app = this;
      ticketsResource.list()
        .then(response => {
          app.tickets = response.tickets;
        });
    },
    approveRequest(request){
      if (confirm('Are you sure you want to approve this request? If the quanity to be updated to is less than the quanity transacted on, it will cause an imbalance. However, Click OK to continue')) {
        const param = { request_id: request };
        approveTicketsResource.store(param)
          .then((response) => {
            this.tickets = response.tickets;
            this.$message({
              message: 'Request was approved successfully.',
              type: 'success',
            });
          });
      }
    },
    rejectRequest(index, request){
      if (confirm('Are you sure you want to reject this request? It will be discarded permanently.')) {
        rejectTicketsResource.destroy(request)
          .then(() => {
            this.tickets.splice(index - 1, 1);
            this.$message({
              message: 'Request was rejected and discarded successfully.',
              type: 'success',
            });
          });
      }
    },
  },
};
</script>
