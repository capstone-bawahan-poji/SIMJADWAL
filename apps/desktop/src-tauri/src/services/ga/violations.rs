use std::collections::HashMap;

use super::model::Violation;
use super::problem::{Problem, HC2, HC3, HC4, SC_HINDARI, SC_INGIN, SC_SKS};

const DAYS: [&str; 7] = [
    "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu",
];

pub fn collect(problem: &Problem, chromosome: &[u16]) -> Vec<Violation> {
    let mut out = Vec::new();

    for (code, key) in [(HC2, Key::Lecturer), (HC3, Key::Cohort)] {
        let mut seen: HashMap<(usize, u16), usize> = HashMap::new();
        for (i, gene) in problem.genes.iter().enumerate() {
            let k = match key {
                Key::Lecturer => gene.lecturer,
                Key::Cohort => gene.cohort,
            };
            let slot = chromosome[i];
            if let Some(&first) = seen.get(&(k, slot)) {
                let weight = if code == HC2 {
                    problem.w_hc2
                } else {
                    problem.w_hc3
                };
                let message = match key {
                    Key::Lecturer => format!(
                        "{} bentrok dengan {} pada {} (dosen {} sama)",
                        label(problem, i),
                        label(problem, first),
                        when(problem, slot),
                        problem.lecturers[gene.lecturer].name
                    ),
                    Key::Cohort => format!(
                        "{} bentrok dengan {} pada {} (semester dan kelas sama)",
                        label(problem, i),
                        label(problem, first),
                        when(problem, slot)
                    ),
                };
                out.push(Violation {
                    detail_index: i,
                    constraint_code: code.into(),
                    penalty: weight,
                    message,
                });
            } else {
                seen.insert((k, slot), i);
            }
        }
    }

    let mut per_program_slot: HashMap<(usize, u16), Vec<usize>> = HashMap::new();
    for (i, gene) in problem.genes.iter().enumerate() {
        per_program_slot
            .entry((gene.program, chromosome[i]))
            .or_default()
            .push(i);
    }
    let mut over: Vec<_> = per_program_slot
        .into_iter()
        .filter(|(_, genes)| genes.len() as u32 > problem.parallel_class_limit)
        .collect();
    over.sort_by_key(|((program, slot), _)| (*program, *slot));
    for ((_, slot), genes) in over {
        out.push(Violation {
            detail_index: *genes.last().unwrap_or(&0),
            constraint_code: HC4.into(),
            penalty: problem.w_hc4,
            message: format!(
                "{} kelas satu prodi berjalan bersamaan pada {} (batas {})",
                genes.len(),
                when(problem, slot),
                problem.parallel_class_limit
            ),
        });
    }

    for (i, gene) in problem.genes.iter().enumerate() {
        let slot = chromosome[i];
        let prefs = &problem.lecturers[gene.lecturer];
        if !prefs.want.is_empty() && !prefs.want[slot as usize] {
            out.push(Violation {
                detail_index: i,
                constraint_code: SC_INGIN.into(),
                penalty: gene.w_want,
                message: format!(
                    "{} pada {}, bukan slot yang diinginkan {}",
                    label(problem, i),
                    when(problem, slot),
                    prefs.name
                ),
            });
        }
        if !prefs.avoid.is_empty() && prefs.avoid[slot as usize] {
            out.push(Violation {
                detail_index: i,
                constraint_code: SC_HINDARI.into(),
                penalty: gene.w_avoid,
                message: format!(
                    "{} pada {}, slot yang dihindari {}",
                    label(problem, i),
                    when(problem, slot),
                    prefs.name
                ),
            });
        }
        let slot_type = problem.slots[slot as usize].slot_type;
        if slot_type != gene.target_slot_type {
            out.push(Violation {
                detail_index: i,
                constraint_code: SC_SKS.into(),
                penalty: gene.w_sks,
                message: format!(
                    "{} pada {} (slot {} SKS, seharusnya {} SKS)",
                    label(problem, i),
                    when(problem, slot),
                    slot_type,
                    gene.target_slot_type
                ),
            });
        }
    }

    out
}

enum Key {
    Lecturer,
    Cohort,
}

fn label(problem: &Problem, gene: usize) -> String {
    let g = &problem.genes[gene];
    let course = problem
        .course_labels
        .get(&g.course_id)
        .cloned()
        .unwrap_or_else(|| g.course_id.to_string());
    let class = char::from_u32(64 + u32::from(g.class_number)).unwrap_or('?');
    if g.meeting_number > 1 {
        format!("{course} kelas {class} pertemuan {}", g.meeting_number)
    } else {
        format!("{course} kelas {class}")
    }
}

fn when(problem: &Problem, slot: u16) -> String {
    let s = &problem.slots[slot as usize];
    let day = DAYS
        .get(usize::from(s.day.saturating_sub(1)))
        .copied()
        .unwrap_or("?");
    format!("{day} sesi {}", s.session)
}

#[cfg(test)]
mod tests {
    use super::super::fitness::{evaluate, Scratch};
    use super::super::problem::{fixtures::*, Problem};
    use super::*;

    #[test]
    fn hard_violation_rows_match_the_fitness_counts() {
        let ds = dataset(&(1..=5).map(|id| (id, 1, 3, 3)).collect::<Vec<_>>(), |_| 7);
        let mut cfg = config();
        cfg.params.parallel_class_limit = 3;
        let problem = Problem::build(&ds, &cfg).unwrap();
        let chromosome = vec![0; 5];

        let score = evaluate(&problem, &chromosome, &mut Scratch::new(&problem));
        let rows = collect(&problem, &chromosome);
        let count = |code: &str| rows.iter().filter(|v| v.constraint_code == code).count() as u32;

        assert_eq!(
            (count(HC2), count(HC3), count(HC4)),
            (score.hc2, score.hc3, score.hc4)
        );
        assert_eq!((score.hc2, score.hc3, score.hc4), (4, 4, 1));
    }

    #[test]
    fn soft_rows_add_up_to_the_points_lost() {
        let ds = dataset(&[(1, 1, 3, 3), (2, 1, 2, 3)], |id| id);
        let problem = Problem::build(&ds, &config()).unwrap();
        let chromosome = vec![1, 0];

        let rows = collect(&problem, &chromosome);
        let score = evaluate(&problem, &chromosome, &mut Scratch::new(&problem));
        let lost: f64 = rows.iter().map(|v| v.penalty).sum();
        assert_eq!(lost, problem.max_fitness() - score.fitness);
        assert!(rows[0].message.contains("Senin sesi 2"));
    }
}