(function () {
  var form = document.getElementById('cleanForm');
  if (!form) return;

  // Element references
  var removeDuplicates = document.getElementById('remove_duplicates');
  var trimWhitespace = document.getElementById('trim_whitespace');
  var normalizeSpaces = document.getElementById('normalize_spaces');
  var removeAccents = document.getElementById('remove_accents');
  var dateStandardize = document.getElementById('standardize_dates');
  var detectOutliers = document.getElementById('detect_outliers');
  var validateEmails = document.getElementById('validate_emails');
  var validateUrls = document.getElementById('validate_urls');
  var validatePhones = document.getElementById('validate_phones');

  // Show/hide outlier options
  if (detectOutliers) {
    detectOutliers.addEventListener('change', function() {
      var outlierOptions = form.querySelector('[name="outlier_method"]').parentElement.parentElement;
      outlierOptions.style.display = this.checked ? 'block' : 'none';
    });
    // Initialize on page load
    var outlierDiv = form.querySelector('[name="outlier_method"]').parentElement.parentElement;
    outlierDiv.style.display = detectOutliers.checked ? 'block' : 'none';
  }

  // Show/hide validation column inputs
  if (validateEmails) {
    validateEmails.addEventListener('change', function() {
      var emailInput = form.querySelector('[name="email_columns"]').parentElement;
      emailInput.style.display = this.checked ? 'block' : 'none';
    });
    form.querySelector('[name="email_columns"]').parentElement.style.display = validateEmails.checked ? 'block' : 'none';
  }

  if (validateUrls) {
    validateUrls.addEventListener('change', function() {
      var urlInput = form.querySelector('[name="url_columns"]').parentElement;
      urlInput.style.display = this.checked ? 'block' : 'none';
    });
    form.querySelector('[name="url_columns"]').parentElement.style.display = validateUrls.checked ? 'block' : 'none';
  }

  if (validatePhones) {
    validatePhones.addEventListener('change', function() {
      var phoneInput = form.querySelector('[name="phone_columns"]').parentElement;
      phoneInput.style.display = this.checked ? 'block' : 'none';
    });
    form.querySelector('[name="phone_columns"]').parentElement.style.display = validatePhones.checked ? 'block' : 'none';
  }

  // Utility functions
  function detectIssues() {
    var msgs = [];
    if (removeDuplicates && removeDuplicates.checked) msgs.push('✓ Duplicate rows will be removed.');
    if (trimWhitespace && trimWhitespace.checked) msgs.push('✓ Whitespace will be trimmed from all string columns.');
    if (normalizeSpaces && normalizeSpaces.checked) msgs.push('✓ Multiple spaces will be collapsed into one.');
    if (removeAccents && removeAccents.checked) msgs.push('✓ Accented characters will be converted.');
    if (dateStandardize && dateStandardize.checked) msgs.push('✓ Dates will be normalized to YYYY-MM-DD.');
    if (detectOutliers && detectOutliers.checked) msgs.push('✓ Outliers will be detected and removed.');
    alert(msgs.length ? 'Selected Operations:\n\n' + msgs.join('\n') : 'No global operations selected yet.');
  }

  function standardizeData() {
    if (trimWhitespace) trimWhitespace.checked = true;
    if (normalizeSpaces) normalizeSpaces.checked = true;
    if (dateStandardize) dateStandardize.checked = true;
    alert('Standard cleaning operations enabled:\n• Trim whitespace\n• Normalize spaces\n• Standardize dates');
  }

  function removeDuplicatesUI() {
    if (removeDuplicates) removeDuplicates.checked = true;
    alert('Duplicate removal enabled.');
  }

  function aggressiveClean() {
    if (trimWhitespace) trimWhitespace.checked = true;
    if (normalizeSpaces) normalizeSpaces.checked = true;
    if (removeAccents) removeAccents.checked = true;
    if (dateStandardize) dateStandardize.checked = true;
    if (removeDuplicates) removeDuplicates.checked = true;
    alert('Aggressive cleaning mode enabled:\n• All standard operations\n• Remove accents\n• Remove duplicates');
  }

  // Export functions to window
  window.detectIssues = detectIssues;
  window.standardizeData = standardizeData;
  window.removeDuplicatesUI = removeDuplicatesUI;
  window.aggressiveClean = aggressiveClean;

  // Form submission handler
  form.addEventListener('submit', function(e) {
    var hasOperations = false;
    
    // Check if any operation is selected
    if (removeDuplicates && removeDuplicates.checked) hasOperations = true;
    if (trimWhitespace && trimWhitespace.checked) hasOperations = true;
    if (normalizeSpaces && normalizeSpaces.checked) hasOperations = true;
    if (removeAccents && removeAccents.checked) hasOperations = true;
    if (dateStandardize && dateStandardize.checked) hasOperations = true;
    if (detectOutliers && detectOutliers.checked) hasOperations = true;
    
    // Check for per-column operations
    var selects = form.querySelectorAll('select[name^="missing_strategy"], select[name^="cast_type"]');
    for (var i = 0; i < selects.length; i++) {
      if (selects[i].value !== 'none' && selects[i].value !== '') {
        hasOperations = true;
        break;
      }
    }
    
    // Check for validation operations
    if (validateEmails && validateEmails.checked) hasOperations = true;
    if (validateUrls && validateUrls.checked) hasOperations = true;
    if (validatePhones && validatePhones.checked) hasOperations = true;

    if (!hasOperations) {
      e.preventDefault();
      alert('Please select at least one cleaning operation.');
      return false;
    }

    // Show confirmation
    var confirmed = confirm('Apply cleaning operations? This will create a new cleaned version of your dataset.');
    if (!confirmed) {
      e.preventDefault();
      return false;
    }
  });
})();
