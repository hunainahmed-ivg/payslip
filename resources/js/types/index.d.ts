export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role?: string;
    is_admin?: boolean;
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
