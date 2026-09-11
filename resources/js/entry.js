// Build stamp: lets a stale tab be spotted from the console.
window.__nmwBuild = __NMW_BUILD__;

Nova.booting(() => {
  Nova.inertia('NovaMenuWeights', require('./views/MenuOrder').default);
});
