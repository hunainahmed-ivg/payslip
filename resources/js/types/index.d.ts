export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role?: string;
    role_label?: string;
    is_admin?: boolean;
    is_platform_operator?: boolean;
    company_id?: number | null;
    permissions?: string[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    companyName?: string;
    currentCompany?: { id: number; company_name: string } | null;
    companies?: Array<{ id: number; company_name: string }>;
    pendingStampedCount?: number;
};
