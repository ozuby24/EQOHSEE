/**
 * Mining Engineering Hub — logika aplikasi.
 *
 * Susunannya: pembantu kecil, lalu modul hitung, lalu perilaku antarmuka
 * (tema, navigasi, pencarian), lalu satu penggambar per halaman, lalu
 * alat hitung, lalu penyalaan.
 *
 * Satu aturan dipegang di seluruh berkas: tidak ada angka turunan yang
 * ditulis sebagai tetapan. Ketersediaan, intensitas, rasio, dan frekuensi
 * kecelakaan semuanya dihitung dari besaran mentah di data.js. Angka yang
 * diketik dua kali cepat atau lambat akan berbeda, dan yang membaca tidak
 * punya cara tahu mana yang benar.
 */
(function () {
  'use strict';

  const D = window.engineeringData;
  const F = window.FAKTOR;
  const K = window.kpiKatalog;

  /* ══════════════════════════════════════════════════════════════
     1. Pembantu
     ══════════════════════════════════════════════════════════════ */
  const $  = (s, akar = document) => akar.querySelector(s);
  const $$ = (s, akar = document) => Array.from(akar.querySelectorAll(s));

  const n = (v, d = 0) => (Number.isFinite(v) ? v : 0).toLocaleString('id-ID', {
    minimumFractionDigits: d, maximumFractionDigits: d,
  });

  /** Angka besar dipendekkan supaya kartu KPI tetap terbaca. */
  function ringkas(v, d = 1) {
    const a = Math.abs(v);
    if (a >= 1e12) return n(v / 1e12, d) + ' T';
    if (a >= 1e9)  return n(v / 1e9,  d) + ' M';   // miliar
    if (a >= 1e6)  return n(v / 1e6,  d) + ' jt';
    if (a >= 1e4)  return n(v / 1e3,  d) + ' rb';
    return n(v, d);
  }

  const rupiah = (v) => 'Rp ' + ringkas(v, 1);

  /** Pembagian yang tidak pernah menghasilkan tak hingga. */
  const bagi = (a, b) => (b > 0 ? a / b : 0);

  const tglPendek = (s) => new Date(s + 'T00:00:00')
    .toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });

  function el(tag, kelas, isi) {
    const e = document.createElement(tag);
    if (kelas) e.className = kelas;
    if (isi != null) e.innerHTML = isi;
    return e;
  }

  /** Teks yang berasal dari data selalu lewat sini sebelum masuk innerHTML. */
  function aman(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  const IKON = {
    produksi:  '<path d="M3 20h18M5 20v-5l5-2 3 3 3-6 3 2v8"/>',
    bahanBakar:'<path d="M4 20h9V4H4zM13 9h3l2 2v7a2 2 0 01-4 0V9z"/><path d="M6.5 8h4"/>',
    energi:    '<path d="M13 3L5 14h6l-1 7 8-11h-6z"/>',
    armada:    '<path d="M3 16V7h11v9M14 10h4l3 3.5V16"/><circle cx="7.5" cy="17.5" r="1.9"/><circle cx="17.5" cy="17.5" r="1.9"/>',
    kunci:     '<path d="M14.7 6.3a4 4 0 105.1 5.1l-9.5 9.5-2.8.7.7-2.8z"/>',
    jam:       '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    perisai:   '<path d="M12 3l8 3v6c0 5-3.4 8.7-8 10-4.6-1.3-8-5-8-10V6z"/><path d="M9 12.2l2 2 4-4.2"/>',
    grafik:    '<path d="M4 20V9M10 20V4M16 20v-7M22 20H2"/>',
    listrik:   '<path d="M6 3h12M9 3v5l-3 5v6a2 2 0 002 2h8a2 2 0 002-2v-6l-3-5V3"/>',
    daun:      '<path d="M20 4C11 4 5 7 5 13a7 7 0 001 3.7C7.5 13 10.4 10.4 14 9c-3 2-5.3 5-6.4 8.6L7 20h2l.5-1.7C16 18 20 13 20 4z"/>',
    uang:      '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5A2.5 2.5 0 0012 8.5c-1.4 0-2.5.8-2.5 2s1.1 2 2.5 2 2.5.8 2.5 2-1.1 2-2.5 2a2.5 2.5 0 01-2.5-1M12 6.5v11"/>',
    peringatan:'<path d="M12 4l9 15.5H3z"/><path d="M12 10v4M12 17h.01"/>',
    kalkulator:'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h2M12 11h2M8 15h2M12 15h2"/>',
    dokumen:   '<path d="M5 4h9l5 5v11H5z"/><path d="M14 4v5h5M8 13h8M8 16h5"/>',
    naik:      '<path d="M5 15l7-7 7 7"/>',
    turun:     '<path d="M5 9l7 7 7-7"/>',
    datar:     '<path d="M5 12h14"/>',
    centang:   '<path d="M4 12.5l5 5L20 6.5"/>',
    silang:    '<path d="M6 6l12 12M18 6L6 18"/>',
    telinga:   '<path d="M9 21a3 3 0 003-3c0-2 4-2.5 4-7a5 5 0 10-10 0"/><path d="M9 11a2 2 0 114 0"/>',
    sampah:    '<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/>',
    tambah:    '<path d="M12 5v14M5 12h14"/>',
  };

  /* ══════════════════════════════════════════════════════════════
     2. Modul hitung
     Seluruh angka turunan lahir di sini dan tidak di tempat lain.
     ══════════════════════════════════════════════════════════════ */
  const hitung = {

    /* ---------- armada ---------- */

    /** Jam terjadwal sebuah unit = kerja + standby + perbaikan. */
    jamTerjadwal(u) { return u.jam_kerja + u.jam_standby + u.jam_rusak; },

    /** Physical Availability — alat siap dipakai, entah dipakai atau tidak. */
    pa(u) { return bagi(u.jam_kerja + u.jam_standby, this.jamTerjadwal(u)) * 100; },

    /** Mechanical Availability — kesiapan dari sisi mesin saja. */
    ma(u) { return bagi(u.jam_kerja, u.jam_kerja + u.jam_rusak) * 100; },

    /** Use of Availability — seberapa banyak kesiapan itu benar dipakai. */
    ua(u) { return bagi(u.jam_kerja, u.jam_kerja + u.jam_standby) * 100; },

    /** Utilization — jam kerja terhadap seluruh jam terjadwal. */
    utilisasi(u) { return bagi(u.jam_kerja, this.jamTerjadwal(u)) * 100; },

    /** Liter per jam operasi — dasar pembanding keborosan. */
    fuelRate(u) { return bagi(u.liter, u.jam_kerja); },

    armadaRingkas() {
      const unit = D.fleet.unit;
      const total = unit.reduce((a, u) => ({
        kerja:   a.kerja   + u.jam_kerja,
        standby: a.standby + u.jam_standby,
        rusak:   a.rusak   + u.jam_rusak,
        liter:   a.liter   + u.liter,
      }), { kerja: 0, standby: 0, rusak: 0, liter: 0 });

      const terjadwal = total.kerja + total.standby + total.rusak;
      return {
        ...total, terjadwal,
        jumlah:    unit.length,
        beroperasi: unit.filter((u) => u.status === 'Operating').length,
        pa: bagi(total.kerja + total.standby, terjadwal) * 100,
        ma: bagi(total.kerja, total.kerja + total.rusak) * 100,
        ua: bagi(total.kerja, total.kerja + total.standby) * 100,
        utilisasi: bagi(total.kerja, terjadwal) * 100,
        fuelRate: bagi(total.liter, total.kerja),
      };
    },

    /* ---------- produksi ---------- */

    produksiRingkas() {
      const h = D.production.harian;
      const ton = h.reduce((a, x) => a + x.ton, 0);
      const bcm = h.reduce((a, x) => a + x.bcm, 0);
      const target = h.reduce((a, x) => a + x.target, 0);
      return {
        hari: h.length,
        ton, bcm, target,
        tonHari: ton / h.length,
        bcmHari: bcm / h.length,
        terakhir: h[h.length - 1],
        capaian: bagi(ton, target) * 100,
        sr: bagi(bcm, ton),                       // stripping ratio
      };
    },

    /** Perubahan hari terakhir terhadap rata-rata hari sebelumnya. */
    trenProduksi() {
      const h = D.production.harian;
      if (h.length < 2) return 0;
      const sebelum = h.slice(0, -1).reduce((a, x) => a + x.ton, 0) / (h.length - 1);
      return bagi(h[h.length - 1].ton - sebelum, sebelum) * 100;
    },

    /* ---------- energi ---------- */

    /** Satu hari energi, seluruh sumber disamakan ke gigajoule. */
    energiHari(e, prod) {
      const gj = e.liter * F.GJ_PER_LITER + e.kwh * F.GJ_PER_KWH;
      return {
        tgl: e.tgl, liter: e.liter, kwh: e.kwh, gj,
        tco2e: e.liter * F.TON_CO2_PER_L + e.kwh * F.TON_CO2_PER_KWH,
        rupiah: e.liter * F.RP_PER_LITER + e.kwh * F.RP_PER_KWH,
        ton: prod ? prod.ton : 0,
        intensitas: prod ? bagi(gj, prod.ton) : 0,
      };
    },

    energiDeret() {
      return D.energy.harian.map((e) =>
        this.energiHari(e, D.production.harian.find((p) => p.tgl === e.tgl)));
    },

    energiRingkas() {
      const deret = this.energiDeret();
      const t = deret.reduce((a, d) => ({
        gj: a.gj + d.gj, liter: a.liter + d.liter, kwh: a.kwh + d.kwh,
        tco2e: a.tco2e + d.tco2e, rupiah: a.rupiah + d.rupiah, ton: a.ton + d.ton,
      }), { gj: 0, liter: 0, kwh: 0, tco2e: 0, rupiah: 0, ton: 0 });

      const intensitas = bagi(t.gj, t.ton);
      const base = D.energy.baseline_gj_per_ton;
      return {
        ...t, deret,
        hari: deret.length,
        intensitas,
        gjHari: t.gj / deret.length,
        literHari: t.liter / deret.length,
        kwhHari: t.kwh / deret.length,
        terbarukanHari: D.energy.terbarukan_kwh_harian,
        terbarukanPersen: bagi(D.energy.terbarukan_kwh_harian, t.kwh / deret.length) * 100,
        fuelRatio: bagi(t.liter, t.ton),
        penurunan: bagi(base - intensitas, base) * 100,
        baseline: base,
        target: D.energy.target_gj_per_ton,
      };
    },

    /** Nilai sebuah program penghematan per bulan, dari satuan asalnya. */
    programNilai(p) {
      const liter = p.hemat_liter_bulan || 0;
      const kwh   = p.hemat_kwh_bulan  || 0;
      return {
        liter, kwh,
        gj: liter * F.GJ_PER_LITER + kwh * F.GJ_PER_KWH,
        tco2e: liter * F.TON_CO2_PER_L + kwh * F.TON_CO2_PER_KWH,
        rupiah: liter * F.RP_PER_LITER + kwh * F.RP_PER_KWH,
      };
    },

    /* ---------- keselamatan ----------
       Frekuensi dihitung, tidak diketik: definisinya berbeda antar
       perusahaan, dan yang dipakai di sini harus dapat ditelusuri. */

    hseRingkas() {
      const h = D.hse, j = h.jam_kerja_kumulatif, p = h.pengali;
      const bobotTotal = h.smkp.reduce((a, e) => a + e.bobot, 0);
      const nilaiSmkp  = h.smkp.reduce((a, e) => a + e.capaian * e.bobot, 0) / bobotTotal;
      return {
        trifr: bagi(h.kejadian.recordable, j) * p,
        ltifr: bagi(h.kejadian.lost_time, j) * p,
        severity: bagi(h.kejadian.hari_hilang, j) * p,
        nearMiss: h.kejadian.near_miss,
        observasi: h.kejadian.observasi,
        insiden: h.kejadian.recordable,
        hariTanpaLti: h.hari_tanpa_lti,
        jamKerja: j,
        nilaiSmkp,
      };
    },

    /* ---------- pemeliharaan ---------- */

    maintenanceRingkas() {
      const m = D.maintenance;
      const b = m.jenis_bulanan[m.jenis_bulanan.length - 1];
      return {
        ...m,
        bulanIni: b,
        totalJam: b.preventif + b.korektif + b.breakdown,
        porsiPreventif: bagi(b.preventif, b.preventif + b.korektif + b.breakdown) * 100,
        terbuka: m.pekerjaan.filter((p) => p.status !== 'Closed').length,
        kritis: m.pekerjaan.filter((p) => p.prioritas === 'Critical' && p.status !== 'Closed').length,
      };
    },
  };

  /* ══════════════════════════════════════════════════════════════
     3. Komponen kecil
     ══════════════════════════════════════════════════════════════ */

  /** Kartu KPI. Tren dibiarkan kosong bila memang tidak ada pembandingnya. */
  function kartuKpi({ label, nilai, satuan, ikon, nada = 'aksen', tren, banding,
                      desimal = 0, tinggiBaik = true }) {
    const k = el('article', 'kpi');
    // Panahnya mengikuti arah angkanya; warnanya mengikuti apakah arah itu
    // baik. Keduanya tidak selalu sama.
    const naik = tren != null && tren > 0.05;
    const turun = tren != null && tren < -0.05;
    const arah = tren == null ? null : naik ? 'naik' : turun ? 'turun' : 'datar';
    const baik = tren == null ? null
      : (Math.abs(tren) <= 0.05 ? 'datar' : (naik === tinggiBaik ? 'naik' : 'turun'));
    const nilaiTeks = typeof nilai === 'string' ? nilai : n(nilai, desimal);

    k.innerHTML = `
      <span class="kpi-ikon t-${nada}"><svg viewBox="0 0 24 24" aria-hidden="true">${ikon}</svg></span>
      <div class="kpi-isi">
        <div class="kpi-label">${aman(label)}</div>
        <div class="kpi-nilai" ${typeof nilai === 'number' ? `data-hitung="${nilai}" data-desimal="${desimal}"` : ''}>${nilaiTeks}${
          satuan ? `<span class="kpi-satuan">${aman(satuan)}</span>` : ''}</div>
        ${arah ? `<div class="kpi-kaki">
          <span class="kpi-tren ${baik}"><svg viewBox="0 0 24 24" aria-hidden="true">${IKON[arah]}</svg>${n(Math.abs(tren), 1)}%</span>
          <span class="kpi-banding">${aman(banding || 'vs periode lalu')}</span>
        </div>` : banding ? `<div class="kpi-kaki"><span class="kpi-banding">${aman(banding)}</span></div>` : ''}
      </div>`;
    return k;
  }

  function isiKpi(wadah, daftar) {
    wadah.innerHTML = '';
    daftar.forEach((d) => wadah.appendChild(kartuKpi(d)));
    hidupkanAngka(wadah);
  }

  /** Angka menghitung naik saat pertama tampil — sekali, tidak berulang. */
  function hidupkanAngka(akar) {
    const kurangiGerak = matchMedia('(prefers-reduced-motion: reduce)').matches;
    $$('[data-hitung]', akar).forEach((e) => {
      const tujuan = parseFloat(e.dataset.hitung);
      const des = parseInt(e.dataset.desimal || '0', 10);
      const satuan = e.querySelector('.kpi-satuan');
      const ekor = satuan ? satuan.outerHTML : '';
      if (kurangiGerak || !Number.isFinite(tujuan)) { return; }

      const mulai = performance.now(), durasi = 850;
      (function bingkai(t) {
        const p = Math.min(1, (t - mulai) / durasi);
        const nilai = tujuan * (1 - Math.pow(1 - p, 3));
        e.innerHTML = n(nilai, des) + ekor;
        if (p < 1) requestAnimationFrame(bingkai);
      })(mulai);
    });
  }

  function lencana(teks) {
    const kunci = String(teks).toLowerCase().replace(/\s+/g, '-');
    return `<span class="lencana b-${kunci}">${aman(teks)}</span>`;
  }

  function toast(pesan, nada = 'info') {
    const wadah = $('#toast-wadah');
    const ikon = nada === 'sukses' ? IKON.centang : nada === 'bahaya' ? IKON.peringatan : IKON.grafik;
    const t = el('div', 'toast ' + (nada === 'info' ? '' : nada),
      `<svg viewBox="0 0 24 24" aria-hidden="true">${ikon}</svg><span>${aman(pesan)}</span>`);
    wadah.appendChild(t);
    setTimeout(() => {
      t.classList.add('keluar');
      t.addEventListener('animationend', () => t.remove(), { once: true });
    }, 3200);
  }

  function legenda(wadah, butir) {
    wadah.innerHTML = butir.map((b) =>
      `<span><i class="${b.putus ? 'putus' : ''}" style="${b.putus ? 'color' : 'background'}:${b.warna}"></i>${aman(b.nama)}</span>`
    ).join('');
  }

  /* ══════════════════════════════════════════════════════════════
     4. Tema
     ══════════════════════════════════════════════════════════════ */
  const KUNCI_TEMA = 'meh-tema';

  function pasangTema(tema) {
    document.documentElement.setAttribute('data-tema', tema);
    const sw = $('#btn-tema');
    if (sw) sw.setAttribute('aria-checked', String(tema === 'light'));
    try { localStorage.setItem(KUNCI_TEMA, tema); } catch (e) { /* mode privat */ }
    // Grafik mengambil warnanya dari variabel tema, jadi harus digambar ulang.
    setTimeout(() => grafikAktif.forEach((g) => g && g.gambarUlang()), 40);
  }

  function temaAwal() {
    try {
      const t = localStorage.getItem(KUNCI_TEMA);
      if (t === 'dark' || t === 'light') return t;
    } catch (e) { /* abaikan */ }
    return matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  }

  /* ══════════════════════════════════════════════════════════════
     5. Navigasi
     ══════════════════════════════════════════════════════════════ */
  const HALAMAN = ['dashboard', 'tools', 'equipment', 'energy', 'fleet',
                   'maintenance', 'hse', 'kpi', 'regulations'];

  const JUDUL = {
    dashboard: 'Dashboard', tools: 'Engineering Tools', equipment: 'Mining Equipment',
    energy: 'Energy Dashboard', fleet: 'Fleet & Productivity', maintenance: 'Maintenance',
    hse: 'HSE & SMKP', kpi: 'Engineering KPI', regulations: 'Regulations & Standards',
  };

  let grafikAktif = [];
  let sudahDigambar = {};

  function bukaHalaman(nama, dorong = true) {
    if (!HALAMAN.includes(nama)) nama = 'dashboard';

    $$('.halaman').forEach((h) => { h.hidden = h.dataset.halaman !== nama; });
    $$('[data-nav]').forEach((a) => a.classList.toggle('aktif', a.dataset.nav === nama));
    $('#sisi-modul').textContent = JUDUL[nama];
    document.title = JUDUL[nama] + ' — EQOHSEE Mining Engineering Hub';

    // Judul bilah atas dibaca dari halamannya sendiri, bukan dari daftar
    // terpisah: satu tempat untuk diubah, dan tidak mungkin melenceng.
    const hal = $(`[data-halaman="${nama}"]`);
    $('#judul-halaman').textContent = ($('h1', hal) || {}).textContent || JUDUL[nama];
    const ket = $('.hal-ket', hal);
    $('#subjudul-halaman').textContent = ket ? ket.textContent.trim() : '';

    if (dorong && location.hash !== '#' + nama) location.hash = nama;

    // Halaman digambar saat pertama dibuka, bukan seluruhnya di awal:
    // menggambar sembilan halaman sekaligus membuat pemuatan pertama
    // terasa berat padahal delapan di antaranya belum dilihat.
    if (!sudahDigambar[nama]) { (GAMBAR[nama] || (() => {}))(); sudahDigambar[nama] = true; }

    document.body.classList.remove('sisi-buka');
    $('#tirai').hidden = true;
    $('#btn-menu').setAttribute('aria-expanded', 'false');
    window.scrollTo({ top: 0 });
  }

  /* ══════════════════════════════════════════════════════════════
     6. Pencarian global
     ══════════════════════════════════════════════════════════════ */
  function indeksCari() {
    const butir = [];

    HALAMAN.forEach((h) => butir.push({
      kelompok: 'Halaman', judul: JUDUL[h],
      ket: 'Buka halaman ' + JUDUL[h], ikon: IKON.grafik, aksi: () => bukaHalaman(h),
      kata: h + ' ' + JUDUL[h],
    }));

    ALAT.forEach((a) => butir.push({
      kelompok: 'Alat Hitung', judul: a.nama, ket: a.rumus,
      ikon: IKON.kalkulator, aksi: () => { bukaHalaman('tools'); sorotAlat(a.id); },
      kata: a.nama + ' ' + a.kata + ' calculator kalkulator',
    }));

    D.fleet.unit.forEach((u) => butir.push({
      kelompok: 'Unit', judul: u.kode, ket: `${u.tipe} · ${u.kelas} · ${u.status}`,
      ikon: IKON.armada, aksi: () => bukaHalaman('fleet'),
      kata: `${u.kode} ${u.tipe} ${u.kelas} ${u.status} fleet armada unit`,
    }));

    D.regulations.forEach((r) => butir.push({
      kelompok: 'Regulasi', judul: r.judul, ket: `${r.kategori} · ${r.tahun} · ${r.penerbit}`,
      ikon: IKON.dokumen, aksi: () => bukaHalaman('regulations'),
      kata: `${r.judul} ${r.kategori} ${r.penerbit} ${r.ket} regulasi standar`,
    }));

    [['Energy Intensity', 'energy'], ['Fuel Ratio', 'kpi'], ['Physical Availability', 'kpi'],
     ['Mechanical Availability', 'kpi'], ['Utilization', 'kpi'], ['Stripping Ratio', 'kpi'],
     ['TRIFR', 'hse'], ['LTIFR', 'hse'], ['SMKP', 'hse'], ['MTBF', 'maintenance'],
     ['MTTR', 'maintenance'], ['PM Compliance', 'maintenance']]
      .forEach(([nama, hal]) => butir.push({
        kelompok: 'Indikator', judul: nama, ket: 'Lihat di ' + JUDUL[hal],
        ikon: IKON.grafik, aksi: () => bukaHalaman(hal), kata: nama + ' ' + hal,
      }));

    return butir;
  }

  function pasangCari() {
    const input = $('#cari-input');
    const kotak = $('#cari-hasil');
    const indeks = indeksCari();
    let hasil = [], sorot = -1;

    function tutup() {
      kotak.hidden = true; sorot = -1;
      input.setAttribute('aria-expanded', 'false');
    }

    function gambar(q) {
      const kunci = q.trim().toLowerCase();
      if (!kunci) { tutup(); return; }

      hasil = indeks.filter((b) => b.kata.toLowerCase().includes(kunci)).slice(0, 12);
      kotak.hidden = false;
      input.setAttribute('aria-expanded', 'true');

      if (!hasil.length) {
        kotak.innerHTML = `<p class="cari-kosong">Tidak ada yang cocok dengan “${aman(q)}”.</p>`;
        return;
      }

      let html = '', kelompokTerakhir = '';
      hasil.forEach((b, i) => {
        if (b.kelompok !== kelompokTerakhir) {
          html += `<div class="cari-kelompok">${aman(b.kelompok)}</div>`;
          kelompokTerakhir = b.kelompok;
        }
        html += `<button class="cari-item" role="option" data-i="${i}" aria-selected="false">
                   <svg viewBox="0 0 24 24" aria-hidden="true">${b.ikon}</svg>
                   <span>${aman(b.judul)}<small>${aman(b.ket)}</small></span>
                 </button>`;
      });
      kotak.innerHTML = html;
    }

    function pilih(i) {
      const b = hasil[i];
      if (!b) return;
      b.aksi(); input.value = ''; tutup(); input.blur();
    }

    input.addEventListener('input', () => gambar(input.value));
    input.addEventListener('focus', () => { if (input.value) gambar(input.value); });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { input.value = ''; tutup(); input.blur(); return; }
      if (!hasil.length || kotak.hidden) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        sorot = (sorot + (e.key === 'ArrowDown' ? 1 : -1) + hasil.length) % hasil.length;
        $$('.cari-item', kotak).forEach((b, i) => {
          b.classList.toggle('sorot', i === sorot);
          b.setAttribute('aria-selected', String(i === sorot));
          if (i === sorot) b.scrollIntoView({ block: 'nearest' });
        });
      } else if (e.key === 'Enter') {
        e.preventDefault(); pilih(sorot >= 0 ? sorot : 0);
      }
    });

    kotak.addEventListener('click', (e) => {
      const b = e.target.closest('.cari-item');
      if (b) pilih(parseInt(b.dataset.i, 10));
    });

    document.addEventListener('click', (e) => {
      if (!e.target.closest('.cari')) tutup();
    });

    // Garis miring memfokuskan pencarian, kebiasaan yang sudah umum.
    document.addEventListener('keydown', (e) => {
      if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
        e.preventDefault(); input.focus();
      }
    });
  }

  /* ══════════════════════════════════════════════════════════════
     7. Penggambar halaman
     ══════════════════════════════════════════════════════════════ */
  const GAMBAR = {};

  /* ---------- Dashboard ---------- */
  GAMBAR.dashboard = function () {
    const p = hitung.produksiRingkas();
    const a = hitung.armadaRingkas();
    const e = hitung.energiRingkas();
    const P = Grafik.palet();

    // Banner: satu kalimat pembuka dan tiga angka yang paling sering
    // ditanyakan lebih dulu.
    const jam = new Date().getHours();
    const sapa = jam < 11 ? 'Selamat pagi' : jam < 15 ? 'Selamat siang'
               : jam < 19 ? 'Selamat sore' : 'Selamat malam';
    $('#dash-banner').innerHTML = `
      <img class="banner-gambar" src="assets/images/mining-operation.jpg" alt="" loading="lazy">
      <div class="banner-isi">
        <h2>${sapa}, ${aman(D.perusahaan.pengguna.nama)} 👋</h2>
        <p>Produksi hari terakhir ${n(p.terakhir.ton)} ton terhadap target ${n(p.terakhir.target)} ton —
           capaian ${n(bagi(p.terakhir.ton, p.terakhir.target) * 100, 1)}%.
           ${a.beroperasi} dari ${a.jumlah} unit sedang beroperasi.</p>
        <a class="banner-aksi" href="#kpi" data-nav="kpi">
          Lihat Engineering KPI
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <div class="banner-angka">
          <div><b>${n(p.capaian, 1)}%</b><small>Capaian pekan ini</small></div>
          <div><b>${n(p.sr, 2)}</b><small>Stripping ratio</small></div>
          <div><b>${n(a.pa, 1)}%</b><small>Physical availability</small></div>
          <div><b>${n(Math.abs(e.penurunan), 1)}%</b><small>Intensitas ${e.penurunan >= 0 ? 'turun' : 'naik'} vs baseline</small></div>
        </div>
      </div>`;
    $('#dash-banner .banner-aksi').addEventListener('click', (ev) => {
      ev.preventDefault(); bukaHalaman('kpi');
    });

    $('#meta-periode').innerHTML =
      `<strong>${aman(D.perusahaan.situs)}</strong>${aman(D.perusahaan.periode)} · ${p.hari} hari tercatat`;

    isiKpi($('#dash-kpi'), [
      { label: 'Production', nilai: p.terakhir.ton, satuan: 'ton/hari', ikon: IKON.produksi,
        nada: 'aksen', tren: hitung.trenProduksi(), banding: 'vs rata-rata pekan ini' },
      { label: 'Fuel Consumption', nilai: a.fuelRate, desimal: 1, satuan: 'L/jam', ikon: IKON.bahanBakar,
        nada: 'info', banding: `${ringkas(a.liter)} L pada ${ringkas(a.kerja)} jam kerja` },
      { label: 'Energy Intensity', nilai: e.intensitas, desimal: 5, satuan: 'GJ/ton', ikon: IKON.energi,
        nada: 'ungu', tren: -e.penurunan, tinggiBaik: false, banding: 'vs baseline tahun lalu' },
      { label: 'Fleet Availability', nilai: a.pa, desimal: 1, satuan: '%', ikon: IKON.armada,
        nada: 'sukses', banding: `${a.beroperasi} dari ${a.jumlah} unit beroperasi` },
      { label: 'Mechanical Availability', nilai: a.ma, desimal: 1, satuan: '%', ikon: IKON.kunci,
        nada: 'sukses', banding: 'Kesiapan dari sisi mesin' },
      { label: 'Utilization', nilai: a.utilisasi, desimal: 1, satuan: '%', ikon: IKON.jam,
        nada: 'aksen', banding: 'Jam kerja ÷ jam terjadwal' },
    ]);

    gambarProduksi('7');

    // Status armada
    const status = ['Operating', 'Standby', 'Maintenance', 'Breakdown'];
    const warnaStatus = [P.seri[1], P.seri[2], P.seri[5], P.seri[3]];
    const dataStatus = status.map((s, i) => ({
      nama: s, nilai: D.fleet.unit.filter((u) => u.status === s).length, warna: warnaStatus[i],
    })).filter((d) => d.nilai > 0);

    grafikAktif.push(Grafik.donat($('#grafik-status'), {
      data: dataStatus, tinggi: 230,
      tengah: String(a.jumlah), tengahKet: 'unit terdaftar',
    }));
    legenda($('#legenda-status'), dataStatus.map((d) => ({ nama: `${d.nama} (${d.nilai})`, warna: d.warna })));

    // Tren intensitas ringkas
    grafikAktif.push(Grafik.garis($('#grafik-energi-mini'), {
      label: e.deret.map((d) => tglPendek(d.tgl)),
      seri: [{ nama: 'Intensitas', data: e.deret.map((d) => d.intensitas), warna: P.seri[4] }],
      acuan: e.target, acuanLabel: 'Target', satuan: 'GJ/ton', desimal: 4, tinggi: 200,
    }));

    // Perhatian hari ini — disusun dari keadaan data, bukan diketik
    const m = hitung.maintenanceRingkas();
    const perhatian = [];
    const boros = [...D.fleet.unit].sort((x, y) => hitung.fuelRate(y) - hitung.fuelRate(x))[0];

    if (m.kritis > 0) perhatian.push({ nada: 'bahaya', ikon: IKON.peringatan,
      judul: `${m.kritis} pekerjaan berprioritas Critical belum ditutup`,
      ket: 'Lihat daftar pekerjaan pada halaman Maintenance.' });

    const rusak = D.fleet.unit.filter((u) => u.status === 'Breakdown');
    if (rusak.length) perhatian.push({ nada: 'bahaya', ikon: IKON.armada,
      judul: `${rusak.length} unit berstatus Breakdown`,
      ket: rusak.map((u) => u.kode).join(', ') + ' — MA di bawah armada.' });

    perhatian.push({ nada: 'aksen', ikon: IKON.bahanBakar,
      judul: `${boros.kode} paling haus: ${n(hitung.fuelRate(boros), 1)} L/jam`,
      ket: `${boros.tipe} · rata-rata armada ${n(a.fuelRate, 1)} L/jam.` });

    perhatian.push({ nada: e.penurunan >= 0 ? 'sukses' : 'bahaya', ikon: IKON.energi,
      judul: `Intensitas energi ${e.penurunan >= 0 ? 'turun' : 'naik'} ${n(Math.abs(e.penurunan), 1)}% dari baseline`,
      ket: `Sekarang ${n(e.intensitas, 5)} GJ/ton, sasaran ${n(e.target, 4)} GJ/ton.` });

    perhatian.push({ nada: p.capaian >= 100 ? 'sukses' : 'aksen', ikon: IKON.produksi,
      judul: `Capaian produksi ${n(p.capaian, 1)}% terhadap target`,
      ket: `${ringkas(p.ton)} ton dari sasaran ${ringkas(p.target)} ton pekan ini.` });

    $('#dash-perhatian').innerHTML = perhatian.map((x) => `
      <li>
        <span class="perhatian-ikon t-${x.nada}"><svg viewBox="0 0 24 24" aria-hidden="true">${x.ikon}</svg></span>
        <span><strong>${aman(x.judul)}</strong><small>${aman(x.ket)}</small></span>
      </li>`).join('');
  };

  let grafikProduksi = null;
  function gambarProduksi(rentang) {
    const P = Grafik.palet();
    const wadah = $('#grafik-produksi');
    let label, aktual, target, desimal = 0, satuan = 'ton';

    if (rentang === '7') {
      const h = D.production.harian;
      label = h.map((x) => tglPendek(x.tgl));
      aktual = h.map((x) => x.ton);
      target = h.map((x) => x.target);
    } else if (rentang === '30') {
      // Tiga puluh hari diringkas menjadi mingguan; tiga puluh batang pada
      // lebar kartu ini hanya menjadi pagar yang tidak terbaca.
      const b = D.production.bulanan[D.production.bulanan.length - 1];
      const rata = b.ton / 30;
      label = ['Pekan 1', 'Pekan 2', 'Pekan 3', 'Pekan 4'];
      const pola = [0.96, 1.03, 0.99, 1.02];
      aktual = pola.map((x) => Math.round(rata * 7 * x));
      target = pola.map(() => Math.round(D.production.target_harian_ton * 7));
    } else {
      const b = D.production.bulanan;
      label = b.map((x) => x.label);
      aktual = b.map((x) => x.ton);
      target = b.map((x) => x.target);
    }

    if (grafikProduksi) {
      const i = grafikAktif.indexOf(grafikProduksi);
      if (i >= 0) grafikAktif.splice(i, 1);
    }
    grafikProduksi = Grafik.batang(wadah, {
      label,
      seri: [
        { nama: 'Aktual', data: aktual, warna: P.seri[0] },
        { nama: 'Target', data: target, warna: 'rgba(138,148,166,.45)' },
      ],
      satuan, desimal, tinggi: 262,
    });
    grafikAktif.push(grafikProduksi);
    legenda($('#legenda-produksi'), [
      { nama: 'Aktual', warna: P.seri[0] }, { nama: 'Target', warna: 'rgba(138,148,166,.6)' },
    ]);
  }

  /* ---------- Energy ---------- */
  GAMBAR.energy = function () {
    const e = hitung.energiRingkas();
    const P = Grafik.palet();

    isiKpi($('#energy-kpi'), [
      { label: 'Total Energy', nilai: e.gj, desimal: 1, satuan: 'GJ', ikon: IKON.energi, nada: 'aksen',
        banding: `${e.hari} hari · ${ringkas(e.ton)} ton produksi` },
      { label: 'Energy Consumption', nilai: e.gjHari, desimal: 1, satuan: 'GJ/hari', ikon: IKON.grafik, nada: 'info',
        banding: 'Rata-rata harian' },
      { label: 'Energy Intensity', nilai: e.intensitas, desimal: 5, satuan: 'GJ/ton', ikon: IKON.produksi, nada: 'ungu',
        tren: -e.penurunan, tinggiBaik: false, banding: `Baseline ${n(e.baseline, 4)}` },
      { label: 'Diesel Consumption', nilai: e.literHari, satuan: 'L/hari', ikon: IKON.bahanBakar, nada: 'aksen',
        banding: `${ringkas(e.liter)} L pada periode ini` },
      { label: 'Electricity Consumption', nilai: e.kwhHari, satuan: 'kWh/hari', ikon: IKON.listrik, nada: 'info',
        banding: `${ringkas(e.kwh)} kWh pada periode ini` },
      { label: 'Renewable Energy', nilai: e.terbarukanPersen, desimal: 1, satuan: '%', ikon: IKON.daun, nada: 'sukses',
        banding: `${n(e.terbarukanHari)} kWh/hari dari PLTS site` },
    ]);

    grafikAktif.push(Grafik.garis($('#grafik-energi'), {
      label: e.deret.map((d) => tglPendek(d.tgl)),
      seri: [
        { nama: 'Aktual',   data: e.deret.map((d) => d.intensitas), warna: P.seri[0] },
        { nama: 'Baseline', data: e.deret.map(() => e.baseline), warna: 'rgba(138,148,166,.55)', isi: false, putus: true },
      ],
      acuan: e.target, acuanLabel: `Target ${n(e.target, 4)}`,
      satuan: 'GJ/ton', desimal: 4, tinggi: 268,
    }));
    legenda($('#legenda-energi'), [
      { nama: 'Aktual', warna: P.seri[0] },
      { nama: 'Baseline', warna: 'rgba(138,148,166,.7)', putus: true },
      { nama: 'Target', warna: P.seri[3], putus: true },
    ]);

    // Kartu penghematan
    const hem = D.energy.penghematan;
    const nadaStatus = hem.status === 'GOOD' ? 'sukses' : 'aksen';
    $('#kartu-hemat').innerHTML = `
      <div class="kartu-kepala"><h2 style="color:#fff">Energy Saving</h2></div>
      <div style="font-size:44px;font-weight:800;letter-spacing:-.03em;color:var(--aksen);line-height:1;
                  font-variant-numeric:tabular-nums">${n(hem.persen, 1)}<span style="font-size:20px">%</span></div>
      <p style="font-size:12.5px;color:#9FB0C4;margin-top:10px;line-height:1.6">${aman(hem.keterangan)}</p>
      <div style="margin-top:16px">${lencana(hem.status === 'GOOD' ? 'Operating' : 'Maintenance')
        .replace('b-operating', 'b-operating').replace('>Operating<', '>' + aman(hem.status) + '<')
        .replace('>Maintenance<', '>' + aman(hem.status) + '<')}</div>
      <div style="margin-top:18px;padding-top:16px;border-top:1px solid rgba(255,255,255,.1)">
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#9FB0C4">
          <span>Baseline</span><b style="color:#fff">${n(e.baseline, 4)} GJ/ton</b></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#9FB0C4;margin-top:7px">
          <span>Sekarang</span><b style="color:#fff">${n(e.intensitas, 5)} GJ/ton</b></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#9FB0C4;margin-top:7px">
          <span>Target</span><b style="color:var(--aksen)">${n(e.target, 4)} GJ/ton</b></div>
      </div>`;
    void nadaStatus;

    // Bauran energi
    const bauran = [
      { nama: 'Solar', nilai: e.liter * F.GJ_PER_LITER, warna: P.seri[0] },
      { nama: 'Listrik', nilai: e.kwh * F.GJ_PER_KWH, warna: P.seri[2] },
    ];
    grafikAktif.push(Grafik.donat($('#grafik-bauran'), {
      data: bauran, tinggi: 226, desimal: 1,
      tengah: n(e.gj, 0), tengahKet: 'GJ total',
    }));
    legenda($('#legenda-bauran'), bauran.map((b) => ({
      nama: `${b.nama} — ${n(bagi(b.nilai, e.gj) * 100, 1)}%`, warna: b.warna,
    })));

    // Program penghematan
    $('#tabel-hemat tbody').innerHTML = hem.program.map((p) => {
      const v = hitung.programNilai(p);
      const satuanHemat = p.hemat_liter_bulan
        ? `${n(p.hemat_liter_bulan)} L` : `${n(p.hemat_kwh_bulan)} kWh`;
      return `<tr>
        <td><span class="tabel-utama">${aman(p.judul)}</span>
            <span class="tabel-sub">${n(v.gj, 1)} GJ · ${n(v.tco2e, 2)} tCO₂e per bulan</span></td>
        <td class="num">${satuanHemat}</td>
        <td class="num">${rupiah(v.rupiah)}</td>
        <td>${lencana(p.status === 'Selesai' ? 'Operating' : p.status === 'Berjalan' ? 'Standby' : 'Low')
              .replace(/>(Operating|Standby|Low)</, '>' + aman(p.status) + '<')}</td>
      </tr>`;
    }).join('');
  };

  /* ---------- Fleet ---------- */
  let urutFleet = { kolom: 'fuelRate', turun: true };

  GAMBAR.fleet = function () {
    const a = hitung.armadaRingkas();
    const p = hitung.produksiRingkas();
    const pr = D.fleet.produktivitas;
    const P = Grafik.palet();

    isiKpi($('#fleet-kpi'), [
      { label: 'Physical Availability', nilai: a.pa, desimal: 1, satuan: '%', ikon: IKON.armada, nada: 'sukses',
        banding: '(Kerja + standby) ÷ terjadwal' },
      { label: 'Mechanical Availability', nilai: a.ma, desimal: 1, satuan: '%', ikon: IKON.kunci, nada: 'sukses',
        banding: 'Kerja ÷ (kerja + perbaikan)' },
      { label: 'Use of Availability', nilai: a.ua, desimal: 1, satuan: '%', ikon: IKON.jam, nada: 'info',
        banding: 'Kerja ÷ (kerja + standby)' },
      { label: 'Utilization', nilai: a.utilisasi, desimal: 1, satuan: '%', ikon: IKON.grafik, nada: 'aksen',
        banding: 'Kerja ÷ jam terjadwal' },
      { label: 'Productivity', nilai: bagi(p.ton, a.kerja), desimal: 1, satuan: 'ton/jam', ikon: IKON.produksi, nada: 'ungu',
        banding: `${ringkas(p.ton)} ton ÷ ${ringkas(a.kerja)} jam` },
      { label: 'Fuel Ratio', nilai: bagi(a.liter, p.ton), desimal: 3, satuan: 'L/ton', ikon: IKON.bahanBakar, nada: 'aksen',
        banding: 'Solar ÷ produksi' },
    ]);

    gambarTabelFleet();

    // Bahan bakar per kelas
    const kelas = [...new Set(D.fleet.unit.map((u) => u.kelas))];
    grafikAktif.push(Grafik.batang($('#grafik-fuel-kelas'), {
      label: kelas,
      seri: [{
        nama: 'L/jam',
        data: kelas.map((k) => {
          const u = D.fleet.unit.filter((x) => x.kelas === k);
          return bagi(u.reduce((s, x) => s + x.liter, 0), u.reduce((s, x) => s + x.jam_kerja, 0));
        }),
        warna: P.seri[0],
      }],
      satuan: 'L/jam', desimal: 1, tinggi: 236,
    }));

    $('#fleet-produktivitas').innerHTML = [
      ['Cycle Time', n(pr.cycle_time_menit, 1), 'menit'],
      ['Hauling Distance', n(pr.jarak_hauling_km, 1), 'km'],
      ['Truck Factor', n(pr.truck_factor, 2), ''],
      ['Match Factor', n(pr.match_factor, 2), ''],
      ['Kecepatan Rata-rata', n(pr.kecepatan_rata_kmh, 1), 'km/jam'],
      ['Stripping Ratio', n(p.sr, 2), 'BCM/ton'],
    ].map(([l, v, s]) => `
      <div>
        <div class="kpi-label">${aman(l)}</div>
        <div style="font-size:21px;font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:-.02em">
          ${v}${s ? `<span class="kpi-satuan">${aman(s)}</span>` : ''}</div>
      </div>`).join('');
  };

  function gambarTabelFleet() {
    const saring = $('#hal-fleet .saring-btn.aktif').dataset.status;
    let unit = D.fleet.unit.slice();
    if (saring !== 'semua') unit = unit.filter((u) => u.status === saring);

    const nilai = (u, k) => ({
      kode: u.kode, tipe: u.tipe, status: u.status, hm: u.hm,
      pa: hitung.pa(u), ma: hitung.ma(u), utilisasi: hitung.utilisasi(u),
      fuelRate: hitung.fuelRate(u),
    }[k]);

    unit.sort((x, y) => {
      const a = nilai(x, urutFleet.kolom), b = nilai(y, urutFleet.kolom);
      const c = typeof a === 'string' ? a.localeCompare(b) : a - b;
      return urutFleet.turun ? -c : c;
    });

    const tbody = $('#tabel-fleet tbody');
    if (!unit.length) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:34px;color:var(--teks-lemah)">
        Tidak ada unit berstatus ${aman(saring)}.</td></tr>`;
      return;
    }

    tbody.innerHTML = unit.map((u) => `
      <tr>
        <td><span class="tabel-utama">${aman(u.kode)}</span><span class="tabel-sub">${aman(u.kelas)}</span></td>
        <td>${aman(u.tipe)}</td>
        <td>${lencana(u.status)}</td>
        <td class="num">${n(hitung.pa(u), 1)}%</td>
        <td class="num">${n(hitung.ma(u), 1)}%</td>
        <td class="num">${n(hitung.utilisasi(u), 1)}%</td>
        <td class="num">${n(hitung.fuelRate(u), 1)} L/jam</td>
        <td class="num">${n(u.hm)}</td>
      </tr>`).join('');

    $$('#tabel-fleet th[data-urut]').forEach((th) => {
      th.classList.toggle('urut-naik',  th.dataset.urut === urutFleet.kolom && !urutFleet.turun);
      th.classList.toggle('urut-turun', th.dataset.urut === urutFleet.kolom &&  urutFleet.turun);
    });
  }

  /* ---------- Equipment ---------- */
  GAMBAR.equipment = function () {
    const a = hitung.armadaRingkas();
    const kelas = [...new Set(D.fleet.unit.map((u) => u.kelas))];

    isiKpi($('#eq-kpi'), [
      { label: 'Unit Terdaftar', nilai: a.jumlah, satuan: 'unit', ikon: IKON.armada, nada: 'aksen',
        banding: `${kelas.length} kelas alat` },
      { label: 'Sedang Beroperasi', nilai: a.beroperasi, satuan: 'unit', ikon: IKON.centang, nada: 'sukses',
        banding: `${n(bagi(a.beroperasi, a.jumlah) * 100, 0)}% dari armada` },
      { label: 'Jam Kerja Armada', nilai: a.kerja, satuan: 'jam', ikon: IKON.jam, nada: 'info',
        banding: `${ringkas(a.terjadwal)} jam terjadwal` },
      { label: 'Solar Terpakai', nilai: a.liter, satuan: 'L', ikon: IKON.bahanBakar, nada: 'ungu',
        banding: `${rupiah(a.liter * F.RP_PER_LITER)} pada periode ini` },
    ]);

    // Acuan per kelas: sebuah unit dinilai terhadap rata-rata kelompoknya
    // sendiri. Excavator dan dump truck memang berbeda haus, dan
    // mengurutkan liter mentah akan selalu menaruh yang bertenaga besar
    // di puncak daftar boros — yang tidak memberi tahu apa-apa.
    const acuan = {};
    kelas.forEach((k) => {
      const u = D.fleet.unit.filter((x) => x.kelas === k);
      acuan[k] = bagi(u.reduce((s, x) => s + x.liter, 0), u.reduce((s, x) => s + x.jam_kerja, 0));
    });

    $('#eq-kisi').innerHTML = D.fleet.unit.map((u) => {
      const fr = hitung.fuelRate(u);
      const rasio = bagi(fr, acuan[u.kelas]);
      const nada = rasio <= 1.05 ? 'sukses' : rasio <= 1.20 ? 'aksen' : 'bahaya';
      const label = rasio <= 1.05 ? 'Efficient' : rasio <= 1.20 ? 'Monitor' : 'High Consumption';
      return `
      <article class="kartu unit-kartu">
        <div class="unit-atas">
          <div>
            <div class="unit-kode">${aman(u.kode)}</div>
            <div class="unit-tipe">${aman(u.tipe)} · ${aman(u.kelas)}</div>
          </div>
          ${lencana(u.status)}
        </div>
        <div class="unit-angka">
          <div><b style="color:var(--${nada === 'sukses' ? 'sukses' : nada === 'aksen' ? 'aksen' : 'bahaya'})">${n(fr, 1)}</b><small>L/jam</small></div>
          <div><b>${n(hitung.ma(u), 1)}%</b><small>MA</small></div>
          <div><b>${n(u.hm)}</b><small>HM</small></div>
        </div>
        <div style="margin-top:14px">
          <div class="bilah"><div class="bilah-isi ${nada === 'sukses' ? 'sukses' : nada === 'bahaya' ? 'bahaya' : ''}"
               style="width:${Math.max(6, Math.min(100, rasio / 1.5 * 100))}%"></div></div>
          <div style="display:flex;justify-content:space-between;margin-top:7px;font-size:11px;color:var(--teks-lemah)">
            <span>${aman(label)}</span><span>Acuan ${aman(u.kelas)}: ${n(acuan[u.kelas], 1)} L/jam</span>
          </div>
        </div>
      </article>`;
    }).join('');
  };

  /* ---------- Maintenance ---------- */
  GAMBAR.maintenance = function () {
    const m = hitung.maintenanceRingkas();
    const P = Grafik.palet();

    isiKpi($('#mt-kpi'), [
      { label: 'PM Compliance', nilai: m.pm_compliance_persen, desimal: 1, satuan: '%', ikon: IKON.centang, nada: 'sukses',
        banding: 'Pemeliharaan terencana yang terlaksana' },
      { label: 'MTBF', nilai: m.mtbf_jam, desimal: 1, satuan: 'jam', ikon: IKON.jam, nada: 'info',
        banding: 'Rata-rata antar kerusakan' },
      { label: 'MTTR', nilai: m.mttr_jam, desimal: 1, satuan: 'jam', ikon: IKON.kunci, nada: 'aksen',
        banding: 'Rata-rata waktu perbaikan' },
      { label: 'Breakdown Frequency', nilai: m.breakdown_per_bulan, satuan: '/bulan', ikon: IKON.peringatan, nada: 'bahaya',
        banding: `${m.terbuka} pekerjaan belum ditutup` },
      { label: 'Maintenance Cost', nilai: rupiah(m.biaya_bulan_rp), ikon: IKON.uang, nada: 'ungu',
        banding: 'Perkiraan bulan berjalan' },
      { label: 'Porsi Preventif', nilai: m.porsiPreventif, desimal: 1, satuan: '%', ikon: IKON.grafik, nada: 'sukses',
        banding: `dari ${ringkas(m.totalJam)} jam pemeliharaan` },
    ]);

    grafikAktif.push(Grafik.batang($('#grafik-maintenance'), {
      label: m.jenis_bulanan.map((x) => x.label),
      seri: [
        { nama: 'Preventif', data: m.jenis_bulanan.map((x) => x.preventif), warna: P.seri[1] },
        { nama: 'Korektif',  data: m.jenis_bulanan.map((x) => x.korektif),  warna: P.seri[0] },
        { nama: 'Breakdown', data: m.jenis_bulanan.map((x) => x.breakdown), warna: P.seri[3] },
      ],
      tumpuk: true, satuan: 'jam', tinggi: 262,
    }));
    legenda($('#legenda-maintenance'), [
      { nama: 'Preventif', warna: P.seri[1] }, { nama: 'Korektif', warna: P.seri[0] },
      { nama: 'Breakdown', warna: P.seri[3] },
    ]);

    const komposisi = [
      { nama: 'Preventif', nilai: m.bulanIni.preventif, warna: P.seri[1] },
      { nama: 'Korektif',  nilai: m.bulanIni.korektif,  warna: P.seri[0] },
      { nama: 'Breakdown', nilai: m.bulanIni.breakdown, warna: P.seri[3] },
    ];
    grafikAktif.push(Grafik.donat($('#grafik-mt-donat'), {
      data: komposisi, tinggi: 226,
      tengah: n(m.porsiPreventif, 0) + '%', tengahKet: 'preventif',
    }));
    legenda($('#legenda-mt-donat'), komposisi.map((k) => ({ nama: `${k.nama} — ${n(k.nilai)} jam`, warna: k.warna })));

    gambarTabelMaintenance();
  };

  function gambarTabelMaintenance() {
    const saring = $('#hal-maintenance .saring-btn.aktif').dataset.prioritas;
    let baris = D.maintenance.pekerjaan.slice();
    if (saring !== 'semua') baris = baris.filter((p) => p.prioritas === saring);

    const tbody = $('#tabel-maintenance tbody');
    if (!baris.length) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:34px;color:var(--teks-lemah)">
        Tidak ada pekerjaan berprioritas ${aman(saring)}.</td></tr>`;
      return;
    }

    const urutan = { Critical: 0, High: 1, Medium: 2, Low: 3 };
    baris.sort((a, b) => urutan[a.prioritas] - urutan[b.prioritas]);

    tbody.innerHTML = baris.map((p) => `
      <tr>
        <td><span class="tabel-utama">${aman(p.unit)}</span></td>
        <td>${aman(p.masalah)}</td>
        <td>${lencana(p.prioritas)}</td>
        <td>${aman(p.status)}</td>
        <td>${aman(p.pic)}</td>
        <td class="num">${new Date(p.tanggal + 'T00:00:00').toLocaleDateString('id-ID',
            { day: '2-digit', month: 'short', year: 'numeric' })}</td>
      </tr>`).join('');
  }

  /* ---------- HSE ---------- */
  GAMBAR.hse = function () {
    const h = hitung.hseRingkas();

    isiKpi($('#hse-kpi'), [
      { label: 'TRIFR', nilai: h.trifr, desimal: 2, ikon: IKON.perisai, nada: 'aksen',
        banding: `${h.insiden} kejadian per ${ringkas(h.jamKerja)} jam kerja` },
      { label: 'LTIFR', nilai: h.ltifr, desimal: 2, ikon: IKON.peringatan, nada: 'bahaya',
        banding: `${D.hse.kejadian.lost_time} kejadian dengan hari hilang` },
      { label: 'Severity Rate', nilai: h.severity, desimal: 1, ikon: IKON.jam, nada: 'ungu',
        banding: `${D.hse.kejadian.hari_hilang} hari kerja hilang` },
      { label: 'Near Miss', nilai: h.nearMiss, ikon: IKON.grafik, nada: 'info',
        banding: 'Terlaporkan pada periode berjalan' },
      { label: 'Safety Observation', nilai: h.observasi, ikon: IKON.centang, nada: 'sukses',
        banding: `${n(bagi(h.observasi, h.nearMiss), 1)}× jumlah near miss` },
      { label: 'Hari Tanpa LTI', nilai: h.hariTanpaLti, satuan: 'hari', ikon: IKON.perisai, nada: 'sukses',
        banding: 'Sejak kejadian terakhir' },
    ]);

    // SMKP — bilah per elemen, ditutup nilai berbobot
    $('#smkp-batang').innerHTML = D.hse.smkp.map((e) => {
      const nada = e.capaian >= 85 ? 'sukses' : e.capaian >= 70 ? '' : 'bahaya';
      return `
      <div class="smkp-baris">
        <div class="smkp-atas">
          <span class="smkp-nama"><span class="smkp-no">${String(e.no).padStart(2, '0')}</span>${aman(e.elemen)}</span>
          <span class="smkp-angka">${n(e.capaian)}%<span class="smkp-bobot">bobot ${e.bobot}%</span></span>
        </div>
        <div class="bilah"><div class="bilah-isi ${nada}" data-lebar="${e.capaian}"></div></div>
      </div>`;
    }).join('');

    const nilai = h.nilaiSmkp;
    const tingkat = nilai >= 90 ? 'Sangat Baik' : nilai >= 80 ? 'Baik' : nilai >= 70 ? 'Cukup' : 'Perlu Perbaikan';
    $('#smkp-total').innerHTML = `
      <div>
        <div class="kpi-label">Nilai Penerapan SMKP</div>
        <div class="angka-besar" style="color:var(--aksen)">${n(nilai, 1)}<span class="kpi-satuan">dari 100</span></div>
      </div>
      <div style="text-align:right">
        <div class="kpi-label">Tingkat</div>
        <strong style="font-size:14px">${tingkat}</strong>
      </div>`;

    // Bilah dianimasikan setelah masuk susunan, supaya lebarnya terlihat tumbuh
    requestAnimationFrame(() => $$('#smkp-batang .bilah-isi').forEach((b) => {
      b.style.width = b.dataset.lebar + '%';
    }));

    // Piramida pelaporan
    const tingkatan = [
      { nama: 'Safety Observation', nilai: h.observasi, nada: 'sukses' },
      { nama: 'Near Miss',          nilai: h.nearMiss,  nada: 'info' },
      { nama: 'Recordable Injury',  nilai: D.hse.kejadian.recordable, nada: 'aksen' },
      { nama: 'Lost Time Injury',   nilai: D.hse.kejadian.lost_time,  nada: 'bahaya' },
    ];
    const maks = Math.max(...tingkatan.map((t) => t.nilai));
    $('#hse-piramida').innerHTML = tingkatan.map((t, i) => `
      <div class="piramida-tingkat t-${t.nada}" style="width:${100 - i * 16}%">
        <span>${aman(t.nama)}</span><b>${n(t.nilai)}</b>
      </div>`).join('');
    void maks;
  };

  /* ---------- KPI ---------- */
  GAMBAR.kpi = function () {
    const a = hitung.armadaRingkas();
    const p = hitung.produksiRingkas();
    const e = hitung.energiRingkas();
    const pr = D.fleet.produktivitas;

    const nilai = {
      pa: [a.pa, 1], ma: [a.ma, 1], ua: [a.ua, 1], utilisasi: [a.utilisasi, 1],
      produktivitas: [bagi(p.ton, a.kerja), 1], fuelRatio: [bagi(a.liter, p.ton), 3],
      produksi: [p.tonHari, 0], sr: [p.sr, 2], cycle: [pr.cycle_time_menit, 1],
      jarak: [pr.jarak_hauling_km, 1], truckFactor: [pr.truck_factor, 2],
      payload: [bagi(D.fleet.unit.filter((u) => u.payload).reduce((s, u) => s + u.payload, 0),
                     D.fleet.unit.filter((u) => u.payload).length), 1],
      energiTotal: [e.gjHari, 1], intensitas: [e.intensitas, 5],
      fuelRate: [a.fuelRate, 1], listrik: [e.kwhHari, 0], hemat: [e.penurunan, 1],
    };

    $('#kpi-kelompok').innerHTML = Object.entries(K).map(([kelompok, daftar]) => `
      <section class="kpi-kelompok">
        <h2 class="kpi-kelompok-judul">${aman(kelompok)}</h2>
        <div class="kisi-kpi">
          ${daftar.map((d) => {
            const [v, des] = nilai[d.kunci] || [0, 0];
            return `<article class="kartu kpi-def">
              <div class="kpi-def-nama">${aman(d.nama)}</div>
              <div class="kpi-def-nilai">${n(v, des)}${d.satuan ? `<span class="kpi-satuan">${aman(d.satuan)}</span>` : ''}</div>
              <div class="kpi-def-rumus">${aman(d.rumus)}</div>
            </article>`;
          }).join('')}
        </div>
      </section>`).join('');
  };

  /* ---------- Regulations ---------- */
  let regKategori = 'Semua';

  GAMBAR.regulations = function () {
    const kategori = ['Semua', ...new Set(D.regulations.map((r) => r.kategori))];
    $('#reg-saring').innerHTML = kategori.map((k) =>
      `<button class="saring-btn ${k === regKategori ? 'aktif' : ''}" data-kategori="${aman(k)}">${aman(k)}</button>`
    ).join('');
    gambarReg();
  };

  function gambarReg() {
    const daftar = regKategori === 'Semua'
      ? D.regulations : D.regulations.filter((r) => r.kategori === regKategori);

    $('#reg-kisi').innerHTML = daftar.map((r) => `
      <article class="kartu reg-kartu">
        <div class="reg-atas">
          <span class="reg-kategori">${aman(r.kategori)}</span>
          <span class="reg-tahun">${r.tahun}</span>
        </div>
        <h3>${aman(r.judul)}</h3>
        <p class="reg-penerbit">${aman(r.penerbit)}</p>
        <p class="reg-ket">${aman(r.ket)}</p>
        <div class="reg-aksi">
          <a class="btn-lihat" href="${aman(r.sumber)}" target="_blank" rel="noopener noreferrer">
            Lihat sumber resmi
            <svg viewBox="0 0 24 24" aria-hidden="true" style="width:14px;height:14px"><path d="M9 5l7 7-7 7"/></svg>
          </a>
        </div>
      </article>`).join('');
  }

  /* ══════════════════════════════════════════════════════════════
     8. Alat hitung
     ══════════════════════════════════════════════════════════════ */
  const ALAT = [
    {
      id: 'fuel-consumption', nama: 'Fuel Consumption Calculator', nada: 'aksen', ikon: IKON.bahanBakar,
      rumus: 'Fuel Consumption = Fuel Used ÷ Operating Hours',
      kata: 'fuel bahan bakar konsumsi liter jam',
      isian: [
        { k: 'liter', label: 'Fuel Used (Liter)', nilai: 23180 },
        { k: 'jam',   label: 'Operating Hours',   nilai: 604 },
      ],
      hasil: 'Liter per jam', satuan: 'L/jam', desimal: 2,
      hitung: (v) => v.jam > 0 ? v.liter / v.jam : null,
      catatan: (v, h) => h == null ? 'Jam operasi harus lebih dari nol.'
        : `Rata-rata armada saat ini ${n(hitung.armadaRingkas().fuelRate, 1)} L/jam.`,
    },
    {
      id: 'fuel-ratio', nama: 'Fuel Ratio Calculator', nada: 'aksen', ikon: IKON.produksi,
      rumus: 'Fuel Ratio = Fuel Consumption ÷ Production',
      kata: 'fuel ratio rasio liter ton produksi',
      isian: [
        { k: 'liter', label: 'Fuel Consumption (L)', nilai: 15380 },
        { k: 'ton',   label: 'Production (Ton)',     nilai: 12450 },
      ],
      hasil: 'Liter per ton', satuan: 'L/ton', desimal: 4,
      hitung: (v) => v.ton > 0 ? v.liter / v.ton : null,
      catatan: (v, h) => h == null ? 'Produksi harus lebih dari nol.'
        : `Setara ${n(h * F.GJ_PER_LITER, 5)} GJ/ton dari solar saja.`,
    },
    {
      id: 'energy-intensity', nama: 'Energy Intensity Calculator', nada: 'ungu', ikon: IKON.energi,
      rumus: 'Energy Intensity = Total Energy ÷ Production',
      kata: 'energy intensity intensitas energi gj ton',
      isian: [
        { k: 'gj',  label: 'Total Energy (GJ)', nilai: 629 },
        { k: 'ton', label: 'Production (Ton)',  nilai: 12450 },
      ],
      hasil: 'Gigajoule per ton', satuan: 'GJ/ton', desimal: 5,
      hitung: (v) => v.ton > 0 ? v.gj / v.ton : null,
      catatan: (v, h) => {
        if (h == null) return 'Produksi harus lebih dari nol.';
        const b = D.energy.baseline_gj_per_ton;
        const d = (b - h) / b * 100;
        return `Terhadap baseline ${n(b, 4)} GJ/ton: ${d >= 0 ? 'turun' : 'naik'} ${n(Math.abs(d), 1)}%.`;
      },
      nadaCatatan: (v, h) => h == null ? 'bahaya'
        : h <= D.energy.baseline_gj_per_ton ? 'sukses' : 'bahaya',
    },
    {
      id: 'availability', nama: 'Availability Calculator', nada: 'sukses', ikon: IKON.armada,
      rumus: 'Availability = (Scheduled − Downtime) ÷ Scheduled × 100',
      kata: 'availability ketersediaan downtime jam',
      isian: [
        { k: 'terjadwal', label: 'Scheduled Hours', nilai: 720 },
        { k: 'downtime',  label: 'Downtime Hours',  nilai: 62 },
      ],
      hasil: 'Availability', satuan: '%', desimal: 2,
      hitung: (v) => {
        if (v.terjadwal <= 0) return null;
        if (v.downtime > v.terjadwal) return null;
        return (v.terjadwal - v.downtime) / v.terjadwal * 100;
      },
      catatan: (v, h) => {
        if (v.terjadwal <= 0) return 'Jam terjadwal harus lebih dari nol.';
        if (v.downtime > v.terjadwal) return 'Downtime tidak boleh melebihi jam terjadwal.';
        return h >= 90 ? 'Di atas 90% — dalam batas yang lazim dituju.'
                       : 'Di bawah 90% — telusuri penyebab downtime terbesarnya.';
      },
      nadaCatatan: (v, h) => h == null ? 'bahaya' : h >= 90 ? 'sukses' : '',
    },
    {
      id: 'utilization', nama: 'Utilization Calculator', nada: 'info', ikon: IKON.jam,
      rumus: 'Utilization = Operating Hours ÷ Available Hours × 100',
      kata: 'utilization utilisasi jam operasi tersedia',
      isian: [
        { k: 'operasi',  label: 'Operating Hours', nilai: 604 },
        { k: 'tersedia', label: 'Available Hours', nilai: 658 },
      ],
      hasil: 'Utilization', satuan: '%', desimal: 2,
      hitung: (v) => {
        if (v.tersedia <= 0) return null;
        if (v.operasi > v.tersedia) return null;
        return v.operasi / v.tersedia * 100;
      },
      catatan: (v, h) => {
        if (v.tersedia <= 0) return 'Jam tersedia harus lebih dari nol.';
        if (v.operasi > v.tersedia) return 'Jam operasi tidak boleh melebihi jam tersedia.';
        return 'Sisanya adalah waktu alat siap tetapi tidak dioperasikan.';
      },
      nadaCatatan: (v, h) => h == null ? 'bahaya' : '',
    },
  ];

  function sorotAlat(id) {
    const kartu = document.getElementById('alat-' + id);
    if (!kartu) return;
    kartu.scrollIntoView({ behavior: 'smooth', block: 'center' });
    kartu.style.transition = 'box-shadow .3s, border-color .3s';
    kartu.style.borderColor = 'var(--aksen)';
    setTimeout(() => { kartu.style.borderColor = ''; }, 1800);
  }

  GAMBAR.tools = function () {
    const kisi = $('#alat-kisi');
    kisi.innerHTML = '';

    ALAT.forEach((a) => {
      const kartu = el('article', 'kartu alat');
      kartu.id = 'alat-' + a.id;
      kartu.innerHTML = `
        <div class="alat-kepala">
          <span class="alat-ikon t-${a.nada}"><svg viewBox="0 0 24 24" aria-hidden="true">${a.ikon}</svg></span>
          <h3>${aman(a.nama)}</h3>
        </div>
        <div class="alat-rumus">${aman(a.rumus)}</div>
        <div class="baris-isian">
          ${a.isian.map((i) => `
            <div class="isian">
              <label for="${a.id}-${i.k}">${aman(i.label)}</label>
              <input type="number" id="${a.id}-${i.k}" data-k="${i.k}" value="${i.nilai}" min="0" step="any" inputmode="decimal">
            </div>`).join('')}
        </div>
        <div class="alat-hasil">
          <div>
            <div class="alat-hasil-label">${aman(a.hasil)}</div>
            <div class="alat-hasil-nilai" data-hasil>—</div>
          </div>
        </div>
        <p class="alat-catatan" data-catatan></p>`;
      kisi.appendChild(kartu);

      function hitungUlang() {
        const v = {};
        a.isian.forEach((i) => {
          const inp = $(`#${a.id}-${i.k}`, kartu);
          v[i.k] = parseFloat(inp.value);
          if (!Number.isFinite(v[i.k])) v[i.k] = 0;
        });
        const h = a.hitung(v);
        const kotakHasil = $('[data-hasil]', kartu);
        const kotakCatatan = $('[data-catatan]', kartu);

        kotakHasil.innerHTML = h == null ? '—'
          : n(h, a.desimal) + `<span class="alat-hasil-satuan">${aman(a.satuan)}</span>`;
        kotakHasil.style.color = h == null ? 'var(--teks-lemah)' : '';

        const nada = a.nadaCatatan ? a.nadaCatatan(v, h) : (h == null ? 'bahaya' : '');
        kotakCatatan.className = 'alat-catatan ' + nada;
        kotakCatatan.textContent = a.catatan(v, h);

        a.isian.forEach((i) => $(`#${a.id}-${i.k}`, kartu)
          .setAttribute('aria-invalid', String(h == null)));
      }

      $$('input', kartu).forEach((i) => i.addEventListener('input', hitungUlang));
      hitungUlang();
    });

    kisi.appendChild(kartuBising());
  };

  /**
   * Kalkulator pajanan bising.
   *
   * Memakai kriteria 85 dBA untuk 8 jam dengan laju pertukaran 3 dB —
   * dasar yang dipakai Permenaker No. 5 Tahun 2018 dan ISO 1999.
   *
   *   Tᵢ   = 8 ÷ 2^((Lᵢ − 85) ÷ 3)      jam yang diizinkan pada tingkat Lᵢ
   *   Dosis = 100 × Σ (Cᵢ ÷ Tᵢ)          persen
   *   TWA   = 85 + 3 × log₂(Dosis ÷ 100) dBA setara 8 jam
   *
   * Beberapa baris pajanan dijumlahkan dosisnya, bukan dirata-rata
   * tingkatnya: satu jam pada 100 dBA jauh lebih berat daripada delapan
   * jam pada 86 dBA, dan perataan biasa menyembunyikan itu.
   */
  function kartuBising() {
    const kartu = el('article', 'kartu alat');
    kartu.id = 'alat-noise-twa';
    kartu.innerHTML = `
      <div class="alat-kepala">
        <span class="alat-ikon t-bahaya"><svg viewBox="0 0 24 24" aria-hidden="true">${IKON.telinga}</svg></span>
        <h3>TWA Noise Calculator</h3>
      </div>
      <div class="alat-rumus">T = 8 ÷ 2^((L − 85) ÷ 3) · Dosis = 100 × Σ(C ÷ T) · TWA = 85 + 3 × log₂(Dosis ÷ 100)</div>
      <div data-baris></div>
      <button class="btn-kecil" data-tambah>
        <svg viewBox="0 0 24 24" aria-hidden="true" style="width:15px;height:15px">${IKON.tambah}</svg>
        Tambah pajanan
      </button>
      <div class="alat-hasil">
        <div>
          <div class="alat-hasil-label">TWA 8 jam</div>
          <div class="alat-hasil-nilai" data-twa>—</div>
        </div>
        <div style="text-align:right">
          <div class="alat-hasil-label">Dosis</div>
          <div class="alat-hasil-nilai" style="font-size:21px" data-dosis>—</div>
        </div>
      </div>
      <p class="alat-catatan" data-catatan></p>`;

    const wadahBaris = $('[data-baris]', kartu);

    function tambahBaris(db = 92, jam = 4) {
      const b = el('div', 'baris-bising');
      b.innerHTML = `
        <div class="isian">
          <label>Tingkat (dBA)</label>
          <input type="number" data-db value="${db}" min="0" max="140" step="0.1" inputmode="decimal">
        </div>
        <div class="isian">
          <label>Durasi (jam)</label>
          <input type="number" data-jam value="${jam}" min="0" max="24" step="0.25" inputmode="decimal">
        </div>
        <button class="btn-kecil btn-hapus" aria-label="Hapus baris pajanan">
          <svg viewBox="0 0 24 24" aria-hidden="true" style="width:15px;height:15px">${IKON.sampah}</svg>
        </button>`;
      wadahBaris.appendChild(b);

      $$('input', b).forEach((i) => i.addEventListener('input', hitungBising));
      $('.btn-hapus', b).addEventListener('click', () => {
        if (wadahBaris.children.length === 1) {
          toast('Sisakan setidaknya satu baris pajanan.', 'bahaya');
          return;
        }
        b.remove(); hitungBising();
      });
    }

    function hitungBising() {
      let dosis = 0, totalJam = 0, adaSalah = false;

      $$('.baris-bising', kartu).forEach((b) => {
        const db  = parseFloat($('[data-db]', b).value);
        const jam = parseFloat($('[data-jam]', b).value);
        if (!Number.isFinite(db) || !Number.isFinite(jam) || jam < 0 || db < 0) { adaSalah = true; return; }
        totalJam += jam;
        if (jam === 0) return;
        const T = 8 / Math.pow(2, (db - 85) / 3);      // jam yang diizinkan
        dosis += jam / T;
      });

      dosis *= 100;
      const kotakTwa = $('[data-twa]', kartu);
      const kotakDosis = $('[data-dosis]', kartu);
      const kotakCatatan = $('[data-catatan]', kartu);

      if (adaSalah || dosis <= 0) {
        kotakTwa.textContent = '—'; kotakDosis.textContent = '—';
        kotakCatatan.className = 'alat-catatan bahaya';
        kotakCatatan.textContent = adaSalah
          ? 'Isi tingkat dan durasi dengan angka yang wajar.'
          : 'Belum ada pajanan yang berdurasi.';
        return;
      }

      const twa = 85 + 3 * (Math.log(dosis / 100) / Math.log(2));
      kotakTwa.innerHTML = n(twa, 1) + '<span class="alat-hasil-satuan">dBA</span>';
      kotakDosis.innerHTML = n(dosis, 0) + '<span class="alat-hasil-satuan">%</span>';

      const lewat = dosis > 100;
      kotakTwa.style.color = lewat ? 'var(--bahaya)' : 'var(--sukses)';
      kotakDosis.style.color = lewat ? 'var(--bahaya)' : 'var(--sukses)';
      kotakCatatan.className = 'alat-catatan ' + (lewat ? 'bahaya' : 'sukses');

      if (totalJam > 24) {
        kotakCatatan.className = 'alat-catatan bahaya';
        kotakCatatan.textContent = `Total durasi ${n(totalJam, 2)} jam melebihi satu hari kerja — periksa kembali datanya.`;
        return;
      }

      kotakCatatan.textContent = lewat
        ? `MELEBIHI NAB. Dosis ${n(dosis, 0)}% terhadap batas harian; TWA ${n(twa, 1)} dBA di atas 85 dBA. `
          + 'Perlu pengendalian teknis, pembatasan durasi, atau alat pelindung pendengaran yang memadai.'
        : `Dalam batas. Dosis ${n(dosis, 0)}% terhadap batas harian, TWA ${n(twa, 1)} dBA terhadap NAB 85 dBA `
          + `selama 8 jam (Permenaker No. 5 Tahun 2018, laju pertukaran 3 dB).`;
    }

    $('[data-tambah]', kartu).addEventListener('click', () => { tambahBaris(88, 2); hitungBising(); });

    // Dua baris contoh: satu di bawah ambang, satu di atasnya, supaya
    // cara kerja penjumlahan dosis langsung terlihat.
    tambahBaris(92, 4);
    tambahBaris(85, 3);
    hitungBising();

    return kartu;
  }

  /* ══════════════════════════════════════════════════════════════
     9. Penyalaan
     ══════════════════════════════════════════════════════════════ */
  function init() {
    document.documentElement.setAttribute('data-tema', temaAwal());

    // Identitas
    $('#profil-nama').textContent = D.perusahaan.pengguna.nama;
    $('#profil-peran').textContent = D.perusahaan.pengguna.peran;
    $('.sisi-pengguna-teks strong').textContent = D.perusahaan.pengguna.nama;
    $('.sisi-pengguna-teks small').textContent = D.perusahaan.pengguna.peran;

    // Nama menu dipakai tooltip saat bilah samping terlipat
    $$('.sisi-tautan').forEach((a) => a.setAttribute('data-nama', a.textContent.trim()));

    // Notifikasi disusun dari keadaan data, bukan dikarang
    const m = hitung.maintenanceRingkas();
    const rusak = D.fleet.unit.filter((u) => u.status === 'Breakdown');
    const e = hitung.energiRingkas();
    const notif = [
      { judul: `${m.kritis} pekerjaan Critical terbuka`, ket: 'Halaman Maintenance · perlu tindakan hari ini' },
      { judul: `${rusak.length} unit Breakdown`, ket: rusak.map((u) => u.kode).join(', ') || 'Tidak ada' },
      { judul: `Intensitas energi ${n(e.intensitas, 5)} GJ/ton`,
        ket: `${e.penurunan >= 0 ? 'Turun' : 'Naik'} ${n(Math.abs(e.penurunan), 1)}% dari baseline` },
    ];
    $('#notif-daftar').innerHTML = notif.map((x) =>
      `<li><strong>${aman(x.judul)}</strong><small>${aman(x.ket)}</small></li>`).join('');
    $('#notif-jumlah').textContent = String(notif.length);

    // Tema
    $('#btn-tema').addEventListener('click', () => {
      const baru = document.documentElement.getAttribute('data-tema') === 'dark' ? 'light' : 'dark';
      pasangTema(baru);
      toast(baru === 'dark' ? 'Mode gelap dinyalakan.' : 'Mode terang dinyalakan.');
    });
    $('#btn-tema').setAttribute('aria-checked',
      String(document.documentElement.getAttribute('data-tema') === 'light'));

    // Notifikasi
    $('#btn-notif').addEventListener('click', (ev) => {
      ev.stopPropagation();
      const p = $('#panel-notif');
      p.hidden = !p.hidden;
      $('#btn-notif').setAttribute('aria-expanded', String(!p.hidden));
    });
    document.addEventListener('click', (ev) => {
      if (!ev.target.closest('.lonceng')) {
        $('#panel-notif').hidden = true;
        $('#btn-notif').setAttribute('aria-expanded', 'false');
      }
    });

    // Bilah samping
    $('#btn-lipat').addEventListener('click', () => {
      document.body.classList.toggle('sisi-lipat-aktif');
      setTimeout(() => grafikAktif.forEach((g) => g && g.gambarUlang()), 320);
    });
    $('#btn-menu').addEventListener('click', () => {
      const buka = !document.body.classList.contains('sisi-buka');
      document.body.classList.toggle('sisi-buka', buka);
      $('#tirai').hidden = !buka;
      $('#btn-menu').setAttribute('aria-expanded', String(buka));
    });
    $('#tirai').addEventListener('click', () => {
      document.body.classList.remove('sisi-buka');
      $('#tirai').hidden = true;
      $('#btn-menu').setAttribute('aria-expanded', 'false');
    });

    // Navigasi
    $$('[data-nav]').forEach((a) => a.addEventListener('click', (ev) => {
      ev.preventDefault();
      bukaHalaman(a.dataset.nav);
    }));
    window.addEventListener('hashchange', () => bukaHalaman(location.hash.slice(1), false));

    // Penyaring produksi
    $$('#hal-dashboard .saring-btn').forEach((b) => b.addEventListener('click', () => {
      $$('#hal-dashboard .saring-btn').forEach((x) => x.classList.remove('aktif'));
      b.classList.add('aktif');
      gambarProduksi(b.dataset.rentang);
    }));

    // Penyaring dan pengurutan armada
    $$('#hal-fleet .saring-btn').forEach((b) => b.addEventListener('click', () => {
      $$('#hal-fleet .saring-btn').forEach((x) => x.classList.remove('aktif'));
      b.classList.add('aktif');
      gambarTabelFleet();
    }));
    $$('#tabel-fleet th[data-urut]').forEach((th) => th.addEventListener('click', () => {
      const k = th.dataset.urut;
      urutFleet = { kolom: k, turun: urutFleet.kolom === k ? !urutFleet.turun : true };
      gambarTabelFleet();
    }));

    // Penyaring pemeliharaan
    $$('#hal-maintenance .saring-btn').forEach((b) => b.addEventListener('click', () => {
      $$('#hal-maintenance .saring-btn').forEach((x) => x.classList.remove('aktif'));
      b.classList.add('aktif');
      gambarTabelMaintenance();
    }));

    // Penyaring regulasi — dipasang sekali, dibaca dari elemen induk
    $('#reg-saring').addEventListener('click', (ev) => {
      const b = ev.target.closest('.saring-btn');
      if (!b) return;
      regKategori = b.dataset.kategori;
      $$('#reg-saring .saring-btn').forEach((x) => x.classList.toggle('aktif', x === b));
      gambarReg();
    });

    $('#btn-bantuan').addEventListener('click', () => {
      toast('Seluruh angka berasal dari data.js — sunting berkas itu, seluruh halaman ikut berubah.', 'sukses');
    });

    pasangCari();
    bukaHalaman(location.hash.slice(1) || 'dashboard', false);

    // Grafik digambar ulang saat lebar jendela berubah cukup jauh
    let lebarTerakhir = window.innerWidth;
    window.addEventListener('resize', () => {
      if (Math.abs(window.innerWidth - lebarTerakhir) < 60) return;
      lebarTerakhir = window.innerWidth;
    }, { passive: true });

    toast('Data contoh dimuat dari data.js — semua angka dapat disunting di sana.');
  }

  document.addEventListener('DOMContentLoaded', init);
})();
