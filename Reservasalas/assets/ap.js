// assets/js/app.js — ReservaSalas ITSZN

// ── Auto-cerrar alerts flash ───────────────────────────────────
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  }, 4000);
});

// ── Confirmar acciones destructivas ──────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

// ── Activar enlace del sidebar según URL actual ───────────────
(function() {
  const path = window.location.pathname;
  document.querySelectorAll('.sidebar a').forEach(a => {
    if (path.includes(a.getAttribute('href'))) {
      a.classList.add('active');
    }
  });
})();

// ── Validación de formulario de reservación ───────────────────
const frmReserva = document.getElementById('frmReserva');
if (frmReserva) {
  frmReserva.addEventListener('submit', function(e) {
    const hi = this.querySelector('[name="hora_inicio"]')?.value;
    const hf = this.querySelector('[name="hora_fin"]')?.value;
    if (hi && hf && hf <= hi) {
      e.preventDefault();
      alert('La hora de fin debe ser mayor a la hora de inicio.');
    }
  });
}

// ── Tooltip básico (title) ─────────────────────────────────────
// Usa el title nativo del navegador por simplicidad.
// Si quieres tooltips personalizados, reemplaza aquí.

console.log('ReservaSalas ITSZN · JS cargado.');