//! Struktur kromosom dan encoding bilangan asli.
//!
//! - Satu kromosom = satu jadwal utuh.
//! - Satu gen      = satu kelas (satu baris matkul.json).
//! - Nilai gen     = id slot waktu (1..=20), selalu diambil dari domain
//!   kelas itu: kelas 3 SKS hanya di sesi 1/3, kelas 2 SKS di sesi 2/4.
//!
//! Karena nilai gen dibatasi domainnya sejak awal, kromosom yang valid
//! otomatis memenuhi aturan SKS-sesi. Pelanggaran lain (bentrok dosen,
//! kelebihan kelas per slot) dinilai oleh fungsi fitness, bukan di sini.
//!
//! Ruangan tidak masuk kromosom. Ruangan dialokasikan setelah GA selesai.

use rand::{Rng, RngExt, SeedableRng};
use rand_chacha::ChaCha8Rng;
use serde::{Deserialize, Serialize};

use crate::error::KromosomError;
use crate::slot::{DaftarSlot, IdSlot, SlotWaktu};

/// Satu kelas yang harus dijadwalkan.
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct Kelas {
    /// Identitas unik, mis. "IF2514101-1".
    pub id: String,
    pub kode_mk: String,
    pub nama_mk: String,
    pub kelas: String,
    pub sks: u8,
    /// Prefix kode MK, mis. "IF".
    pub prodi: String,
    /// Label fakultas, mis. "FSTI". Kosong jika data dimuat tanpa label.
    #[serde(default)]
    pub fakultas: Option<String>,
    /// Nama dosen setelah disatukan ejaannya.
    pub dosen: Vec<String>,
    /// Nomor dosen (untuk cek bentrok cepat di fitness).
    pub dosen_idx: Vec<u16>,
    /// Slot yang boleh diisi gen ini.
    pub slot_diizinkan: Vec<IdSlot>,
}

/// Urutan tetap kelas di dalam kromosom. Gen ke-i selalu milik `kelas[i]`.
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct GeneLayout {
    kelas: Vec<Kelas>,
}

impl GeneLayout {
    /// Kelas diurutkan menurut `id`, jadi urutan gen tidak bergantung
    /// pada urutan data di file atau di basis data.
    pub fn baru(mut kelas: Vec<Kelas>) -> Result<Self, KromosomError> {
        if kelas.is_empty() {
            return Err(KromosomError::LayoutKosong);
        }
        kelas.sort_by(|a, b| a.id.cmp(&b.id));
        if let Some(p) = kelas.windows(2).find(|p| p[0].id == p[1].id) {
            return Err(KromosomError::KelasDuplikat(p[0].id.clone()));
        }
        if let Some(k) = kelas.iter().find(|k| k.slot_diizinkan.is_empty()) {
            return Err(KromosomError::DataTidakValid(format!("kelas {} tidak punya slot", k.id)));
        }
        Ok(Self { kelas })
    }

    pub fn panjang(&self) -> usize {
        self.kelas.len()
    }

    pub fn kelas(&self) -> &[Kelas] {
        &self.kelas
    }

    /// Nilai yang boleh diisi gen ke-`gen`. Dipakai inisialisasi dan mutasi.
    pub fn domain(&self, gen: usize) -> &[IdSlot] {
        &self.kelas[gen].slot_diizinkan
    }
}

/// Satu kandidat jadwal.
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct Kromosom {
    pub genes: Vec<IdSlot>,
}

/// Satu baris jadwal hasil decode.
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct JadwalEntry {
    pub id_kelas: String,
    pub kode_mk: String,
    pub nama_mk: String,
    pub kelas: String,
    pub sks: u8,
    pub prodi: String,
    #[serde(default)]
    pub fakultas: Option<String>,
    pub dosen: Vec<String>,
    pub waktu: SlotWaktu,
}

