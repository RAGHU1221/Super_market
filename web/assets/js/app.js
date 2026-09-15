// Small shared helpers used across pages
function confirmDelete(msg) {
  return confirm(msg || 'Delete this record? / இதை நீக்கவா?');
}

// Auto-dismiss flash messages
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.flash').forEach(function (el) {
    setTimeout(function () { el.style.display = 'none'; }, 5000);
  });
});
