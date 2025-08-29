document.addEventListener('DOMContentLoaded', () => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const labels = { non_letto: 'Non letto', in_lettura: 'In lettura', letto: 'Letto' };
  const classes = { non_letto: 'status-non_letto', in_lettura: 'status-in_lettura', letto: 'status-letto' };
  const cycle = { non_letto: 'in_lettura', in_lettura: 'letto', letto: 'non_letto' };

  // Delegazione globale: funziona ovunque compaia il bottone
  document.body.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-status[data-action="toggle-status"]');
    if (!btn) return;
    e.preventDefault();

    const id = btn.dataset.id;
    const next = btn.dataset.next;
    const card = btn.closest('.card');
    const badge = card?.querySelector('.js-status');

    if (!id || !next) return;
    try {
      btn.disabled = true;
      const body = new URLSearchParams({ id, next, csrf_token: csrf }).toString();
      const res = await fetch('books-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.msg || 'Errore');

      // Successo: aggiorno badge e prossimo stato
      if (badge) {
        badge.className = 'badge rounded-pill js-status ' + (classes[next] || '');
        badge.textContent = labels[next] || next;
      }
      const upcoming = cycle[next] || 'in_lettura';
      btn.dataset.next = upcoming;
      btn.innerHTML = '<i class="fa-solid fa-retweet me-1"></i>Segna: ' + (labels[upcoming] || upcoming);
    } catch (err) {
      console.error(err);
      btn.classList.add('btn-danger');
      setTimeout(() => btn.classList.remove('btn-danger'), 900);
    } finally {
      btn.disabled = false;
    }
  });
});
