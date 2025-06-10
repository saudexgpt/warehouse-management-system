import { get, set } from 'idb-keyval';
import store from '@/store';
function saveReturns(returns) {
  set('unsaved_returns', (JSON.stringify(returns)))
    .then().catch((err) => console.log('Cannots save offline returns!', err));
}
function loadOfflineUnsavedReturns() {
  get('unsaved_returns').then((value) => {
    if (value) {
      const unsavedReturns = JSON.parse(value);

      store.dispatch('returns/setUnsavedReturns', unsavedReturns);
    }
  });
}

const state = {
  unsavedReturns: {
    warehouse_id: '',
    customer_ids: [],
    returns_number: '',
    status: 'pending',
    returns_date: '',
    subtotal: 0,
    discount: 0,
    amount: 0,
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

};
const mutations = {
  SET_RETURNS(state, value) {
    state.unsavedReturns = value;
  },
};
const actions = {

  saveUnsavedReturns({ commit }, returns) {
    saveReturns(returns);
  },
  setUnsavedReturns({ commit }, returns) {
    commit('SET_RETURNS', returns);
  },

  loadOfflineReturns() {
    return new Promise((resolve) => {
      loadOfflineUnsavedReturns();
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
