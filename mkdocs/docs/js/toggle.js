document.addEventListener('DOMContentLoaded', function() {
  const originalCheckbox = document.getElementById('showOriginal');
  const romanizeCheckbox = document.getElementById('showRomanized');
  const shibuyaCheckbox = document.getElementById('showShibuya');
  const yosanoCheckbox = document.getElementById('showYosano');
  const annotationsCheckbox = document.getElementById('showAnnotations');

  const originalTexts = document.querySelectorAll('.original');
  const romanizedTexts = document.querySelectorAll('.romanized');
  const shibuyaTexts = document.querySelectorAll('.shibuya');
  const yosanoTexts = document.querySelectorAll('.yosano');
  const annotationsTexts = document.querySelectorAll('.annotations');

  originalCheckbox.addEventListener('change', function() {
    originalTexts.forEach(text => text.style.display = this.checked ? 'block' : 'none');
  });

  romanizeCheckbox.addEventListener('change', function() {
    romanizedTexts.forEach(text => text.style.display = this.checked ? 'block' : 'none');
  });

  shibuyaCheckbox.addEventListener('change', function() {
    shibuyaTexts.forEach(text => text.style.display = this.checked ? 'block' : 'none');
  });

  yosanoCheckbox.addEventListener('change', function() {
    yosanoTexts.forEach(text => text.style.display = this.checked ? 'block' : 'none');
  });

  annotationsCheckbox.addEventListener('change', function() {
    annotationsTexts.forEach(text => text.style.display = this.checked ? 'block' : 'none');
  });
});