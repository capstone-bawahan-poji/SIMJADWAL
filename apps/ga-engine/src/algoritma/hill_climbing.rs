//! Hill Climbing: algoritma pembanding yang lebih sederhana dari GA.
//!
//! Mulai dari satu kromosom acak. Di tiap langkah, satu kelas dipindah ke
//! slot acak lain dari domainnya (kunci SKS tetap berlaku). Perubahan
//! dipertahankan jika penaltinya tidak lebih buruk.
//!
//! Dipakai untuk membuktikan bahwa algoritma bisa diganti tanpa mengubah
//! bagian lain, dan sebagai pembanding hasil GA dengan jumlah evaluasi yang sama.

use rand::{RngExt, SeedableRng};
use rand_chacha::ChaCha8Rng;
use serde::{Deserialize, Serialize};

use super::{Algoritma, HasilOptimasi, Kemajuan};
use crate::evaluasi::Penilai;
use crate::kromosom::{GeneLayout, Kromosom};

#[derive(Debug, Clone, PartialEq, Serialize, Deserialize)]
#[serde(default)]
pub struct ParameterHc {
    /// Banyak langkah maksimum. Bawaan 50.000 = sama dengan anggaran
    /// evaluasi GA bawaan (populasi 100 x 500 generasi).
    pub langkah_maks: usize,
}

impl Default for ParameterHc {
    fn default() -> Self {
        Self {
            langkah_maks: 50_000,
        }
    }
}

/// Seberapa sering kemajuan dilaporkan dan dicatat ke riwayat.
const INTERVAL_LAPORAN: usize = 100;

pub struct HillClimbing(pub ParameterHc);

impl Algoritma for HillClimbing {
    fn nama(&self) -> &'static str {
        "Hill Climbing"
    }

    fn jalankan(
        &self,
        layout: &GeneLayout,
        penilai: &Penilai,
        seed: u64,
        pantau: &mut dyn FnMut(&Kemajuan) -> bool,
    ) -> HasilOptimasi {
        let maks = self.0.langkah_maks;
        let mut rng = ChaCha8Rng::seed_from_u64(seed);
        let mut kromosom = Kromosom::acak(layout, &mut rng);
        let mut nilai = penilai.nilai(&kromosom.genes);
        let mut jumlah_evaluasi = 1;
        let mut riwayat = vec![nilai.penalti];
        let mut langkah = 0;
        let mut dihentikan = false;

        while langkah < maks && !nilai.sempurna() {
            if langkah % INTERVAL_LAPORAN == 0
                && !pantau(&Kemajuan {
                    iterasi: langkah,
                    iterasi_maks: maks,
                    terbaik: nilai,
                })
            {
                dihentikan = true;
                break;
            }
            let gen = rng.random_range(0..kromosom.genes.len());
            let domain = layout.domain(gen);
            let lama = kromosom.genes[gen];
            kromosom.genes[gen] = domain[rng.random_range(0..domain.len())];
            let baru = penilai.nilai(&kromosom.genes);
            jumlah_evaluasi += 1;
            if baru.penalti <= nilai.penalti {
                nilai = baru;
            } else {
                kromosom.genes[gen] = lama;
            }
            langkah += 1;
            if langkah % INTERVAL_LAPORAN == 0 {
                riwayat.push(nilai.penalti);
            }
        }
        pantau(&Kemajuan {
            iterasi: langkah,
            iterasi_maks: maks,
            terbaik: nilai,
        });

        HasilOptimasi {
            algoritma: self.nama().into(),
            kromosom,
            penilaian: nilai,
            iterasi: langkah,
            jumlah_evaluasi,
            riwayat_penalti: riwayat,
            dihentikan,
        }
    }
}
