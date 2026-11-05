//! One GA run
//!
//! Generation order: crossover → mutation → fitness → selection → new population.
//! No elitism: the best chromosome ever seen is kept aside and becomes the result.
//! Stop rule: at least `min_generations`; after that stop when the best fitness has not
//! improved for `stagnation_limit` generations, or at `max_generations`.

use std::sync::atomic::{AtomicBool, Ordering};

use rand::SeedableRng;
use rand_chacha::ChaCha8Rng;
use rayon::prelude::*;

use super::fitness::{evaluate, Chromosome, Score, Scratch};
use super::model::{GaParams, SelectionOperator};
use super::operators::{crossover, mutate, random_chromosome};
use super::problem::Problem;
use super::selection::{select, Roulette};

#[derive(Debug, Clone, Copy)]
pub struct Progress {
    pub generation: u32,
    pub best_fitness: f64,
    pub current_fitness: f64,
}

#[derive(Debug, Clone)]
pub struct Outcome {
    pub best: Chromosome,
    pub best_score: Score,
    pub best_generation: u32,
    pub best_chromosome_index: usize,
    pub final_generation: u32,
    pub fitness_history: Vec<f64>,
    pub cancelled: bool,
}

pub fn run(
    problem: &Problem,
    params: &GaParams,
    operator: SelectionOperator,
    seed: u64,
    cancel: &AtomicBool,
    on_progress: &(dyn Fn(Progress) + Sync),
) -> Outcome {
    let mut rng = ChaCha8Rng::seed_from_u64(seed);
    let size = params.population_size.max(2);

    let mut population: Vec<Chromosome> = (0..size)
        .map(|_| random_chromosome(problem, &mut rng))
        .collect();
    let mut scores = evaluate_all(problem, &population);

    let (index, score) = best_of(&scores);
    let mut best = population[index].clone();
    let mut best_score = score;
    let mut best_generation = 0;
    let mut best_chromosome_index = index;
    let mut fitness_history = vec![score.fitness];
    let mut generation = 0;
    let mut cancelled = false;

    on_progress(Progress {
        generation,
        best_fitness: best_score.fitness,
        current_fitness: score.fitness,
    });

    while generation < params.max_generations {
        if cancel.load(Ordering::Relaxed) {
            cancelled = true;
            break;
        }
        generation += 1;

        crossover(&mut population, params.crossover_rate, &mut rng);
        mutate(&mut population, problem, params.mutation_rate, &mut rng);
        scores = evaluate_all(problem, &population);

        let (index, score) = best_of(&scores);
        fitness_history.push(score.fitness);
        if score.fitness > best_score.fitness {
            best = population[index].clone();
            best_score = score;
            best_generation = generation;
            best_chromosome_index = index;
        }
        on_progress(Progress {
            generation,
            best_fitness: best_score.fitness,
            current_fitness: score.fitness,
        });

        if generation >= params.min_generations
            && generation - best_generation >= params.stagnation_limit
        {
            break;
        }

        // Selection builds the population the next generation starts from.
        let fitness: Vec<f64> = scores.iter().map(|s| s.fitness).collect();
        let roulette = Roulette::new(&fitness);
        let next: Vec<usize> = (0..size)
            .map(|_| {
                select(
                    operator,
                    &fitness,
                    &roulette,
                    params.tournament_size,
                    &mut rng,
                )
            })
            .collect();
        population = next.iter().map(|&i| population[i].clone()).collect();
    }

    Outcome {
        best,
        best_score,
        best_generation,
        best_chromosome_index,
        final_generation: generation,
        fitness_history,
        cancelled,
    }
}

fn evaluate_all(problem: &Problem, population: &[Chromosome]) -> Vec<Score> {
    population
        .par_iter()
        .map_init(
            || Scratch::new(problem),
            |scratch, c| evaluate(problem, c, scratch),
        )
        .collect()
}

fn best_of(scores: &[Score]) -> (usize, Score) {
    let mut best = 0;
    for (i, s) in scores.iter().enumerate().skip(1) {
        if s.fitness > scores[best].fitness {
            best = i;
        }
    }
    (best, scores[best])
}

#[cfg(test)]
mod tests {
    use super::super::problem::{fixtures::*, Problem};
    use super::*;
    use std::sync::atomic::AtomicU32;

