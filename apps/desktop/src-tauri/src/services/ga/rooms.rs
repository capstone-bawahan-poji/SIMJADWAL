//! Room allocation after the GA. Rooms are not part of
//! the chromosome. First-fit as in the TA, with two orderings so large rooms are not wasted:
//! classes go from largest to smallest, rooms are tried from smallest to largest.
//! Program datasets pass their cells: a meeting may only take a room the program holds in its slot.

use std::collections::{HashMap, HashSet};

use super::model::{Cell, OccupiedRoom, Room};
use super::problem::Problem;

pub fn allocate(
    problem: &Problem,
    chromosome: &[u16],
    rooms: &[Room],
    occupied: &[OccupiedRoom],
    cells: &[Cell],
) -> Vec<Option<i64>> {
    let mut candidates: Vec<&Room> = rooms.iter().collect();
    candidates.sort_by_key(|r| (r.capacity, r.id));

    let slot_index: HashMap<i64, u16> = problem
        .slots
        .iter()
        .enumerate()
        .map(|(i, s)| (s.id, i as u16))
        .collect();
    let mut used: HashSet<(u16, i64)> = occupied
        .iter()
        .filter_map(|o| slot_index.get(&o.time_slot_id).map(|&s| (s, o.room_id)))
        .collect();
    let allowed: HashSet<(u16, i64)> = cells
        .iter()
        .filter_map(|c| slot_index.get(&c.time_slot_id).map(|&s| (s, c.room_id)))
        .collect();
    let usable = |slot: u16, room: i64| cells.is_empty() || allowed.contains(&(slot, room));

    let mut order: Vec<usize> = (0..problem.genes.len()).collect();
    // Rooms of classes that do not move are kept first, then the largest classes.
    order.sort_by_key(|&i| {
        (
            problem.genes[i].fixed_room_id.is_none(),
            std::cmp::Reverse(problem.genes[i].class_capacity),
        )
    });

    let mut assigned = vec![None; problem.genes.len()];
    for i in order {
        let gene = &problem.genes[i];
        let slot = chromosome[gene.unit];

        let fixed = gene.fixed_room_id.filter(|id| {
            rooms.iter().any(|r| r.id == *id) && !used.contains(&(slot, *id)) && usable(slot, *id)
        });
        let room = fixed.or_else(|| {
            candidates
                .iter()
                .find(|r| {
                    r.capacity >= gene.class_capacity
                        && !used.contains(&(slot, r.id))
                        && usable(slot, r.id)
                })
                .map(|r| r.id)
        });

        if let Some(id) = room {
            used.insert((slot, id));
        }
        assigned[i] = room;
    }
    assigned
}

#[cfg(test)]
mod tests {
    use super::super::problem::{fixtures::*, Problem};
    use super::*;

    fn room(id: i64, capacity: u32) -> Room {
        Room {
            id,
            code: format!("R{id}"),
            capacity,
        }
    }

    #[test]
    fn large_class_gets_the_large_room_first() {
        let mut ds = dataset(&[(1, 1, 3, 3), (2, 1, 3, 5)], |id| id);
        ds.courses[1].class_capacity = 80;
        let problem = Problem::build(&ds, &config()).unwrap();
        let rooms = vec![room(1, 100), room(2, 50)];

        assert_eq!(
            allocate(&problem, &[0, 0], &rooms, &[], &[]),
            vec![Some(2), Some(1)]
        );
    }

    #[test]
    fn occupied_and_too_small_rooms_are_skipped() {
        let problem =
            Problem::build(&dataset(&[(1, 1, 3, 3), (2, 1, 3, 5)], |id| id), &config()).unwrap();
        let rooms = vec![room(1, 40), room(2, 50)];
        // Room 2 is taken at slot id 1 by another faculty; room 1 is too small (40 < 50).
        let occupied = vec![OccupiedRoom {
            time_slot_id: 1,
            room_id: 2,
        }];

        assert_eq!(
            allocate(&problem, &[0, 1], &rooms, &occupied, &[]),
            vec![None, Some(2)]
        );
    }

    #[test]
    fn one_room_serves_one_class_per_slot() {
        let problem =
            Problem::build(&dataset(&[(1, 1, 3, 3), (2, 1, 3, 5)], |id| id), &config()).unwrap();
        let rooms = vec![room(1, 50)];

        assert_eq!(
            allocate(&problem, &[0, 0], &rooms, &[], &[]),
            vec![Some(1), None]
        );
        assert_eq!(
            allocate(&problem, &[0, 1], &rooms, &[], &[]),
            vec![Some(1), Some(1)]
        );
    }

    #[test]
    fn program_meetings_only_take_their_cells() {
        let mut ds = dataset(&[(1, 1, 3, 3), (2, 1, 3, 5)], |id| id);
        // Slot index 0 = time slot id 1: the program holds room 2 there, never room 1.
        ds.cells = vec![Cell {
            time_slot_id: 1,
            room_id: 2,
        }];
        let problem = Problem::build(&ds, &config()).unwrap();
        let rooms = vec![room(1, 50), room(2, 50)];

        assert_eq!(
            allocate(&problem, &[0, 0], &rooms, &[], &ds.cells),
            vec![Some(2), None]
        );
    }
}
