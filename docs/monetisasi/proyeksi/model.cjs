/* Model pendapatan & keuntungan EQOHSEE — satu sumber angka.
   Dipakai oleh: generator tabel dokumen (node) dan halaman review (browser).
   Semua uang dalam rupiah bulat. Pendapatan diakui per bulan
   (langganan tahunan = 10/12 harga bulanan per bulan). PPN tidak dihitung
   sebagai pendapatan (titipan). */
(function (root) {
  const HARGA = {
    silver: 149000, gold: 399000, diamond: 799000,
    tahunanFaktor: 10 / 12,            // bayar 10 bulan, dapat 12
    modulTambahan: 149000,             // per modul audit / bulan
    slotIujp: 49000,                   // per IUJP tambahan / bulan (Diamond)
    paketOperasional: 999000,          // paket tarif tetap (Safety Operations, Environment & ESG) / bulan
    erpPerKaryawan: 9000,              // ERP SaaS People: per karyawan aktif / bulan
    erpMinimum: 999000,                // ERP SaaS People: tagihan minimum / bulan
    erpPerSite: 1499000,               // ERP SaaS Operations: per site / bulan
    penggunaTambahan: 29000,           // per pengguna / bulan
    onboardingGold: 1500000, onboardingDiamond: 3500000,
    vvipLisensi: 25000000,             // paket menyeluruh, sekali bayar (katalog saat ini)
    vvipTahunan: 22000000,             // server 4 vCPU/4 GB Rp6 jt + pemeliharaan 8 blok Rp16 jt
  };

  const SKENARIO = {
    konservatif: {
      label: 'Konservatif',
      daftar0: 60, tumbuh: [0.05, 0.03, 0.015],
      konversi: 0.025, langsungGold: 0.5, langsungDiamond: 0.2, vvipPerTahun: 1,
      churn: { silver: 0.045, gold: 0.032, diamond: 0.02 },
      porsiTahunan: 0.35, sponsorMaks: 3,
      tim: [12000000, 18000000, 25000000], pemasaran: [3000000, 4000000, 5000000], umum: [2000000, 3000000, 4000000],
    },
    moderat: {
      label: 'Moderat',
      daftar0: 120, tumbuh: [0.07, 0.04, 0.02],
      konversi: 0.04, langsungGold: 1, langsungDiamond: 0.5, vvipPerTahun: 2,
      churn: { silver: 0.035, gold: 0.025, diamond: 0.015 },
      porsiTahunan: 0.4, sponsorMaks: 6,
      tim: [15000000, 30000000, 60000000], pemasaran: [5000000, 8000000, 15000000], umum: [3000000, 5000000, 8000000],
    },
    optimis: {
      label: 'Optimis',
      daftar0: 180, tumbuh: [0.08, 0.04, 0.02],
      konversi: 0.05, langsungGold: 2, langsungDiamond: 1, vvipPerTahun: 4,
      churn: { silver: 0.028, gold: 0.02, diamond: 0.012 },
      porsiTahunan: 0.45, sponsorMaks: 10,
      tim: [25000000, 50000000, 90000000], pemasaran: [10000000, 20000000, 35000000], umum: [5000000, 10000000, 15000000],
    },
  };

  /* Asumsi bersama — sama untuk tiga skenario supaya perbedaannya hanya
     berasal dari pasar (pendaftar, konversi, churn, kontrak). */
  const ASUMSI = {
    campuran: { silver: 0.65, gold: 0.28, diamond: 0.07 },   // dari konversi Bronze
    iujpPerGold: 2, iujpPerDiamond: 6, konversiJaringan: 0.007, // IUJP terhubung → Silver / bulan
    naikGoldKeDiamond: 0.01, naikSilverKeGold: 0.005,           // per bulan
    bronzeAktif: 0.35,                                          // porsi pendaftar yang masih aktif
    lampiran: {                                                 // add-on, porsi pelanggan aktif
      goldModul: 0.20, diamondSlot: 0.30, slotRata: 3,
      goldPaket: 0.04, diamondPaket: 0.18, paketRata: 1.2,            // paket tarif tetap
      goldErpOrang: 0.04, goldKaryawan: 100, diamondErpOrang: 0.12, diamondKaryawan: 250, // ERP People
      goldErpSite: 0.02, diamondErpSite: 0.06, siteRata: 1.5,          // ERP Operations
      goldPengguna: 0.10, goldPenggunaRata: 3, diamondPengguna: 0.20, diamondPenggunaRata: 8,
      onboarding: 0.25,
    },
    topupPorsiBerbayar: 0.10, topupRata: 180000, topupPorsiBronze: 0.005, topupBronze: 99000,
    digitalPorsiBronze: 0.015, digitalPorsiSilver: 0.04, digitalRata: 55000,
    kursusPorsiBronze: 0.008, kursusRata: 199000,
    tayangPublik0: 10000, tayangTumbuh: 0.06, tayangPerBronze: 15, rpm: 15000,
    sponsorRata: 1500000, sponsorMulai: 6,
    // Iklan pembuka modul: tampil setiap Bronze membuka modul
    bukaModulPerBronze: 12, isiPembuka: 0.5, ecpmPembuka: 60000,  // per Bronze aktif/bln, porsi terjual, Rp per 1.000
    biaya: {
      kreditRp: 200,                                   // biaya AI per kredit terpakai
      pakai: { silver: 30, gold: 100, diamond: 400, bronzeBaru: 3 },
      topupCogs: 0.25,
      gerbang: 0.012,                                  // biaya gateway, campuran QRIS/VA/kartu
      infraDasar: 3000000, infraPerBerbayar: 12000, infraPerBronze: 300, serverVvip: 400000,
      komisiMitra: 0.06,                               // dari langganan + add-on + ERP
      konten: 0.10, pengajar: 0.30, komisiSponsor: 0.10,
      pph: 0.005,                                      // PPh final 0,5% omzet (asumsi, PP 55/2022)
      batasUmkm: 4800000000,                           // omzet setahun; di atasnya tarif umum
      pphBadan: 0.22,                                  // tarif umum atas laba (tanpa fasilitas Ps. 31E)
    },
  };

  function tahunKe(m) { return Math.min(2, Math.floor((m - 1) / 12)); }

  function proyeksi(kunci, bulan = 36, ubah = {}) {
    const s = Object.assign({}, SKENARIO[kunci], ubah);
    const a = ASUMSI, h = HARGA, b = a.biaya, L = a.lampiran;
    let S = 0, G = 0, D = 0, V = 0;          // pelanggan aktif (pecahan dibiarkan; dibulatkan saat tampil)
    let daftar = s.daftar0, bronzeKumulatif = 0, tayang = a.tayangPublik0;
    let kumulatif = 0, impas = null, impasKumulatif = null, titikTerendah = 0, omzetTahunIni = 0;
    const baris = [];

    for (let m = 1; m <= bulan; m++) {
      const t = tahunKe(m);
      if (m > 1) { daftar *= 1 + s.tumbuh[t]; tayang *= 1 + (t === 0 ? a.tayangTumbuh : a.tayangTumbuh / 2); }
      bronzeKumulatif += daftar;
      const bronzeAktif = bronzeKumulatif * a.bronzeAktif;

      // ── arus pelanggan ──
      const baruKonversi = daftar * s.konversi;
      const terhubung = G * a.iujpPerGold + D * a.iujpPerDiamond;
      const baruS = baruKonversi * a.campuran.silver + terhubung * a.konversiJaringan;
      const baruG = baruKonversi * a.campuran.gold + s.langsungGold;
      const baruD = baruKonversi * a.campuran.diamond + s.langsungDiamond;
      const naikSG = S * a.naikSilverKeGold, naikGD = G * a.naikGoldKeDiamond;
      const vvipBaru = (m % Math.max(1, Math.round(12 / s.vvipPerTahun)) === 0) ? 1 : 0;

      S = S * (1 - s.churn.silver) + baruS - naikSG;
      G = G * (1 - s.churn.gold) + baruG + naikSG - naikGD;
      D = D * (1 - s.churn.diamond) + baruD + naikGD;
      V = V + vvipBaru;

      // harga efektif: campuran bulanan & tahunan
      const ef = (p) => p * ((1 - s.porsiTahunan) + s.porsiTahunan * h.tahunanFaktor);

      // ── pendapatan ──
      const langganan = S * ef(h.silver) + G * ef(h.gold) + D * ef(h.diamond);
      const addon =
        G * L.goldModul * h.modulTambahan +
        D * L.diamondSlot * L.slotRata * h.slotIujp +
        (G * L.goldPengguna * L.goldPenggunaRata + D * L.diamondPengguna * L.diamondPenggunaRata) * h.penggunaTambahan;
      const orangErp = (n) => Math.max(h.erpMinimum, n * h.erpPerKaryawan);
      const erp =
        (G * L.goldPaket + D * L.diamondPaket * L.paketRata) * h.paketOperasional +
        G * L.goldErpOrang * orangErp(L.goldKaryawan) + D * L.diamondErpOrang * orangErp(L.diamondKaryawan) +
        (G * L.goldErpSite + D * L.diamondErpSite * L.siteRata) * h.erpPerSite;
      const kreditAi = (S + G + D) * a.topupPorsiBerbayar * a.topupRata + bronzeAktif * a.topupPorsiBronze * a.topupBronze;
      const digital = (bronzeAktif * a.digitalPorsiBronze + S * a.digitalPorsiSilver) * a.digitalRata;
      const kursus = bronzeAktif * a.kursusPorsiBronze * a.kursusRata;
      const tayangTotal = tayang + bronzeAktif * a.tayangPerBronze;
      const sponsorN = m < a.sponsorMulai ? 0 : Math.min(s.sponsorMaks, Math.floor((m - a.sponsorMulai) / 3) + 1);
      const iklan = tayangTotal / 1000 * a.rpm + sponsorN * a.sponsorRata;
      const tayangPembuka = bronzeAktif * a.bukaModulPerBronze;
      const iklanModul = tayangPembuka * a.isiPembuka / 1000 * a.ecpmPembuka;
      const layanan = (baruG * h.onboardingGold + baruD * h.onboardingDiamond) * L.onboarding;
      const vvip = vvipBaru * h.vvipLisensi + V * h.vvipTahunan / 12;

      const pendapatan = langganan + addon + erp + kreditAi + digital + kursus + iklan + iklanModul + layanan + vvip;
      const mrr = langganan + addon + erp + V * h.vvipTahunan / 12;

      // ── biaya ──
      const biayaAi = (S * b.pakai.silver + G * b.pakai.gold + D * b.pakai.diamond + daftar * b.pakai.bronzeBaru) * b.kreditRp
        + kreditAi * b.topupCogs;
      const biayaGerbang = (pendapatan - vvip) * b.gerbang;
      const infra = b.infraDasar + (S + G + D) * b.infraPerBerbayar + bronzeAktif * b.infraPerBronze + V * b.serverVvip;
      const pemasaran = s.pemasaran[t] + (langganan + addon + erp) * b.komisiMitra + sponsorN * a.sponsorRata * b.komisiSponsor;
      const konten = digital * b.konten + kursus * b.pengajar;
      const tim = s.tim[t];
      omzetTahunIni = (m - 1) % 12 === 0 ? pendapatan : omzetTahunIni + pendapatan;
      const umum = s.umum[t];
      const biayaOps = biayaAi + biayaGerbang + infra + pemasaran + konten + tim + umum;
      const labaSebelumPajak = pendapatan - biayaOps;
      const pajak = omzetTahunIni <= b.batasUmkm ? pendapatan * b.pph : Math.max(0, labaSebelumPajak) * b.pphBadan;
      const biaya = biayaOps + pajak;
      const laba = pendapatan - biaya;
      kumulatif += laba;
      if (kumulatif < titikTerendah) titikTerendah = kumulatif;
      if (impasKumulatif === null && kumulatif >= 0 && m > 1) impasKumulatif = m;
      // impas berulang: pendapatan berulang (tanpa lisensi VVIP sekali bayar) menutup biaya
      const labaBerulang = laba - vvipBaru * h.vvipLisensi;
      if (labaBerulang > 0) { if (impas === null) impas = m; } else impas = null;

      baris.push({
        m, daftar, bronzeAktif, S, G, D, V,
        langganan, addon, erp, kreditAi, digital, kursus, iklan, iklanModul, tayangPembuka, layanan, vvip,
        pendapatan, mrr,
        biayaAi, biayaGerbang, infra, pemasaran, konten, tim, umum, pajak, biaya,
        labaSebelumPajak, laba, kumulatif,
        marginKotor: pendapatan - biayaAi - biayaGerbang - infra - konten,
      });
    }
    const tahunan = [0, 1, 2].map((y) => {
      const r = baris.slice(y * 12, y * 12 + 12);
      const jml = (k) => r.reduce((a, x) => a + x[k], 0);
      return { tahun: y + 1, pendapatan: jml('pendapatan'), biaya: jml('biaya'), laba: jml('laba'),
               marginKotor: jml('marginKotor'), akhir: r[r.length - 1] };
    }).filter((x) => x.akhir);
    return { skenario: s, baris, tahunan, impas, impasKumulatif, titikTerendah };
  }

  /* Potret satu bulan pada basis pelanggan tetap — dipakai untuk
     "jembatan kenaikan": dari simulasi dokumen awal ke model yang
     dioptimalkan. Nilai p dapat diubah di simulator. */
  const POTRET_AWAL = {
    silver: 100, gold: 100, diamond: 20,
    hargaSilver: 149000, hargaGold: 399000, hargaDiamond: 799000,
    digital: 5000000, sponsor: 3000000,
    bronzeAktif: 2000,                 // untuk tuas iklan pembuka modul
  };

  function jembatan(p = POTRET_AWAL) {
    const h = HARGA, L = ASUMSI.lampiran;
    const dasar = p.silver * p.hargaSilver + p.gold * p.hargaGold + p.diamond * p.hargaDiamond + p.digital + p.sponsor;
    const tuas = [
      { kode: 'T1', nama: 'Modul audit tambahan (Gold)', nilai: p.gold * L.goldModul * h.modulTambahan },
      { kode: 'T2', nama: 'Slot IUJP tambahan (Diamond)', nilai: p.diamond * L.diamondSlot * L.slotRata * h.slotIujp },
      { kode: 'T3', nama: 'Paket Operasional & ERP SaaS (per karyawan / site)', nilai:
          (p.gold * L.goldPaket + p.diamond * L.diamondPaket * L.paketRata) * h.paketOperasional +
          p.gold * L.goldErpOrang * Math.max(h.erpMinimum, L.goldKaryawan * h.erpPerKaryawan) +
          p.diamond * L.diamondErpOrang * Math.max(h.erpMinimum, L.diamondKaryawan * h.erpPerKaryawan) +
          (p.gold * L.goldErpSite + p.diamond * L.diamondErpSite * L.siteRata) * h.erpPerSite },
      { kode: 'T4', nama: 'Pengguna tambahan', nilai: (p.gold * L.goldPengguna * L.goldPenggunaRata + p.diamond * L.diamondPengguna * L.diamondPenggunaRata) * h.penggunaTambahan },
      { kode: 'T5', nama: 'Top-up kredit AI', nilai: (p.silver + p.gold + p.diamond) * ASUMSI.topupPorsiBerbayar * ASUMSI.topupRata },
      { kode: 'T6', nama: 'Jaringan IUJP → Silver (8%/tahun)', nilai: Math.round((p.gold * ASUMSI.iujpPerGold + p.diamond * ASUMSI.iujpPerDiamond) * 0.08) * p.hargaSilver },
      { kode: 'T7', nama: 'Upgrade Gold → Diamond (10%/tahun)', nilai: Math.round(p.gold * 0.10) * (p.hargaDiamond - p.hargaGold) },
      { kode: 'T8', nama: 'Kursus berbayar Learning Center', nilai: 25 * ASUMSI.kursusRata },
      { kode: 'T9', nama: 'VVIP dedicated (2 kontrak, dirata 12 bln)', nilai: 2 * (h.vvipLisensi + h.vvipTahunan) / 12 },
      { kode: 'T10', nama: 'Iklan pembuka modul (2.000 Bronze aktif)', nilai: p.bronzeAktif * ASUMSI.bukaModulPerBronze * ASUMSI.isiPembuka / 1000 * ASUMSI.ecpmPembuka },
    ].map((x) => ({ ...x, nilai: Math.round(x.nilai) }));
    const total = dasar + tuas.reduce((a, x) => a + x.nilai, 0);
    return { dasar, tuas, total };
  }

  /* Ekonomi per pelanggan pada skenario moderat: ARPU bulanan efektif,
     margin kotor per bulan, umur rata-rata (1/churn), dan LTV. */
  function unitEkonomi(kunci = 'moderat') {
    const s = SKENARIO[kunci], h = HARGA, b = ASUMSI.biaya;
    const ef = (p) => p * ((1 - s.porsiTahunan) + s.porsiTahunan * h.tahunanFaktor);
    return ['silver', 'gold', 'diamond'].map((k) => {
      const arpu = ef(h[k]);
      const biaya = b.pakai[k] * b.kreditRp + arpu * b.gerbang + b.infraPerBerbayar + arpu * b.komisiMitra;
      const margin = arpu - biaya;
      const umur = 1 / s.churn[k];
      return { paket: k, arpu, biaya, margin, persenMargin: margin / arpu, umur, ltv: margin * umur, cacMaks: margin * umur / 3 };
    });
  }

  const api = { HARGA, SKENARIO, ASUMSI, proyeksi, jembatan, unitEkonomi, POTRET_AWAL };
  if (typeof module !== 'undefined' && module.exports) module.exports = api; else root.ModelEqohsee = api;
})(typeof window !== 'undefined' ? window : globalThis);
