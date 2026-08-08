

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

/* Parallax hero: lapisan bergerak lebih lambat dari gulungan halaman,
   sehingga terbaca sebagai kedalaman. Dijalankan di rAF dan hanya saat
   bagian itu masih terlihat, supaya tidak membebani gulungan. */
function pasangParallax() {
  const lapis = document.querySelectorAll('[data-parallax]');
  if (!lapis.length || gerakMinimal) return;

  let menunggu = false;
  const gambar = () => {
    menunggu = false;
    const y = window.scrollY;
    if (y > window.innerHeight * 1.3) return;      // hero sudah lewat
    lapis.forEach((el) => {
      const laju = parseFloat(el.dataset.parallax) || 0;
      el.style.transform = `translate3d(0, ${(y * laju).toFixed(1)}px, 0)`;
    });
  };

  window.addEventListener('scroll', () => {
    if (menunggu) return;
    menunggu = true;
    requestAnimationFrame(gambar);
  }, { passive: true });

  gambar();
}

document.addEventListener('DOMContentLoaded', pasangParallax);
