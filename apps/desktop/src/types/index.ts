export type Role = 'superadmin' | 'admin_fakultas' | 'admin_tpb' | 'admin_prodi' | 'dosen' | 'mahasiswa';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: Role | null;
  faculty: { id: number; name: string } | null;
}

export interface AuthToken {
  token: string;
  user: AuthUser;
}