(function () {
  var zone = document.getElementById('uploadZone');
  var input = document.getElementById('datasetFile');
  var form = document.getElementById('uploadForm');
  var progressBar = document.getElementById('uploadProgressBar');
  var statusText = document.getElementById('uploadStatus');

  if (!zone || !input || !form) return;

  function setStatus(msg) {
    if (statusText) statusText.textContent = msg;
  }

  function validateFile(file) {
    if (!file) return 'Please choose a file.';
    var name = file.name.toLowerCase();
    if (!(name.endsWith('.csv') || name.endsWith('.xlsx'))) return 'Only CSV and XLSX files are allowed.';
    if (file.size > 20 * 1024 * 1024) return 'File is too large. Maximum size is 20MB.';
    return '';
  }

  zone.addEventListener('click', function () { input.click(); });
  zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('drag'); });
  zone.addEventListener('dragleave', function () { zone.classList.remove('drag'); });
  zone.addEventListener('drop', function (e) {
    e.preventDefault();
    zone.classList.remove('drag');
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      input.files = e.dataTransfer.files;
      setStatus(e.dataTransfer.files[0].name);
    }
  });

  input.addEventListener('change', function () {
    if (input.files[0]) setStatus(input.files[0].name);
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var file = input.files[0];
    var err = validateFile(file);
    if (err) {
      setStatus(err);
      return;
    }

    var data = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../actions/upload_handler.php', true);

    xhr.upload.onprogress = function (ev) {
      if (!ev.lengthComputable || !progressBar) return;
      var pct = Math.round((ev.loaded / ev.total) * 100);
      progressBar.style.width = pct + '%';
      setStatus('Uploading... ' + pct + '%');
    };

    xhr.onload = function () {
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.redirect) {
            window.location.href = response.redirect;
            return;
          }
          setStatus(response.message || 'Upload complete.');
        } catch (err) {
          window.location.href = '../pages/projects.php';
        }
      } else {
        setStatus('Upload failed. Please try again.');
      }
    };

    xhr.onerror = function () { setStatus('Network error while uploading.'); };
    xhr.send(data);
  });
})();
