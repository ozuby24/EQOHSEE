/**
 * Tabel yang pas di layar — tanpa geser ke samping.
 *
 * Dua lapis, keduanya menyeluruh sehingga tidak ada tabel yang lupa:
 *
 * 1. CSS (app.css, "Tabel pas layar") memadatkan tabel di layar di bawah
 *    1280 px: judul kolom boleh turun baris, jarak sel dirapatkan, dan
 *    lebar minimum paksa dibuang. Pada tablet itu sudah cukup untuk
 *    sebagian besar tabel.
 *
 * 2. Modul ini memeriksa tabel yang MASIH lebih lebar dari wadahnya dan
 *    mengubahnya menjadi kartu bertumpuk: satu baris menjadi satu kartu,
 *    tiap sel diberi label judul kolomnya. Diukur, bukan ditebak dari
 *    lebar layar — tabel tiga kolom di HP tetap tabel, tabel dua belas
 *    kolom di tablet menjadi kartu hanya bila memang tidak muat.
 *
 * Dikecualikan: tabel tanpa baris judul (tidak ada label yang dapat
 * dipasang), lembar sertifikat, dan tabel bertanda `data-tabel-tetap`.
 * Tabel bertanda `data-tumpuk-bawah="560"` juga ditumpuk bila lebarnya
 * di bawah angka itu walau tidak meluap: tabel lembar cetak berkolom
 * persen memeras kolomnya alih-alih meluap, jadi luapan tidak pernah
 * terukur.
 * Lembar cetak IKUT disesuaikan di layar sempit; aturan kartunya hanya
 * berlaku untuk @media screen, jadi hasil cetak kertasnya tidak berubah.
 *
 * Vue boleh menggambar ulang sel kapan saja dan membuang atribut yang
 * dipasang dari luar; karena itu pemeriksaannya diulang setiap kali DOM
 * berubah (ditunda satu bingkai, digabung) dan setiap kali layar diubah
 * ukurannya.
 */

const KELAS = 'tabel-tumpuk';

function dikecualikan(t: HTMLTableElement): boolean {
  return !!t.closest('[data-tabel-tetap], .sl-lembar') || !t.tHead;
}

/** Wadah yang memotong atau menggulir tabel — pembanding lebarnya. */
function wadah(t: HTMLElement): HTMLElement {
  let c = t.parentElement;
  while (c && c !== document.body) {
    if (/(auto|scroll|hidden|clip)/.test(getComputedStyle(c).overflowX)) return c;
    c = c.parentElement;
  }
  return document.documentElement;
}

/**
 * Lebar yang disediakan induk bagi tabel: lebar isi induknya, ditambah
 * margin negatif bila tabel sengaja dilebarkan sampai tepi kartu.
 *
 * Pembanding wadah gulir saja tidak cukup. Tabel di dalam lembar cetak
 * atau kartu berbantalan dapat melewati bingkai kartunya sementara
 * masih lebih sempit dari layar — "Terbuka" dan "10.00" menembus garis
 * kanan lembar di ponsel tanpa satu piksel pun luapan halaman.
 */
function lebarDiberi(t: HTMLTableElement): number {
  const induk = t.parentElement;
  if (!induk || !induk.clientWidth) return Infinity;
  const ci = getComputedStyle(induk);
  if (ci.display.startsWith('inline') || ci.display === 'contents') return Infinity;
  const ct = getComputedStyle(t);
  return induk.clientWidth
    - (parseFloat(ci.paddingLeft) || 0) - (parseFloat(ci.paddingRight) || 0)
    - (parseFloat(ct.marginLeft) || 0) - (parseFloat(ct.marginRight) || 0);
}

