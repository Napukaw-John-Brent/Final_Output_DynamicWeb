(function() {
  var form = document.getElementById('pin-form');
  var hidden = document.getElementById('pin-value');
  var dots = document.querySelectorAll('.pin-dot');
  var sendAgain = document.getElementById('send-again');

  function collectPin() {
    var s = '';
    dots.forEach(function(d) { s += d.value || ''; });
    if (hidden) hidden.value = s;
    return s;
  }

  dots.forEach(function(input, i) {
    input.addEventListener('input', function() {
      var v = this.value.replace(/\D/g, '');
      this.value = v.slice(0, 1);
      if (v && i < dots.length - 1) dots[i + 1].focus();
      collectPin();
    });
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Backspace' && !this.value && i > 0) dots[i - 1].focus();
    });
    input.addEventListener('paste', function(e) {
      e.preventDefault();
      var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      pasted.split('').forEach(function(ch, j) {
        if (dots[j]) { dots[j].value = ch; }
      });
      if (dots[pasted.length - 1]) dots[pasted.length - 1].focus();
      collectPin();
    });
  });

  if (form) {
    form.addEventListener('submit', function(e) {
      collectPin();
      if (hidden && hidden.value.length !== 6) {
        e.preventDefault();
      }
    });
  }

  if (sendAgain) {
    sendAgain.addEventListener('click', function() {
      this.textContent = 'Sent! Check your email.';
      this.disabled = true;
      setTimeout(function() {
        sendAgain.textContent = 'Send Again';
        sendAgain.disabled = false;
      }, 3000);
    });
  }
})();
