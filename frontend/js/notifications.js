document.addEventListener('DOMContentLoaded', () => {
  const badge = document.getElementById('notification-count');
  const list = document.querySelector('[data-notifications-live]');

  async function fetchJson(url) {
    const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!response.ok) throw new Error('Erreur réseau');
    return response.json();
  }

  async function refreshCount() {
    if (!badge) return;
    try {
      const data = await fetchJson('backend/api/notifications/count.php');
      if (data.success) badge.textContent = data.count;
    } catch (e) {}
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  }

  async function refreshList() {
    if (!list) return;
    try {
      const data = await fetchJson('backend/api/notifications/list.php');
      if (!data.success) return;
      if (!data.notifications.length) {
        list.innerHTML = '<div class="empty-state">Aucune notification pour le moment.</div>';
        return;
      }
      const csrf = list.dataset.csrf || '';
      list.innerHTML = data.notifications.map(n => `
        <article class="notification ${Number(n.est_lue) ? 'read' : 'unread'}">
          <div>
            <h3>${escapeHtml(n.titre)}</h3>
            <p>${escapeHtml(n.message)}</p>
            <p class="muted">${escapeHtml(n.date_creation)} · ${escapeHtml(n.type_notification)}</p>
          </div>
          <div class="actions-row">
            ${n.lien ? `<a class="btn secondary" href="${escapeHtml(n.lien)}">Ouvrir</a>` : ''}
            ${Number(n.est_lue) ? '' : `<form method="post"><input type="hidden" name="csrf_token" value="${escapeHtml(csrf)}"><input type="hidden" name="id_notification" value="${Number(n.id_notification)}"><button class="btn" type="submit">Marquer lu</button></form>`}
          </div>
        </article>`).join('');
    } catch (e) {}
  }

  refreshCount();
  refreshList();
  setInterval(refreshCount, 10000);
  if (list) setInterval(refreshList, 15000);
});
