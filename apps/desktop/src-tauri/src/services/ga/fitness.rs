//! Fitness of one chromosome
//!
//! `fitness = C − Σ(HC violations × HC weight) + Σ(SC met × SC weight)`
//!
//! Program datasets replace HC4 with the cells: a slot holds at most as many meetings as the
//! program has cells in it. Each meeting over that is penalised with the HC4 weight, but it is not
//! an HC4 violation for the server; it shows as a meeting without a room.

use super::problem::Problem;

pub type Chromosome = Vec<u16>;

#[derive(Debug, Clone, Copy, Default, PartialEq)]
pub struct Score {
    pub fitness: f64,
    pub hc2: u32,
    pub hc3: u32,
    pub hc4: u32,
    /// Program datasets: meetings beyond the cells of their slot.
    pub cell_overflow: u32,
    pub soft_points: f64,
}

impl Score {
    pub fn hard_violations(&self) -> u32 {
        self.hc2 + self.hc3 + self.hc4 + self.cell_overflow
    }
}

#[derive(Debug, Clone)]
pub struct Scratch {
    lecturer_slot: Vec<u16>,
    cohort_slot: Vec<u16>,
    program_slot: Vec<u16>,
}

impl Scratch {
    pub fn new(problem: &Problem) -> Self {
        let s = problem.slot_count();
        Self {
            lecturer_slot: vec![0; problem.lecturers.len() * s],
            cohort_slot: vec![0; problem.cohort_count * s],
            program_slot: vec![0; problem.program_count * s],
        }
    }
}

pub fn evaluate(problem: &Problem, chromosome: &[u16], scratch: &mut Scratch) -> Score {
    let s = problem.slot_count();
    scratch.lecturer_slot.fill(0);
    scratch.cohort_slot.fill(0);
    scratch.program_slot.fill(0);

    let mut soft_points = 0.0;
    for gene in &problem.genes {
        let slot = chromosome[gene.unit] as usize;
        scratch.lecturer_slot[gene.lecturer * s + slot] += 1;
        if let Some(cohort) = gene.cohort {
            scratch.cohort_slot[cohort * s + slot] += 1;
        }
        if let Some(program) = gene.program {
            scratch.program_slot[program * s + slot] += 1;
        }

        let prefs = &problem.lecturers[gene.lecturer];
        if !prefs.want.is_empty() && prefs.want[slot] {
            soft_points += gene.w_want;
        }
        if !prefs.avoid.is_empty() && !prefs.avoid[slot] {
            soft_points += gene.w_avoid;
        }
        if problem.slots[slot].slot_type == gene.target_slot_type {
            soft_points += gene.w_sks;
        }
    }

    // TPB: a program meets a regular group once per unit, however many classes the group has.
    for (unit, &slot) in problem.units.iter().zip(chromosome) {
        for &cohort in &unit.cohorts {
            scratch.cohort_slot[cohort * s + slot as usize] += 1;
        }
    }
    for course in &problem.retake {
        if course
            .units
            .iter()
            .any(|&u| !course.blocked[chromosome[u] as usize])
        {
            soft_points += course.weight;
        }
    }

    let excess = |counts: &[u16]| {
        counts
            .iter()
            .map(|&c| u32::from(c.saturating_sub(1)))
            .sum::<u32>()
    };
    let hc2 = excess(&scratch.lecturer_slot);
    let hc3 = excess(&scratch.cohort_slot);
    let (hc4, cell_overflow) = if problem.uses_cells() {
        let mut overflow = 0u32;
        for slot in 0..s {
            let meetings: u32 = (0..problem.program_count)
                .map(|p| u32::from(scratch.program_slot[p * s + slot]))
                .sum();
            overflow += meetings.saturating_sub(problem.slot_cells[slot]);
        }
        (0, overflow)
    } else {
        let limit = problem.parallel_class_limit;
        let hc4 = scratch
            .program_slot
            .iter()
            .filter(|&&c| u32::from(c) > limit)
            .count() as u32;
        (hc4, 0)
    };

    let penalty = f64::from(hc2) * problem.w_hc2
        + f64::from(hc3) * problem.w_hc3
        + f64::from(hc4 + cell_overflow) * problem.w_hc4;

    Score {
        fitness: problem.fitness_constant - penalty + soft_points,
        hc2,
        hc3,
        hc4,
        cell_overflow,
        soft_points,
    }
}

