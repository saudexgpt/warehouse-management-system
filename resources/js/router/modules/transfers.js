import Layout from '@/layout';

const permissionRoutes = {
  path: '/transfers',
  component: Layout,
  redirect: 'noredirect',
  alwaysShow: true, // will always show the root menu
  meta: {
    title: 'Transfers',
    icon: 'el-icon-s-promotion',
    permissions: ['view invoice', 'view waybill', 'manage waybill'],
    // roles: ['admin', 'stock officer'],
  },
  children: [

    {
      path: 'transfer-request',
      component: () => import('@/app/transfers/Request'),
      name: 'TransferRequest',
      meta: {
        title: 'Transfer Request',
        permissions: ['create invoice', 'view invoice'],
      },
    },
    // {
    //   path: 'details/:id',
    //   component: () => import('@/app/transfers/Details'),
    //   name: 'InvoiceDetails',
    //   meta: { title: 'Invoice Details', noCache: true, permissions: ['view invoice'] },
    //   hidden: true,
    // },
    {
      path: 'create-new',
      component: () => import('@/app/transfers/partials/CreateTransferRequest'),
      name: 'CreateTransferRequest',
      meta: {
        title: 'Create New',
        permissions: ['create invoice'],
      },
      hidden: true,
    },
    {
      path: 'waybill',
      component: () => import('@/app/transfers/Waybill'),
      name: 'TransferWaybills',
      meta: {
        title: 'Waybills',
        permissions: ['view waybill', 'manage waybill'],
      },
    },
    // {
    //   path: 'waybill-delivery-cost',
    //   component: () => import('@/app/transfers/WaybillDeliveryCost'),
    //   name: 'WaybillDeliveryCost',
    //   meta: {
    //     title: 'Delivery Cost',
    //     permissions: ['manage waybill cost'],
    //   },
    // },
    // {
    //   path: 'extra-delivery-cost',
    //   component: () => import('@/app/transfers/ExtraDeliveryCost'),
    //   name: 'ExtraDeliveryCost',
    //   meta: {
    //     title: 'Extra Delivery Cost',
    //     permissions: ['manage waybill cost'],
    //   },
    // },
    {
      path: 'generate-transfer-waybill',
      component: () => import('@/app/transfers/partials/GenerateWaybill'),
      name: 'GenerateTransferWaybill',
      meta: {
        title: 'Generate Waybill',
        permissions: ['generate waybill', 'manage waybill'],
      },
      hidden: true,
    },
  ],
};

export default permissionRoutes;
