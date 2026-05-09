(function () {
  var body = document.body;

  function setSidebarState(collapsed) {
    if (collapsed) {
      body.classList.add('sidebar-collapsed');
      localStorage.setItem('sidebarCollapsed', '1');
    } else {
      body.classList.remove('sidebar-collapsed');
      localStorage.setItem('sidebarCollapsed', '0');
    }
  }

  window.toggleSidebar = function () {
    setSidebarState(!body.classList.contains('sidebar-collapsed'));
  };

  var savedSidebar = localStorage.getItem('sidebarCollapsed');
  if (savedSidebar === '1') {
    setSidebarState(true);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.collapsible-panel').forEach(function (panel) {
      var toggleButton = panel.querySelector('.toggle-panel');
      if (!toggleButton) return;

      function setCollapsed(collapsed) {
        panel.dataset.collapsed = collapsed ? 'true' : 'false';
        if (collapsed) {
          toggleButton.innerHTML = '<i class="fa-solid fa-chevron-down"></i><span>Show</span>';
        } else {
          toggleButton.innerHTML = '<i class="fa-solid fa-chevron-up"></i><span>Hide</span>';
        }
      }

      toggleButton.addEventListener('click', function () {
        var collapsed = panel.dataset.collapsed === 'true';
        setCollapsed(!collapsed);
      });

      setCollapsed(panel.dataset.collapsed === 'true');
    });
  });
})();