impl Kromosom {
    /// Buat kromosom dari gen yang sudah ada (mis. hasil crossover) dan
    /// pastikan valid.
    pub fn dari_genes(genes: Vec<IdSlot>, layout: &GeneLayout) -> Result<Self, KromosomError> {
        let k = Self { genes };
        k.validasi(layout)?;
        Ok(k)
    }

    /// Kromosom acak: tiap gen diisi slot acak dari domainnya sendiri.
    pub fn acak<R: Rng + ?Sized>(layout: &GeneLayout, rng: &mut R) -> Self {
        let genes = (0..layout.panjang())
            .map(|i| {
                let domain = layout.domain(i);
                domain[rng.random_range(0..domain.len())]
            })
            .collect();
        Self { genes }
    }

    /// Panjang harus sama dengan jumlah kelas, dan tiap gen ada di domainnya.
    pub fn validasi(&self, layout: &GeneLayout) -> Result<(), KromosomError> {
        if self.genes.len() != layout.panjang() {
            return Err(KromosomError::PanjangTidakSesuai {
                diharapkan: layout.panjang(),
                didapat: self.genes.len(),
            });
        }
        for (i, (&slot, kelas)) in self.genes.iter().zip(layout.kelas()).enumerate() {
            if !kelas.slot_diizinkan.contains(&slot) {
                return Err(KromosomError::SlotTidakDiizinkan { gen: i, id_kelas: kelas.id.clone(), slot });
            }
        }
        Ok(())
    }

    /// Terjemahkan kromosom menjadi daftar jadwal yang bisa dibaca manusia.
    pub fn decode(&self, layout: &GeneLayout, slot: &DaftarSlot) -> Result<Vec<JadwalEntry>, KromosomError> {
        self.validasi(layout)?;
        self.genes
            .iter()
            .zip(layout.kelas())
            .map(|(&id_slot, k)| {
                Ok(JadwalEntry {
                    id_kelas: k.id.clone(),
                    kode_mk: k.kode_mk.clone(),
                    nama_mk: k.nama_mk.clone(),
                    kelas: k.kelas.clone(),
                    sks: k.sks,
                    prodi: k.prodi.clone(),
                    fakultas: k.fakultas.clone(),
                    dosen: k.dosen.clone(),
                    waktu: slot.get(id_slot)?.clone(),
                })
            })
            .collect()
    }

    /// Jumlah kelas di tiap slot (index 0 = slot 1). Berguna untuk melihat
    /// sebaran jadwal dan membandingkan dengan jumlah ruangan.
    pub fn kelas_per_slot(&self, total_slot: IdSlot) -> Vec<usize> {
        let mut hitung = vec![0; total_slot as usize];
        for &g in &self.genes {
            if let Some(n) = hitung.get_mut(g as usize - 1) {
                *n += 1;
            }
        }
        hitung
    }
}

