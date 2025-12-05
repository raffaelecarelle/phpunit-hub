import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import FilterPanelContent from '../header/filter/FilterPanelContent.vue';
import { useStore } from '../../store.js'; // Corrected path

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('FilterPanelContent', () => {
  let mockStore;

  beforeEach(() => {
    mockStore = {
      state: {
        selectedGroups: [],
        availableGroups: { 'group1': 'Group 1' },
        selectedSuites: [],
        availableSuites: { 'suite1': 'Suite 1' },
        options: {
          displayMode: 'default',
          displayWarnings: false,
          displayDeprecations: false,
          displayNotices: false,
          displaySkipped: false,
          displayIncomplete: false,
          displayRisky: false,
          stopOnDefect: false,
          stopOnError: false,
          stopOnFailure: false,
          stopOnWarning: false,
          stopOnRisky: false,
        },
        coverage: false, // Initial state for coverage
      },
      clearFilters: vi.fn(),
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly', () => {
    const wrapper = mount(FilterPanelContent);
    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('h3').text()).toBe('Filters & Settings');
  });

  it('clears filters when "Clear Filters" button is clicked', async () => {
    const wrapper = mount(FilterPanelContent);
    await wrapper.find('button').trigger('click');
    expect(mockStore.clearFilters).toHaveBeenCalled();
  });

  it('updates selectedGroups when group filter changes', async () => {
    const wrapper = mount(FilterPanelContent);
    const select = wrapper.find('#group-filter');
    await select.setValue(['group1']);
    expect(mockStore.state.selectedGroups).toEqual(['group1']);
  });

  it('updates selectedSuites when suite filter changes', async () => {
    const wrapper = mount(FilterPanelContent);
    const select = wrapper.find('#filter-suite');
    await select.setValue(['suite1']);
    expect(mockStore.state.selectedSuites).toEqual(['suite1']);
  });

  it('updates displayMode when radio button changes', async () => {
    const wrapper = mount(FilterPanelContent);
    const radio = wrapper.find('input[type="radio"][value="individual"]');
    await radio.setValue('individual');
    expect(mockStore.state.options.displayMode).toBe('individual');
  });

  it('toggles displayWarnings checkbox', async () => {
    const wrapper = mount(FilterPanelContent);
    // Find the label containing the text "Show Warnings"
    const warningsLabel = wrapper.findAll('label').filter(label => label.text().includes('Show Warnings'))[0];

    // Ensure the label was found
    expect(warningsLabel.exists()).toBe(true);

    // Find the checkbox inside this label
    const checkbox = warningsLabel.find('input[type="checkbox"]');

    expect(checkbox.exists()).toBe(true); // Ensure the checkbox is found
    expect(checkbox.element.checked).toBe(false); // Initial state from mockStore

    // Simulate checking
    await checkbox.setValue(true);
    expect(mockStore.state.options.displayWarnings).toBe(true);
    expect(checkbox.element.checked).toBe(true);

    // Simulate unchecking
    await checkbox.setValue(false);
    expect(mockStore.state.options.displayWarnings).toBe(false);
    expect(checkbox.element.checked).toBe(false);
  });

  it('toggles coverage checkbox', async () => {
    const wrapper = mount(FilterPanelContent);

    // Find the label containing the text "Run with Code Coverage"
    const coverageLabel = wrapper.findAll('label').filter(label => label.text().includes('Run with Code Coverage'))[0];

    // Ensure the label was found
    expect(coverageLabel.exists()).toBe(true);

    // Find the checkbox inside this label
    const checkbox = coverageLabel.find('input[type="checkbox"]');

    expect(checkbox.exists()).toBe(true); // Ensure the checkbox is found
    expect(checkbox.element.checked).toBe(false); // Initial state from mockStore

    // Simulate checking
    await checkbox.setValue(true);
    expect(mockStore.state.coverage).toBe(true);
    expect(checkbox.element.checked).toBe(true);

    // Simulate unchecking
    await checkbox.setValue(false);
    expect(mockStore.state.coverage).toBe(false);
    expect(checkbox.element.checked).toBe(false);
  });
});