/** Label per kolom dari seluruh baris judul, dengan rowspan/colspan. */
function labelKolom(t: HTMLTableElement): string[] {
  const kisi: string[][] = [];
  const baris = Array.from(t.tHead!.rows);
  baris.forEach((tr, r) => {
    kisi[r] ??= [];
    let c = 0;
    for (const sel of Array.from(tr.cells)) {
      while (kisi[r][c] !== undefined) c++;
      // innerText, bukan textContent: judul dua baris ("Pekan 1" di atas
      // "1 Sep–7 Sep") tidak boleh tergabung menjadi "Pekan 11 Sep".
      const teks = ((sel as HTMLElement).innerText || sel.textContent || '')
        .split(/\n+/).map((b) => b.replace(/\s+/g, ' ').trim()).filter(Boolean).join(' · ');
      for (let dr = 0; dr < sel.rowSpan; dr++) {
        kisi[r + dr] ??= [];
        for (let dc = 0; dc < sel.colSpan; dc++) kisi[r + dr][c + dc] = dr === 0 ? teks : '';
      }
      c += sel.colSpan;
    }
  });
  const lebar = Math.max(0, ...kisi.map((k) => k.length));
  return Array.from({ length: lebar }, (_, j) => {
    const bagian: string[] = [];
    for (const k of kisi) {
      const s = k[j];
      if (s && !bagian.includes(s)) bagian.push(s);
    }
    return bagian.join(' · ');
  });
}

function pasangLabel(t: HTMLTableElement): void {
  const label = labelKolom(t);
  for (const tb of Array.from(t.tBodies)) {
    const isi: boolean[][] = [];
    Array.from(tb.rows).forEach((tr, r) => {
      isi[r] ??= [];
      let c = 0;
      for (const sel of Array.from(tr.cells)) {
        while (isi[r][c]) c++;
        const l = sel.colSpan > 1 && sel.colSpan >= label.length ? '' : (label[c] ?? '');
        if (sel.getAttribute('data-label') !== l) sel.setAttribute('data-label', l);

        // Teks panjang atau tombol aksi mengambil selebar kartu.
        const lebar = (sel.textContent || '').trim().length > 28
          || !!sel.querySelector('button, select, textarea, input, table, .eq-btn-utama, .eq-btn-lain');
        sel.classList.toggle('sel-lebar', lebar);
        for (let dr = 0; dr < sel.rowSpan; dr++) {
          isi[r + dr] ??= [];
          for (let dc = 0; dc < sel.colSpan; dc++) isi[r + dr][c + dc] = true;
        }
        c += sel.colSpan;
      }
    });
  }
}

function periksa(): void {
  const tabel = Array.from(document.querySelectorAll<HTMLTableElement>('#app table'))
    .filter((t) => !dikecualikan(t));

  // Ukur semuanya dalam bentuk tabel dulu, lalu ubah sekaligus —
  // satu kali tata letak, tanpa kedip di antara keduanya.
  const tadi = tabel.map((t) => t.classList.contains(KELAS));
  tabel.forEach((t) => t.classList.remove(KELAS));

  // Sel tanggal/angka ditandai SEBELUM lebarnya diukur: tabel yang
  // karenanya tidak muat lagi harus ikut menjadi kartu, bukan meluap.
  tabel.forEach(tandaiSelAngka);

  // Diukur dengan tata letak otomatis — lihat `table.tabel-ukur` di app.css.
  tabel.forEach((t) => t.classList.add('tabel-ukur'));

  const tumpuk = tabel.map((t) => {
    const w = wadah(t);
    // Wadah yang berubah lebar tanpa perubahan DOM — bilah samping
    // dilipat, huruf selesai dimuat — memicu pemeriksaan ulang.
    if (ukuran && !diamati.has(w)) { diamati.add(w); ukuran.observe(w); }
    if (t.offsetParent === null) return false;
    if (t.scrollWidth > w.clientWidth + 1) return true;
    if (t.offsetWidth > lebarDiberi(t) + 1) return true;
    // Tabel lembar cetak berkolom persen (table-layout: fixed) SELALU
    // "muat": kolomnya dipersempit, bukan diluapkan, sampai "No" menjadi
    // "N O" dan "Evaluasi" menjadi "EVA LUA SI". Lebar yang masih layak
    // untuk tabel semacam itu dinyatakan oleh lembarnya sendiri.
    const batas = Number(t.dataset.tumpukBawah) || 0;
    return batas > 0 && t.clientWidth < batas;
  });

  tabel.forEach((t) => t.classList.remove('tabel-ukur'));

  tabel.forEach((t, i) => {
    if (tumpuk[i]) {
      pasangLabel(t);
      t.classList.add(KELAS);
    } else if (tadi[i]) {
      t.classList.remove(KELAS);
    }
  });

  // Sel kartu yang isinya masih tidak muat di satu kolom kisi
  // (lencana panjang, angka dengan satuan) diberi selebar kartu.
  tabel.forEach((t, i) => {
    if (!tumpuk[i]) return;
    for (const sel of Array.from(t.querySelectorAll<HTMLElement>(':scope > tbody > tr > td, :scope > tfoot > tr > td'))) {
      if (sel.scrollWidth > sel.clientWidth + 1) sel.classList.add('sel-lebar');
    }
  });

  tabel.forEach((t, i) => { if (!tumpuk[i]) samakanRataJudul(t); });
}

