document.addEventListener('DOMContentLoaded', () => {
  const timer = document.getElementById('auction-timer');
  if (!timer) return;
  const end = new Date(timer.dataset.end.replace(' ', 'T')).getTime();
  const auctionId = timer.dataset.auctionId;
  let hasReloaded = false;

  async function refreshAuctionStatus() {
    if (!auctionId) return;
    try {
      const response = await fetch(`backend/api/auctions/status.php?id_auction=${encodeURIComponent(auctionId)}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!response.ok) return;
      const data = await response.json();
      if (!data.success) return;
      if (data.auction.statut_enchere !== 'en_cours' && !hasReloaded) {
        hasReloaded = true;
        timer.textContent = 'Enchère terminée. Mise à jour de la page...';
        setTimeout(() => window.location.reload(), 800);
      }
    } catch (e) {}
  }

  const tick = () => {
    const diff = end - Date.now();
    if (diff <= 0) {
      timer.textContent = 'Enchère terminée. Clôture en cours...';
      refreshAuctionStatus();
      return;
    }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    timer.textContent = `Temps restant : ${d}j ${h}h ${m}m ${s}s`;
    setTimeout(tick, 1000);
  };

  tick();
  setInterval(refreshAuctionStatus, 15000);
});
