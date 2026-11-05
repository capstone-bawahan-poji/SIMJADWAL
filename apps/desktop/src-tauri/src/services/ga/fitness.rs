//! Fitness of one chromosome
//!
//! `fitness = C − Σ(HC violations × HC weight) + Σ(SC met × SC weight)`

use super::problem::Problem;

pub type Chromosome = Vec<u16>;

#[derive(Debug, Clone, Copy, Default, PartialEq)]
pub struct Score {
    pub fitness: f64,
    pub hc2: u32,
    pub hc3: u32,
    pub hc4: u32,
    pub soft_points: f64,
}

impl Score {
    pub fn hard_violations(&self) -> u32 {
        self.hc2 + self.hc3 + self.hc4
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
    for (gene, &slot) in problem.genes.iter().zip(chromosome) {
        let slot = slot as usize;
        scratch.lecturer_slot[gene.lecturer * s + slot] += 1;
        scratch.cohort_slot[gene.cohort * s + slot] += 1;
        scratch.program_slot[gene.program * s + slot] += 1;

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

    let excess = |counts: &[u16]| {
        counts
            .iter()
            .map(|&c| u32::from(c.saturating_sub(1)))
            .sum::<u32>()
    };
    let hc2 = excess(&scratch.lecturer_slot);
    let hc3 = excess(&scratch.cohort_slot);
    let limit = problem.parallel_class_limit;
    let hc4 = scratch
        .program_slot
        .iter()
        .filter(|&&c| u32::from(c) > limit)
        .count() as u32;

    let penalty = f64::from(hc2) * problem.w_hc2
        + f64::from(hc3) * problem.w_hc3
        + f64::from(hc4) * problem.w_hc4;

    Score {
        fitness: problem.fitness_constant - penalty + soft_points,
        hc2,
        hc3,
        hc4,
        soft_points,
    }
}

#[cfg(test)]
mod tests {
    use super::super::model::{Preference, PreferenceType};
    use super::super::problem::{fixtures::*, Problem};
    use super::*;

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
}