/* ─────────── Tanggal dan angka pendek tidak dipatah ───────────
 *
 * "24 Sep 2026" yang turun menjadi tiga baris, "2026-09-01 05:30"
 * menjadi dua, "83.800 ton" menjadi "83.800" dan "ton" — terukur di
 * pemantauan lingkungan, perintah kerja, P2H, dan laporan konservasi.
 * Sel-sel itu tidak memakai kelas `.num`, jadi aturan angka di app.css
 * tidak menjangkaunya; yang dikenali di sini BENTUK isinya.
 *
 * Hanya isi yang pendek dan seluruhnya berupa tanggal, jam, atau angka
 * bersatuan. Kalimat yang kebetulan mengandung angka tetap boleh
 * membungkus.
 */
const SEL_ANGKA = 'sel-angka';
const POLA_ANGKA = new RegExp(
  '^(?:'
  + 'Rp\\s?[\\d.,]+'                                                 // uang
  + '|[+−-]?[\\d.,]+\\s?(?:%|[a-zA-Z/²³°]{1,6})?'                   // angka bersatuan
  + '|\\d{1,2}\\s[A-Za-z]{3,9}\\s\\d{2,4}(?:,?\\s\\d{1,2}[.:]\\d{2})?' // 24 Sep 2026 [05:30]
  + '|\\d{4}-\\d{2}-\\d{2}(?:\\s\\d{1,2}:\\d{2})?'                  // 2026-09-01 [05:30]
  + '|\\d{1,2}[-/.]\\d{1,2}[-/.]\\d{2,4}'                           // 02-02-2021
  + '|\\d{1,2}[.:]\\d{2}(?:\\s?[–-]\\s?\\d{1,2}[.:]\\d{2})?'        // 05:30 [– 17:30]
  + '|[A-Z][A-Z0-9]{0,7}(?:[-/.][A-Z0-9]{1,10}){1,5}'               // KO-PRA-001, PKWT/2026/007, II.3.2
  + ')$',
);

function tandaiSelAngka(t: HTMLTableElement): void {
  if (t.closest('.lembar')) return;

  for (const tb of Array.from(t.tBodies)) {
    for (const tr of Array.from(tb.rows).slice(0, 300)) {
      for (const sel of Array.from(tr.cells)) {
        const jalan = document.createTreeWalker(sel, NodeFilter.SHOW_TEXT, {
          acceptNode: (n) => ((n.textContent ?? '').trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP),
        });
        const pertama = jalan.nextNode();
        const teks = (pertama?.textContent ?? '').replace(/\s+/g, ' ').trim();
        sel.classList.toggle(SEL_ANGKA, teks.length > 0 && teks.length <= 22 && POLA_ANGKA.test(teks));
      }
    }
  }
}

