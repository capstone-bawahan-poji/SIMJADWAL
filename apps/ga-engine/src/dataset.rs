//! Memuat dataset (matkul.json, sesi.json, ruang.json) dan menyiapkannya
//! untuk engine: menyatukan variasi nama dosen, membuang ruangan ganda,
//! menghitung domain slot tiap kelas, dan membuat laporan kualitas data.

use std::collections::{BTreeMap, BTreeSet, HashMap};
use std::path::Path;

use serde::{Deserialize, Serialize};

use crate::error::KromosomError;
use crate::kromosom::{GeneLayout, Kelas};
use crate::slot::DaftarSlot;

/// Satu baris di matkul.json (satu kelas).
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct MatkulRaw {
    /// Contoh "IF2514101-1". Unik per kelas, dipakai sebagai identitas gen.
    pub id: String,
    pub kode_mk: String,
    pub nama: String,
    /// Label kelas apa adanya (A, B, C, D, X, W, Z, ...).
    pub kelas: String,
    pub sks: u8,
    pub dosen: Vec<String>,
    pub allowed_sessions: Vec<u8>,
    /// Label fakultas. Tidak ada di file JSON; diisi dari nama folder data
    /// (`data/fsti` -> "FSTI") saat dimuat.
    #[serde(default, skip_serializing_if = "Option::is_none")]
    pub fakultas: Option<String>,
}

/// Satu baris di sesi.json.
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct SesiRaw {
    pub day: String,
    pub session: u8,
    pub start: String,
    pub end: String,
    /// Tipe SKS slot ini (2 atau 3).
    #[serde(rename = "type")]
    pub tipe: u8,
}

/// Isi ketiga file JSON, belum diolah.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct DatasetMentah {
    pub matkul: Vec<MatkulRaw>,
    pub sesi: Vec<SesiRaw>,
    pub ruang: Vec<String>,
}

impl DatasetMentah {
    pub fn dari_json(matkul: &str, sesi: &str, ruang: &str) -> Result<Self, KromosomError> {
        let baca = |nama: &str, e: serde_json::Error| KromosomError::DataTidakTerbaca(format!("{nama}: {e}"));
        Ok(Self {
            matkul: serde_json::from_str(matkul).map_err(|e| baca("matkul.json", e))?,
            sesi: serde_json::from_str(sesi).map_err(|e| baca("sesi.json", e))?,
            ruang: serde_json::from_str(ruang).map_err(|e| baca("ruang.json", e))?,
        })
    }

    /// Baca matkul.json, sesi.json, dan ruang.json dari satu folder.
    /// Nama folder dipakai sebagai label fakultas: `data/fsti` -> "FSTI".
    pub fn dari_folder(folder: impl AsRef<Path>) -> Result<Self, KromosomError> {
        let folder = folder.as_ref();
        let baca = |nama: &str| {
            std::fs::read_to_string(folder.join(nama))
                .map_err(|e| KromosomError::DataTidakTerbaca(format!("{}: {e}", folder.join(nama).display())))
        };
        let data = Self::dari_json(&baca("matkul.json")?, &baca("sesi.json")?, &baca("ruang.json")?)?;
        Ok(match folder.file_name().and_then(|n| n.to_str()) {
            Some(nama) => data.dengan_fakultas(&nama.to_uppercase()),
            None => data,
        })
    }

    /// Dataset FSTI yang ikut tertanam di engine (folder `data/fsti`).
    pub fn fsti_bawaan() -> Self {
        Self::dari_json(
            include_str!("../data/fsti/matkul.json"),
            include_str!("../data/fsti/sesi.json"),
            include_str!("../data/fsti/ruang.json"),
        )
        .expect("dataset bawaan harus valid")
        .dengan_fakultas("FSTI")
    }

    /// Beri label fakultas ke kelas yang belum punya label.
    pub fn dengan_fakultas(mut self, nama: &str) -> Self {
        for mk in &mut self.matkul {
            mk.fakultas.get_or_insert_with(|| nama.to_string());
        }
        self
    }

