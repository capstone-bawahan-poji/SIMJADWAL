//! Variation operators of Laporan TA §4.6.4–4.6.5. Both only touch time slots, so gene order,
//! courses and lecturers never change.

use rand::Rng;

use super::fitness::Chromosome;
use super::problem::Problem;

pub fn random_chromosome<R: Rng>(problem: &Problem, rng: &mut R) -> Chromosome {
    let slots = problem.slot_count() as u16;
    problem
        .genes
        .iter()
        .map(|g| g.fixed_slot.unwrap_or_else(|| rng.random_range(0..slots)))
        .collect()
}

pub fn crossover<R: Rng>(population: &mut [Chromosome], rate: f64, rng: &mut R) {
    for pair in population.chunks_exact_mut(2) {
        let r: f64 = rng.random();
        let n = pair[0].len();
        if r >= rate || n < 2 {
            continue;
        }
        let cut = rng.random_range(1..n);
        let (left, right) = pair.split_at_mut(1);
        left[0][cut..].swap_with_slice(&mut right[0][cut..]);
    }
}

pub fn mutate<R: Rng>(population: &mut [Chromosome], problem: &Problem, rate: f64, rng: &mut R) {
    if problem.free_genes.is_empty() {
        return;
    }
    let slots = problem.slot_count() as u16;
    for chromosome in population.iter_mut() {
        let r: f64 = rng.random();
        if r >= rate {
            continue;
        }
        let gene = problem.free_genes[rng.random_range(0..problem.free_genes.len())];
        chromosome[gene] = rng.random_range(0..slots);
    }
}

#[cfg(test)]
mod tests {
    use super::super::problem::{fixtures::*, Problem};
    use super::*;
    use rand::SeedableRng;
    use rand_chacha::ChaCha8Rng;

    #[test]
    fn crossover_swaps_tails_of_paired_chromosomes() {
        let mut population = vec![vec![1, 1, 1, 1], vec![2, 2, 2, 2], vec![3, 3, 3, 3]];
        let mut rng = ChaCha8Rng::seed_from_u64(1);
        crossover(&mut population, 1.0, &mut rng);

        let cut = population[0]
            .iter()
            .position(|&g| g == 2)
            .expect("tails swapped");
        assert!((1..4).contains(&cut));
        assert!(
            population[0][..cut].iter().all(|&g| g == 1)
                && population[0][cut..].iter().all(|&g| g == 2)
        );
        assert!(
            population[1][..cut].iter().all(|&g| g == 2)
                && population[1][cut..].iter().all(|&g| g == 1)
        );
        assert_eq!(population[2], vec![3, 3, 3, 3], "odd one out stays");
    }

    #[test]
    fn rates_of_zero_leave_the_population_untouched() {
        let problem =
            Problem::build(&dataset(&[(1, 1, 3, 3), (2, 1, 3, 3)], |id| id), &config()).unwrap();
        let mut population = vec![vec![0, 1], vec![2, 3]];
        let mut rng = ChaCha8Rng::seed_from_u64(1);
        crossover(&mut population, 0.0, &mut rng);
        mutate(&mut population, &problem, 0.0, &mut rng);
        assert_eq!(population, vec![vec![0, 1], vec![2, 3]]);
    }

    #[test]
    fn mutation_changes_at_most_one_gene_per_chromosome() {
        let problem = Problem::build(
            &dataset(&[(1, 1, 3, 3), (2, 1, 3, 3), (3, 1, 3, 3)], |id| id),
            &config(),
        )
        .unwrap();
        let original = vec![vec![0, 0, 0]; 50];
        let mut population = original.clone();
        let mut rng = ChaCha8Rng::seed_from_u64(9);
        mutate(&mut population, &problem, 1.0, &mut rng);
        for (before, after) in original.iter().zip(&population) {
            assert!(before.iter().zip(after).filter(|(a, b)| a != b).count() <= 1);
        }
    }

    #[test]
    fn pinned_genes_never_move() {
        let mut ds = dataset(&[(1, 1, 3, 3), (2, 1, 3, 3)], |id| id);
        ds.classes[0].fixed_time_slot_id = Some(5);
        let problem = Problem::build(&ds, &config()).unwrap();
        let mut rng = ChaCha8Rng::seed_from_u64(2);
        let mut population: Vec<Chromosome> = (0..10)
            .map(|_| random_chromosome(&problem, &mut rng))
            .collect();
        for _ in 0..1000 {
            mutate(&mut population, &problem, 1.0, &mut rng);
        }
        // Slot id 5 = Tue session 1 = index 4.
        assert!(population.iter().all(|c| c[0] == 4));
    }
}