/* ─────────── Judul kolom mengikuti perataan isinya ───────────
 *
 * Angka di dalam tabel dirata-kanankan oleh aturan global `.num` di
 * app.css — dan itu benar untuk angka. Tetapi judul kolomnya ditulis
 * sendiri-sendiri di tiap halaman, dan sebagian besar bawaannya rata
 * kiri. Hasilnya terukur di puluhan halaman: judul "Tanggal", "Nomor",
 * "Terbit", "Masa" menempel di kiri sementara isinya di kanan, dan mata
 * yang membaca ke bawah kehilangan kolomnya.
 *
 * Yang disamakan JUDULNYA, bukan isinya: isi yang rata kanan memang
 * disengaja (angka sejajar per digit), sedangkan judul yang rata kiri
 * hanyalah bawaan yang tidak pernah dipilih.
 *
 * Diukur dari baris isi yang tampak, bukan dari kelasnya: sebuah sel
 * dapat dirata-kanankan lewat `.num`, `text-right`, atau gaya sebaris,
 * dan ketiganya sama saja bagi yang membaca.
 */
const RATA_KANAN = 'th-rata-kanan';

function rataTeks(td: HTMLElement): string | null {
  const jalan = document.createTreeWalker(td, NodeFilter.SHOW_TEXT, {
    acceptNode: (n) => ((n.textContent ?? '').trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP),
  });
  const teks = jalan.nextNode();
  if (!teks) return null;

  let el = teks.parentElement;
  while (el && el !== td && getComputedStyle(el).display.startsWith('inline')) el = el.parentElement;

  const a = getComputedStyle(el ?? td).textAlign;
  return a === 'right' || a === 'end' ? 'kanan' : a === 'center' ? 'tengah' : 'kiri';
}

function samakanRataJudul(t: HTMLTableElement): void {
  const kepala = t.tHead?.rows[t.tHead.rows.length - 1];
  if (!kepala || t.closest('.lembar')) return;

  const judul = Array.from(kepala.cells) as HTMLTableCellElement[];
  if (judul.some((c) => c.colSpan > 1)) return;

  const baris = Array.from(t.tBodies[0]?.rows ?? [])
    .filter((r) => r.cells.length === judul.length)
    .slice(0, 12);
  if (!baris.length) return;

  judul.forEach((th, i) => {
    th.classList.remove(RATA_KANAN);
    if (!(th.textContent ?? '').trim()) return;
    if (getComputedStyle(th).textAlign !== 'left' && getComputedStyle(th).textAlign !== 'start') return;

    let isi = 0;
    let kanan = 0;
    for (const r of baris) {
      const a = rataTeks(r.cells[i] as HTMLElement);
      if (!a) continue;
      isi++;
      if (a === 'kanan') kanan++;
    }

    if (isi && kanan / isi > 0.6) th.classList.add(RATA_KANAN);
  });
}

let antre = 0;
let sedangMemeriksa = false;

function jadwalkan(): void {
  if (antre) return;
  antre = requestAnimationFrame(() => {
    antre = 0;
    sedangMemeriksa = true;
    try { periksa(); } finally {
      // Perubahan kelas/atribut yang kita buat sendiri memicu pengamat;
      // abaikan catatan yang sudah terkumpul supaya tidak berputar.
      pengamat?.takeRecords();
      sedangMemeriksa = false;
    }
  });
}

let pengamat: MutationObserver | null = null;
let ukuran: ResizeObserver | null = null;
const diamati = new WeakSet<Element>();
const lebarTerakhir = new WeakMap<Element, number>();

export function pasangTabelPas(): void {
  if (typeof window === 'undefined' || pengamat) return;

  pengamat = new MutationObserver(() => { if (!sedangMemeriksa) jadwalkan(); });
  const akar = document.getElementById('app') ?? document.body;
  pengamat.observe(akar, { childList: true, subtree: true, characterData: true });

  // Hanya perubahan LEBAR yang berarti; tinggi wadah berubah setiap kali
  // tabelnya sendiri ditumpuk, dan menanggapinya akan berputar.
  ukuran = new ResizeObserver((catatan) => {
    for (const c of catatan) {
      const l = Math.round(c.contentRect.width);
      if (lebarTerakhir.get(c.target) !== l) { lebarTerakhir.set(c.target, l); jadwalkan(); }
    }
  });

  let tunda = 0;
  window.addEventListener('resize', () => {
    clearTimeout(tunda);
    tunda = window.setTimeout(jadwalkan, 120);
  });
  document.fonts?.ready.then(jadwalkan).catch(() => {});

  jadwalkan();
}
