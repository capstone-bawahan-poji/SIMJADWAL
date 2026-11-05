//! Algoritma penjadwalan yang bisa diganti.
//!
//! Semua algoritma memakai antarmuka yang sama (`Algoritma`): menerima layout
//! gen dan `Penilai`, lalu mengembalikan kromosom terbaik. Karena penilaian
//! jadwal ada di modul `evaluasi`, mengganti algoritma tidak mengubah apa pun
//! di luar modul ini.
//!
//! Menambah algoritma baru:
//! 1. Buat file baru di folder ini dan implementasikan trait `Algoritma`.
//! 2. Tambahkan satu varian di `PilihanAlgoritma` (dan di `buat`, `semua_bawaan`).
//!
//! App desktop memilih algoritma lewat JSON, mis.
//! `{"nama": "genetika", "parameter": {"ukuran_populasi": 100}}` atau
//! `{"nama": "hill_climbing", "parameter": {}}`. Parameter yang tidak diisi
//! memakai nilai bawaan.

mod genetika;
mod hill_climbing;

pub use genetika::{Genetika, ParameterGa};
pub use hill_climbing::{HillClimbing, ParameterHc};

use serde::{Deserialize, Serialize};

use crate::evaluasi::{Penilai, Penilaian};
use crate::kromosom::{GeneLayout, Kromosom};

/// Kabar kemajuan saat algoritma berjalan (untuk progress bar di desktop).
#[derive(Debug, Clone, PartialEq, Serialize, Deserialize)]
pub struct Kemajuan {
    pub iterasi: usize,
    pub iterasi_maks: usize,
    pub terbaik: Penilaian,
}

/// Hasil satu kali menjalankan algoritma.
#[derive(Debug, Clone, PartialEq, Serialize, Deserialize)]
pub struct HasilOptimasi {
    pub algoritma: String,
    pub kromosom: Kromosom,
    pub penilaian: Penilaian,
    /// Generasi (GA) atau langkah (Hill Climbing) yang sudah dijalankan.
    pub iterasi: usize,
    /// Berapa kali fungsi penilaian dipanggil. Dipakai untuk membandingkan
    /// algoritma dengan anggaran yang sama.
    pub jumlah_evaluasi: usize,
    /// Penalti terbaik dari waktu ke waktu (untuk grafik konvergensi).
    pub riwayat_penalti: Vec<f64>,
    /// True jika dihentikan dari luar (tombol batal) sebelum selesai.
    pub dihentikan: bool,
}

/// Kontrak yang harus dipenuhi setiap algoritma penjadwalan.
pub trait Algoritma {
    /// Nama yang ditampilkan ke pengguna.
    fn nama(&self) -> &'static str;

    /// Cari jadwal terbaik. `pantau` dipanggil berkala; kembalikan `false`
    /// untuk menghentikan algoritma lebih awal. Hasil harus selalu kromosom
    /// yang valid (setiap gen di dalam domainnya).
    fn jalankan(
        &self,
        layout: &GeneLayout,
        penilai: &Penilai,
        seed: u64,
        pantau: &mut dyn FnMut(&Kemajuan) -> bool,
    ) -> HasilOptimasi;
}

/// Pilihan algoritma beserta parameternya. Ini satu-satunya tempat yang
/// perlu diubah saat menambah atau mengganti algoritma.
#[derive(Debug, Clone, PartialEq, Serialize, Deserialize)]
#[serde(tag = "nama", content = "parameter", rename_all = "snake_case")]
pub enum PilihanAlgoritma {
    Genetika(#[serde(default)] ParameterGa),
    HillClimbing(#[serde(default)] ParameterHc),
}

impl Default for PilihanAlgoritma {
    fn default() -> Self {
        Self::Genetika(ParameterGa::default())
    }
}

impl PilihanAlgoritma {
    pub fn buat(&self) -> Box<dyn Algoritma> {
        match self {
            Self::Genetika(p) => Box::new(Genetika(p.clone())),
            Self::HillClimbing(p) => Box::new(HillClimbing(p.clone())),
        }
    }

    /// Semua algoritma yang tersedia dengan parameter bawaan (untuk dropdown di desktop).
    pub fn semua_bawaan() -> Vec<Self> {
        vec![
            Self::Genetika(ParameterGa::default()),
            Self::HillClimbing(ParameterHc::default()),
        ]
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::evaluasi::tests::data_kecil;
    use crate::evaluasi::BobotPenalti;

    #[test]
    fn pilihan_dibaca_dari_json_dengan_parameter_sebagian() {
        let p: PilihanAlgoritma =
            serde_json::from_str(r#"{"nama":"genetika","parameter":{"ukuran_populasi":30}}"#)
                .unwrap();
        assert_eq!(
            p,
            PilihanAlgoritma::Genetika(ParameterGa {
                ukuran_populasi: 30,
                ..Default::default()
            })
        );

        let hc: PilihanAlgoritma =
            serde_json::from_str(r#"{"nama":"hill_climbing","parameter":{}}"#).unwrap();
        assert_eq!(hc, PilihanAlgoritma::HillClimbing(ParameterHc::default()));

        for p in PilihanAlgoritma::semua_bawaan() {
            let balik: PilihanAlgoritma =
                serde_json::from_str(&serde_json::to_string(&p).unwrap()).unwrap();
            assert_eq!(balik, p);
        }
    }

    #[test]
    fn semua_algoritma_menemukan_jadwal_sempurna_di_data_kecil() {
        let d = data_kecil();
        let penilai = Penilai::baru(&d, BobotPenalti::default());
        for pilihan in PilihanAlgoritma::semua_bawaan() {
            let algo = pilihan.buat();
            let hasil = algo.jalankan(&d.layout, &penilai, 1, &mut |_| true);
            hasil.kromosom.validasi(&d.layout).unwrap();
            assert!(
                hasil.penilaian.sempurna(),
                "{}: {:?}",
                algo.nama(),
                hasil.penilaian
            );
            assert_eq!(hasil.penilaian, penilai.nilai(&hasil.kromosom.genes));
        }
    }

    #[test]
    fn seed_sama_hasil_sama_untuk_semua_algoritma() {
        let d = data_kecil();
        let penilai = Penilai::baru(&d, BobotPenalti::default());
        for pilihan in PilihanAlgoritma::semua_bawaan() {
            let a = pilihan
                .buat()
                .jalankan(&d.layout, &penilai, 9, &mut |_| true);
            let b = pilihan
                .buat()
                .jalankan(&d.layout, &penilai, 9, &mut |_| true);
            assert_eq!(a, b);
        }
    }

    #[test]
    fn algoritma_bisa_dihentikan_dari_luar() {
        let d = data_kecil();
        let penilai = Penilai::baru(&d, BobotPenalti::default());
        for pilihan in PilihanAlgoritma::semua_bawaan() {
            let hasil = pilihan
                .buat()
                .jalankan(&d.layout, &penilai, 3, &mut |_| false);
            assert!(hasil.dihentikan || hasil.penilaian.sempurna());
            hasil.kromosom.validasi(&d.layout).unwrap();
        }
    }
}
