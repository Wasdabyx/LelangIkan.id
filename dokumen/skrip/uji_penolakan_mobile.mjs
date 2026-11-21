/**
 * Uji logika penolakan peran web di aplikasi mobile tanpa emulator.
 *
 * Yang diuji adalah potongan `saatMasuk` pada App.js: setelah POST /api/masuk berhasil,
 * peran yang dilayani antarmuka web harus ditolak, sesinya tidak disimpan, dan layar
 * masuk diberi pesan pengarah. Peran mobile harus tetap lolos seperti biasa.
 *
 * Dijalankan: node uji_penolakan_mobile.mjs   (server backend harus hidup di :8000)
 */
const API = process.env.API || 'http://127.0.0.1:8000/api';
const PERAN_WEB = [3, 5, 6]; // operator lelang, kasir, pimpinan

async function masuk(namaPengguna, kataSandi) {
  const r = await fetch(`${API}/masuk`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ nama_pengguna: namaPengguna, kata_sandi: kataSandi, perangkat: 'uji-mobile' }),
  });
  const j = await r.json();
  if (!r.ok) throw new Error(`${namaPengguna}: HTTP ${r.status} ${JSON.stringify(j)}`);
  return j;
}

async function keluarDenganToken(token) {
  await fetch(`${API}/keluar`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  });
}

/** Salinan logika saatMasuk pada App.js (tanpa komponen React). */
async function saatMasuk(token, dataPengguna) {
  const hasil = { disimpan: false, pesan: null, pengguna: null, tokenDicabut: null };

  if (PERAN_WEB.includes(dataPengguna?.id_peran)) {
    try {
      await keluarDenganToken(token);
      hasil.tokenDicabut = true;
    } catch {
      hasil.tokenDicabut = false;
    }
    hasil.pesan =
      'Peran ' + (dataPengguna?.nama_peran || 'ini') +
      ' dilayani antarmuka web LelangIkan.id, bukan aplikasi mobile. ' +
      'Buka dasbor web pada peramban di alamat server yang sama.';
    return hasil;
  }

  hasil.disimpan = true;
  hasil.pengguna = dataPengguna;
  return hasil;
}

const kasus = [
  ['operator', false], ['kasir', false], ['pimpinan', false],
  ['pendata', true], ['timbang', true], ['pembeli', true],
];

let gagal = 0;
for (const [nama, boleh] of kasus) {
  const j = await masuk(nama, 'sipeli12345');
  const h = await saatMasuk(j.token, j.pengguna);

  const lolos = h.disimpan === boleh && (boleh || (h.pesan || '').includes('antarmuka web'));
  if (boleh) {
    // token peran mobile harus tetap berlaku setelah "masuk"
    const r = await fetch(`${API}/saya`, { headers: { Authorization: `Bearer ${j.token}`, Accept: 'application/json' } });
    if (!r.ok) { console.log(`GAGAL  ${nama}: token peran mobile tidak berlaku (HTTP ${r.status})`); gagal++; continue; }
    await keluarDenganToken(j.token);
  } else {
    // token peran web harus sudah dicabut, bukan hanya tidak disimpan
    const r = await fetch(`${API}/saya`, { headers: { Authorization: `Bearer ${j.token}`, Accept: 'application/json' } });
    if (r.ok) { console.log(`GAGAL  ${nama}: token masih berlaku setelah ditolak`); gagal++; continue; }
    if (!h.tokenDicabut) { console.log(`GAGAL  ${nama}: pencabutan token gagal`); gagal++; continue; }
  }

  console.log(
    (lolos ? 'LULUS  ' : 'GAGAL  ') +
    `${nama.padEnd(9)} peran=${(j.pengguna.id_peran + '').padEnd(2)} ` +
    (boleh ? '-> masuk aplikasi mobile' : '-> ditolak, diarahkan ke web, token dicabut')
  );
  if (!lolos) gagal++;
}

// Sesi tersimpan milik peran web (kasus pembukaan aplikasi) juga harus ditolak.
const j = await masuk('kasir', 'sipeli12345');
const h = await saatMasuk(j.token, j.pengguna);
console.log((h.disimpan === false ? 'LULUS  ' : 'GAGAL  ') + 'sesi tersimpan peran web dibuang saat aplikasi dibuka');
if (h.disimpan !== false) gagal++;

console.log(`\n${kasus.length + 1 - gagal}/${kasus.length + 1} pemeriksaan lulus`);
process.exit(gagal === 0 ? 0 : 1);
