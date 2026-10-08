export type User = {
    id: string;
    name: string;
    email: string;
    hospital_id: string | null;
    role: 'TRIAGE' | 'DOCTOR' | 'HOSPITAL_ADMIN' | 'SUPER_ADMIN';
    is_active: boolean;
    avatar?: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
