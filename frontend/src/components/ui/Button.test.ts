import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Button from './Button.vue'

describe('Button', () => {
  it('renders its slot content', () => {
    const wrapper = mount(Button, { slots: { default: 'Read the story' } })
    expect(wrapper.text()).toBe('Read the story')
  })

  it('applies the requested variant class', () => {
    const wrapper = mount(Button, { props: { variant: 'outline' }, slots: { default: 'Cancel' } })
    expect(wrapper.classes()).toContain('border-input')
  })

  it('is a native button element', () => {
    const wrapper = mount(Button, { slots: { default: 'Go' } })
    expect(wrapper.element.tagName).toBe('BUTTON')
  })
})
