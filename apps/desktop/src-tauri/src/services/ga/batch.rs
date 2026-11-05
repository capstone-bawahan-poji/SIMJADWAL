//! A batch is every selected operator × `run_count` runs. Runs execute in
//! parallel; seed = `base_seed + run_index`, so TS and TSR see the same seed series.

use std::collections::HashMap;
use std::sync::atomic::AtomicBool;
use std::time::{SystemTime, UNIX_EPOCH};

use rayon::prelude::*;
use serde_json::json;
use uuid::Uuid;

use super::engine::{self, Progress};
use super::fitness::Score;
use super::model::{BatchRequest, RunResult, RunSpec, RunSummary, ScheduleDetail};
use super::problem::{Problem, HC1, HC2, HC3, HC4, SC_HINDARI, SC_INGIN, SC_SKS};
use super::{rooms, violations};
use crate::error::AppError;

pub const MAX_RUNS_PER_BATCH: usize = 40;

pub fn expand(request: &BatchRequest) -> Result<Vec<RunSpec>, AppError> {
    let params = &request.config.params;
    let mut operators = if request.operators.is_empty() {
        vec![params.selection_operator]
    } else {
        request.operators.clone()
    };
    operators.dedup();

    if !(1..=10).contains(&params.run_count) {
        return Err(AppError::Invalid(
            "Jumlah run per skenario harus 1 sampai 10.".into(),
        ));
    }
    let total = operators.len() * params.run_count as usize;
    if total > MAX_RUNS_PER_BATCH {
        return Err(AppError::Invalid(format!(
            "Satu batch maksimal {MAX_RUNS_PER_BATCH} run, diminta {total}."
        )));
    }

    Ok(operators
        .iter()
        .flat_map(|&operator| {
            (0..params.run_count).map(move |run_index| RunSpec {
                run_id: Uuid::new_v4().to_string(),
                operator,
                run_index,
                seed: params.base_seed.wrapping_add(u64::from(run_index)),
            })
        })
        .collect())
}

pub fn validate(request: &BatchRequest) -> Result<(), AppError> {
    let p = &request.config.params;
    let checks = [
        (p.population_size >= 2, "Ukuran populasi minimal 2."),
        (p.min_generations >= 1, "Generasi minimum minimal 1."),
        (
            p.max_generations >= p.min_generations,
            "Generasi maksimum tidak boleh kurang dari minimum.",
        ),
        (p.stagnation_limit >= 1, "Batas stagnasi minimal 1."),
        (
            (0.0..=1.0).contains(&p.crossover_rate),
            "Crossover rate harus 0 sampai 1.",
        ),
        (
            (0.0..=1.0).contains(&p.mutation_rate),
            "Mutation rate harus 0 sampai 1.",
        ),
        (
            p.tournament_size >= 2 && p.tournament_size <= p.population_size,
            "Ukuran seleksi minimal 2 dan tidak melebihi populasi.",
        ),
        (
            p.parallel_class_limit >= 1,
            "Jumlah kelas paralel minimal 1.",
        ),
        (
            p.fitness_constant > 0.0,
            "Konstanta fitness harus lebih dari 0.",
        ),
    ];
    match checks.iter().find(|(ok, _)| !ok) {
        Some((_, message)) => Err(AppError::Invalid((*message).into())),
        None => Ok(()),
    }
}

pub fn execute(
    request: &BatchRequest,
    batch_id: &str,
    specs: &[RunSpec],
    cancel: &AtomicBool,
    on_progress: &(dyn Fn(&str, Progress) + Sync),
    on_finished: &(dyn Fn(&RunResult) + Sync),
) -> Result<(), AppError> {
    let problem = Problem::build(&request.dataset, &request.config)?;
    specs.par_iter().for_each(|spec| {
        let result = run_one(&problem, request, batch_id, spec, cancel, &|p| {
            on_progress(&spec.run_id, p)
        });
        on_finished(&result);
    });
    Ok(())
}