#[cfg(test)]
mod tests {
    use super::super::model::{
        ClassRow, ConstraintType, Dataset, Lecturer, LecturerBlock, Preference, PreferenceType,
        ProgramBlock, RetakeBlock, TpbBlocks, TpbGroup, TpbParticipant,
    };
    use super::super::problem::{fixtures::*, Problem};
    use super::*;
    use rand::SeedableRng;

    fn at(day: u16, session: u16) -> u16 {
        (day - 1) * 4 + (session - 1)
    }

    #[test]
    fn laporan_ta_five_gene_example() {
        let lecturers = [10, 10, 5, 5, 12];
        let mut ds = dataset(
            &[
                (1, 1, 3, 1),
                (2, 1, 3, 1),
                (3, 1, 2, 1),
                (4, 1, 2, 1),
                (5, 1, 3, 1),
            ],
            |id| lecturers[(id - 1) as usize],
        );
        for (class, n) in ds.classes.iter_mut().zip([1, 2, 1, 2, 1]) {
            class.class_number = n;
        }
        // Lecturer 12 wants waktu 9 (TA preferensi {3}); lecturer 5 wants slots it is not placed in.
        let pref = |l: i64, slot: i64| {
            (
                l,
                Preference {
                    time_slot_id: slot,
                    kind: PreferenceType::Want,
                },
            )
        };
        for (l, p) in [pref(12, 9), pref(5, 5), pref(5, 6)] {
            ds.lecturers
                .iter_mut()
                .find(|x| x.id == l)
                .unwrap()
                .preferences
                .push(p);
        }
        let problem = Problem::build(&ds, &config()).unwrap();
        // waktuId 9 = Wed session 1, 13 = Thu session 1, 4 = Mon session 4 (ids are 1-based).
        let chromosome = vec![8, 12, 3, 3, 8];
        let mut scratch = Scratch::new(&problem);
        let score = evaluate(&problem, &chromosome, &mut scratch);

        assert_eq!(score.hc2, 1, "lecturer 5 twice at waktu 4 (Tabel 4.9)");
        assert_eq!(
            score.hc3, 1,
            "class 1 semester 1 twice at waktu 9 (Tabel 4.11)"
        );
        assert_eq!(score.hc4, 0, "Tabel 4.13");
        // SC preferensi = 1 (Tabel 4.14), SC SKS = 5 × 2 (Tabel 4.15).
        assert_eq!(score.soft_points, 11.0);
        assert_eq!(score.fitness, 500.0 - 20.0 + 11.0);
    }

    #[test]
    fn hc4_counts_one_violation_per_program_slot_over_the_limit() {
        let ds = dataset(
            &(1..=7).map(|id| (id, 1, 3, id as u8)).collect::<Vec<_>>(),
            |id| id,
        );
        let mut cfg = config();
        cfg.params.parallel_class_limit = 5;
        let problem = Problem::build(&ds, &cfg).unwrap();
        let mut scratch = Scratch::new(&problem);

        let score = evaluate(&problem, &[0; 7], &mut scratch);
        assert_eq!((score.hc2, score.hc3, score.hc4), (0, 0, 1));
    }

