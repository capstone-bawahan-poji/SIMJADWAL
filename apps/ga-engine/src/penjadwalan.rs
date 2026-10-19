//! Pintu masuk untuk app desktop: jalankan algoritma pilihan, lalu susun
//! jadwal akhir lengkap dengan ruangan.

use std::time::Instant;

use serde::{Deserialize, Serialize};

use crate::algoritma::{HasilOptimasi, Kemajuan, PilihanAlgoritma};
use crate::dataset::Dataset;
use crate::error::KromosomError;
use crate::evaluasi::{BobotPenalti, Pelanggaran, Penilai};
use crate::kromosom::{JadwalEntry, Kromosom};

/// Semua yang bisa diatur dari app desktop, dalam satu objek JSON, mis.
/// `{"algoritma": {"nama": "genetika", "parameter": {}}, "bobot": {}, "seed": 12345}`.
/// Field yang tidak diisi memakai nilai bawaan.
#[derive(Debug, Clone, PartialEq, Serialize, Deserialize)]
#[serde(default)]
pub struct Pengaturan {
    pub algoritma: PilihanAlgoritma,
    pub bobot: BobotPenalti,
    pub seed: u64,
}

impl Default for Pengaturan {
    fn default() -> Self {
        Self {
            algoritma: PilihanAlgoritma::default(),
            bobot: BobotPenalti::default(),
            seed: 12345,
        }
    }
}

#[derive(Debug, Clone, PartialEq, Serialize, Deserialize)]
pub struct HasilPenjadwalan {
    pub hasil: HasilOptimasi,
    /// Jadwal akhir, satu baris per kelas, sudah berisi ruangan.
    pub jadwal: Vec<JadwalEntry>,
    pub pelanggaran: Vec<Pelanggaran>,
    pub durasi_ms: u64,
}

/// Jalankan algoritma sesuai pengaturan dan susun jadwal akhirnya.
pub fn jalankan_penjadwalan(
    data: &Dataset,
    pengaturan: &Pengaturan,
    pantau: &mut dyn FnMut(&Kemajuan) -> bool,
) -> Result<HasilPenjadwalan, KromosomError> {
    let penilai = Penilai::baru(data, pengaturan.bobot);
    let mulai = Instant::now();
    let hasil =
        pengaturan
            .algoritma
            .buat()
            .jalankan(&data.layout, &penilai, pengaturan.seed, pantau);
    let durasi_ms = mulai.elapsed().as_millis() as u64;
    hasil.kromosom.validasi(&data.layout)?;
    Ok(HasilPenjadwalan {
        jadwal: susun_jadwal(data, &hasil.kromosom)?,
        pelanggaran: penilai.rincian(&hasil.kromosom.genes),
        hasil,
        durasi_ms,
    })
}

/// Bagikan ruangan ke kelas di tiap slot, urut sesuai daftar ruangan.
/// Kelas yang tidak kebagian (slot penuh) mendapat `None`.
/// Data ruangan belum punya kapasitas, jadi semua ruangan dianggap setara.
pub fn alokasi_ruangan(
    kromosom: &Kromosom,
    ruang: &[String],
    total_slot: usize,
) -> Vec<Option<String>> {
    let mut terpakai = vec![0usize; total_slot];
    kromosom
        .genes
        .iter()
        .map(|&slot| {
            let i = &mut terpakai[slot as usize - 1];
            let r = ruang.get(*i).cloned();
            *i += 1;
            r
        })
        .collect()
}

/// Decode kromosom menjadi jadwal yang bisa dibaca, lengkap dengan ruangan.
pub fn susun_jadwal(
    data: &Dataset,
    kromosom: &Kromosom,
) -> Result<Vec<JadwalEntry>, KromosomError> {
    let mut jadwal = kromosom.decode(&data.layout, &data.slot)?;
    let ruangan = alokasi_ruangan(kromosom, &data.ruang, data.slot.total() as usize);
    for (j, r) in jadwal.iter_mut().zip(ruangan) {
        j.ruangan = r;
    }
    Ok(jadwal)
}

#[cfg(test)]
mod tests {
    use std::collections::BTreeSet;

    use super::*;
    use crate::evaluasi::tests::data_kecil;

    #[test]
    fn pengaturan_dari_json_desktop() {
        let p: Pengaturan = serde_json::from_str(
            r#"{"algoritma":{"nama":"hill_climbing","parameter":{"langkah_maks":10}},"seed":7}"#,
        )
        .unwrap();
        assert_eq!(p.seed, 7);
        assert_eq!(p.bobot, BobotPenalti::default());
        assert!(
            matches!(p.algoritma, PilihanAlgoritma::HillClimbing(ref h) if h.langkah_maks == 10)
        );
        let kosong: Pengaturan = serde_json::from_str("{}").unwrap();
        assert_eq!(kosong, Pengaturan::default());
    }

    #[test]
    fn ruangan_tidak_dobel_dalam_satu_slot() {
        let k = Kromosom {
            genes: vec![1, 1, 1, 2],
        };
        let ruang = vec!["E101".to_string(), "E102".to_string()];
        assert_eq!(
            alokasi_ruangan(&k, &ruang, 20),
            vec![
                Some("E101".into()),
                Some("E102".into()),
                None,
                Some("E101".into())
            ]
        );
    }

    #[test]
    fn jadwal_akhir_lengkap_dengan_ruangan() {
        let d = data_kecil();
        let h = jalankan_penjadwalan(&d, &Pengaturan::default(), &mut |_| true).unwrap();
        assert!(h.pelanggaran.is_empty());
        assert_eq!(h.jadwal.len(), 4);
        assert!(h
            .jadwal
            .iter()
            .all(|j| j.ruangan.is_some() && j.waktu.tipe_sks == j.sks));
        let pasangan: BTreeSet<_> = h
            .jadwal
            .iter()
            .map(|j| (j.waktu.id_slot, j.ruangan.clone()))
            .collect();
        assert_eq!(
            pasangan.len(),
            4,
            "tidak ada ruangan dipakai dua kelas di slot yang sama"
        );
    }
}