    fn small_problem() -> Problem {
        // 3 programs × 6 courses, 9 lecturers, mixed SKS: a schedule with 0 HC exists.
        let courses: Vec<(i64, i64, u8, u8)> = (0..18)
            .map(|i| {
                (
                    i + 1,
                    i % 3 + 1,
                    [2, 3, 4][(i % 3) as usize],
                    (i % 4 + 1) as u8,
                )
            })
            .collect();
        Problem::build(&dataset(&courses, |id| id % 9 + 1), &config()).unwrap()
    }

    fn no_progress() -> impl Fn(Progress) + Sync {
        |_| {}
    }

    #[test]
    fn same_seed_gives_the_same_run() {
        let problem = small_problem();
        let params = params();
        let cancel = AtomicBool::new(false);
        let a = run(
            &problem,
            &params,
            SelectionOperator::TS,
            42,
            &cancel,
            &no_progress(),
        );
        let b = run(
            &problem,
            &params,
            SelectionOperator::TS,
            42,
            &cancel,
            &no_progress(),
        );
        assert_eq!(a.best, b.best);
        assert_eq!(a.fitness_history, b.fitness_history);

        let c = run(
            &problem,
            &params,
            SelectionOperator::TS,
            43,
            &cancel,
            &no_progress(),
        );
        assert_ne!(a.fitness_history, c.fitness_history);
    }

    #[test]
    fn runs_at_least_min_and_at_most_max_generations() {
        let problem = small_problem();
        let cancel = AtomicBool::new(false);
        for op in [SelectionOperator::TS, SelectionOperator::TSR] {
            let outcome = run(&problem, &params(), op, 1, &cancel, &no_progress());
            assert!(
                (30..=60).contains(&outcome.final_generation),
                "{}",
                outcome.final_generation
            );
            assert_eq!(
                outcome.fitness_history.len() as u32,
                outcome.final_generation + 1
            );
        }
    }

    #[test]
    fn stops_on_stagnation_after_the_minimum() {
        let problem = small_problem();
        let mut p = params();
        p.min_generations = 5;
        p.max_generations = 10_000;
        p.stagnation_limit = 15;
        let outcome = run(
            &problem,
            &p,
            SelectionOperator::TSR,
            3,
            &AtomicBool::new(false),
            &no_progress(),
        );
        assert!(outcome.final_generation < 10_000);
        assert_eq!(outcome.final_generation - outcome.best_generation, 15);
    }

    #[test]
    fn best_fitness_never_drops_and_matches_its_chromosome() {
        let problem = small_problem();
        let outcome = run(
            &problem,
            &params(),
            SelectionOperator::TS,
            5,
            &AtomicBool::new(false),
            &no_progress(),
        );
        let mut scratch = Scratch::new(&problem);
        assert_eq!(
            evaluate(&problem, &outcome.best, &mut scratch).fitness,
            outcome.best_score.fitness
        );
        let max_history = outcome
            .fitness_history
            .iter()
            .cloned()
            .fold(f64::MIN, f64::max);
        assert_eq!(max_history, outcome.best_score.fitness);
        assert!(outcome.best_score.fitness >= outcome.fitness_history[0]);
    }

    #[test]
    fn evolution_improves_on_the_initial_population() {
        let problem = small_problem();
        let mut p = params();
        p.population_size = 60;
        p.min_generations = 200;
        p.max_generations = 400;
        p.stagnation_limit = 100;
        let outcome = run(
            &problem,
            &p,
            SelectionOperator::TS,
            11,
            &AtomicBool::new(false),
            &no_progress(),
        );
        assert!(outcome.best_score.fitness > outcome.fitness_history[0]);
        assert_eq!(outcome.best_score.hard_violations(), 0);
    }

    #[test]
    fn cancel_stops_the_run_and_reports_it() {
        let problem = small_problem();
        let cancel = AtomicBool::new(false);
        let seen = AtomicU32::new(0);
        let on_progress = |p: Progress| {
            seen.store(p.generation, std::sync::atomic::Ordering::Relaxed);
            if p.generation == 3 {
                cancel.store(true, Ordering::Relaxed);
            }
        };
        let outcome = run(
            &problem,
            &params(),
            SelectionOperator::TS,
            1,
            &cancel,
            &on_progress,
        );
        assert!(outcome.cancelled);
        assert_eq!(outcome.final_generation, 3);
        assert_eq!(seen.load(std::sync::atomic::Ordering::Relaxed), 3);
    }
}
