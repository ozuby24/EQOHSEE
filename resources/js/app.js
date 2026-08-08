

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

/* ── Gerak halaman publik ─────────────────────────────────────────────
   Dua perilaku kecil yang membuat halaman terasa hidup tanpa pustaka
   tambahan: elemen muncul saat masuk layar, dan kartu miring mengikuti
   kursor. Keduanya mundur dengan aman — bila API-nya tidak tersedia atau
   pengguna meminta gerak minimal, isi halaman tetap tampil utuh.        */
const gerakMinimal = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function pasangReveal() {
  const target = document.querySelectorAll('.reveal');
  if (!target.length) return;

  if (gerakMinimal || !('IntersectionObserver' in window)) {
    target.forEach((el) => el.classList.add('tampil'));
    return;
  }

  const pengamat = new IntersectionObserver((entri) => {
    entri.forEach((e) => {
      if (!e.isIntersecting) return;
      e.target.classList.add('tampil');
      pengamat.unobserve(e.target);          // sekali muncul, selesai
    });
  }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

  target.forEach((el) => pengamat.observe(el));
}

function pasangTilt() {
  if (gerakMinimal) return;
  // Perangkat sentuh tidak punya kursor melayang; kemiringan justru
  // membuat kartu terasa goyah saat disentuh.
  if (!window.matchMedia?.('(hover: hover) and (pointer: fine)').matches) return;

  document.querySelectorAll('[data-tilt]').forEach((kartu) => {
    const MAKS = 7;                          // derajat, sengaja halus
    let rafId = null;

    const gerak = (ev) => {
      if (rafId) return;
      rafId = requestAnimationFrame(() => {
        rafId = null;
        const k = kartu.getBoundingClientRect();
        const x = (ev.clientX - k.left) / k.width  - 0.5;
        const y = (ev.clientY - k.top)  / k.height - 0.5;
        kartu.style.setProperty('--ry', `${( x * MAKS).toFixed(2)}deg`);
        kartu.style.setProperty('--rx', `${(-y * MAKS).toFixed(2)}deg`);
      });
    };

    const pulih = () => {
      if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
      kartu.style.setProperty('--rx', '0deg');
      kartu.style.setProperty('--ry', '0deg');
    };

    kartu.addEventListener('pointermove', gerak);
    kartu.addEventListener('pointerleave', pulih);
    kartu.addEventListener('blur', pulih);
  });
}

document.addEventListener('DOMContentLoaded', () => { pasangReveal(); pasangTilt(); });
