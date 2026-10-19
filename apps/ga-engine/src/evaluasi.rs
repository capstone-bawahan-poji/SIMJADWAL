//! Penilaian jadwal (fungsi fitness): hard constraint (HC) + soft constraint (SC).
//!
//! Modul ini terpisah dari algoritma. GA, Hill Climbing, atau algoritma lain
//! yang ditambahkan nanti semuanya memakai `Penilai` yang sama, sehingga
//! mengganti algoritma tidak mengubah cara jadwal dinilai.
//!
//! **Hard constraint** (wajib dipenuhi, jadwal tidak layak jika dilanggar):
//! - `dosen_bentrok`: satu dosen mengajar lebih dari satu kelas di slot yang sama.
//! - `melebihi_ruangan`: jumlah kelas di satu slot lebih banyak dari jumlah ruangan.
//!
//! **Soft constraint** (sebaiknya dipenuhi, jadwal tetap layak jika dilanggar):
//! - `dosen_padat`: dosen mengajar lebih dari `batas_kelas_dosen_per_hari` kelas dalam sehari.
//! - `dosen_tanpa_jeda`: dosen mengajar di dua sesi yang bersambung tanpa jeda
//!   (sesi 1 lalu 2, atau sesi 3 lalu 4).
//!
//! Penalti = jumlah (bobot x banyak pelanggaran). Bobot HC dibuat jauh lebih
//! besar (1000) daripada SC (1), sehingga satu pelanggaran HC selalu lebih
//! buruk daripada ratusan pelanggaran SC. Fitness = 1 / (1 + penalti).
//!
//! Kunci SKS tidak dinilai di sini karena sudah dijamin oleh domain gen.

use serde::{Deserialize, Serialize};

use crate::dataset::Dataset;
use crate::slot::IdSlot;

/// Bobot penalti tiap aturan, plus batas untuk aturan `dosen_padat`.
#[derive(Debug, Clone, Copy, PartialEq, Serialize, Deserialize)]
#[serde(default)]
pub struct BobotPenalti {
    // Hard constraint
    pub dosen_bentrok: f64,
    pub melebihi_ruangan: f64,
    // Soft constraint
    pub dosen_padat: f64,
    pub dosen_tanpa_jeda: f64,
    /// Bukan bobot: batas kelas seorang dosen per hari untuk aturan `dosen_padat`.
    pub batas_kelas_dosen_per_hari: u16,
}

impl Default for BobotPenalti {
    fn default() -> Self {
        Self {
            dosen_bentrok: 1000.0,
            melebihi_ruangan: 1000.0,
            dosen_padat: 1.0,
            dosen_tanpa_jeda: 1.0,
            batas_kelas_dosen_per_hari: 2,
        }
    }
}

/// Ringkasan nilai satu jadwal.
#[derive(Debug, Clone, Copy, PartialEq, Serialize, Deserialize)]
pub struct Penilaian {
    /// Semakin kecil semakin baik. 0 berarti tidak ada pelanggaran sama sekali.
    pub penalti: f64,
    /// 1 / (1 + penalti). Nilai 1 berarti jadwal sempurna.
    pub fitness: f64,
    // Hard constraint
    pub dosen_bentrok: usize,
    pub melebihi_ruangan: usize,
    // Soft constraint
    pub dosen_padat: usize,
    pub dosen_tanpa_jeda: usize,
}

impl Penilaian {
    pub fn pelanggaran_hard(&self) -> usize {
        self.dosen_bentrok + self.melebihi_ruangan
    }

    pub fn pelanggaran_soft(&self) -> usize {
        self.dosen_padat + self.dosen_tanpa_jeda
    }

    /// Semua hard constraint terpenuhi: jadwal layak dipakai.
    pub fn layak(&self) -> bool {
        self.pelanggaran_hard() == 0
    }

    /// Tidak ada pelanggaran sama sekali, hard maupun soft.
    pub fn sempurna(&self) -> bool {
        self.layak() && self.pelanggaran_soft() == 0
    }
}

