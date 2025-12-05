import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import Header from '../Header.vue';

// Mock child components
vi.mock('../header/HeaderTitle.vue', () => ({
  default: {
    name: 'HeaderTitle',
    template: '<div class="mock-header-title"></div>',
  },
}));
vi.mock('../header/FilterPanel.vue', () => ({
  default: {
    name: 'FilterPanel',
    template: '<div class="mock-filter-panel"></div>',
  },
}));
vi.mock('../header/ClearResultsButton.vue', () => ({
  default: {
    name: 'ClearResultsButton',
    props: ['isAnyTestRunning', 'results'], // Define expected props
    template: '<button class="mock-clear-results-button" @click="$emit(\'clearAllResults\')"></button>', // Added @click to emit
  },
}));
vi.mock('../header/RunFailedButton.vue', () => ({
  default: {
    name: 'RunFailedButton',
    props: ['isAnyTestRunning', 'hasFailedTests'], // Define expected props
    template: '<button class="mock-run-failed-button" @click="$emit(\'runFailedTests\')"></button>', // Added @click to emit
  },
}));
vi.mock('../header/RunStopAllButton.vue', () => ({
  default: {
    name: 'RunStopAllButton',
    props: ['isAnyTestRunning', 'isAnyStopPending'], // Define expected props
    template: '<button class="mock-run-stop-all-button" @click="$emit(\'togglePlayStop\')"></button>', // Added @click to emit
  },
}));

describe('Header', () => {
  it('renders correctly and passes props to child components', () => {
    const wrapper = mount(Header, {
      props: {
        isAnyTestRunning: false,
        hasFailedTests: true,
        isAnyStopPending: false,
        results: [],
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.mock-header-title').exists()).toBe(true);
    expect(wrapper.find('.mock-filter-panel').exists()).toBe(true);
    expect(wrapper.find('.mock-clear-results-button').exists()).toBe(true);
    expect(wrapper.find('.mock-run-failed-button').exists()).toBe(true);
    expect(wrapper.find('.mock-run-stop-all-button').exists()).toBe(true);

    // Check if props are passed to ClearResultsButton
    const clearResultsButton = wrapper.findComponent({ name: 'ClearResultsButton' });
    expect(clearResultsButton.props('isAnyTestRunning')).toBe(false);
    expect(clearResultsButton.props('results')).toEqual([]);

    // Check if props are passed to RunFailedButton
    const runFailedButton = wrapper.findComponent({ name: 'RunFailedButton' });
    expect(runFailedButton.props('isAnyTestRunning')).toBe(false);
    expect(runFailedButton.props('hasFailedTests')).toBe(true);

    // Check if props are passed to RunStopAllButton
    const runStopAllButton = wrapper.findComponent({ name: 'RunStopAllButton' });
    expect(runStopAllButton.props('isAnyTestRunning')).toBe(false);
    expect(runStopAllButton.props('isAnyStopPending')).toBe(false);
  });

  it('emits clearAllResults when ClearResultsButton emits clearAllResults', async () => {
    const wrapper = mount(Header, {
      props: {
        isAnyTestRunning: false,
        hasFailedTests: true,
        isAnyStopPending: false,
        results: [],
      },
    });

    const clearResultsButton = wrapper.findComponent({ name: 'ClearResultsButton' });
    await clearResultsButton.trigger('click'); // Trigger the click on the button in the mock
    // Now check if the parent component (Header) emitted the event
    expect(wrapper.emitted().clearAllResults).toBeTruthy();
    expect(wrapper.emitted().clearAllResults.length).toBe(1);
  });

  it('emits runFailedTests when RunFailedButton emits runFailedTests', async () => {
    const wrapper = mount(Header, {
      props: {
        isAnyTestRunning: false,
        hasFailedTests: true,
        isAnyStopPending: false,
        results: [],
      },
    });

    const runFailedButton = wrapper.findComponent({ name: 'RunFailedButton' });
    await runFailedButton.trigger('click'); // Trigger the click on the button in the mock
    expect(wrapper.emitted().runFailedTests).toBeTruthy();
    expect(wrapper.emitted().runFailedTests.length).toBe(1);
  });

  it('emits togglePlayStop when RunStopAllButton emits togglePlayStop', async () => {
    const wrapper = mount(Header, {
      props: {
        isAnyTestRunning: false,
        hasFailedTests: true,
        isAnyStopPending: false,
        results: [],
      },
    });

    const runStopAllButton = wrapper.findComponent({ name: 'RunStopAllButton' });
    await runStopAllButton.trigger('click'); // Trigger the click on the button in the mock
    expect(wrapper.emitted().togglePlayStop).toBeTruthy();
    expect(wrapper.emitted().togglePlayStop.length).toBe(1);
  });
});
