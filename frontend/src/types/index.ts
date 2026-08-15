export interface Blog {
  id: number
  title: string
  content?: string
  excerpt?: string
  word_count?: number
  image: string | null
  category_id: number | null
  category_name?: string
  created_at?: string
  updated_at?: string
  views?: number
}

export interface Category {
  id: number
  name: string
  description: string | null
  blogs?: Blog[]
}