#[derive(Debug, Clone, Copy, PartialEq, Eq, Serialize, Deserialize)]
#[serde(rename_all = "snake_case")]
pub enum JenisAturan {
    Hard,
    Soft,
}

/// Satu pelanggaran beserta kelas yang terlibat, untuk ditampilkan di app
/// desktop atau dicatat saat koordinator mengubah jadwal secara manual.
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
#[serde(tag = "aturan", rename_all = "snake_case")]
pub enum Pelanggaran {
    DosenBentrok {
        dosen: String,
        id_slot: IdSlot,
        id_kelas: Vec<String>,
    },
    MelebihiRuangan {
        id_slot: IdSlot,
        jumlah_kelas: usize,
        jumlah_ruangan: usize,
    },
    DosenPadat {
        dosen: String,
        hari: String,
        jumlah_kelas: usize,
        batas: u16,
        id_kelas: Vec<String>,
    },
    DosenTanpaJeda {
        dosen: String,
        hari: String,
        id_kelas: Vec<String>,
    },
}

impl Pelanggaran {
    pub fn jenis(&self) -> JenisAturan {
        match self {
            Self::DosenBentrok { .. } | Self::MelebihiRuangan { .. } => JenisAturan::Hard,
            Self::DosenPadat { .. } | Self::DosenTanpaJeda { .. } => JenisAturan::Soft,
        }
    }
}

/// Menilai jadwal untuk satu dataset. Dibuat sekali, dipakai berulang kali.
#[derive(Debug, Clone)]
pub struct Penilai {
    dosen_per_gen: Vec<Vec<u16>>,
    id_kelas: Vec<String>,
    nama_dosen: Vec<String>,
    jumlah_slot: usize,
    jumlah_ruangan: usize,
    /// Index slot (0-based) yang termasuk tiap hari, dan nama harinya.
    slot_per_hari: Vec<(String, Vec<usize>)>,
    /// Pasangan slot bersambung di hari yang sama (selesai slot a = mulai slot b).
    pasangan_tanpa_jeda: Vec<(usize, usize)>,
    pub bobot: BobotPenalti,
}

impl Penilai {
    pub fn baru(data: &Dataset, bobot: BobotPenalti) -> Self {
        let kelas = data.layout.kelas();
        let slot = data.slot.semua();

        let mut slot_per_hari: Vec<(String, Vec<usize>)> = Vec::new();
        for (i, s) in slot.iter().enumerate() {
            match slot_per_hari
                .iter_mut()
                .find(|(hari, _)| *hari == s.nama_hari)
            {
                Some((_, isi)) => isi.push(i),
                None => slot_per_hari.push((s.nama_hari.clone(), vec![i])),
            }
        }
        let mut pasangan_tanpa_jeda = Vec::new();
        for (a, sa) in slot.iter().enumerate() {
            for (b, sb) in slot.iter().enumerate() {
                if sa.hari_index == sb.hari_index && sa.selesai == sb.mulai {
                    pasangan_tanpa_jeda.push((a, b));
                }
            }
        }

        Self {
            dosen_per_gen: kelas.iter().map(|k| k.dosen_idx.clone()).collect(),
            id_kelas: kelas.iter().map(|k| k.id.clone()).collect(),
            nama_dosen: data.dosen.clone(),
            jumlah_slot: slot.len(),
            jumlah_ruangan: data.ruang.len(),
            slot_per_hari,
            pasangan_tanpa_jeda,
            bobot,
        }
    }

    /// Banyak kelas per (dosen, slot) dan per slot.
    fn hitung(&self, genes: &[IdSlot]) -> (Vec<u16>, Vec<usize>) {
        let mut per_dosen = vec![0u16; self.nama_dosen.len() * self.jumlah_slot];
        let mut per_slot = vec![0usize; self.jumlah_slot];
        for (dosen, &slot) in self.dosen_per_gen.iter().zip(genes) {
            let s = slot as usize - 1;
            per_slot[s] += 1;
            for &d in dosen {
                per_dosen[d as usize * self.jumlah_slot + s] += 1;
            }
        }
        (per_dosen, per_slot)
    }

