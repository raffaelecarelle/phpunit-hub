import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import FilterPanelButton from '../header/filter/FilterPanelButton.vue';

describe('FilterPanelButton', () => {
  it('renders correctly', () => {
    const wrapper = mount(FilterPanelButton);
    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('button').text()).toBe('Filters / Settings');
  });

  it('emits "toggle" event when clicked', async () => {
    const wrapper = mount(FilterPanelButton);
    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted().toggle).toBeTruthy();
    expect(wrapper.emitted().toggle.length).toBe(1);
  });
});
