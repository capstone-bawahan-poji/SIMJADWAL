//! Turns the dataset into dense, index-based tables so the fitness loop never hashes.
//!
//! One gene = one meeting of one class

use std::collections::HashMap;

use super::model::{Dataset, PreferenceType, RunConfig};
use crate::error::AppError;

pub const HC1: &str = "HC1";
pub const HC2: &str = "HC2";
pub const HC3: &str = "HC3";
pub const HC4: &str = "HC4";
pub const SC_INGIN: &str = "SC_INGIN";
pub const SC_HINDARI: &str = "SC_HINDARI";
pub const SC_SKS: &str = "SC_SKS";

const SOFT_CODES: [&str; 3] = [SC_INGIN, SC_HINDARI, SC_SKS];

#[derive(Debug, Clone)]
pub struct Gene {
    pub course_id: i64,
    pub class_number: u16,
    pub meeting_number: u8,
    pub lecturer_id: i64,
    pub lecturer: usize,
    pub program: usize,
    pub cohort: usize,
    pub target_slot_type: u8,
    pub class_capacity: u32,
    pub fixed_slot: Option<u16>,
    pub fixed_room_id: Option<i64>,
    pub w_want: f64,
    pub w_avoid: f64,
    pub w_sks: f64,
}

#[derive(Debug, Clone)]
pub struct Slot {
    pub id: i64,
    pub day: u8,
    pub session: u8,
    pub slot_type: u8,
}

#[derive(Debug, Clone)]
pub struct LecturerPrefs {
    pub name: String,
    pub want: Vec<bool>,
    pub avoid: Vec<bool>,
}

#[derive(Debug, Clone)]
pub struct Problem {
    pub genes: Vec<Gene>,
    pub slots: Vec<Slot>,
    pub lecturers: Vec<LecturerPrefs>,
    pub course_labels: HashMap<i64, String>,
    pub program_count: usize,
    pub cohort_count: usize,
    pub parallel_class_limit: u32,
    pub fitness_constant: f64,
    pub w_hc1: f64,
    pub w_hc2: f64,
    pub w_hc3: f64,
    pub w_hc4: f64,
    pub free_genes: Vec<usize>,
}

impl Problem {
    pub fn slot_count(&self) -> usize {
        self.slots.len()
    }

    pub fn max_fitness(&self) -> f64 {
        self.fitness_constant
            + self
                .genes
                .iter()
                .map(|g| {
                    let prefs = &self.lecturers[g.lecturer];
                    let want = if prefs.want.is_empty() { 0.0 } else { g.w_want };
                    let avoid = if prefs.avoid.is_empty() {
                        0.0
                    } else {
                        g.w_avoid
                    };
                    want + avoid + g.w_sks
                })
                .sum::<f64>()
    }