/// Populasi awal sebanyak `ukuran` kromosom acak.
///
/// ChaCha8 dengan `base_seed`: seed yang sama selalu menghasilkan populasi
/// yang sama di perangkat mana pun, sehingga eksperimen bisa diulang.
pub fn populasi_awal(layout: &GeneLayout, ukuran: usize, base_seed: u64) -> Vec<Kromosom> {
    let mut rng = ChaCha8Rng::seed_from_u64(base_seed);
    (0..ukuran).map(|_| Kromosom::acak(layout, &mut rng)).collect()
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::slot::tests::sesi_standar;

    fn kelas(id: &str, sks: u8, dosen: u16, slot: &DaftarSlot) -> Kelas {
        let sesi: &[u8] = if sks == 3 { &[1, 3] } else { &[2, 4] };
        Kelas {
            id: id.into(),
            kode_mk: id.split('-').next().unwrap().into(),
            nama_mk: format!("MK {id}"),
            kelas: "A".into(),
            sks,
            prodi: "IF".into(),
            fakultas: None,
            dosen: vec![format!("Dosen {dosen}")],
            dosen_idx: vec![dosen],
            slot_diizinkan: slot.domain_untuk(sks, sesi),
        }
    }

    fn contoh() -> (DaftarSlot, GeneLayout) {
        let s = DaftarSlot::dari_sesi(&sesi_standar()).unwrap();
        let l = GeneLayout::baru(vec![
            kelas("IF3-1", 2, 2, &s),
            kelas("IF1-1", 3, 1, &s),
            kelas("IF1-2", 3, 1, &s),
        ])
        .unwrap();
        (s, l)
    }

    #[test]
    fn layout_diurutkan_menurut_id() {
        let (_, l) = contoh();
        let ids: Vec<_> = l.kelas().iter().map(|k| k.id.as_str()).collect();
        assert_eq!(ids, vec!["IF1-1", "IF1-2", "IF3-1"]);
    }

    #[test]
    fn layout_menolak_id_ganda_dan_kosong() {
        let s = DaftarSlot::dari_sesi(&sesi_standar()).unwrap();
        assert_eq!(
            GeneLayout::baru(vec![kelas("A-1", 3, 1, &s), kelas("A-1", 2, 2, &s)]),
            Err(KromosomError::KelasDuplikat("A-1".into()))
        );
        assert_eq!(GeneLayout::baru(vec![]), Err(KromosomError::LayoutKosong));
    }

    #[test]
    fn kromosom_acak_selalu_di_domainnya() {
        let (_, l) = contoh();
        for k in populasi_awal(&l, 500, 42) {
            k.validasi(&l).unwrap();
            assert_eq!(k.genes[0] % 2, 1, "3 SKS -> slot ganjil (sesi 1/3)");
            assert_eq!(k.genes[2] % 2, 0, "2 SKS -> slot genap (sesi 2/4)");
        }
    }

    #[test]
    fn seed_sama_hasil_sama() {
        let (_, l) = contoh();
        assert_eq!(populasi_awal(&l, 50, 7), populasi_awal(&l, 50, 7));
        assert_ne!(populasi_awal(&l, 50, 7), populasi_awal(&l, 50, 8));
    }

    #[test]
    fn validasi_menangkap_gen_rusak() {
        let (_, l) = contoh();
        assert!(Kromosom::dari_genes(vec![1, 3], &l).is_err()); // kurang panjang
        assert!(matches!(
            Kromosom::dari_genes(vec![2, 3, 4], &l), // slot 2 = sesi 2, bukan untuk 3 SKS
            Err(KromosomError::SlotTidakDiizinkan { gen: 0, .. })
        ));
        assert!(Kromosom::dari_genes(vec![1, 19, 20], &l).is_ok());
    }

    #[test]
    fn decode_memetakan_gen_ke_kelas_yang_benar() {
        let (s, l) = contoh();
        let jadwal = Kromosom::dari_genes(vec![1, 9, 20], &l).unwrap().decode(&l, &s).unwrap();
        let ringkas: Vec<_> = jadwal
            .iter()
            .map(|j| (j.id_kelas.as_str(), j.waktu.nama_hari.as_str(), j.waktu.sesi_ke, j.waktu.mulai.as_str()))
            .collect();
        assert_eq!(
            ringkas,
            vec![
                ("IF1-1", "Senin", 1, "08:00"),
                ("IF1-2", "Rabu", 1, "08:00"),
                ("IF3-1", "Jumat", 4, "15:30"),
            ]
        );
    }

    #[test]
    fn kelas_per_slot_menghitung_sebaran() {
        let k = Kromosom { genes: vec![1, 1, 20] };
        let n = k.kelas_per_slot(20);
        assert_eq!((n[0], n[19], n.iter().sum::<usize>()), (2, 1, 3));
    }

    #[test]
    fn jadwal_bisa_dikirim_sebagai_json() {
        let (s, l) = contoh();
        let jadwal = Kromosom::dari_genes(vec![3, 7, 6], &l).unwrap().decode(&l, &s).unwrap();
        let json = serde_json::to_string(&jadwal).unwrap();
        let balik: Vec<JadwalEntry> = serde_json::from_str(&json).unwrap();
        assert_eq!(balik, jadwal);
    }
}
