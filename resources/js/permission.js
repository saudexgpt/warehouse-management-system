import router from './router';
import store from './store';
import { Message } from 'element-ui';
import NProgress from 'nprogress'; // progress bar
import 'nprogress/nprogress.css'; // progress bar style
import { getToken } from '@/utils/auth'; // get token from cookie
import getPageTitle from '@/utils/get-page-title';

NProgress.configure({ showSpinner: false }); // NProgress Configuration

const whiteList = ['/login', '/auth-redirect']; // no redirect whitelist

router.beforeEach(async(to, from, next) => {
  // start progress bar
  NProgress.start();
  // set page title
  document.title = getPageTitle(to.meta.title);

  // determine whether the user has logged in
  const hasToken = getToken();
  if (hasToken) {
    if (to.path !== '/default-password/change') {
      if (store.getters.pStatus !== 'default') {
        if (to.path === '/login') {
          // if is logged in, redirect to the home page
          next({ path: '/' });
          NProgress.done();
        } else {
          // check whether password status is default and redirect user to change their password

          // determine whether the user has obtained his permission roles through getInfo
          const hasRoles = store.getters.roles && store.getters.roles.length > 0;
          if (hasRoles) {
            next();
          } else {
            try {
              store.dispatch('customer/fetch');
              store.dispatch('app/setNecessaryParams');
              // get user info
              // note: roles must be a object array! such as: ['admin'] or ,['manager','editor']
              const { roles, permissions } = await store.dispatch('user/getInfo');

              // generate accessible routes map based on roles
              // const accessRoutes = await store.dispatch('permission/generateRoutes', roles, permissions);
              store.dispatch('permission/generateRoutes', { roles, permissions }).then(response => {
                // dynamically add accessible routes
                router.addRoutes(response);

                // hack method to ensure that addRoutes is complete
                // set the replace: true, so the navigation will not leave a history record
                next({ ...to, replace: true });
              });
            } catch (error) {
              // remove token and go to login page to re-login
              await store.dispatch('user/resetToken');
              Message.error(error || 'Has Error');
              next(`/login`);
              NProgress.done();
            }
          }
        }
      } else {
        next('/default-password');
        NProgress.done();
      }
    } else {
      next();
      NProgress.done();
    }
  } else {
    /* has no token*/

    if (whiteList.indexOf(to.matched[0] ? to.matched[0].path : '') !== -1) {
      // in the free login whitelist, go directly
      next();
    } else {
      // other pages that do not have permission to access are redirected to the login page.
      next(`/login`);
      NProgress.done();
    }
  }
});

router.afterEach(() => {
  // finish progress bar
  NProgress.done();
});
