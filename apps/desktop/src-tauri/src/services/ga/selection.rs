//! Parent selection. Both operators fill the new population by repeated
//! tournaments over the same population, with replacement.

use rand::Rng;

use super::model::SelectionOperator;

pub fn select<R: Rng>(
    operator: SelectionOperator,
    fitness: &[f64],
    roulette: &Roulette,
    tournament_size: usize,
    rng: &mut R,
) -> usize {
    let k = tournament_size.max(2);
    let mut best = pick(operator, fitness.len(), roulette, rng);
    for _ in 1..k {
        let challenger = pick(operator, fitness.len(), roulette, rng);
        if fitness[challenger] > fitness[best] {
            best = challenger;
        }
    }
    best
}

fn pick<R: Rng>(
    operator: SelectionOperator,
    len: usize,
    roulette: &Roulette,
    rng: &mut R,
) -> usize {
    match operator {
        SelectionOperator::TS => rng.random_range(0..len),
        SelectionOperator::TSR => roulette.spin(rng),
    }
}

#[derive(Debug, Clone)]
pub struct Roulette {
    cumulative: Vec<f64>,
}

impl Roulette {
    pub fn new(fitness: &[f64]) -> Self {
        let total: f64 = fitness.iter().map(|f| f.max(0.0)).sum();
        let mut acc = 0.0;
        let cumulative = fitness
            .iter()
            .map(|f| {
                acc += if total > 0.0 {
                    f.max(0.0) / total
                } else {
                    1.0 / fitness.len() as f64
                };
                acc
            })
            .collect();
        Self { cumulative }
    }

    pub fn spin<R: Rng>(&self, rng: &mut R) -> usize {
        let r: f64 = rng.random();
        let i = self.cumulative.partition_point(|&c| c < r);
        i.min(self.cumulative.len() - 1)
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use rand::SeedableRng;
    use rand_chacha::ChaCha8Rng;

    const FITNESS: [f64; 5] = [450.0, 230.0, 100.0, 160.0, 310.0];

    #[test]
    fn roulette_ranges_match_laporan_ta_table_4_18() {
        let r = Roulette::new(&FITNESS);
        let rounded: Vec<f64> = r
            .cumulative
            .iter()
            .map(|c| (c * 100.0).round() / 100.0)
            .collect();
        assert_eq!(rounded, vec![0.36, 0.54, 0.62, 0.75, 1.0]);
    }

    #[test]
    fn roulette_spin_lands_in_its_cumulative_range() {
        let r = Roulette::new(&FITNESS);
        assert_eq!(r.cumulative.partition_point(|&c| c < 0.61), 2);
        assert_eq!(r.cumulative.partition_point(|&c| c < 0.83), 4);
    }

    #[test]
    fn the_weakest_chromosome_wins_only_against_itself() {
        let roulette = Roulette::new(&FITNESS);
        let mut rng = ChaCha8Rng::seed_from_u64(1);
        let wins = (0..10_000)
            .filter(|_| select(SelectionOperator::TS, &FITNESS, &roulette, 2, &mut rng) == 2)
            .count();
        assert!((250..550).contains(&wins), "{wins}");
    }

    #[test]
    fn tsr_favours_fitter_chromosomes_more_than_ts() {
        let roulette = Roulette::new(&FITNESS);
        let mut rng = ChaCha8Rng::seed_from_u64(7);
        let count_best = |op, rng: &mut ChaCha8Rng| {
            (0..5000)
                .filter(|_| select(op, &FITNESS, &roulette, 2, rng) == 0)
                .count()
        };
        let ts = count_best(SelectionOperator::TS, &mut rng);
        let tsr = count_best(SelectionOperator::TSR, &mut rng);
        assert!(tsr > ts, "TSR {tsr} vs TS {ts}");
    }

    #[test]
    fn negative_or_zero_fitness_does_not_break_the_wheel() {
        let r = Roulette::new(&[0.0, -5.0, 0.0]);
        let mut rng = ChaCha8Rng::seed_from_u64(3);
        for _ in 0..100 {
            assert!(r.spin(&mut rng) < 3);
        }
    }
}