    #[test]
    fn hc3_and_hc4_are_per_program_but_hc2_is_faculty_wide() {
        // Same semester and class in two programs, same slot: no HC3. Same lecturer: HC2.
        let ds = dataset(&[(1, 1, 3, 3), (2, 2, 3, 3)], |_| 7);
        let problem = Problem::build(&ds, &config()).unwrap();
        let mut scratch = Scratch::new(&problem);

        let score = evaluate(&problem, &[at(1, 1), at(1, 1)], &mut scratch);
        assert_eq!((score.hc2, score.hc3, score.hc4), (1, 0, 0));
    }

    #[test]
    fn avoid_preference_is_met_when_the_slot_is_not_avoided() {
        let mut ds = dataset(&[(1, 1, 2, 3)], |_| 7);
        ds.lecturers[0].preferences.push(Preference {
            time_slot_id: 4,
            kind: PreferenceType::Avoid,
        });
        let problem = Problem::build(&ds, &config()).unwrap();
        let mut scratch = Scratch::new(&problem);

        // Mon session 4 (avoided, type 2): SC_SKS only.
        assert_eq!(
            evaluate(&problem, &[at(1, 4)], &mut scratch).soft_points,
            2.0
        );
        // Mon session 2 (not avoided, type 2): SC_HINDARI + SC_SKS.
        assert_eq!(
            evaluate(&problem, &[at(1, 2)], &mut scratch).soft_points,
            3.0
        );
    }

    #[test]
    fn per_program_soft_weight_is_used() {
        let mut ds = dataset(&[(1, 1, 3, 3)], |_| 7);
        ds.lecturers[0].preferences.push(Preference {
            time_slot_id: 1,
            kind: PreferenceType::Want,
        });
        let mut cfg = config();
        cfg.sc_weights_by_study_program.insert(
            "1".into(),
            [("SC_INGIN".to_string(), 4.0)].into_iter().collect(),
        );
        let problem = Problem::build(&ds, &cfg).unwrap();
        let mut scratch = Scratch::new(&problem);

        assert_eq!(
            evaluate(&problem, &[at(1, 1)], &mut scratch).soft_points,
            4.0 + 2.0
        );
    }

    fn tpb_dataset() -> Dataset {
        // Course 1: classes 1-2 in regular group 10 (programs 1 and 2, semester 1), class 3 in retake group 11.
        // Course 2: class 1 in regular group 20 (program 1, semester 1).
        let mut ds = dataset(&[(1, 1, 3, 1), (2, 1, 2, 1)], |id| id);
        ds.scope = Some("tpb".into());
        ds.faculty = None;
        for c in &mut ds.courses {
            c.study_program_id = None;
            c.semester = None;
        }
        let class = |course_id, class_number, lecturer_id, group| ClassRow {
            course_id,
            class_number,
            lecturer_id,
            fixed_room_id: None,
            fixed_time_slot_id: None,
            tpb_group_id: Some(group),
            ..Default::default()
        };
        ds.lecturers.push(Lecturer {
            id: 3,
            name: "Dosen 3".into(),
            preferences: Vec::new(),
        });
        ds.classes = vec![
            class(1, 1, 1, 10),
            class(1, 2, 2, 10),
            class(1, 3, 3, 11),
            class(2, 1, 2, 20),
        ];
        let participant = |id| TpbParticipant {
            id,
            target_semester: Some(1),
        };
        ds.tpb_groups = vec![
            TpbGroup {
                id: 10,
                cohort: "maba".into(),
                study_programs: vec![participant(1), participant(2)],
            },
            TpbGroup {
                id: 11,
                cohort: "mengulang".into(),
                study_programs: vec![TpbParticipant {
                    id: 1,
                    target_semester: None,
                }],
            },
            TpbGroup {
                id: 20,
                cohort: "maba".into(),
                study_programs: vec![participant(1)],
            },
        ];
        ds
    }

