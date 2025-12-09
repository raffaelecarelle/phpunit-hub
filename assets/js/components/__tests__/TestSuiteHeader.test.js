import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TestSuiteHeader from '../sidebar/TestSuiteHeader.vue';
import { useStore } from '../../store.js';

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('TestSuiteHeader', () => {
  let mockStore;
  const mockIsTestRunning = vi.fn();
  const mockIsTestStopPending = vi.fn();
  const mockSuite = { id: 'suite1', name: 'App\\Tests\\MySuite' };

  beforeEach(() => {
    mockStore = {
      state: {
        expandedSuites: new Set(),
      },
    };
    useStore.mockReturnValue(mockStore);
    mockIsTestRunning.mockClear();
    mockIsTestStopPending.mockClear();
  });

  it('renders correctly with suite name', () => {
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.font-bold').text()).toBe('App\\Tests\\MySuite');
  });

  it('applies "rotated" class to arrow when suite is expanded', () => {
    mockStore.state.expandedSuites.add('suite1');
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    expect(wrapper.find('.suite-arrow').classes()).toContain('rotated');
  });

  it('does not apply "rotated" class to arrow when suite is not expanded', () => {
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    expect(wrapper.find('.suite-arrow').classes()).not.toContain('rotated');
  });

  it('shows spinner and stop button when suite is running', () => {
    const suite = { ...mockSuite, runId: 'run123', isRunning: true };
    mockIsTestRunning.mockImplementation((s) => s.id === suite.id);
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: suite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    expect(wrapper.find('.spinner').exists()).toBe(true);
    // Check for the stop button by its title
    expect(wrapper.find('span[title="Stop this suite"]').exists()).toBe(true);
    // Check that the play button is not present
    expect(wrapper.find('span[title="Run all tests in this suite"]').exists()).toBe(false);
  });

  it('shows run button when suite is not running and not stop pending', () => {
    const suite = { ...mockSuite, runId: null };
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: suite,
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    expect(wrapper.find('.spinner').exists()).toBe(false);
    // Check that the stop button is not present
    expect(wrapper.find('span[title="Stop this suite"]').exists()).toBe(false);
    // Check for the play button by its title
    expect(wrapper.find('span[title="Run all tests in this suite"]').exists()).toBe(true);
    expect(wrapper.find('span[title="Run all tests in this suite"]').classes()).toContain('text-green-500');
  });

  it('run button is disabled (gray) when suite is stop pending', () => {
    const suite = { ...mockSuite, runId: null };
    mockIsTestRunning.mockReturnValue(false);
    mockIsTestStopPending.mockImplementation((s) => s.id === suite.id);
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: suite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    // Find the play button and check its parent span's classes
    expect(wrapper.find('span[title="Stopping..."]').classes()).toContain('text-gray-500');
  });

  it('emits "toggle-suite" when suite header is clicked', async () => {
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    // Find the clickable div directly using its classes
    const clickableDiv = wrapper.find('.flex.items-center.flex-grow.cursor-pointer');
    expect(clickableDiv.exists()).toBe(true); // Ensure the div is found

    await clickableDiv.trigger('click');
    expect(wrapper.emitted()['toggle-suite']).toBeTruthy();
    expect(wrapper.emitted()['toggle-suite'][0][0]).toBe('suite1');
  });

  it('emits "stopSingleTest" when stop button is clicked', async () => {
    const suite = { ...mockSuite, isRunning: true };
    mockIsTestRunning.mockImplementation((s) => s.id === suite.id);
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: suite,
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    // Find the stop button by its title and trigger click
    await wrapper.find('span[title="Stop this suite"]').trigger('click');
    // Assert on emitted event for TestSuiteHeader.vue
    expect(wrapper.emitted().stopSingleTest).toBeTruthy();
  });

  it('emits "runSuiteTests" when run button is clicked', async () => {
    const suite = { ...mockSuite, runId: null };
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: suite,
        isTestRunning: false,
        isTestStopPending: false,
      },
    });

    // Find the run button by its title and trigger click
    await wrapper.find('span[title="Run all tests in this suite"]').trigger('click');
    // Assert on emitted event for TestSuiteHeader.vue
    expect(wrapper.emitted().runSuiteTests).toBeTruthy();
    expect(wrapper.emitted().runSuiteTests[0][0]).toBe('suite1');
  });
});
