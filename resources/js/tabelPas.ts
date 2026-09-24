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

  const tumpuk = tabel.map((t) => {
    const w = wadah(t);
    // Wadah yang berubah lebar tanpa perubahan DOM — bilah samping
    // dilipat, huruf selesai dimuat — memicu pemeriksaan ulang.
    if (ukuran && !diamati.has(w)) { diamati.add(w); ukuran.observe(w); }
    return t.offsetParent !== null && t.scrollWidth > w.clientWidth + 1;
  });

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
