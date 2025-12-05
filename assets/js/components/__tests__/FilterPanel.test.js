import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import FilterPanel from '../header/FilterPanel.vue';
import { useStore } from '../../store.js'; // Adjust path as necessary

// Mock child components
vi.mock('../header/filter/FilterPanelButton.vue', () => ({
  default: {
    name: 'FilterPanelButton',
    template: '<button class="mock-filter-panel-button" @click="$emit(\'toggle\')"></button>',
  },
}));
vi.mock('../header/filter/FilterPanelContent.vue', () => ({
  default: {
    name: 'FilterPanelContent',
    template: '<div class="mock-filter-panel-content"></div>',
  },
}));

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('FilterPanel', () => {
  let mockStore;

  beforeEach(() => {
    mockStore = {
      state: {
        showFilterPanel: false,
      },
      toggleFilterPanel: vi.fn(),
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly', () => {
    const wrapper = mount(FilterPanel);
    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.mock-filter-panel-button').exists()).toBe(true);
    expect(wrapper.find('.mock-filter-panel-content').exists()).toBe(true);
  });

  it('FilterPanelContent is hidden by default', () => {
    const wrapper = mount(FilterPanel);
    expect(wrapper.find('.mock-filter-panel-content').isVisible()).toBe(false);
  });

  it('FilterPanelContent is visible when store.state.showFilterPanel is true', async () => {
    mockStore.state.showFilterPanel = true;
    const wrapper = mount(FilterPanel);
    await wrapper.vm.$nextTick(); // Wait for reactivity
    expect(wrapper.find('.mock-filter-panel-content').isVisible()).toBe(true);
  });

  it('calls store.toggleFilterPanel when FilterPanelButton emits toggle', async () => {
    const wrapper = mount(FilterPanel);
    const filterPanelButton = wrapper.findComponent({ name: 'FilterPanelButton' });
    await filterPanelButton.vm.$emit('toggle');
    expect(mockStore.toggleFilterPanel).toHaveBeenCalledTimes(1);
  });
});
