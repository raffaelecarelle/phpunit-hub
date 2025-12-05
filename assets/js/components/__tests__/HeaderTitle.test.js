import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import HeaderTitle from '../header/HeaderTitle.vue';

describe('HeaderTitle', () => {
  it('renders the correct title', () => {
    const wrapper = mount(HeaderTitle);
    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('h1').exists()).toBe(true);
    expect(wrapper.find('h1').text()).toBe('PHPUnit Hub');
    expect(wrapper.find('h1').classes()).toContain('text-2xl');
    expect(wrapper.find('h1').classes()).toContain('font-bold');
    expect(wrapper.find('h1').classes()).toContain('text-white');
  });
});
