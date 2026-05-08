(function () {
  var form = document.getElementById('cleanForm');
  if (!form) return;

  var removeDuplicates = document.getElementById('remove_duplicates');
  var trimWhitespace = document.getElementById('trim_whitespace');
  var dateStandardize = document.getElementById('standardize_dates');

  function detectIssues() {
    var msgs = [];
    if (removeDuplicates && removeDuplicates.checked) msgs.push('Duplicate rows will be removed.');
    if (trimWhitespace && trimWhitespace.checked) msgs.push('Whitespace will be trimmed from all string columns.');
    if (dateStandardize && dateStandardize.checked) msgs.push('Dates will be normalized to YYYY-MM-DD.');
    alert(msgs.length ? msgs.join('\n') : 'No global operations selected yet.');
  }

  function standardizeData() {
    if (trimWhitespace) trimWhitespace.checked = true;
    if (dateStandardize) dateStandardize.checked = true;
    alert('Standardization toggles enabled.');
  }

  function removeDuplicatesUI() {
    if (removeDuplicates) removeDuplicates.checked = true;
    alert('Remove duplicates enabled.');
  }

  window.detectIssues = detectIssues;
  window.standardizeData = standardizeData;
  window.removeDuplicatesUI = removeDuplicatesUI;
})();
