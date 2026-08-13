export interface User {
  id: number | null
  username: string
  email: string | null
  role: 'admin' | 'editor' | 'viewer'
}

export interface Blog {
  id: number
  title: string
  content: string
  image: string | null
  category_id: number | null
  category_name?: string
  created_at?: string
  updated_at?: string
}

export interface Category {
  id: number
  name: string
  description: string | null
  blogs?: Blog[]
}

export interface PaginatedResponse<T> {
  success: boolean
  data: T[]
  pagination: {
    total: number
    page: number
    limit: number
    pages: number
  }
}

export interface ApiResponse<T> {
  success: boolean
  data?: T
  error?: string
  message?: string
}

export interface MagicLinkRequest {
  email: string
}

export interface BlogFormData {
  title: string
  content: string
  category_id: number | null
  image?: File | null
}

export interface CategoryFormData {
  name: string
  description: string
}