pub fn run_one(
    problem: &Problem,
    request: &BatchRequest,
    batch_id: &str,
    spec: &RunSpec,
    cancel: &AtomicBool,
    on_progress: &(dyn Fn(Progress) + Sync),
) -> RunResult {
    let params = &request.config.params;
    let started_at_ms = now_ms();
    let outcome = engine::run(
        problem,
        params,
        spec.operator,
        spec.seed,
        cancel,
        on_progress,
    );
    let room_ids = rooms::allocate(
        problem,
        &outcome.best,
        &request.dataset.rooms,
        &request.dataset.occupied_rooms,
    );
    let violations = violations::collect(problem, &outcome.best);

    let schedule_details: Vec<ScheduleDetail> = problem
        .genes
        .iter()
        .zip(&outcome.best)
        .zip(&room_ids)
        .map(|((gene, &slot), &room_id)| ScheduleDetail {
            course_id: gene.course_id,
            class_number: gene.class_number,
            meeting_number: gene.meeting_number,
            lecturer_id: gene.lecturer_id,
            time_slot_id: problem.slots[slot as usize].id,
            room_id,
        })
        .collect();

    let max_fitness = problem.max_fitness();
    let summary = summarize(&outcome.best_score, &violations, &room_ids, max_fitness);

    RunResult {
        run_id: spec.run_id.clone(),
        batch_id: batch_id.to_string(),
        faculty_id: request.dataset.faculty.id,
        period: request.dataset.period.clone(),
        dataset_fingerprint: request.dataset.fingerprint.clone(),
        preset_id: request.config.preset_id,
        operator: spec.operator,
        run_index: spec.run_index,
        parameter_snapshot: snapshot(problem, request, spec, outcome.best_chromosome_index),
        best_fitness: outcome.best_score.fitness,
        max_fitness,
        best_generation: outcome.best_generation,
        best_chromosome_index: outcome.best_chromosome_index,
        final_generation: outcome.final_generation,
        fitness_history: outcome.fitness_history,
        cancelled: outcome.cancelled,
        started_at_ms,
        finished_at_ms: now_ms(),
        summary,
        schedule_details,
        violations,
    }
}

fn summarize(
    score: &Score,
    violations: &[super::model::Violation],
    room_ids: &[Option<i64>],
    max_fitness: f64,
) -> RunSummary {
    RunSummary {
        hc_violations: score.hard_violations(),
        sc_unmet: violations
            .iter()
            .filter(|v| v.constraint_code.starts_with("SC_"))
            .count() as u32,
        unassigned_rooms: room_ids.iter().filter(|r| r.is_none()).count() as u32,
        fitness_percent: if max_fitness > 0.0 {
            score.fitness / max_fitness * 100.0
        } else {
            0.0
        },
    }
}

fn snapshot(
    problem: &Problem,
    request: &BatchRequest,
    spec: &RunSpec,
    best_chromosome_index: usize,
) -> serde_json::Value {
    let p = &request.config.params;
    let weight = |code: &str| -> f64 {
        request
            .config
            .weights
            .get(code)
            .copied()
            .unwrap_or_else(|| {
                request
                    .dataset
                    .constraint_types
                    .iter()
                    .find(|t| t.code == code)
                    .map(|t| t.weight)
                    .unwrap_or(0.0)
            })
    };
    let weights: HashMap<&str, f64> = [HC1, HC2, HC3, HC4, SC_INGIN, SC_HINDARI, SC_SKS]
        .into_iter()
        .map(|code| (code, weight(code)))
        .collect();
    let overrides: HashMap<&String, HashMap<&String, f64>> = request
        .config
        .sc_weights_by_study_program
        .iter()
        .map(|(program, row)| {
            let soft = row
                .iter()
                .filter(|(code, w)| {
                    [SC_INGIN, SC_HINDARI, SC_SKS].contains(&code.as_str()) && **w > 0.0
                })
                .map(|(code, w)| (code, *w))
                .collect::<HashMap<_, _>>();
            (program, soft)
        })
        .filter(|(_, row)| !row.is_empty())
        .collect();

    json!({
        "population_size": p.population_size,
        "min_generations": p.min_generations,
        "max_generations": p.max_generations,
        "stagnation_limit": p.stagnation_limit,
        "crossover_rate": p.crossover_rate,
        "mutation_rate": p.mutation_rate,
        "selection_operator": spec.operator,
        "tournament_size": p.tournament_size,
        "parallel_class_limit": problem.parallel_class_limit,
        "fitness_constant": p.fitness_constant,
        "base_seed": p.base_seed,
        "seed": spec.seed,
        "run_index": spec.run_index,
        "best_chromosome_index": best_chromosome_index,
        "weights": weights,
        "sc_weights_by_study_program": overrides,
    })
}

fn now_ms() -> u64 {
    SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .map(|d| d.as_millis() as u64)
        .unwrap_or(0)
}

#[cfg(test)]
mod tests {
    use super::super::model::{BatchRequest, SelectionOperator};
    use super::super::problem::fixtures::*;
    use super::*;
    use std::sync::Mutex;

