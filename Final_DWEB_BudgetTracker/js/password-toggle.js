document.querySelectorAll('.toggle-password').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var id = this.getAttribute('data-target');
    var input = id ? document.getElementById(id) : this.previousElementSibling;
    if (!input) return;

    var isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    var icon = this.querySelector('img');
    if (icon) {
      var nowVisible = isPassword; // we just switched to text when it was password
      icon.src = nowVisible ? '../images/x.svg' : '../images/eye.svg';
    }

    this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
  });
});
