import Layout from '@/layout';

const permissionRoutes = {
  path: '/stock-count',
  component: Layout,
  redirect: 'noredirect',
  alwaysShow: true, // will always show the root menu
  meta: {
    title: 'Stock Count',
    icon: 'el-icon-s-operation',
    // permissions: ['view-menu-warehouse'],
    permissions: ['view stock count', 'can count stocks'],
  },
  children: [

    {
      path: 'view',
      component: () => import('@/app/stock/count-stock/index'),
      name: 'ViewStockCount',
      meta: {
        title: 'View',
        permissions: ['view stock count'],
      },
    },
    {
      path: 'new-count',
      component: () => import('@/app/stock/count-stock/NewCount'),
      name: 'NewCount',
      meta: {
        title: 'New Count',
        permissions: ['can count stocks'],
      },
    },

  ],
};

export default permissionRoutes;
