import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TestSidebar from '../TestSidebar.vue';
import { useStore } from '../../store.js'; // Corrected path
import { ref } from 'vue';

// Mock child components
vi.mock('../sidebar/TestSearchBar.vue', () => ({
  default: {
    name: 'TestSearchBar',
    template: '<div class="mock-test-search-bar" @update:filtered-suites="$emit(\'update:filtered-suites\', $event)"></div>',
  },
}));
vi.mock('../sidebar/TestSuite.vue', () => ({
  default: {
    name: 'TestSuite',
    template: '<div class="mock-test-suite"></div>',
  },
}));

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('TestSidebar', () => {
  let mockStore;

  beforeEach(() => {
    mockStore = {
      state: {
        isLoading: false,
        testSuites: [],
      },
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly when not loading and no tests found', () => {
    const wrapper = mount(TestSidebar, {
      props: {
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.mock-test-search-bar').exists()).toBe(true);
    expect(wrapper.text()).toContain('No tests found.');
    expect(wrapper.find('.mock-test-suite').exists()).toBe(false);
    expect(wrapper.find('.spinner-big').exists()).toBe(false);
  });

  it('shows spinner when loading', () => {
    mockStore.state.isLoading = true;
    const wrapper = mount(TestSidebar, {
      props: {
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    expect(wrapper.find('.spinner-big').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('No tests found.');
    expect(wrapper.find('.mock-test-suite').exists()).toBe(false);
  });

  it('renders TestSuite components when tests are present and not loading', async () => {
    mockStore.state.testSuites = [{ id: 'suite1', name: 'Suite 1' }];
    const wrapper = mount(TestSidebar, {
      props: {
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    // Wait for the component to re-render after store state change
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.mock-test-suite').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('No tests found.');
    expect(wrapper.find('.spinner-big').exists()).toBe(false);
  });

  it('updates suitesToDisplay when TestSearchBar emits update:filtered-suites', async () => {
    // Initial state: no test suites in the store
    mockStore.state.testSuites = []; // Ensure it's empty initially

    const wrapper = mount(TestSidebar, {
      props: {
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    // Initially, no TestSuite components should be rendered
    expect(wrapper.findAll('.mock-test-suite').length).toBe(0);
    expect(wrapper.text()).toContain('No tests found.');

    const testSearchBar = wrapper.findComponent({ name: 'TestSearchBar' });
    const filteredSuites = [{ id: 'filtered1', name: 'Filtered Suite' }];

    // Emit the event from the mock TestSearchBar
    await testSearchBar.vm.$emit('update:filtered-suites', filteredSuites);

    // Wait for Vue to react to the change in suitesToDisplay
    await wrapper.vm.$nextTick();

    // Now, a TestSuite component should be rendered for each filtered suite
    expect(wrapper.findAll('.mock-test-suite').length).toBe(filteredSuites.length);
    expect(wrapper.text()).not.toContain('No tests found.'); // The message should disappear
  });

  it('emits toggle-suite when TestSuite emits toggle-suite', async () => {
    mockStore.state.testSuites = [{ id: 'suite1', name: 'Suite 1' }];
    const wrapper = mount(TestSidebar, {
      props: {
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    await wrapper.vm.$nextTick();
    const testSuite = wrapper.findComponent({ name: 'TestSuite' });
    await testSuite.vm.$emit('toggle-suite', 'suite1');

    expect(wrapper.emitted()['toggle-suite']).toBeTruthy();
    expect(wrapper.emitted()['toggle-suite'][0][0]).toBe('suite1');
  });

  it('emits stopSingleTest when TestSuite emits stopSingleTest', async () => {
    mockStore.state.testSuites = [{ id: 'suite1', name: 'Suite 1' }];
    const wrapper = mount(TestSidebar, {
      props: {
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    await wrapper.vm.$nextTick();
    const testSuite = wrapper.findComponent({ name: 'TestSuite' });
    await testSuite.vm.$emit('stopSingleTest', 'runId123');

    expect(wrapper.emitted().stopSingleTest).toBeTruthy();
    expect(wrapper.emitted().stopSingleTest[0][0]).toBe('runId123');
  });

  it('emits runSuiteTests when TestSuite emits runSuiteTests', async () => {
    mockStore.state.testSuites = [{ id: 'suite1', name: 'Suite 1' }];
    const wrapper = mount(TestSidebar, {
      props: {
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    await wrapper.vm.$nextTick();
    const testSuite = wrapper.findComponent({ name: 'TestSuite' });
    await testSuite.vm.$emit('runSuiteTests', 'suite1');

    expect(wrapper.emitted().runSuiteTests).toBeTruthy();
    expect(wrapper.emitted().runSuiteTests[0][0]).toBe('suite1');
  });

  it('emits runSingleTest when TestSuite emits runSingleTest', async () => {
    mockStore.state.testSuites = [{ id: 'suite1', name: 'Suite 1' }];
    const wrapper = mount(TestSidebar, {
      props: {
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    await wrapper.vm.$nextTick();
    const testSuite = wrapper.findComponent({ name: 'TestSuite' });
    await testSuite.vm.$emit('runSingleTest', 'testId456');

    expect(wrapper.emitted().runSingleTest).toBeTruthy();
    expect(wrapper.emitted().runSingleTest[0][0]).toBe('testId456');
  });
});
