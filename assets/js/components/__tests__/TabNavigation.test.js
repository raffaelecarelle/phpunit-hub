import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TabNavigation from '../TabNavigation.vue';
import { useStore } from '../../store.js'; // Corrected path

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('TabNavigation', () => {
  let mockStore;

  beforeEach(() => {
    mockStore = {
      state: {
        activeTab: 'results',
      },
      setActiveTab: vi.fn(),
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly with "results" tab active', () => {
    const wrapper = mount(TabNavigation);

    expect(wrapper.exists()).toBe(true);
    const resultsTab = wrapper.findAll('a')[0];
    const coverageTab = wrapper.findAll('a')[1];

    expect(resultsTab.text()).toBe('Results');
    expect(resultsTab.classes()).toContain('bg-blue-600');
    expect(resultsTab.classes()).toContain('text-white');
    expect(coverageTab.text()).toBe('Coverage');
    expect(coverageTab.classes()).toContain('bg-gray-700');
    expect(coverageTab.classes()).toContain('text-gray-300');
  });

  it('renders correctly with "coverage" tab active', () => {
    mockStore.state.activeTab = 'coverage';
    const wrapper = mount(TabNavigation);

    const resultsTab = wrapper.findAll('a')[0];
    const coverageTab = wrapper.findAll('a')[1];

    expect(resultsTab.classes()).toContain('bg-gray-700');
    expect(resultsTab.classes()).toContain('text-gray-300');
    expect(coverageTab.classes()).toContain('bg-blue-600');
    expect(coverageTab.classes()).toContain('text-white');
  });

  it('calls setActiveTab with "results" when results tab is clicked', async () => {
    const wrapper = mount(TabNavigation);
    const resultsTab = wrapper.findAll('a')[0];

    await resultsTab.trigger('click');

    expect(mockStore.setActiveTab).toHaveBeenCalledTimes(1);
    expect(mockStore.setActiveTab).toHaveBeenCalledWith('results');
  });

  it('calls setActiveTab with "coverage" when coverage tab is clicked', async () => {
    const wrapper = mount(TabNavigation);
    const coverageTab = wrapper.findAll('a')[1];

    await coverageTab.trigger('click');

    expect(mockStore.setActiveTab).toHaveBeenCalledTimes(1);
    expect(mockStore.setActiveTab).toHaveBeenCalledWith('coverage');
  });
});
