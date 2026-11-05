/** Shapes of GET /api/v1/scheduling/dataset */

export type ConstraintStatus = 'draft' | 'submitted' | 'accepted';

export interface DatasetStudyProgram {
  id: number;
  code: string;
  name: string;
  constraint_status: ConstraintStatus;
  constraint_reviewed_at: string | null;
  lecturers_count: number;
  courses_count: number;
  included: boolean;
}

export interface DatasetTimeSlot {
  id: number;
  day: number;
  session: number;
  start_time: string;
  end_time: string;
  type: 2 | 3;
}

export interface DatasetRoom {
  id: number;
  code: string;
  name: string | null;
  capacity: number;
  faculty_id: number | null;
}

export interface DatasetCourse {
  id: number;
  study_program_id: number;
  code: string;
  name: string;
  sks: 2 | 3 | 4;
  semester: number;
  parallel_class_count: number;
  class_capacity: number;
  is_tpb: boolean;
}

export interface DatasetClass {
  course_id: number;
  class_number: number;
  lecturer_id: number;
  fixed_room_id: number | null;
}

export interface DatasetPreference {
  time_slot_id: number;
  type: 'want' | 'avoid';
}

export interface DatasetLecturer {
  id: number;
  nip: string;
  name: string;
  title: string | null;
  study_program_id: number;
  preferences: DatasetPreference[];
}

export interface DatasetConstraintType {
  code: string;
  category: 'HC' | 'SC';
  description: string;
  weight: number;
}

export interface SchedulingDataset {
  fingerprint: string;
  generated_at: string;
  period: string;
  faculty: { id: number; code: string; name: string };
  study_programs: DatasetStudyProgram[];
  time_slots: DatasetTimeSlot[];
  rooms: DatasetRoom[];
  courses: DatasetCourse[];
  classes: DatasetClass[];
  lecturers: DatasetLecturer[];
  constraint_types: DatasetConstraintType[];
  occupied_rooms: { time_slot_id: number; room_id: number }[];
}

export interface FacultyOption {
  id: number;
  code: string;
  name: string;
}
