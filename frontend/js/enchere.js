document.addEventListener('DOMContentLoaded', () => {
  const timer = document.getElementById('auction-timer');
  if (!timer) return;
  const end = new Date(timer.dataset.end.replace(' ', 'T')).getTime();
  const tick = () => {
    const diff = end - Date.now();
    if (diff <= 0) { timer.textContent = 'Enchère terminée'; return; }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    timer.textContent = `Temps restant : ${d}j ${h}h ${m}m ${s}s`;
    requestAnimationFrame(() => setTimeout(tick, 500));
  };
  tick();
});
