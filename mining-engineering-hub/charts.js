/**
 * Mining Engineering Hub — grafik.
 *
 * Digambar sendiri di atas canvas, tanpa pustaka luar.
 *
 * Syaratnya adalah situs ini jalan dengan klik ganda index.html dan
 * tanpa galat di konsol. Pustaka lewat CDN melanggar keduanya begitu
 * jaringan tidak ada — dan jaringan site tambang memang begitu. Yang
 * dibutuhkan halaman ini cuma garis, batang, dan donat; menariknya
 * lewat kabel sejauh itu tidak sepadan.
 *
 * Seluruh grafik: animasi masuk, tooltip mengikuti kursor, menyesuaikan
 * lebar induknya, dan menghormati prefers-reduced-motion.
 */
(function (global) {
  'use strict';

  const GERAK_MINIMAL = global.matchMedia
    ? global.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false;

  /** Warna diambil dari variabel tema supaya grafik ikut berganti mode. */
  function warnaTema(nama, cadangan) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
    return v || cadangan;
  }

  function palet() {
    return {
      teks:    warnaTema('--teks-lemah', '#8A94A6'),
      garis:   warnaTema('--garis', 'rgba(255,255,255,.10)'),
      panel:   warnaTema('--panel', '#151C27'),
      seri: [
        warnaTema('--aksen', '#F5A524'),
        warnaTema('--sukses', '#2FBF71'),
        warnaTema('--info', '#4C9AFF'),
        warnaTema('--bahaya', '#E5484D'),
        warnaTema('--ungu', '#9A7CF0'),
        warnaTema('--jingga', '#F0862B'),
      ],
    };
  }

  const mudah = (t) => 1 - Math.pow(1 - t, 3);          // ease-out cubic
  const angka = (n, d = 0) => n.toLocaleString('id-ID', {
    minimumFractionDigits: d, maximumFractionDigits: d,
  });

  /**
   * Kerangka bersama seluruh grafik: ukuran mengikuti induk, piksel
   * mengikuti kerapatan layar, dan gambar ulang saat lebarnya berubah.
   */
  function buatKanvas(wadah, tinggi) {
    wadah.innerHTML = '';
    const cv = document.createElement('canvas');
    cv.className = 'grafik-kanvas';
    cv.style.height = tinggi + 'px';
    wadah.appendChild(cv);

    const tip = document.createElement('div');
    tip.className = 'grafik-tip';
    tip.setAttribute('role', 'status');
    wadah.appendChild(tip);

    return { cv, ctx: cv.getContext('2d'), tip };
  }

  /**
     Mengembalikan null bila wadahnya tidak punya lebar.

     Ini terjadi sungguhan: animasi berjalan 700 ms, dan bila orang pindah
     halaman di tengahnya, wadahnya menjadi hidden — clientWidth nol —
     sementara bingkai berikutnya tetap datang. Seluruh hitungan jari-jari
     lalu menjadi negatif dan canvas melempar galat. Yang benar adalah
     berhenti menggambar, bukan menggambar bentuk mustahil.
  */
  function ukur(cv, tinggi) {
    const lebar = cv.parentElement.clientWidth;
    if (!lebar || lebar <= 0) return null;

    const dpr = global.devicePixelRatio || 1;
    cv.width = Math.max(1, Math.round(lebar * dpr));
    cv.height = Math.round(tinggi * dpr);
    cv.style.width = '100%';
    const ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    return { lebar, tinggi };
  }

  /** Jari-jari tidak pernah boleh negatif, berapa pun sempitnya bidang. */
  const jari = (v) => Math.max(0, v);

  /** Skala sumbu Y yang berhenti di angka bulat, bukan di angka data. */
  function skala(maks, minimal = 0) {
    if (maks <= minimal) return { atas: minimal + 1, langkah: 1 };
    const kasar = (maks - minimal) / 4;
    const pangkat = Math.pow(10, Math.floor(Math.log10(kasar)));
    const langkah = [1, 2, 2.5, 5, 10].map((m) => m * pangkat)
      .find((l) => l >= kasar) || pangkat * 10;
    return { atas: Math.ceil(maks / langkah) * langkah, langkah };
  }

  function jalankan(gambar) {
    if (GERAK_MINIMAL) { gambar(1); return; }
    const mulai = performance.now();
    const durasi = 700;
    (function bingkai(t) {
      const p = Math.min(1, (t - mulai) / durasi);
      gambar(mudah(p));
      if (p < 1) requestAnimationFrame(bingkai);
    })(mulai);
  }

  function amatiLebar(cv, gambarUlang) {
    if (!('ResizeObserver' in global)) {
      global.addEventListener('resize', gambarUlang);
      return;
    }
    let lebarTerakhir = cv.parentElement.clientWidth;
    new ResizeObserver(() => {
      const l = cv.parentElement.clientWidth;
      if (Math.abs(l - lebarTerakhir) < 2) return;   // abaikan getaran subpiksel
      lebarTerakhir = l;
      gambarUlang();
    }).observe(cv.parentElement);
  }

  /* ══════════════════════════════════════════════════════════════
     Grafik garis — satu atau beberapa seri, dengan garis acuan
     ══════════════════════════════════════════════════════════════ */
  function garis(wadah, opsi) {
    const tinggi = opsi.tinggi || 260;
    const { cv, ctx, tip } = buatKanvas(wadah, tinggi);
    const pad = { kiri: 52, kanan: 14, atas: 18, bawah: 30 };
    let titikLayar = [];

    function gambar(kemajuan) {
      const P = palet();
      const ukuran = ukur(cv, tinggi);
      if (!ukuran) return;                 // wadah sedang tersembunyi
      const { lebar } = ukuran;
      ctx.clearRect(0, 0, lebar, tinggi);

      const semua = opsi.seri.flatMap((s) => s.data)
        .concat(opsi.acuan != null ? [opsi.acuan] : []);
      const { atas, langkah } = skala(Math.max(...semua));

      const w = lebar - pad.kiri - pad.kanan;
      const h = tinggi - pad.atas - pad.bawah;
      const x = (i, n) => pad.kiri + (n > 1 ? (i / (n - 1)) * w : w / 2);
      const y = (v) => pad.atas + h - (v / atas) * h;

      // Garis bantu mendatar dan angkanya
      ctx.strokeStyle = P.garis; ctx.fillStyle = P.teks;
      ctx.lineWidth = 1; ctx.font = '11px Inter, system-ui, sans-serif';
      ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
      for (let v = 0; v <= atas + 1e-9; v += langkah) {
        const yy = Math.round(y(v)) + 0.5;
        ctx.beginPath(); ctx.moveTo(pad.kiri, yy); ctx.lineTo(lebar - pad.kanan, yy); ctx.stroke();
        ctx.fillText(angka(v, opsi.desimal || 0), pad.kiri - 8, yy);
      }

      // Label sumbu X, dijarangkan bila kesempitan
      ctx.textAlign = 'center'; ctx.textBaseline = 'top';
      const n = opsi.label.length;
      const lompat = Math.ceil(n / Math.max(2, Math.floor(w / 64)));
      opsi.label.forEach((l, i) => {
        if (i % lompat && i !== n - 1) return;
        ctx.fillText(l, x(i, n), tinggi - pad.bawah + 9);
      });

      // Garis acuan (target atau baseline)
      if (opsi.acuan != null) {
        const yy = Math.round(y(opsi.acuan)) + 0.5;
        ctx.save();
        ctx.setLineDash([6, 5]); ctx.strokeStyle = P.seri[3]; ctx.lineWidth = 1.5;
        ctx.beginPath(); ctx.moveTo(pad.kiri, yy); ctx.lineTo(lebar - pad.kanan, yy); ctx.stroke();
        ctx.restore();
        ctx.fillStyle = P.seri[3]; ctx.textAlign = 'left'; ctx.textBaseline = 'bottom';
        ctx.font = '600 10px Inter, system-ui, sans-serif';
        ctx.fillText(opsi.acuanLabel || 'Target', pad.kiri + 4, yy - 3);
      }

      titikLayar = [];
      opsi.seri.forEach((s, si) => {
        const warna = s.warna || P.seri[si % P.seri.length];
        const n2 = s.data.length;
        const tampak = Math.max(2, Math.ceil(n2 * kemajuan));

        if (s.isi !== false) {
          const g = ctx.createLinearGradient(0, pad.atas, 0, tinggi - pad.bawah);
          g.addColorStop(0, warna + '33'); g.addColorStop(1, warna + '00');
          ctx.fillStyle = g; ctx.beginPath();
          ctx.moveTo(x(0, n2), y(s.data[0]));
          for (let i = 1; i < tampak; i++) ctx.lineTo(x(i, n2), y(s.data[i]));
          ctx.lineTo(x(tampak - 1, n2), tinggi - pad.bawah);
          ctx.lineTo(x(0, n2), tinggi - pad.bawah);
          ctx.closePath(); ctx.fill();
        }

        ctx.strokeStyle = warna; ctx.lineWidth = 2.4;
        ctx.lineJoin = 'round'; ctx.lineCap = 'round';
        if (s.putus) ctx.setLineDash([5, 4]);
        ctx.beginPath();
        for (let i = 0; i < tampak; i++) {
          const px = x(i, n2), py = y(s.data[i]);
          i ? ctx.lineTo(px, py) : ctx.moveTo(px, py);
        }
        ctx.stroke(); ctx.setLineDash([]);

        for (let i = 0; i < tampak; i++) {
          const px = x(i, n2), py = y(s.data[i]);
          ctx.fillStyle = P.panel; ctx.beginPath(); ctx.arc(px, py, 3.6, 0, Math.PI * 2); ctx.fill();
          ctx.strokeStyle = warna; ctx.lineWidth = 2; ctx.stroke();
          if (kemajuan === 1) titikLayar.push({ px, py, si, i });
        }
      });
    }

    function isiTip(t) {
      const baris = opsi.seri.map((s, si) => {
        const warna = s.warna || palet().seri[si % palet().seri.length];
        return `<span class="tip-baris"><i style="background:${warna}"></i>${s.nama}
                <b>${angka(s.data[t.i], opsi.desimal || 0)}${opsi.satuan ? ' ' + opsi.satuan : ''}</b></span>`;
      }).join('');
      tip.innerHTML = `<strong>${opsi.label[t.i]}</strong>${baris}`;
    }

    cv.addEventListener('pointermove', (e) => {
      if (!titikLayar.length) return;
      const r = cv.getBoundingClientRect();
      const mx = e.clientX - r.left;
      let dekat = null, jarak = Infinity;
      titikLayar.forEach((t) => {
        const d = Math.abs(t.px - mx);
        if (d < jarak) { jarak = d; dekat = t; }
      });
      if (!dekat || jarak > 40) { tip.classList.remove('tampil'); return; }
      isiTip(dekat);
      tip.classList.add('tampil');
      tip.style.left = dekat.px + 'px';
      tip.style.top = dekat.py + 'px';
    });
    cv.addEventListener('pointerleave', () => tip.classList.remove('tampil'));

    jalankan(gambar);
    amatiLebar(cv, () => gambar(1));
    return { gambarUlang: () => jalankan(gambar) };
  }

  /* ══════════════════════════════════════════════════════════════
     Grafik batang — berdampingan atau bertumpuk
     ══════════════════════════════════════════════════════════════ */
  function batang(wadah, opsi) {
    const tinggi = opsi.tinggi || 260;
    const { cv, ctx, tip } = buatKanvas(wadah, tinggi);
    const pad = { kiri: 52, kanan: 14, atas: 18, bawah: 30 };
    let kotak = [];

    function gambar(kemajuan) {
      const P = palet();
      const ukuran = ukur(cv, tinggi);
      if (!ukuran) return;                 // wadah sedang tersembunyi
      const { lebar } = ukuran;
      ctx.clearRect(0, 0, lebar, tinggi);

      const n = opsi.label.length;
      const tumpuk = opsi.tumpuk === true;
      const puncak = tumpuk
        ? Math.max(...opsi.label.map((_, i) => opsi.seri.reduce((a, s) => a + s.data[i], 0)))
        : Math.max(...opsi.seri.flatMap((s) => s.data));
      const { atas, langkah } = skala(Math.max(puncak, opsi.acuan || 0));

      const w = lebar - pad.kiri - pad.kanan;
      const h = tinggi - pad.atas - pad.bawah;
      const y = (v) => pad.atas + h - (v / atas) * h;

      ctx.strokeStyle = P.garis; ctx.fillStyle = P.teks;
      ctx.lineWidth = 1; ctx.font = '11px Inter, system-ui, sans-serif';
      ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
      for (let v = 0; v <= atas + 1e-9; v += langkah) {
        const yy = Math.round(y(v)) + 0.5;
        ctx.beginPath(); ctx.moveTo(pad.kiri, yy); ctx.lineTo(lebar - pad.kanan, yy); ctx.stroke();
        ctx.fillText(angka(v, opsi.desimal || 0), pad.kiri - 8, yy);
      }

      const lebarSlot = w / n;
      const lebarGrup = jari(Math.min(lebarSlot * 0.68, 68));
      const lebarBatang = tumpuk ? lebarGrup : lebarGrup / opsi.seri.length;

      kotak = [];
      opsi.label.forEach((l, i) => {
        const kiriGrup = pad.kiri + lebarSlot * i + (lebarSlot - lebarGrup) / 2;
        let dasar = tinggi - pad.bawah;

        opsi.seri.forEach((s, si) => {
          const warna = s.warna || P.seri[si % P.seri.length];
          const nilai = s.data[i] * kemajuan;
          const tinggiBatang = (nilai / atas) * h;
          const px = tumpuk ? kiriGrup : kiriGrup + lebarBatang * si;
          const py = tumpuk ? dasar - tinggiBatang : (tinggi - pad.bawah) - tinggiBatang;

          ctx.fillStyle = warna;
          const r = jari(Math.min(4, lebarBatang / 2, tinggiBatang));
          ctx.beginPath();
          ctx.roundRect(px + 1, py, Math.max(1, lebarBatang - 2), Math.max(0, tinggiBatang),
                        tumpuk && si < opsi.seri.length - 1 ? 0 : [r, r, 0, 0]);
          ctx.fill();

          if (tumpuk) dasar -= tinggiBatang;
          if (kemajuan === 1) kotak.push({ x: px, y: py, w: lebarBatang, h: tinggiBatang, i });
        });

        ctx.fillStyle = P.teks; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        ctx.font = '11px Inter, system-ui, sans-serif';
        ctx.fillText(l, kiriGrup + lebarGrup / 2, tinggi - pad.bawah + 9);
      });

      if (opsi.acuan != null) {
        const yy = Math.round(y(opsi.acuan)) + 0.5;
        ctx.save(); ctx.setLineDash([6, 5]);
        ctx.strokeStyle = P.seri[3]; ctx.lineWidth = 1.5;
        ctx.beginPath(); ctx.moveTo(pad.kiri, yy); ctx.lineTo(lebar - pad.kanan, yy); ctx.stroke();
        ctx.restore();
      }
    }

    cv.addEventListener('pointermove', (e) => {
      if (!kotak.length) return;
      const r = cv.getBoundingClientRect();
      const mx = e.clientX - r.left, my = e.clientY - r.top;
      const kena = kotak.find((k) => mx >= k.x && mx <= k.x + k.w && my >= k.y - 6 && my <= k.y + k.h);
      if (!kena) { tip.classList.remove('tampil'); return; }
      const P = palet();
      tip.innerHTML = `<strong>${opsi.label[kena.i]}</strong>` + opsi.seri.map((s, si) =>
        `<span class="tip-baris"><i style="background:${s.warna || P.seri[si % P.seri.length]}"></i>${s.nama}
         <b>${angka(s.data[kena.i], opsi.desimal || 0)}${opsi.satuan ? ' ' + opsi.satuan : ''}</b></span>`).join('');
      tip.classList.add('tampil');
      tip.style.left = (kena.x + kena.w / 2) + 'px';
      tip.style.top = kena.y + 'px';
    });
    cv.addEventListener('pointerleave', () => tip.classList.remove('tampil'));

    jalankan(gambar);
    amatiLebar(cv, () => gambar(1));
    return { gambarUlang: () => jalankan(gambar) };
  }

  /* ══════════════════════════════════════════════════════════════
     Donat — komposisi, dengan angka di tengahnya
     ══════════════════════════════════════════════════════════════ */
  function donat(wadah, opsi) {
    const tinggi = opsi.tinggi || 220;
    const { cv, ctx, tip } = buatKanvas(wadah, tinggi);
    let juring = [];

    function gambar(kemajuan) {
      const P = palet();
      const ukuran = ukur(cv, tinggi);
      if (!ukuran) return;                 // wadah sedang tersembunyi
      const { lebar } = ukuran;
      ctx.clearRect(0, 0, lebar, tinggi);

      const total = opsi.data.reduce((a, d) => a + d.nilai, 0) || 1;
      const cx = lebar / 2, cy = tinggi / 2;
      const jariLuar = jari(Math.min(lebar, tinggi) / 2 - 8);
      const jariDalam = jariLuar * 0.63;

      let sudut = -Math.PI / 2;
      juring = [];
      opsi.data.forEach((d, i) => {
        const lebarSudut = (d.nilai / total) * Math.PI * 2 * kemajuan;
        ctx.beginPath();
        ctx.arc(cx, cy, jariLuar, sudut, sudut + lebarSudut);
        ctx.arc(cx, cy, jariDalam, sudut + lebarSudut, sudut, true);
        ctx.closePath();
        ctx.fillStyle = d.warna || P.seri[i % P.seri.length];
        ctx.fill();
        if (kemajuan === 1) juring.push({ dari: sudut, sampai: sudut + lebarSudut, i });
        sudut += lebarSudut;
      });

      ctx.fillStyle = warnaTema('--teks', '#E8ECF3');
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.font = '700 21px Inter, system-ui, sans-serif';
      ctx.fillText(opsi.tengah || angka(total), cx, cy - 6);
      ctx.fillStyle = P.teks;
      ctx.font = '11px Inter, system-ui, sans-serif';
      ctx.fillText(opsi.tengahKet || '', cx, cy + 14);
    }

    cv.addEventListener('pointermove', (e) => {
      if (!juring.length) return;
      const r = cv.getBoundingClientRect();
      const dx = e.clientX - r.left - cv.parentElement.clientWidth / 2;
      const dy = e.clientY - r.top - tinggi / 2;
      let a = Math.atan2(dy, dx);
      if (a < -Math.PI / 2) a += Math.PI * 2;
      const kena = juring.find((j) => a >= j.dari && a <= j.sampai);
      const total = opsi.data.reduce((x, d) => x + d.nilai, 0) || 1;
      if (!kena) { tip.classList.remove('tampil'); return; }
      const d = opsi.data[kena.i];
      tip.innerHTML = `<strong>${d.nama}</strong><span class="tip-baris"><b>${angka(d.nilai, opsi.desimal || 0)}</b>
                       · ${angka(d.nilai / total * 100, 1)}%</span>`;
      tip.classList.add('tampil');
      tip.style.left = (e.clientX - r.left) + 'px';
      tip.style.top = (e.clientY - r.top) + 'px';
    });
    cv.addEventListener('pointerleave', () => tip.classList.remove('tampil'));

    jalankan(gambar);
    amatiLebar(cv, () => gambar(1));
    return { gambarUlang: () => jalankan(gambar) };
  }

  global.Grafik = { garis, batang, donat, palet };
})(window);
