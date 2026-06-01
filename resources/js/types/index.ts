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

export interface PaginatedResponse<T> {
    data: T[]
    meta: {
        page: number
        per_page: number
        total: number
        last_page: number
    }
}
