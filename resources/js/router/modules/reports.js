import Layout from '@/layout';

const permissionRoutes = {
  path: '/reports',
  component: Layout,
  redirect: 'noredirect',
  alwaysShow: true, // will always show the root menu
  meta: {
    title: 'Reports',
    icon: 'el-icon-s-data',
  },
  children: [
    {
      path: 'bin-card',
      component: () => import('@/app/reports/BinCard'),
      name: 'BinCard',
      meta: {
        title: 'Bin Card',
        icon: 'el-icon-s-management',
        permissions: ['view reports'],
      },
    },
    {
      path: 'graphical-reports',
      component: () => import('@/app/reports/index'),
      name: 'GraphicalReports',
      meta: {
        title: 'Graphical',
        icon: 'el-icon-pie-chart',
        permissions: ['view reports'],
      },
    },
    {
      path: 'downloads',
      component: () => import('@/app/reports/downloads'),
      name: 'DownloadReports',
      meta: {
        title: 'Downloadables',
        icon: 'el-icon-download',
        permissions: ['view reports'],
      },
    },
    {
      path: 'audit-trails',
      component: () => import('@/app/reports/AuditTrail'),
      name: 'AuditTrail',
      meta: {
        title: 'Audit Trail',
        icon: 'el-icon-video-camera',
        permissions: ['view audit trail'],
      },
    },

    {
      path: 'backup',
      component: () => import('@/app/reports/BackUp'),
      name: 'BackUp',
      meta: {
        title: 'Back Up',
        icon: 'el-icon-download',
        permissions: ['backup database'],
      },
    },

  ],
};

export default permissionRoutes;