    fn kelas_dosen_di_hari(&self, per_dosen: &[u16], dosen: usize, slot_hari: &[usize]) -> usize {
        slot_hari
            .iter()
            .map(|&s| per_dosen[dosen * self.jumlah_slot + s] as usize)
            .sum()
    }

    fn mengajar(&self, per_dosen: &[u16], dosen: usize, slot: usize) -> bool {
        per_dosen[dosen * self.jumlah_slot + slot] > 0
    }

    /// Nilai satu jadwal. `genes` harus kromosom yang valid (lihat `Kromosom::validasi`).
    pub fn nilai(&self, genes: &[IdSlot]) -> Penilaian {
        debug_assert_eq!(genes.len(), self.dosen_per_gen.len());
        let (per_dosen, per_slot) = self.hitung(genes);
        let batas = self.bobot.batas_kelas_dosen_per_hari as usize;

        // Hard constraint
        let dosen_bentrok: usize = per_dosen
            .iter()
            .map(|&n| n.saturating_sub(1) as usize)
            .sum();
        let melebihi_ruangan: usize = per_slot
            .iter()
            .map(|&n| n.saturating_sub(self.jumlah_ruangan))
            .sum();

        // Soft constraint
        let mut dosen_padat = 0;
        let mut dosen_tanpa_jeda = 0;
        for d in 0..self.nama_dosen.len() {
            for (_, slot_hari) in &self.slot_per_hari {
                dosen_padat += self
                    .kelas_dosen_di_hari(&per_dosen, d, slot_hari)
                    .saturating_sub(batas);
            }
            for &(a, b) in &self.pasangan_tanpa_jeda {
                if self.mengajar(&per_dosen, d, a) && self.mengajar(&per_dosen, d, b) {
                    dosen_tanpa_jeda += 1;
                }
            }
        }

        let w = &self.bobot;
        let penalti = w.dosen_bentrok * dosen_bentrok as f64
            + w.melebihi_ruangan * melebihi_ruangan as f64
            + w.dosen_padat * dosen_padat as f64
            + w.dosen_tanpa_jeda * dosen_tanpa_jeda as f64;
        Penilaian {
            penalti,
            fitness: 1.0 / (1.0 + penalti),
            dosen_bentrok,
            melebihi_ruangan,
            dosen_padat,
            dosen_tanpa_jeda,
        }
    }

    /// Daftar pelanggaran lengkap dengan kelas yang terlibat, hard dulu lalu soft.
    pub fn rincian(&self, genes: &[IdSlot]) -> Vec<Pelanggaran> {
        let (per_dosen, per_slot) = self.hitung(genes);
        let s_total = self.jumlah_slot;
        // Kelas milik tiap (dosen, slot)
        let mut kelas_di = vec![Vec::new(); per_dosen.len()];
        for (gen, (dosen, &slot)) in self.dosen_per_gen.iter().zip(genes).enumerate() {
            for &d in dosen {
                kelas_di[d as usize * s_total + slot as usize - 1].push(self.id_kelas[gen].clone());
            }
        }
        let batas = self.bobot.batas_kelas_dosen_per_hari;
        let mut hard = Vec::new();
        let mut soft = Vec::new();

        for (i, &n) in per_dosen.iter().enumerate() {
            if n > 1 {
                hard.push(Pelanggaran::DosenBentrok {
                    dosen: self.nama_dosen[i / s_total].clone(),
                    id_slot: (i % s_total) as IdSlot + 1,
                    id_kelas: kelas_di[i].clone(),
                });
            }
        }
        for (s, &n) in per_slot.iter().enumerate() {
            if n > self.jumlah_ruangan {
                hard.push(Pelanggaran::MelebihiRuangan {
                    id_slot: s as IdSlot + 1,
                    jumlah_kelas: n,
                    jumlah_ruangan: self.jumlah_ruangan,
                });
            }
        }
        for d in 0..self.nama_dosen.len() {
            for (hari, slot_hari) in &self.slot_per_hari {
                let jumlah = self.kelas_dosen_di_hari(&per_dosen, d, slot_hari);
                if jumlah > batas as usize {
                    soft.push(Pelanggaran::DosenPadat {
                        dosen: self.nama_dosen[d].clone(),
                        hari: hari.clone(),
                        jumlah_kelas: jumlah,
                        batas,
                        id_kelas: slot_hari
                            .iter()
                            .flat_map(|&s| kelas_di[d * s_total + s].clone())
                            .collect(),
                    });
                }
            }
            for &(a, b) in &self.pasangan_tanpa_jeda {
                if self.mengajar(&per_dosen, d, a) && self.mengajar(&per_dosen, d, b) {
                    let hari = self
                        .slot_per_hari
                        .iter()
                        .find(|(_, isi)| isi.contains(&a))
                        .map(|(h, _)| h.clone())
                        .unwrap_or_default();
                    soft.push(Pelanggaran::DosenTanpaJeda {
                        dosen: self.nama_dosen[d].clone(),
                        hari,
                        id_kelas: [
                            kelas_di[d * s_total + a].clone(),
                            kelas_di[d * s_total + b].clone(),
                        ]
                        .concat(),
                    });
                }
            }
        }
        hard.extend(soft);
        hard
    }
}

