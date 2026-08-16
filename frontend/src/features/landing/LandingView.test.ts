import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const apiGet = vi.fn()
const apiPost = vi.fn()

vi.mock('@/api/client', () => ({
  api: {
    get: (...args: unknown[]) => apiGet(...args),
    post: (...args: unknown[]) => apiPost(...args),
  },
}))

import LandingView from './LandingView.vue'

const sampleBlogs = {
  data: {
    data: [
      {
        id: 12,
        title: 'On Slowing Down',
        content: 'A first paragraph of the story body.',
        category_name: 'Writing',
        created_at: '2026-08-01T00:00:00Z',
        views: 42,
      },
    ],
  },
}

const sampleCategories = {
  data: {
    data: [{ id: 1, name: 'Writing' }],
  },
}

describe('LandingView', () => {
  beforeEach(() => {
    apiGet.mockReset()
    apiPost.mockReset()
    apiGet.mockResolvedValueOnce(sampleBlogs).mockResolvedValueOnce(sampleCategories)
  })

  it('renders the hero and a featured story after loading', async () => {
    const wrapper = mount(LandingView)
    await flushPromises()

    expect(wrapper.text()).toContain('A quiet place for')
    expect(wrapper.text()).toContain('On Slowing Down')
    expect(apiGet).toHaveBeenCalledWith('/api/index.php?action=blogs&limit=6')
    expect(apiGet).toHaveBeenCalledWith('/api/index.php?action=categories')
  })

  it('shows a success message after subscribing to the newsletter', async () => {
    apiPost.mockResolvedValue({ data: { success: true } })
    const wrapper = mount(LandingView)
    await flushPromises()

    await wrapper.find('input[type="email"]').setValue('reader@example.com')
    await wrapper.find('form').trigger('submit.prevent')

    expect(apiPost).toHaveBeenCalledWith('/api/index.php?action=newsletter', {
      email: 'reader@example.com',
    })
    expect(wrapper.text()).toContain("You're on the list")
  })

  it('shows an error message when the subscription fails', async () => {
    apiPost.mockRejectedValue(new Error('boom'))
    const wrapper = mount(LandingView)
    await flushPromises()

    await wrapper.find('input[type="email"]').setValue('reader@example.com')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('That email could not be subscribed')
  })
})
