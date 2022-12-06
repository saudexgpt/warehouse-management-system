
import Resource from '@/api/resource';

const state = {
  customers: [],
  customer_types: [],
};

const mutations = {
  SET_CUSTOMERS: (state, customers) => {
    state.customers = customers;
  },
  SET_CUSTOMER_TYPES: (state, customer_types) => {
    state.customer_types = customer_types;
  },
};

const actions = {

  fetch({ commit }) {
    const getCustomers = new Resource('fetch-customers');
    return new Promise((resolve, reject) => {
      getCustomers.list().then((response) => {
        commit('SET_CUSTOMERS', response.customers);
        commit('SET_CUSTOMER_TYPES', response.customer_types);
        resolve();
      })
        .catch(error => {
          reject(error);
        });
    });
  },
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
};
