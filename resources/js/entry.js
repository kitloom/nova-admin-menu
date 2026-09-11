// Build stamp: lets a stale tab be spotted from the console.
window.__adminMenuBuild = __ADMIN_MENU_BUILD__;

Nova.booting(() => {
  Nova.inertia('NovaAdminMenu', require('./views/MenuOrder').default);
});