    #[test]
    fn tpb_classes_of_one_group_share_one_unit() {
        let problem = Problem::build(&tpb_dataset(), &config()).unwrap();

        assert_eq!(problem.units.len(), 3, "group 10, group 11, group 20");
        assert_eq!(problem.genes[0].unit, problem.genes[1].unit);
        assert_ne!(problem.genes[0].unit, problem.genes[2].unit);
        assert!(problem
            .genes
            .iter()
            .all(|g| g.program.is_none() && g.cohort.is_none()));
    }

    #[test]
    fn tpb_hc3_counts_a_program_once_per_group_meeting() {
        let problem = Problem::build(&tpb_dataset(), &config()).unwrap();
        let mut scratch = Scratch::new(&problem);
        let unit_of = |course: i64| {
            problem
                .genes
                .iter()
                .find(|g| g.course_id == course)
                .unwrap()
                .unit
        };
        let mut chromosome = vec![at(1, 2); 3];

        // Groups 10 and 20 both hold program 1 semester 1 in the same slot: one clash, not two.
        let score = evaluate(&problem, &chromosome, &mut scratch);
        assert_eq!(score.hc3, 1);
        // Lecturer 2 teaches class 2 of group 10 and class 1 of group 20 in that slot.
        assert_eq!(score.hc2, 1);

        chromosome[unit_of(2)] = at(2, 2);
        let score = evaluate(&problem, &chromosome, &mut scratch);
        assert_eq!(
            (score.hc2, score.hc3),
            (0, 0),
            "the retake group never clashes with programs"
        );
        let rows = super::super::violations::collect(&problem, &chromosome);
        assert!(rows.iter().all(|v| !v.constraint_code.starts_with("HC")));
    }

    #[test]
    fn faculty_genes_never_get_a_slot_blocked_by_tpb() {
        let mut ds = dataset(&[(1, 1, 3, 1), (2, 1, 3, 3)], |id| id);
        ds.tpb_blocks = Some(TpbBlocks {
            study_programs: vec![ProgramBlock {
                study_program_id: 1,
                semester: 1,
                time_slot_ids: (1..=18).collect(),
            }],
            retake: Vec::new(),
            lecturers: vec![LecturerBlock {
                lecturer_id: 2,
                time_slot_ids: (2..=20).collect(),
            }],
        });
        let problem = Problem::build(&ds, &config()).unwrap();
        assert_eq!(
            problem.units[0].allowed,
            vec![18, 19],
            "slots 19 and 20 (ids are 1-based)"
        );
        assert_eq!(problem.units[1].allowed, vec![0]);

        let mut rng = rand::rngs::StdRng::seed_from_u64(7);
        for _ in 0..50 {
            let mut population = vec![super::super::operators::random_chromosome(
                &problem, &mut rng,
            )];
            super::super::operators::mutate(&mut population, &problem, 1.0, &mut rng);
            assert!([18, 19].contains(&population[0][0]));
            assert_eq!(population[0][1], 0);
        }
    }

    #[test]
    fn a_fully_blocked_class_is_rejected() {
        let mut ds = dataset(&[(1, 1, 3, 1)], |id| id);
        ds.tpb_blocks = Some(TpbBlocks {
            study_programs: vec![ProgramBlock {
                study_program_id: 1,
                semester: 1,
                time_slot_ids: (1..=20).collect(),
            }],
            ..TpbBlocks::default()
        });
        assert!(Problem::build(&ds, &config()).is_err());
    }