    pub fn build(dataset: &Dataset, config: &RunConfig) -> Result<Self, AppError> {
        let params = &config.params;
        if dataset.time_slots.is_empty() {
            return Err(AppError::Invalid(
                "Dataset tidak memiliki slot waktu.".into(),
            ));
        }
        if dataset.time_slots.len() > u16::MAX as usize {
            return Err(AppError::Invalid("Jumlah slot waktu terlalu besar.".into()));
        }

        let mut slots: Vec<Slot> = dataset
            .time_slots
            .iter()
            .map(|s| Slot {
                id: s.id,
                day: s.day,
                session: s.session,
                slot_type: s.slot_type,
            })
            .collect();
        slots.sort_by_key(|s| (s.day, s.session, s.id));
        let slot_index: HashMap<i64, u16> = slots
            .iter()
            .enumerate()
            .map(|(i, s)| (s.id, i as u16))
            .collect();

        let mut lecturers = Vec::with_capacity(dataset.lecturers.len());
        let mut lecturer_index = HashMap::new();
        for l in &dataset.lecturers {
            let mut want = vec![false; slots.len()];
            let mut avoid = vec![false; slots.len()];
            let (mut has_want, mut has_avoid) = (false, false);
            for p in &l.preferences {
                if let Some(&s) = slot_index.get(&p.time_slot_id) {
                    match p.kind {
                        PreferenceType::Want => {
                            want[s as usize] = true;
                            has_want = true;
                        }
                        PreferenceType::Avoid => {
                            avoid[s as usize] = true;
                            has_avoid = true;
                        }
                    }
                }
            }
            lecturer_index.insert(l.id, lecturers.len());
            lecturers.push(LecturerPrefs {
                name: l.name.clone(),
                want: if has_want { want } else { Vec::new() },
                avoid: if has_avoid { avoid } else { Vec::new() },
            });
        }

        let weight = |code: &str| -> f64 {
            config.weights.get(code).copied().unwrap_or_else(|| {
                dataset
                    .constraint_types
                    .iter()
                    .find(|t| t.code == code)
                    .map(|t| t.weight)
                    .unwrap_or(0.0)
            })
        };
        let soft_weight = |program_id: i64, code: &str| -> f64 {
            config
                .sc_weights_by_study_program
                .get(&program_id.to_string())
                .and_then(|row| row.get(code))
                .copied()
                .filter(|w| *w > 0.0 && SOFT_CODES.contains(&code))
                .unwrap_or_else(|| weight(code))
        };

        let program_index: HashMap<i64, usize> = dataset
            .study_programs
            .iter()
            .filter(|p| p.included)
            .enumerate()
            .map(|(i, p)| (p.id, i))
            .collect();
        let courses: HashMap<i64, &super::model::Course> =
            dataset.courses.iter().map(|c| (c.id, c)).collect();

        let mut cohorts: HashMap<(usize, u8, u16), usize> = HashMap::new();
        let mut genes = Vec::new();
        let mut classes = dataset.classes.clone();
        classes.sort_by_key(|c| (c.course_id, c.class_number));

        for class in &classes {
            let course = courses.get(&class.course_id).ok_or_else(|| {
                AppError::Invalid(format!(
                    "Kelas merujuk mata kuliah {} yang tidak ada.",
                    class.course_id
                ))
            })?;
            let program = *program_index.get(&course.study_program_id).ok_or_else(|| {
                AppError::Invalid(format!(
                    "Mata kuliah {} bukan milik prodi yang ikut dijadwalkan.",
                    course.code
                ))
            })?;
            let lecturer = *lecturer_index.get(&class.lecturer_id).ok_or_else(|| {
                AppError::Invalid(format!("Dosen {} tidak ada di dataset.", class.lecturer_id))
            })?;
            let next_cohort = cohorts.len();
            let cohort = *cohorts
                .entry((program, course.semester, class.class_number))
                .or_insert(next_cohort);
            let fixed_slot = match class.fixed_time_slot_id {
                Some(id) => Some(*slot_index.get(&id).ok_or_else(|| {
                    AppError::Invalid(format!("Slot terpatok {id} tidak ada di dataset."))
                })?),
                None => None,
            };
            let (meetings, target_slot_type) = match course.sks {
                2 => (1, 2),
                3 => (1, 3),
                4 => (2, 2),
                other => {
                    return Err(AppError::Invalid(format!(
                        "Mata kuliah {} memiliki {other} SKS; hanya 2, 3, dan 4 yang dijadwalkan.",
                        course.code
                    )))
                }
            };
            for meeting_number in 1..=meetings {
                genes.push(Gene {
                    course_id: course.id,
                    class_number: class.class_number,
                    meeting_number,
                    lecturer_id: class.lecturer_id,
                    lecturer,
                    program,
                    cohort,
                    target_slot_type,
                    class_capacity: course.class_capacity,
                    fixed_slot,
                    fixed_room_id: class.fixed_room_id,
                    w_want: soft_weight(course.study_program_id, SC_INGIN),
                    w_avoid: soft_weight(course.study_program_id, SC_HINDARI),
                    w_sks: soft_weight(course.study_program_id, SC_SKS),
                });
            }
        }

        if genes.is_empty() {
            return Err(AppError::Invalid(
                "Tidak ada kelas untuk dijadwalkan. Pastikan ada prodi yang sudah diterima.".into(),
            ));
        }

        let free_genes = genes
            .iter()
            .enumerate()
            .filter(|(_, g)| g.fixed_slot.is_none())
            .map(|(i, _)| i)
            .collect();
        let course_labels = dataset
            .courses
            .iter()
            .map(|c| (c.id, format!("{} {}", c.code, c.name)))
            .collect();

        Ok(Self {
            genes,
            slots,
            lecturers,
            course_labels,
            program_count: program_index.len(),
            cohort_count: cohorts.len(),
            parallel_class_limit: params.parallel_class_limit,
            fitness_constant: params.fitness_constant,
            w_hc1: weight(HC1),
            w_hc2: weight(HC2),
            w_hc3: weight(HC3),
            w_hc4: weight(HC4),
            free_genes,
        })
    }
}

#[cfg(test)]
pub(crate) mod fixtures {
    //! Small hand-made datasets for unit tests.

    use super::super::model::*;
    use std::collections::HashMap;

    pub fn params() -> GaParams {
        GaParams {
            population_size: 20,
            min_generations: 30,
            max_generations: 60,
            stagnation_limit: 20,
            crossover_rate: 0.9,
            mutation_rate: 0.5,
            tournament_size: 2,
            selection_operator: SelectionOperator::TS,
            parallel_class_limit: 5,
            fitness_constant: 500.0,
            base_seed: 12345,
            run_count: 2,
        }
    }

    pub fn config() -> RunConfig {
        RunConfig {
            params: params(),
            weights: HashMap::new(),
            sc_weights_by_study_program: HashMap::new(),
            preset_id: None,
        }
    }

