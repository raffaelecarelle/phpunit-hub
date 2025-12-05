import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TestSuiteHeader from '../sidebar/TestSuiteHeader.vue';
import { useStore } from '../../store.js'; // Adjust path as necessary

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
    mockIsTestRunning.mockReturnValue(true);
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: { ...mockSuite, runId: 'run123' },
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    expect(wrapper.find('.spinner').exists()).toBe(true);
    expect(wrapper.find('svg[d="M6 6h8v8H6z"]').exists()).toBe(true); // Stop icon
    expect(wrapper.find('svg[d^="M6.3 2.841A1.5"]').exists()).toBe(false); // Play icon
  });

  it('shows run button when suite is not running and not stop pending', () => {
    mockIsTestRunning.mockReturnValue(false);
    mockIsTestStopPending.mockReturnValue(false);
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: { ...mockSuite, runId: null },
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    expect(wrapper.find('.spinner').exists()).toBe(false);
    expect(wrapper.find('svg[d="M6 6h8v8H6z"]').exists()).toBe(false); // Stop icon
    expect(wrapper.find('svg[d^="M6.3 2.841A1.5"]').exists()).toBe(true); // Play icon
    expect(wrapper.find('svg[d^="M6.3 2.841A1.5"]').parent('span').classes()).toContain('text-green-500');
  });

  it('run button is disabled (gray) when suite is stop pending', () => {
    mockIsTestRunning.mockReturnValue(false);
    mockIsTestStopPending.mockReturnValue(true);
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: { ...mockSuite, runId: null },
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    expect(wrapper.find('svg[d^="M6.3 2.841A1.5"]').parent('span').classes()).toContain('text-gray-500');
  });

  it('emits "toggle-suite" when suite header is clicked', async () => {
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    await wrapper.find('.suite-header > div:first-child').trigger('click');
    expect(wrapper.emitted()['toggle-suite']).toBeTruthy();
    expect(wrapper.emitted()['toggle-suite'][0][0]).toBe('suite1');
  });

  it('emits "stopSingleTest" when stop button is clicked', async () => {
    mockIsTestRunning.mockReturnValue(true);
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: { ...mockSuite, runId: 'run123' },
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    await wrapper.find('svg[d="M6 6h8v8H6z"]').parent('span').trigger('click');
    expect(wrapper.emitted().stopSingleTest).toBeTruthy();
    expect(wrapper.emitted().stopSingleTest[0][0]).toBe('run123');
  });

  it('emits "runSuiteTests" when run button is clicked', async () => {
    mockIsTestRunning.mockReturnValue(false);
    mockIsTestStopPending.mockReturnValue(false);
    const wrapper = mount(TestSuiteHeader, {
      props: {
        suite: { ...mockSuite, runId: null },
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    await wrapper.find('svg[d^="M6.3 2.841A1.5"]').parent('span').trigger('click');
    expect(wrapper.emitted().runSuiteTests).toBeTruthy();
    expect(wrapper.emitted().runSuiteTests[0][0]).toBe('suite1');
  });
});
