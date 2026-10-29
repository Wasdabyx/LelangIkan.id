import { Lencana } from '../../components/Dasar';
import { warna } from '../../tema';

/** Lencana status lot lelang (DPPL diagram status lot) */
const STATUS_LOT = {
  DRAFT_HARGA: { teks: 'Draf harga', latar: warna.peringatanLatar, warna: warna.peringatan },
  MENUNGGU_LELANG: { teks: 'Siap dilelang', latar: warna.suksesLatar, warna: warna.sukses },
  DILELANG: { teks: 'Sedang dilelang', latar: warna.utamaMuda, warna: warna.utama },
  TERJUAL: { teks: 'Terjual', latar: warna.suksesLatar, warna: warna.sukses },
  TIDAK_TERJUAL: { teks: 'Tidak terjual', latar: warna.abu, warna: '#344054' },
  DIBATALKAN: { teks: 'Dibatalkan', latar: warna.galatLatar, warna: warna.galat },
  SELESAI: { teks: 'Selesai', latar: warna.abu, warna: '#344054' },
};

export default function LencanaLot({ status }) {
  const w = STATUS_LOT[status] || { teks: status, latar: warna.abu, warna: warna.teks };
  return <Lencana teks={w.teks} latar={w.latar} warnaTeks={w.warna} />;
}