    /// Gabungkan data beberapa fakultas supaya dijadwalkan bersama: ruangan
    /// dipakai bersama dan dosen yang mengajar di dua fakultas dikenali
    /// sebagai orang yang sama, sehingga bentroknya bisa dicek.
    ///
    /// Syarat: sesi.json semua fakultas sama. Ruangan yang sudah ada di
    /// fakultas sebelumnya tidak ditambahkan lagi.
    pub fn gabung(bagian: impl IntoIterator<Item = DatasetMentah>) -> Result<Self, KromosomError> {
        let mut bagian = bagian.into_iter();
        let mut hasil = bagian
            .next()
            .ok_or_else(|| KromosomError::DataTidakValid("tidak ada data fakultas untuk digabung".into()))?;
        let acuan = urutan_sesi(&hasil.sesi);
        for b in bagian {
            if urutan_sesi(&b.sesi) != acuan {
                return Err(KromosomError::DataTidakValid(format!(
                    "sesi.json {} berbeda dengan {}. Fakultas dengan jam sesi berbeda belum bisa dijadwalkan bersama",
                    b.nama_fakultas(),
                    hasil.nama_fakultas()
                )));
            }
            let sudah: BTreeSet<String> = hasil.ruang.iter().map(|r| r.trim().to_string()).collect();
            hasil.ruang.extend(b.ruang.into_iter().filter(|r| !sudah.contains(r.trim())));
            hasil.matkul.extend(b.matkul);
        }
        Ok(hasil)
    }

    /// Label fakultas yang ada di data ini, mis. "FSTI" atau "FSTI+FTIK".
    pub fn nama_fakultas(&self) -> String {
        let nama: BTreeSet<&str> = self.matkul.iter().filter_map(|m| m.fakultas.as_deref()).collect();
        if nama.is_empty() {
            "(tanpa label)".into()
        } else {
            nama.into_iter().collect::<Vec<_>>().join("+")
        }
    }
}

/// Isi sesi.json dalam urutan tetap, untuk membandingkan dua fakultas.
fn urutan_sesi(sesi: &[SesiRaw]) -> Vec<(String, u8, String, String, u8)> {
    let mut v: Vec<_> = sesi
        .iter()
        .map(|s| (s.day.trim().to_lowercase(), s.session, s.start.clone(), s.end.clone(), s.tipe))
        .collect();
    v.sort();
    v
}

/// Ringkasan dan peringatan tentang data. Peringatan tidak menghentikan
/// engine, tapi perlu dicek tim sebelum hasil jadwal dipakai.
#[derive(Debug, Clone, PartialEq, Eq, Serialize, Deserialize)]
pub struct LaporanData {
    pub jumlah_kelas: usize,
    pub jumlah_mata_kuliah: usize,
    pub jumlah_dosen: usize,
    pub jumlah_ruang: usize,
    pub jumlah_slot: usize,
    /// Jumlah kelas per prefix kode MK (IF, SI, TE, ...).
    pub kelas_per_prodi: BTreeMap<String, usize>,
    /// Jumlah kelas per SKS.
    pub kelas_per_sks: BTreeMap<u8, usize>,
    /// Jumlah kelas per fakultas (hanya kelas yang punya label fakultas).
    pub kelas_per_fakultas: BTreeMap<String, usize>,
    pub peringatan: Vec<String>,
}

/// Data yang sudah siap dipakai engine.
#[derive(Debug, Clone)]
pub struct Dataset {
    pub slot: DaftarSlot,
    pub layout: GeneLayout,
    /// Nama dosen setelah variasi ejaan disatukan. Index = `dosen_idx` di `Kelas`.
    pub dosen: Vec<String>,
    /// Ruangan unik, urutan sesuai kemunculan pertama di ruang.json.
    pub ruang: Vec<String>,
    pub laporan: LaporanData,
}

/// Kunci pembanding nama dosen: huruf kecil, hanya bagian sebelum gelar
/// (sebelum koma pertama), tanpa awalan Dr./Ir./Prof., tanpa tanda baca.
/// "Indrawan, S.Pd., M.Si." dan "Indrawan, S.Pd. M.Si." -> "indrawan".
pub fn kunci_dosen(nama: &str) -> String {
    let kecil = nama.to_lowercase();
    let sebelum_gelar = kecil.split(',').next().unwrap_or("");
    let bersih: String = sebelum_gelar
        .chars()
        .map(|c| if c.is_alphabetic() || c.is_whitespace() { c } else { ' ' })
        .collect();
    bersih
        .split_whitespace()
        .filter(|kata| !matches!(*kata, "dr" | "ir" | "prof"))
        .collect::<Vec<_>>()
        .join(" ")
}

