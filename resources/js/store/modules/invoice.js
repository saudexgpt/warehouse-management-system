import { get, set } from 'idb-keyval';
import store from '@/store';
function saveInvoice(invoice) {
  set('unsaved_invoice', (JSON.stringify(invoice)))
    .then().catch((err) => console.log('Cannots save offline invoice!', err));
}
function loadOfflineUnsavedInvoice() {
  get('unsaved_invoice').then((value) => {
    if (value) {
      const unsavedInvoice = JSON.parse(value);

      store.dispatch('invoice/setUnsavedInvoice', unsavedInvoice);
    }
  });
}

const state = {
  unsavedInvoice: {
    warehouse_id: '',
    customer_ids: [],
    invoice_number: '',
    status: 'pending',
    invoice_date: '',
    subtotal: 0,
    discount: 0,
    amount: 0,
    notes: '',
    invoice_items: [
      {
        item: null,
        load: false,
        item_id: '',
        quantity: 0,
        type: '',
        item_rate: null,
        rate: null,
        is_promo: false,
        amount: 0,
        batches: [],
        batches_of_items_in_stock: [],
        total_stocked: null,
        total_invoiced_quantity: null,
      },
    ],
  },

};
const mutations = {
  SET_INVOICE(state, value) {
    state.unsavedInvoice = value;
  },
};
const actions = {

  saveUnsavedInvoice({ commit }, invoice) {
    saveInvoice(invoice);
  },
  setUnsavedInvoice({ commit }, invoice) {
    commit('SET_INVOICE', invoice);
  },

  loadOfflineInvoice() {
    return new Promise((resolve) => {
      loadOfflineUnsavedInvoice();
      setTimeout(function() {
        resolve('success');
      }, 2000);
    });
  },

};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
};
