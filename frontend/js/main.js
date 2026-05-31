document.addEventListener('DOMContentLoaded', () => {
  const button = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-nav');
  if (button && nav) button.addEventListener('click', () => nav.classList.toggle('open'));

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const required = form.querySelectorAll('[required]');
      for (const field of required) {
        if (!String(field.value || '').trim()) {
          event.preventDefault();
          field.focus();
          field.style.boxShadow = '0 0 0 4px rgba(183,66,63,.18)';
          return;
        }
      }
    });
  });

  const mainProductImage = document.getElementById('main-product-image');
  document.querySelectorAll('.gallery img').forEach((thumb) => {
    thumb.addEventListener('click', () => {
      if (mainProductImage) mainProductImage.src = thumb.dataset.large || thumb.src;
    });
  });

  document.querySelectorAll('.alert').forEach((alert) => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-6px)';
    }, 4500);
  });
});