#[cfg(test)]
pub(crate) mod tests {
    use super::*;
    use crate::dataset::{siapkan, DatasetMentah};

    fn data_dari(matkul: &str, ruang: &str) -> Dataset {
        let sesi = serde_json::to_string(&crate::slot::tests::sesi_standar()).unwrap();
        siapkan(&DatasetMentah::dari_json(matkul, &sesi, ruang).unwrap()).unwrap()
    }

    /// 4 kelas, 2 ruangan. Budi mengajar IF1-1 dan IF1-2 (3 SKS), Sari SI2-1 dan SI3-1 (2 SKS).
    pub(crate) fn data_kecil() -> Dataset {
        data_dari(
            r#"[
          {"id":"IF1-1","kode_mk":"IF1","nama":"Algo","kelas":"A","sks":3,"dosen":["Budi"],"allowed_sessions":[1,3]},
          {"id":"IF1-2","kode_mk":"IF1","nama":"Algo","kelas":"B","sks":3,"dosen":["Budi"],"allowed_sessions":[1,3]},
          {"id":"SI2-1","kode_mk":"SI2","nama":"Basdat","kelas":"A","sks":2,"dosen":["Sari"],"allowed_sessions":[2,4]},
          {"id":"SI3-1","kode_mk":"SI3","nama":"RPL","kelas":"A","sks":2,"dosen":["Sari"],"allowed_sessions":[2,4]}
        ]"#,
            r#"["E101","E102"]"#,
        )
    }

    /// Ani mengajar 3 kelas: A1 (3 SKS), A2 (2 SKS), A3 (3 SKS).
    fn data_ani() -> Dataset {
        data_dari(
            r#"[
          {"id":"A1-1","kode_mk":"A1","nama":"MK 1","kelas":"A","sks":3,"dosen":["Ani"],"allowed_sessions":[1,3]},
          {"id":"A2-1","kode_mk":"A2","nama":"MK 2","kelas":"A","sks":2,"dosen":["Ani"],"allowed_sessions":[2,4]},
          {"id":"A3-1","kode_mk":"A3","nama":"MK 3","kelas":"A","sks":3,"dosen":["Ani"],"allowed_sessions":[1,3]}
        ]"#,
            r#"["E101"]"#,
        )
    }

    #[test]
    fn jadwal_tanpa_pelanggaran_bernilai_sempurna() {
        let d = data_kecil();
        let p = Penilai::baru(&d, BobotPenalti::default());
        // urutan gen: IF1-1, IF1-2, SI2-1, SI3-1. Budi Senin sesi 1 & 3, Sari Senin sesi 2 & 4.
        let n = p.nilai(&[1, 3, 2, 4]);
        assert!(n.sempurna());
        assert_eq!((n.penalti, n.fitness), (0.0, 1.0));
        assert!(p.rincian(&[1, 3, 2, 4]).is_empty());
    }

    #[test]
    fn hc_dosen_bentrok_terdeteksi_beserta_kelasnya() {
        let d = data_kecil();
        let p = Penilai::baru(&d, BobotPenalti::default());
        let n = p.nilai(&[1, 1, 2, 4]); // Budi dua kelas di slot 1
        assert_eq!(
            (n.dosen_bentrok, n.melebihi_ruangan, n.pelanggaran_soft()),
            (1, 0, 0)
        );
        assert_eq!(n.penalti, 1000.0);
        assert!(!n.layak());
        let r = p.rincian(&[1, 1, 2, 4]);
        assert_eq!(
            r,
            vec![Pelanggaran::DosenBentrok {
                dosen: "Budi".into(),
                id_slot: 1,
                id_kelas: vec!["IF1-1".into(), "IF1-2".into()]
            }]
        );
        assert_eq!(r[0].jenis(), JenisAturan::Hard);
    }

    #[test]
    fn hc_kelas_melebihi_ruangan_terdeteksi() {
        let mut d = data_kecil();
        d.ruang.truncate(1); // tinggal 1 ruangan
        let bobot = BobotPenalti {
            dosen_bentrok: 1.0,
            melebihi_ruangan: 10.0,
            ..Default::default()
        };
        let p = Penilai::baru(&d, bobot);
        let n = p.nilai(&[1, 1, 2, 4]); // slot 1 berisi 2 kelas
        assert_eq!((n.dosen_bentrok, n.melebihi_ruangan), (1, 1));
        assert_eq!(n.penalti, 11.0, "bobot dipakai");
        assert!(p
            .rincian(&[1, 1, 2, 4])
            .contains(&Pelanggaran::MelebihiRuangan {
                id_slot: 1,
                jumlah_kelas: 2,
                jumlah_ruangan: 1
            }));
    }

    #[test]
    fn sc_dosen_padat_dan_tanpa_jeda() {
        let d = data_ani();
        let p = Penilai::baru(&d, BobotPenalti::default());
        // Ani: Senin sesi 1, 2, 3 -> 3 kelas sehari (batas 2) dan sesi 1-2 bersambung
        let n = p.nilai(&[1, 2, 3]);
        assert_eq!((n.dosen_padat, n.dosen_tanpa_jeda), (1, 1));
        assert!(
            n.layak() && !n.sempurna(),
            "SC tidak membuat jadwal tidak layak"
        );
        assert_eq!(n.penalti, 2.0);
        let r = p.rincian(&[1, 2, 3]);
        assert_eq!(
            r,
            vec![
                Pelanggaran::DosenPadat {
                    dosen: "Ani".into(),
                    hari: "Senin".into(),
                    jumlah_kelas: 3,
                    batas: 2,
                    id_kelas: vec!["A1-1".into(), "A2-1".into(), "A3-1".into()]
                },
                Pelanggaran::DosenTanpaJeda {
                    dosen: "Ani".into(),
                    hari: "Senin".into(),
                    id_kelas: vec!["A1-1".into(), "A2-1".into()]
                },
            ]
        );
        assert!(r.iter().all(|x| x.jenis() == JenisAturan::Soft));

        // Senin sesi 1 & 4 (ada jeda), Selasa sesi 3 -> sempurna
        assert!(p.nilai(&[1, 4, 7]).sempurna());
        // Sesi 2 lalu 3 dipisah istirahat 12:00-13:00, jadi bukan "tanpa jeda"
        assert_eq!(p.nilai(&[5, 2, 3]).dosen_tanpa_jeda, 0);
    }

    #[test]
    fn hc_selalu_lebih_berat_dari_sc() {
        let d = data_ani();
        let p = Penilai::baru(&d, BobotPenalti::default());
        let satu_hc = p.nilai(&[1, 2, 1]); // A1 dan A3 bentrok di slot 1
        let banyak_sc = p.nilai(&[1, 2, 3]);
        assert!(satu_hc.penalti > banyak_sc.penalti);
    }
}
