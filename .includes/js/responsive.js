document.addEventListener('DOMContentLoaded', function () {

  /* ==========================================================================
     Nav toggle (mobile off-canvas) + sidebar collapse (desktop)

     Theming is NOT handled here. EvoDir's theme-selector module
     (modules/theme-selector/module.php + module.js) already owns
     #themeToggle — it scans themes/ server-side, reads each
     theme's identity from its own CSS custom properties, and builds the
     picker modal from that. A second script claiming the same button
     would just fight it for the click listener.
     ========================================================================== */
  const appShell = document.getElementById('appShell');
  const appSidebar = document.getElementById('appSidebar');
  const navToggle = document.getElementById('navToggle');
  const navBackdrop = document.getElementById('navBackdrop');
  const sidebarToggle = document.getElementById('sidebarToggle');

  // Guarded independently — a page missing one of these shouldn't throw
  // and take the other control down with it.
  if (navToggle && appSidebar && navBackdrop) {
    function toggleMobileMenu() {
      const isOpen = appSidebar.classList.toggle('is-open');
      navBackdrop.classList.toggle('is-open', isOpen);
      navToggle.setAttribute('aria-expanded', String(isOpen));
    }
    navToggle.addEventListener('click', toggleMobileMenu);
    navBackdrop.addEventListener('click', toggleMobileMenu);
  }

  // Desktop collapse control lives in the header (not inside the sidebar
  // it controls), so it stays reachable after the sidebar collapses.
  if (sidebarToggle && appShell) {
    sidebarToggle.addEventListener('click', () => {
      const collapsed = appShell.classList.toggle('sidebar-collapsed');
      sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
    });
  }

});