    fn request(operators: Vec<SelectionOperator>, run_count: u32) -> BatchRequest {
        let mut config = config();
        config.params.run_count = run_count;
        BatchRequest {
            dataset: dataset(&[(1, 1, 3, 3), (2, 1, 2, 3), (3, 2, 4, 5)], |id| id),
            config,
            operators,
        }
    }

    #[test]
    fn expand_crosses_operators_with_runs_and_shares_the_seed_series() {
        let specs = expand(&request(
            vec![SelectionOperator::TS, SelectionOperator::TSR],
            10,
        ))
        .unwrap();
        assert_eq!(specs.len(), 20);
        let seeds = |op| {
            specs
                .iter()
                .filter(|s| s.operator == op)
                .map(|s| s.seed)
                .collect::<Vec<_>>()
        };
        assert_eq!(
            seeds(SelectionOperator::TS),
            (12345..12355).collect::<Vec<_>>()
        );
        assert_eq!(seeds(SelectionOperator::TS), seeds(SelectionOperator::TSR));
    }

    #[test]
    fn expand_rejects_more_than_ten_runs_per_operator() {
        assert!(expand(&request(vec![], 11)).is_err());
        assert_eq!(expand(&request(vec![], 3)).unwrap().len(), 3);
    }

    #[test]
    fn execute_returns_one_complete_result_per_run() {
        let req = request(vec![SelectionOperator::TS, SelectionOperator::TSR], 2);
        let specs = expand(&req).unwrap();
        let results = Mutex::new(Vec::new());
        execute(
            &req,
            "batch-1",
            &specs,
            &AtomicBool::new(false),
            &|_, _| {},
            &|r| results.lock().unwrap().push(r.clone()),
        )
        .unwrap();

        let results = results.into_inner().unwrap();
        assert_eq!(results.len(), 4);
        for r in &results {
            // 1 + 1 + 2 meetings (4 SKS)
            assert_eq!(r.schedule_details.len(), 4);
            assert_eq!(r.batch_id, "batch-1");
            assert!(r.best_fitness <= r.max_fitness);
            assert_eq!(r.parameter_snapshot["fitness_constant"], 500.0);
            assert_eq!(r.parameter_snapshot["weights"]["SC_SKS"], 2.0);
            assert_eq!(r.summary.hc_violations, 0);
        }
    }
}

#[cfg(test)]
mod bench {
    //! `SIMJADWAL_DATASET=/path/dataset.json cargo test --release bench -- --ignored --nocapture`
    use super::super::model::{BatchRequest, Dataset, GaParams, RunConfig, SelectionOperator};
    use super::*;

    #[test]
    #[ignore = "needs a real dataset file"]
    fn full_faculty_run_with_laporan_ta_parameters() {
        let path = std::env::var("SIMJADWAL_DATASET").expect("SIMJADWAL_DATASET");
        let dataset: Dataset = serde_json::from_slice(&std::fs::read(path).unwrap()).unwrap();
        for operator in [SelectionOperator::TS, SelectionOperator::TSR] {
            let request = BatchRequest {
                dataset: dataset.clone(),
                config: RunConfig {
                    params: GaParams {
                        population_size: 100,
                        min_generations: 500,
                        max_generations: 1000,
                        stagnation_limit: 200,
                        crossover_rate: 0.9,
                        mutation_rate: 0.5,
                        tournament_size: 2,
                        selection_operator: operator,
                        parallel_class_limit: 5,
                        fitness_constant: 500.0,
                        base_seed: 12345,
                        run_count: 1,
                    },
                    weights: Default::default(),
                    sc_weights_by_study_program: Default::default(),
                    preset_id: None,
                },
                operators: vec![],
            };
            let problem = Problem::build(&request.dataset, &request.config).unwrap();
            let spec = &expand(&request).unwrap()[0];
            let t = std::time::Instant::now();
            let r = run_one(
                &problem,
                &request,
                "bench",
                spec,
                &AtomicBool::new(false),
                &|_| {},
            );
            println!(
                "{operator:?}: genes {} | fitness {:.0}/{:.0} ({:.1}%) | HC {} | SC unmet {} | rooms unassigned {} | gen best {} final {} | {:?}",
                problem.genes.len(),
                r.best_fitness,
                r.max_fitness,
                r.summary.fitness_percent,
                r.summary.hc_violations,
                r.summary.sc_unmet,
                r.summary.unassigned_rooms,
                r.best_generation,
                r.final_generation,
                t.elapsed()
            );
        }
    }
}
