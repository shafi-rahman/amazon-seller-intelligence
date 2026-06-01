export interface User {
    id: number
    name: string
    email: string
    role: string
    email_verified_at: string | null
    created_at: string
    workspaces?: Workspace[]
}

export interface Workspace {
    id: number
    name: string
    slug: string
    marketplace: string
    currency: string
    settings: Record<string, unknown>
    created_at: string
    members?: WorkspaceMember[]
    role?: string
}

export interface WorkspaceMember {
    user_id: number
    name: string
    email: string
    role: string
}

export interface ApiError {
    message: string
    errors?: Record<string, string[]>
}

export interface ImportBatch {
    id: number
    type: string
    original_filename: string
    status: string
    total_rows: number
    processed_rows: number
    failed_rows: number
    percent: number
    started_at: string | null
    completed_at: string | null
    created_at: string
}

export interface ImportStatus {
    id: number
    type: string
    status: string
    total_rows: number
    processed_rows: number
    failed_rows: number
    percent: number
    started_at: string | null
    completed_at: string | null
    meta: Record<string, unknown>
}

export interface PaginatedResponse<T> {
    data: T[]
    meta: {
        page: number
        per_page: number
        total: number
        last_page: number
    }
}
