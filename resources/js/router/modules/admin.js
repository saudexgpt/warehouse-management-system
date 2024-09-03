/** When your routing table is too long, you can split it into small modules**/
import Layout from '@/layout';

const adminRoutes = {
  path: '/administrator',
  component: Layout,
  redirect: '/administrator/users',
  name: 'Administrator',
  alwaysShow: true,
  meta: {
    title: 'administrator',
    icon: 'el-icon-setting',
    roles: ['admin', 'assistant admin'],
  },
  children: [
    /** User managements */
    {
      path: 'tickets',
      component: () => import('@/app/tickets'),
      name: 'Tickets',
      // meta: { title: 'userProfile', noCache: true, permissions: ['manage user'] },
      meta: { title: 'Issue Tickets', icon: 'el-icon-document', roles: ['admin', 'assistant admin'] },
    },
    {
      path: 'users/edit/:id(\\d+)',
      component: () => import('@/app/users/UserProfile'),
      name: 'UserProfile',
      meta: { title: 'userProfile', noCache: true, permissions: ['manage user'] },
      // meta: { title: 'userProfile', noCache: true, roles: ['admin'] },
      hidden: true,
    },
    {
      path: 'customers',
      component: () => import('@/app/users/Customer'),
      name: 'CustomerList',
      meta: { title: 'Customers', icon: 'el-icon-user', permissions: ['manage user'] },
    },
    {
      path: 'users',
      component: () => import('@/app/users/List'),
      name: 'UserList',
      meta: { title: 'users', icon: 'el-icon-user', permissions: ['manage user'] },
      // meta: { title: 'users', icon: 'el-icon-user', roles: ['admin'] },
    },
    /** Role and permission */
    {
      path: 'roles',
      component: () => import('@/app/role-permission/List'),
      name: 'RoleList',
      meta: { title: 'rolePermission', icon: 'el-icon-s-check', permissions: ['manage permission'] },
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

export default adminRoutes;