    #[test]
    fn sc_ulang_is_met_while_one_class_escapes_the_retake_slots() {
        let mut ds = dataset(&[(1, 1, 3, 3)], |id| id);
        ds.lecturers.push(Lecturer {
            id: 2,
            name: "Dosen 2".into(),
            preferences: Vec::new(),
        });
        ds.classes.push(ClassRow {
            course_id: 1,
            class_number: 2,
            lecturer_id: 2,
            fixed_room_id: None,
            fixed_time_slot_id: None,
            tpb_group_id: None,
            ..Default::default()
        });
        ds.constraint_types.push(ConstraintType {
            code: "SC_ULANG".into(),
            weight: 3.0,
        });
        ds.tpb_blocks = Some(TpbBlocks {
            retake: vec![RetakeBlock {
                study_program_id: 1,
                time_slot_ids: vec![1, 2],
            }],
            ..TpbBlocks::default()
        });
        let problem = Problem::build(&ds, &config()).unwrap();
        let mut scratch = Scratch::new(&problem);
        assert_eq!(problem.retake.len(), 1);

        // Slot ids 1 and 2 are indexes 0 and 1. Index 2 (Mon session 3) is free and also type 3.
        let escaped = evaluate(&problem, &[0, 2], &mut scratch).soft_points;
        let trapped = evaluate(&problem, &[0, 0], &mut scratch).soft_points;
        assert_eq!(escaped - trapped, 3.0);
        let rows = super::super::violations::collect(&problem, &[0, 0]);
        assert_eq!(
            rows.iter()
                .filter(|v| v.constraint_code == "SC_ULANG")
                .count(),
            1
        );
        assert_eq!(
            problem.max_fitness() - evaluate(&problem, &[0, 0], &mut scratch).fitness,
            rows.iter().map(|v| v.penalty).sum::<f64>()
        );
    }

    #[test]
    fn tpb_revision_freezes_unchanged_groups_and_avoids_faculty_slots() {
        let mut ds = tpb_dataset();
        // Group 10 (classes 1-2 of course 1) is frozen at slot id 6, with class 1 in room 2.
        for c in ds.classes.iter_mut().filter(|c| c.tpb_group_id == Some(10)) {
            c.fixed_time_slot_ids = vec![6];
            c.fixed_room_ids = vec![if c.class_number == 1 { Some(2) } else { None }];
        }
        // Group 20 moves; faculties hold program 1 semester 1 in all slots but ids 3 and 4.
        ds.tpb_blocks = Some(TpbBlocks {
            study_programs: vec![ProgramBlock {
                study_program_id: 1,
                semester: 1,
                time_slot_ids: (1..=20).filter(|s| *s != 3 && *s != 4).collect(),
            }],
            ..TpbBlocks::default()
        });
        let problem = Problem::build(&ds, &config()).unwrap();
        let frozen = problem.genes[0].unit;
        let moving = problem.genes[3].unit;

        assert_eq!(problem.units[frozen].fixed_slot, Some(5));
        assert_eq!(problem.genes[0].fixed_room_id, Some(2));
        assert_eq!(problem.units[moving].allowed, vec![2, 3]);

        let mut rng = rand::rngs::StdRng::seed_from_u64(3);
        for _ in 0..30 {
            let mut pop = vec![super::super::operators::random_chromosome(
                &problem, &mut rng,
            )];
            super::super::operators::mutate(&mut pop, &problem, 1.0, &mut rng);
            assert_eq!(pop[0][frozen], 5);
            assert!([2, 3].contains(&pop[0][moving]));
        }
    }

    #[test]
    fn program_dataset_counts_meetings_over_the_cells_not_hc4() {
        use super::super::model::Cell;
        let mut ds = dataset(&[(1, 10, 3, 3), (2, 10, 3, 5), (3, 10, 3, 7)], |id| id);
        ds.cells = vec![
            Cell {
                time_slot_id: 1,
                room_id: 1,
            },
            Cell {
                time_slot_id: 1,
                room_id: 2,
            },
        ];
        let mut cfg = config();
        cfg.params.parallel_class_limit = 1;
        let p = Problem::build(&ds, &cfg).unwrap();
        let mut scratch = Scratch::new(&p);
        let score = evaluate(&p, &[0, 0, 0], &mut scratch);

        assert_eq!(
            (score.hc4, score.cell_overflow),
            (0, 1),
            "three meetings, two cells, no parallel limit"
        );
        assert_eq!(score.fitness, 500.0 - 10.0 + 3.0 * 2.0);
    }
}
