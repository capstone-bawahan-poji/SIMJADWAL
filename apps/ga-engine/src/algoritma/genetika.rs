//! Algoritma genetika.
//!
//! - Populasi awal: kromosom acak, tiap gen dari domainnya (kunci SKS).
//! - Seleksi: turnamen (ambil beberapa kromosom acak, pilih yang penaltinya terkecil).
//! - Crossover: 1 titik. Aman tanpa perbaikan karena gen di posisi yang sama
//!   selalu berbagi domain.
//! - Mutasi: tiap gen punya peluang kecil diganti slot acak dari domainnya.
//! - Elitisme: beberapa kromosom terbaik langsung dibawa ke generasi berikutnya.
//! - Berhenti jika jadwal sempurna (tanpa pelanggaran HC maupun SC) ditemukan
//!   atau generasi maksimum tercapai.
//!
//! Nilai parameter bawaan adalah titik awal dan akan disetel (tuning) nanti.

use rand::{RngExt, SeedableRng};
use rand_chacha::ChaCha8Rng;
use serde::{Deserialize, Serialize};

use super::{Algoritma, HasilOptimasi, Kemajuan};
use crate::evaluasi::{Penilai, Penilaian};
use crate::kromosom::{GeneLayout, Kromosom};

#[derive(Debug, Clone, PartialEq, Serialize, Deserialize)]
#[serde(default)]
pub struct ParameterGa {
    pub ukuran_populasi: usize,
    pub generasi_maks: usize,
    /// Peluang dua induk disilangkan (0-1).
    pub peluang_crossover: f64,
    /// Peluang tiap gen dimutasi (0-1).
    pub peluang_mutasi: f64,
    /// Banyak kromosom yang diadu dalam satu turnamen seleksi.
    pub ukuran_turnamen: usize,
    /// Banyak kromosom terbaik yang langsung dibawa ke generasi berikutnya.
    pub jumlah_elit: usize,
}

impl Default for ParameterGa {
    fn default() -> Self {
        Self {
            ukuran_populasi: 100,
            generasi_maks: 500,
            peluang_crossover: 0.9,
            peluang_mutasi: 0.01,
            ukuran_turnamen: 3,
            jumlah_elit: 2,
        }
    }
}

pub struct Genetika(pub ParameterGa);

impl Algoritma for Genetika {
    fn nama(&self) -> &'static str {
        "Algoritma Genetika"
    }

    fn jalankan(
        &self,
        layout: &GeneLayout,
        penilai: &Penilai,
        seed: u64,
        pantau: &mut dyn FnMut(&Kemajuan) -> bool,
    ) -> HasilOptimasi {
        let p = &self.0;
        let n = p.ukuran_populasi.max(2);
        let elit = p.jumlah_elit.min(n);
        let turnamen = p.ukuran_turnamen.max(1);
        let mut rng = ChaCha8Rng::seed_from_u64(seed);
        let mut jumlah_evaluasi = 0;
        let mut nilai = |k: Kromosom| {
            jumlah_evaluasi += 1;
            let n = penilai.nilai(&k.genes);
            (k, n)
        };

        let mut populasi: Vec<(Kromosom, Penilaian)> = (0..n)
            .map(|_| nilai(Kromosom::acak(layout, &mut rng)))
            .collect();
        urutkan(&mut populasi);
        let mut riwayat = vec![populasi[0].1.penalti];
        let mut generasi = 0;
        let mut dihentikan = false;

        while generasi < p.generasi_maks && !populasi[0].1.sempurna() {
            if !pantau(&Kemajuan {
                iterasi: generasi,
                iterasi_maks: p.generasi_maks,
                terbaik: populasi[0].1,
            }) {
                dihentikan = true;
                break;
            }
            let mut berikut: Vec<(Kromosom, Penilaian)> = populasi[..elit].to_vec();
            while berikut.len() < n {
                let induk1 = &populasi[pilih_turnamen(&populasi, turnamen, &mut rng)].0;
                let induk2 = &populasi[pilih_turnamen(&populasi, turnamen, &mut rng)].0;
                let (mut anak1, mut anak2) = if rng.random_bool(p.peluang_crossover.clamp(0.0, 1.0))
                {
                    crossover_satu_titik(induk1, induk2, &mut rng)
                } else {
                    (induk1.clone(), induk2.clone())
                };
                mutasi(&mut anak1, layout, p.peluang_mutasi, &mut rng);
                mutasi(&mut anak2, layout, p.peluang_mutasi, &mut rng);
                berikut.push(nilai(anak1));
                if berikut.len() < n {
                    berikut.push(nilai(anak2));
                }
            }
            populasi = berikut;
            urutkan(&mut populasi);
            generasi += 1;
            riwayat.push(populasi[0].1.penalti);
        }
        pantau(&Kemajuan {
            iterasi: generasi,
            iterasi_maks: p.generasi_maks,
            terbaik: populasi[0].1,
        });

        let (kromosom, penilaian) = populasi.swap_remove(0);
        HasilOptimasi {
            algoritma: self.nama().into(),
            kromosom,
            penilaian,
            iterasi: generasi,
            jumlah_evaluasi,
            riwayat_penalti: riwayat,
            dihentikan,
        }
    }
}

/// Urutkan dari penalti terkecil. Urutan stabil supaya hasil tetap sama untuk seed yang sama.
fn urutkan(populasi: &mut [(Kromosom, Penilaian)]) {
    populasi.sort_by(|a, b| a.1.penalti.total_cmp(&b.1.penalti));
}

fn pilih_turnamen(
    populasi: &[(Kromosom, Penilaian)],
    ukuran: usize,
    rng: &mut ChaCha8Rng,
) -> usize {
    (0..ukuran)
        .map(|_| rng.random_range(0..populasi.len()))
        .min_by(|&a, &b| populasi[a].1.penalti.total_cmp(&populasi[b].1.penalti))
        .expect("ukuran turnamen minimal 1")
}

fn crossover_satu_titik(a: &Kromosom, b: &Kromosom, rng: &mut ChaCha8Rng) -> (Kromosom, Kromosom) {
    let panjang = a.genes.len();
    if panjang < 2 {
        return (a.clone(), b.clone());
    }
    let titik = rng.random_range(1..panjang);
    let mut anak1 = a.genes[..titik].to_vec();
    anak1.extend_from_slice(&b.genes[titik..]);
    let mut anak2 = b.genes[..titik].to_vec();
    anak2.extend_from_slice(&a.genes[titik..]);
    (Kromosom { genes: anak1 }, Kromosom { genes: anak2 })
}

fn mutasi(k: &mut Kromosom, layout: &GeneLayout, peluang: f64, rng: &mut ChaCha8Rng) {
    let peluang = peluang.clamp(0.0, 1.0);
    for (i, gen) in k.genes.iter_mut().enumerate() {
        if rng.random_bool(peluang) {
            let domain = layout.domain(i);
            *gen = domain[rng.random_range(0..domain.len())];
        }
    }
}
