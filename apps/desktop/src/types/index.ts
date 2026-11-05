export type Role = 'superadmin' | 'admin_fakultas' | 'admin_tpb' | 'admin_prodi' | 'dosen' | 'mahasiswa';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: Role | null;
  faculty_id: number | null;
  faculty: { id: number; code: string; name: string } | null;
  is_active: boolean;
}