(function () {
  var state = { filters: {}, sort: {} };

  function text(v) { return (v === null || v === undefined) ? '' : String(v); }

  function sortTable(table, colIndex) {
    var tbody = table.tBodies[0];
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var key = table.getAttribute('id') + ':' + colIndex;
    state.sort[key] = state.sort[key] === 'asc' ? 'desc' : 'asc';
    var dir = state.sort[key];

    rows.sort(function (a, b) {
      var va = text(a.cells[colIndex] ? a.cells[colIndex].dataset.raw || a.cells[colIndex].textContent : '').trim();
      var vb = text(b.cells[colIndex] ? b.cells[colIndex].dataset.raw || b.cells[colIndex].textContent : '').trim();
      var na = Number(va), nb = Number(vb);
      if (!isNaN(na) && !isNaN(nb)) return dir === 'asc' ? na - nb : nb - na;
      return dir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
    });

    rows.forEach(function (r) { tbody.appendChild(r); });
  }

  function closeAllMenus() {
    document.querySelectorAll('.filter-menu').forEach(function (m) { m.remove(); });
  }

  function applyFilters(table) {
    var tbody = table.tBodies[0];
    Array.from(tbody.querySelectorAll('tr')).forEach(function (row) {
      var show = true;
      Object.keys(state.filters).forEach(function (k) {
        var parts = k.split(':');
        if (parts[0] !== table.id) return;
        var col = Number(parts[1]);
        var allow = state.filters[k];
        if (!allow || allow.size === 0) return;
        var val = text(row.cells[col] ? row.cells[col].dataset.raw || row.cells[col].textContent : '').trim();
        if (!allow.has(val)) show = false;
      });
      row.style.display = show ? '' : 'none';
    });
  }

  function openFilterMenu(table, th, colIndex) {
    closeAllMenus();
    var key = table.id + ':' + colIndex;
    var values = new Set();
    Array.from(table.tBodies[0].querySelectorAll('tr')).forEach(function (row) {
      var val = text(row.cells[colIndex] ? row.cells[colIndex].dataset.raw || row.cells[colIndex].textContent : '').trim();
      values.add(val);
    });

    var menu = document.createElement('div');
    menu.className = 'filter-menu';
    var selected = state.filters[key] || new Set();
    values.forEach(function (v) {
      var line = document.createElement('label');
      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.checked = selected.size === 0 ? true : selected.has(v);
      cb.addEventListener('change', function () {
        if (!state.filters[key]) state.filters[key] = new Set(values);
        if (cb.checked) state.filters[key].add(v);
        else state.filters[key].delete(v);
      });
      line.appendChild(cb);
      line.appendChild(document.createTextNode(v || '(blank)'));
      menu.appendChild(line);
    });

    var apply = document.createElement('button');
    apply.className = 'btn btn-primary';
    apply.textContent = 'Apply';
    apply.addEventListener('click', function () { applyFilters(table); closeAllMenus(); });
    menu.appendChild(apply);

    th.appendChild(menu);
  }

  function enableInlineEditing(scope) {
    scope.querySelectorAll('[data-editable="1"]').forEach(function (td) {
      td.addEventListener('dblclick', function () {
        if (td.querySelector('input')) return;
        var old = td.textContent;
        var input = document.createElement('input');
        input.type = 'text';
        input.value = old;
        input.style.width = '100%';
        td.innerHTML = '';
        td.appendChild(input);
        input.focus();
        function commit() {
          td.dataset.raw = input.value;
          td.textContent = input.value;
        }
        input.addEventListener('blur', commit);
        input.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') input.blur();
          if (e.key === 'Escape') { td.textContent = old; }
        });
      });
    });
  }

  function setupFindReplace() {
    var modal = document.getElementById('findModal');
    var findInput = document.getElementById('findValue');
    var replaceInput = document.getElementById('replaceValue');
    var doReplace = document.getElementById('doReplace');
    var closeBtn = document.getElementById('closeFindModal');
    if (!modal || !findInput || !replaceInput || !doReplace || !closeBtn) return;

    document.addEventListener('keydown', function (e) {
      if (e.ctrlKey && (e.key === 'h' || e.key === 'H')) {
        e.preventDefault();
        modal.style.display = 'flex';
        findInput.focus();
      }
    });

    closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
    doReplace.addEventListener('click', function () {
      var find = findInput.value;
      var rep = replaceInput.value;
      if (!find) return;
      document.querySelectorAll('td[data-editable="1"]').forEach(function (td) {
        if (td.textContent.includes(find)) {
          var next = td.textContent.split(find).join(rep);
          td.textContent = next;
          td.dataset.raw = next;
        }
      });
    });
  }

  document.addEventListener('click', function () { closeAllMenus(); });

  document.querySelectorAll('table[data-interactive="1"]').forEach(function (table) {
    if (!table.id) table.id = 'tbl_' + Math.random().toString(36).slice(2, 8);
    table.querySelectorAll('thead th').forEach(function (th, idx) {
      th.classList.add('sortable');
      th.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('filter-trigger')) return;
        sortTable(table, idx);
      });

      var btn = document.createElement('button');
      btn.className = 'filter-trigger';
      btn.innerHTML = '<i class="fa-solid fa-filter"></i>';
      btn.type = 'button';
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        openFilterMenu(table, th, idx);
      });
      th.appendChild(btn);
    });
    enableInlineEditing(table);
  });

  setupFindReplace();

  window.sortFirstTable = function () {
    var t = document.querySelector('table[data-interactive="1"]');
    if (t) sortTable(t, 0);
  };

  window.toggleAllFilters = function () {
    var t = document.querySelector('table[data-interactive="1"]');
    if (!t) return;
    var th = t.querySelector('thead th');
    if (th) openFilterMenu(t, th, 0);
  };
})();
