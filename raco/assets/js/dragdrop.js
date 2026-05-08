(function(){
  if (!window.RACO_DATASET_ROWS) window.RACO_DATASET_ROWS = [];
  var widgetCounter = 0;
  window.widgetCharts = window.widgetCharts || {};

  function makePaletteDraggable() {
    var items = document.querySelectorAll('.palette-item');
    items.forEach(function(it){
      it.addEventListener('dragstart', function(e){
        e.dataTransfer.setData('text/plain', it.getAttribute('data-type'));
      });
    });
  }

  function initCanvas() {
    var canvas = document.getElementById('vizCanvas');
    if (!canvas) return;
    canvas.addEventListener('dragover', function(e){ e.preventDefault(); canvas.classList.add('droppable'); });
    canvas.addEventListener('dragleave', function(e){ canvas.classList.remove('droppable'); });
    canvas.addEventListener('drop', function(e){
      e.preventDefault();
      canvas.classList.remove('droppable');
      var type = e.dataTransfer.getData('text/plain') || 'bar';
      var rect = canvas.getBoundingClientRect();
      var x = e.clientX - rect.left;
      var y = e.clientY - rect.top;
      createWidget(type, x, y);
    });
  }

  function createWidget(type, x, y) {
    widgetCounter++;
    var id = 'widget_' + widgetCounter;
    var container = document.createElement('div');
    container.className = 'viz-widget';
    container.style.left = (x) + 'px';
    container.style.top = (y) + 'px';
    container.setAttribute('data-id', id);
    container.innerHTML = '\n      <div class="viz-widget-toolbar">\n        <select class="widget-type">\n          <option value="bar">Bar</option>\n          <option value="line">Line</option>\n          <option value="pie">Pie</option>\n          <option value="scatter">Scatter</option>\n          <option value="histogram">Histogram</option>\n        </select>\n        <button class="widget-refresh btn">Update</button>\n        <button class="widget-remove btn">✕</button>\n      </div>\n      <div class="viz-widget-body">\n        <div style="position:relative;width:100%;height:240px;"><canvas id=""></canvas></div>\n      </div>';

    var canvasEl = container.querySelector('canvas');
    canvasEl.id = id + '_canvas';
    canvasEl.style.width = '100%';
    canvasEl.style.height = '100%';
    var typeSelect = container.querySelector('.widget-type');
    typeSelect.value = type;

    container.querySelector('.widget-remove').addEventListener('click', function(){
      destroyWidget(id);
    });
    container.querySelector('.widget-refresh').addEventListener('click', function(){
      renderWidget(id);
    });
    container.querySelector('.widget-type').addEventListener('change', function(){
      renderWidget(id);
    });

    // drag to move
    var dragging = false, offset = {x:0,y:0};
    var toolbar = container.querySelector('.viz-widget-toolbar');
    toolbar.addEventListener('mousedown', function(e){
      dragging = true;
      offset.x = e.clientX - container.getBoundingClientRect().left;
      offset.y = e.clientY - container.getBoundingClientRect().top;
      container.classList.add('dragging');
      e.preventDefault();
    });
    document.addEventListener('mousemove', function(e){
      if (!dragging) return;
      var parent = document.getElementById('vizCanvas').getBoundingClientRect();
      var nx = e.clientX - parent.left - offset.x;
      var ny = e.clientY - parent.top - offset.y;
      container.style.left = nx + 'px';
      container.style.top = ny + 'px';
    });
    document.addEventListener('mouseup', function(){ if (dragging) { dragging=false; container.classList.remove('dragging'); } });

    document.getElementById('vizCanvas').appendChild(container);
    renderWidget(id);
  }

  function destroyWidget(id) {
    var el = document.querySelector('[data-id="'+id+'"]');
    if (!el) return;
    if (window.widgetCharts && window.widgetCharts[id]) {
      try { window.widgetCharts[id].destroy(); } catch(e){}
      delete window.widgetCharts[id];
    }
    el.parentNode.removeChild(el);
  }

  function renderWidget(id) {
    var el = document.querySelector('[data-id="'+id+'"]');
    if (!el) return;
    var canvas = el.querySelector('canvas');
    var type = el.querySelector('.widget-type').value;
    var xKey = document.getElementById('xAxis') ? document.getElementById('xAxis').value : null;
    var yKey = document.getElementById('yAxis') ? document.getElementById('yAxis').value : null;
    
    if (!xKey || !yKey || !window.RACO_DATASET_ROWS || !window.RACO_DATASET_ROWS.length) {
      var ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#ccc';
      ctx.font = '12px Arial';
      ctx.fillText('Select X and Y axes', 10, canvas.height/2);
      return;
    }
    
    // Destroy old chart instance
    if (window.widgetCharts[id]) {
      try { 
        window.widgetCharts[id].destroy(); 
      } catch(e) {
        console.error('Error destroying chart:', e);
      }
      delete window.widgetCharts[id];
    }
    
    // Small delay to ensure DOM is ready
    setTimeout(function() {
      try {
        var chart = window.racoCreateChart(canvas, window.RACO_DATASET_ROWS, type, xKey, yKey);
        if (chart) {
          window.widgetCharts[id] = chart;
        } else {
          console.warn('Failed to create chart for widget', id);
        }
      } catch(e) {
        console.error('Error rendering chart:', e);
      }
    }, 50);
  }

  document.addEventListener('DOMContentLoaded', function(){
    makePaletteDraggable();
    initCanvas();
    
    // When X or Y axis changes, re-render all widgets
    var xAxisSel = document.getElementById('xAxis');
    var yAxisSel = document.getElementById('yAxis');
    if (xAxisSel) {
      xAxisSel.addEventListener('change', function(){
        document.querySelectorAll('[data-id]').forEach(function(el){
          var id = el.getAttribute('data-id');
          renderWidget(id);
        });
      });
    }
    if (yAxisSel) {
      yAxisSel.addEventListener('change', function(){
        document.querySelectorAll('[data-id]').forEach(function(el){
          var id = el.getAttribute('data-id');
          renderWidget(id);
        });
      });
    }
    
    // allow creating initial widget by clicking palette items
    var items = document.querySelectorAll('.palette-item');
    items.forEach(function(it){
      it.addEventListener('click', function(){
        var canvas = document.getElementById('vizCanvas');
        var rect = canvas.getBoundingClientRect();
        createWidget(it.getAttribute('data-type') || 'bar', 20 + Math.random()*100, 20 + Math.random()*80);
      });
    });
  });
})();