/// Olah data mentah menjadi `Dataset`.
pub fn siapkan(mentah: &DatasetMentah) -> Result<Dataset, KromosomError> {
    let slot = DaftarSlot::dari_sesi(&mentah.sesi)?;
    let mut peringatan = Vec::new();

    // Dosen: kelompokkan variasi ejaan, pakai ejaan yang paling sering muncul.
    let mut varian: BTreeMap<String, BTreeMap<String, usize>> = BTreeMap::new();
    for mk in &mentah.matkul {
        if mk.dosen.is_empty() {
            return Err(KromosomError::DataTidakValid(format!("kelas {} tanpa dosen", mk.id)));
        }
        for d in &mk.dosen {
            *varian.entry(kunci_dosen(d)).or_default().entry(d.trim().to_string()).or_default() += 1;
        }
    }
    let mut dosen = Vec::with_capacity(varian.len());
    let mut idx_dosen = HashMap::new();
    for (kunci, ejaan) in &varian {
        let utama = ejaan
            .iter()
            .max_by(|a, b| a.1.cmp(b.1).then(a.0.len().cmp(&b.0.len())))
            .map(|(nama, _)| nama.clone())
            .unwrap_or_default();
        if ejaan.len() > 1 {
            let semua: Vec<_> = ejaan.keys().map(|s| format!("\"{s}\"")).collect();
            peringatan.push(format!(
                "Nama dosen ditulis {} cara dan dianggap orang yang sama: {}",
                ejaan.len(),
                semua.join(", ")
            ));
        }
        idx_dosen.insert(kunci.clone(), dosen.len() as u16);
        dosen.push(utama);
    }

    // Ruangan: buang duplikat.
    let mut ruang = Vec::new();
    let mut terlihat = BTreeSet::new();
    let mut ganda = BTreeMap::<String, usize>::new();
    for r in &mentah.ruang {
        let r = r.trim().to_string();
        if terlihat.insert(r.clone()) {
            ruang.push(r);
        } else {
            *ganda.entry(r).or_insert(1) += 1;
        }
    }
    for (r, n) in &ganda {
        peringatan.push(format!("Ruangan {r} tercantum {n} kali di ruang.json, dihitung satu"));
    }

    // Kelas -> gen.
    let mut kelas = Vec::with_capacity(mentah.matkul.len());
    let mut kelas_per_prodi = BTreeMap::new();
    let mut kelas_per_sks = BTreeMap::new();
    let mut kelas_per_fakultas = BTreeMap::new();
    let mut sks_per_mk: BTreeMap<&str, BTreeSet<u8>> = BTreeMap::new();
    let mut label_tak_lazim = BTreeSet::new();
    for mk in &mentah.matkul {
        let domain = slot.domain_untuk(mk.sks, &mk.allowed_sessions);
        if domain.is_empty() {
            return Err(KromosomError::DataTidakValid(format!(
                "kelas {} ({} SKS, allowed_sessions {:?}) tidak punya slot yang cocok",
                mk.id, mk.sks, mk.allowed_sessions
            )));
        }
        let prodi: String = mk.kode_mk.chars().take_while(|c| c.is_ascii_alphabetic()).collect();
        *kelas_per_prodi.entry(prodi.clone()).or_insert(0) += 1;
        *kelas_per_sks.entry(mk.sks).or_insert(0) += 1;
        if let Some(f) = &mk.fakultas {
            *kelas_per_fakultas.entry(f.clone()).or_insert(0) += 1;
        }
        sks_per_mk.entry(mk.kode_mk.as_str()).or_default().insert(mk.sks);
        if !matches!(mk.kelas.as_str(), "A" | "B" | "C" | "D") {
            label_tak_lazim.insert(mk.kelas.clone());
        }
        kelas.push(Kelas {
            id: mk.id.clone(),
            kode_mk: mk.kode_mk.clone(),
            nama_mk: mk.nama.clone(),
            kelas: mk.kelas.clone(),
            sks: mk.sks,
            prodi,
            fakultas: mk.fakultas.clone(),
            dosen: mk.dosen.iter().map(|d| dosen[idx_dosen[&kunci_dosen(d)] as usize].clone()).collect(),
            dosen_idx: mk.dosen.iter().map(|d| idx_dosen[&kunci_dosen(d)]).collect(),
            slot_diizinkan: domain,
        });
    }

    let campur: Vec<_> = sks_per_mk
        .iter()
        .filter(|(_, s)| s.len() > 1)
        .map(|(k, _)| k.to_string())
        .collect();
    if !campur.is_empty() {
        peringatan.push(format!(
            "{} mata kuliah punya SKS berbeda antar kelasnya (contoh: {}). Pastikan ini disengaja",
            campur.len(),
            campur.iter().take(5).cloned().collect::<Vec<_>>().join(", ")
        ));
    }
    if !label_tak_lazim.is_empty() {
        peringatan.push(format!(
            "Ada label kelas selain A-D: {}. Engine memakai `id` sebagai identitas, jadi ini aman",
            label_tak_lazim.into_iter().collect::<Vec<_>>().join(", ")
        ));
    }
    peringatan.push(
        "Data tidak memuat semester, jadi bentrok jadwal mahasiswa satu angkatan belum bisa dicek".into(),
    );
    peringatan.push("Data ruangan tidak memuat kapasitas".into());

    // Kelayakan kasar: apakah jumlah kelas per tipe muat di ruangan x slot.
    for (&sks, &n) in &kelas_per_sks {
        let slot_tipe = slot.semua().iter().filter(|s| s.tipe_sks == sks).count();
        let kapasitas = slot_tipe * ruang.len();
        if n > kapasitas {
            peringatan.push(format!(
                "{n} kelas {sks} SKS melebihi kapasitas {slot_tipe} slot x {} ruangan = {kapasitas}",
                ruang.len()
            ));
        }
    }

    let jumlah_mata_kuliah = sks_per_mk.len();
    let layout = GeneLayout::baru(kelas)?;
    let laporan = LaporanData {
        jumlah_kelas: layout.panjang(),
        jumlah_mata_kuliah,
        jumlah_dosen: dosen.len(),
        jumlah_ruang: ruang.len(),
        jumlah_slot: slot.total() as usize,
        kelas_per_prodi,
        kelas_per_sks,
        kelas_per_fakultas,
        peringatan,
    };
    Ok(Dataset { slot, layout, dosen, ruang, laporan })
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn kunci_dosen_menyatukan_variasi_gelar() {
        assert_eq!(kunci_dosen("Indrawan, S.Pd., M.Si."), kunci_dosen("Indrawan, S.Pd. M.Si."));
        assert_eq!(
            kunci_dosen("Alvianus Kristian Sumual, M.E"),
            kunci_dosen("Alvianus Kristian Sumual, S.E., M.E.")
        );
        assert_eq!(kunci_dosen("Dr. Musyarofah, S.Pd., M.Si."), "musyarofah");
        assert_eq!(kunci_dosen("Ir. Riovan Styx Roring, S.T., M.Kom"), "riovan styx roring");
        assert_ne!(kunci_dosen("Rahmania, S.Pd., M.Sc."), kunci_dosen("Indrawan, S.Pd., M.Si."));
    }

    fn contoh_json() -> (String, String, String) {
        let matkul = r#"[
          {"id":"IF1-1","kode_mk":"IF1","nama":"Algo","kelas":"A","sks":3,"dosen":["Budi, M.Kom."],"allowed_sessions":[1,3]},
          {"id":"IF1-2","kode_mk":"IF1","nama":"Algo","kelas":"B","sks":3,"dosen":["Budi, M.Kom"],"allowed_sessions":[1,3]},
          {"id":"SI2-1","kode_mk":"SI2","nama":"Basis Data","kelas":"X","sks":2,"dosen":["Sari, M.T."],"allowed_sessions":[2,4]}
        ]"#;
        let sesi = serde_json::to_string(&crate::slot::tests::sesi_standar()).unwrap();
        let ruang = r#"["E101","E102","E101"]"#;
        (matkul.into(), sesi, ruang.into())
    }

    #[test]
    fn siapkan_menyatukan_dosen_dan_ruangan() {
        let (m, s, r) = contoh_json();
        let d = siapkan(&DatasetMentah::dari_json(&m, &s, &r).unwrap()).unwrap();
        assert_eq!(d.laporan.jumlah_kelas, 3);
        assert_eq!(d.dosen.len(), 2, "Budi dengan dua ejaan dihitung satu orang");
        assert_eq!(d.ruang, vec!["E101", "E102"]);
        let k = d.layout.kelas();
        assert_eq!(k[0].dosen_idx, k[1].dosen_idx);
        assert!(d.laporan.peringatan.iter().any(|p| p.contains("E101")));
        assert!(d.laporan.peringatan.iter().any(|p| p.contains("label kelas")));
    }

    /// Fakultas kedua: satu kelas diajar dosen yang juga mengajar di fakultas
    /// pertama ("Budi"), satu ruangan dipakai bersama (E102).
    fn fakultas_kedua(id: &str) -> DatasetMentah {
        let matkul = format!(
            r#"[{{"id":"{id}","kode_mk":"TK1","nama":"Termo","kelas":"A","sks":3,"dosen":["Budi, M.Kom."],"allowed_sessions":[1,3]}}]"#
        );
        let sesi = serde_json::to_string(&crate::slot::tests::sesi_standar()).unwrap();
        DatasetMentah::dari_json(&matkul, &sesi, r#"["E102","F201"]"#).unwrap().dengan_fakultas("FB")
    }

    fn fakultas_pertama() -> DatasetMentah {
        let (m, s, r) = contoh_json();
        DatasetMentah::dari_json(&m, &s, &r).unwrap().dengan_fakultas("FA")
    }

    #[test]
    fn gabung_dua_fakultas_berbagi_ruang_dan_dosen() {
        let mentah = DatasetMentah::gabung([fakultas_pertama(), fakultas_kedua("TK1-1")]).unwrap();
        assert_eq!(mentah.nama_fakultas(), "FA+FB");
        let d = siapkan(&mentah).unwrap();
        assert_eq!(d.laporan.jumlah_kelas, 4);
        assert_eq!(d.ruang, vec!["E101", "E102", "F201"], "E102 dipakai bersama, dihitung satu");
        let ruang_ganda: Vec<_> = d.laporan.peringatan.iter().filter(|p| p.starts_with("Ruangan")).collect();
        assert_eq!(ruang_ganda.len(), 1, "hanya E101 yang ganda di file FA: {ruang_ganda:?}");
        assert_eq!(d.laporan.kelas_per_fakultas, BTreeMap::from([("FA".into(), 3), ("FB".into(), 1)]));

        let kelas = d.layout.kelas();
        let budi_fa = &kelas.iter().find(|k| k.id == "IF1-1").unwrap().dosen_idx;
        let budi_fb = &kelas.iter().find(|k| k.id == "TK1-1").unwrap().dosen_idx;
        assert_eq!(budi_fa, budi_fb, "dosen lintas fakultas dikenali sebagai orang yang sama");
        assert_eq!(kelas.iter().find(|k| k.id == "TK1-1").unwrap().fakultas.as_deref(), Some("FB"));
    }

    #[test]
    fn gabung_menolak_id_kelas_sama_antar_fakultas() {
        let mentah = DatasetMentah::gabung([fakultas_pertama(), fakultas_kedua("IF1-1")]).unwrap();
        assert_eq!(siapkan(&mentah).unwrap_err(), KromosomError::KelasDuplikat("IF1-1".into()));
    }

    #[test]
    fn gabung_menolak_jam_sesi_berbeda() {
        let mut lain = fakultas_kedua("TK1-1");
        lain.sesi[0].start = "07:30".into();
        let hasil = DatasetMentah::gabung([fakultas_pertama(), lain]);
        assert!(matches!(hasil, Err(KromosomError::DataTidakValid(p)) if p.contains("sesi.json FB")));
        assert!(DatasetMentah::gabung([]).is_err());
    }

    #[test]
    fn label_fakultas_tidak_menimpa_label_lama() {
        let d = fakultas_pertama().dengan_fakultas("LAIN");
        assert!(d.matkul.iter().all(|m| m.fakultas.as_deref() == Some("FA")));
    }

    #[test]
    fn kelas_tanpa_slot_cocok_ditolak() {
        let (_, s, r) = contoh_json();
        let salah = r#"[{"id":"X-1","kode_mk":"X","nama":"X","kelas":"A","sks":3,"dosen":["A"],"allowed_sessions":[2]}]"#;
        let hasil = siapkan(&DatasetMentah::dari_json(salah, &s, &r).unwrap());
        assert!(matches!(hasil, Err(KromosomError::DataTidakValid(_))));
    }
}
