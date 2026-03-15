document.querySelectorAll('.toggle-password').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var id = this.getAttribute('data-target');
    var input = id ? document.getElementById(id) : this.previousElementSibling;
    if (!input) return;
    var isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    var eye = this.querySelector('.icon-eye');
    var eyeOpen = this.querySelector('.icon-eye-open');
    if (eye && eyeOpen) {
      eye.hidden = isPassword;
      eyeOpen.hidden = !isPassword;
    }
    this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
  });
});