    fn constraint_types() -> Vec<ConstraintType> {
        [
            ("HC1", 10.0),
            ("HC2", 10.0),
            ("HC3", 10.0),
            ("HC4", 10.0),
            ("SC_INGIN", 1.0),
            ("SC_HINDARI", 1.0),
            ("SC_SKS", 2.0),
        ]
        .into_iter()
        .map(|(code, weight)| ConstraintType {
            code: code.into(),
            weight,
        })
        .collect()
    }

    pub fn time_slots() -> Vec<TimeSlot> {
        (1..=5u8)
            .flat_map(|day| {
                (1..=4u8).map(move |session| TimeSlot {
                    id: i64::from((day - 1) * 4 + session),
                    day,
                    session,
                    slot_type: if session % 2 == 1 { 3 } else { 2 },
                })
            })
            .collect()
    }

    pub fn dataset(courses: &[(i64, i64, u8, u8)], lecturer_of: impl Fn(i64) -> i64) -> Dataset {
        let mut programs: Vec<i64> = courses.iter().map(|c| c.1).collect();
        programs.sort();
        programs.dedup();
        let mut lecturers: Vec<i64> = courses.iter().map(|c| lecturer_of(c.0)).collect();
        lecturers.sort();
        lecturers.dedup();
        Dataset {
            fingerprint: "sha256:test".into(),
            period: "2026/2027-GANJIL".into(),
            faculty: FacultyRef { id: 1 },
            study_programs: programs
                .iter()
                .map(|&id| StudyProgram { id, included: true })
                .collect(),
            time_slots: time_slots(),
            rooms: vec![
                Room {
                    id: 1,
                    code: "E101".into(),
                    capacity: 50,
                },
                Room {
                    id: 2,
                    code: "E102".into(),
                    capacity: 100,
                },
            ],
            courses: courses
                .iter()
                .map(|&(id, program, sks, semester)| Course {
                    id,
                    study_program_id: program,
                    code: format!("MK{id}"),
                    name: format!("Mata Kuliah {id}"),
                    sks,
                    semester,
                    class_capacity: 50,
                })
                .collect(),
            classes: courses
                .iter()
                .map(|&(id, ..)| ClassRow {
                    course_id: id,
                    class_number: 1,
                    lecturer_id: lecturer_of(id),
                    fixed_room_id: None,
                    fixed_time_slot_id: None,
                })
                .collect(),
            lecturers: lecturers
                .iter()
                .map(|&id| Lecturer {
                    id,
                    name: format!("Dosen {id}"),
                    preferences: Vec::new(),
                })
                .collect(),
            constraint_types: constraint_types(),
            occupied_rooms: Vec::new(),
        }
    }
}

#[cfg(test)]
mod tests {
    use super::super::model::Preference;
    use super::fixtures::*;
    use super::*;

    #[test]
    fn four_sks_course_gives_two_genes_and_targets_type_two_slots() {
        let ds = dataset(&[(1, 10, 4, 3), (2, 10, 3, 3), (3, 10, 2, 3)], |_| 7);
        let p = Problem::build(&ds, &config()).unwrap();
        assert_eq!(p.genes.len(), 4);
        let types: Vec<u8> = p.genes.iter().map(|g| g.target_slot_type).collect();
        assert_eq!(types, vec![2, 2, 3, 2]);
        assert_eq!(p.genes[1].meeting_number, 2);
    }

    #[test]
    fn overrides_apply_to_soft_constraints_only_and_must_be_positive() {
        let ds = dataset(&[(1, 10, 3, 3), (2, 20, 3, 3)], |_| 7);
        let mut cfg = config();
        cfg.sc_weights_by_study_program.insert(
            "10".into(),
            HashMap::from([
                ("SC_INGIN".into(), 3.0),
                ("HC2".into(), 99.0),
                ("SC_SKS".into(), 0.0),
            ]),
        );
        let p = Problem::build(&ds, &cfg).unwrap();
        assert_eq!(p.genes[0].w_want, 3.0);
        assert_eq!(
            p.genes[0].w_sks, 2.0,
            "zero override falls back to the global weight"
        );
        assert_eq!(
            p.genes[1].w_want, 1.0,
            "other program keeps the global weight"
        );
        assert_eq!(p.w_hc2, 10.0, "HC weight never comes from an override");
    }

    #[test]
    fn max_fitness_counts_preference_terms_only_for_lecturers_who_have_them() {
        let mut ds = dataset(&[(1, 10, 3, 3), (2, 10, 3, 3)], |id| id);
        ds.lecturers[0].preferences.push(Preference {
            time_slot_id: 1,
            kind: PreferenceType::Want,
        });
        let p = Problem::build(&ds, &config()).unwrap();
        // C + (want 1 + sks 2) + (sks 2)
        assert_eq!(p.max_fitness(), 505.0);
    }

    #[test]
    fn empty_dataset_is_rejected() {
        let ds = dataset(&[], |_| 1);
        assert!(Problem::build(&ds, &config()).is_err());
    }
}
