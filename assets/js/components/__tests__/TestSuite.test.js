import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TestSuite from '../sidebar/TestSuite.vue';
import { useStore } from '../../store.js'; // Corrected path

// Mock child components
vi.mock('../sidebar/TestSuiteHeader.vue', () => ({
  default: {
    name: 'TestSuiteHeader',
    props: ['suite', 'isTestRunning', 'isTestStopPending'],
    template: '<div class="mock-test-suite-header" @toggle-suite="$emit(\'toggle-suite\', suite.id)" @stopSingleTest="$emit(\'stopSingleTest\', $event)" @runSuiteTests="$emit(\'runSuiteTests\', $event)"></div>',
  },
}));
vi.mock('../sidebar/TestList.vue', () => ({
  default: {
    name: 'TestList',
    props: ['suite', 'isTestRunning'],
    template: '<ul class="mock-test-list" @runSingleTest="$emit(\'runSingleTest\', $event)"></ul>',
  },
}));

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('TestSuite', () => {
  let mockStore;
  const mockIsTestRunning = vi.fn();
  const mockIsTestStopPending = vi.fn();
  const mockSuite = { id: 'suite1', name: 'MySuite', methods: [] };

  beforeEach(() => {
    mockStore = {
      state: {
        expandedSuites: new Set(),
      },
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders TestSuiteHeader and TestList components', () => {
    const wrapper = mount(TestSuite, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.mock-test-suite-header').exists()).toBe(true);
    expect(wrapper.find('.mock-test-list').exists()).toBe(true);
  });

  it('passes correct props to TestSuiteHeader', () => {
    const wrapper = mount(TestSuite, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    const header = wrapper.findComponent({ name: 'TestSuiteHeader' });
    expect(header.props('suite')).toEqual(mockSuite);
    expect(header.props('isTestRunning')).toBe(mockIsTestRunning);
    expect(header.props('isTestStopPending')).toBe(mockIsTestStopPending);
  });

  it('passes correct props to TestList', () => {
    const wrapper = mount(TestSuite, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    const list = wrapper.findComponent({ name: 'TestList' });
    expect(list.props('suite')).toEqual(mockSuite);
    expect(list.props('isTestRunning')).toBe(mockIsTestRunning);
  });

  it('TestList is hidden by default if suite is not expanded', () => {
    const wrapper = mount(TestSuite, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    expect(wrapper.find('.mock-test-list').isVisible()).toBe(false);
  });

  it('TestList is visible if suite is expanded', async () => {
    mockStore.state.expandedSuites.add('suite1');
    const wrapper = mount(TestSuite, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });
    await wrapper.vm.$nextTick(); // Wait for reactivity

    expect(wrapper.find('.mock-test-list').isVisible()).toBe(true);
  });

  it('re-emits "toggle-suite" from TestSuiteHeader', async () => {
    const wrapper = mount(TestSuite, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    const header = wrapper.findComponent({ name: 'TestSuiteHeader' });
    await header.vm.$emit('toggle-suite', 'suite1');

    expect(wrapper.emitted()['toggle-suite']).toBeTruthy();
    expect(wrapper.emitted()['toggle-suite'][0][0]).toBe('suite1');
  });

  it('re-emits "stopSingleTest" from TestSuiteHeader', async () => {
    const wrapper = mount(TestSuite, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    const header = wrapper.findComponent({ name: 'TestSuiteHeader' });
    await header.vm.$emit('stopSingleTest', 'runId123');

    expect(wrapper.emitted().stopSingleTest).toBeTruthy();
    expect(wrapper.emitted().stopSingleTest[0][0]).toBe('runId123');
  });

  it('re-emits "runSuiteTests" from TestSuiteHeader', async () => {
    const wrapper = mount(TestSuite, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });

    const header = wrapper.findComponent({ name: 'TestSuiteHeader' });
    await header.vm.$emit('runSuiteTests', 'suite1');

    expect(wrapper.emitted().runSuiteTests).toBeTruthy();
    expect(wrapper.emitted().runSuiteTests[0][0]).toBe('suite1');
  });

  it('re-emits "runSingleTest" from TestList', async () => {
    mockStore.state.expandedSuites.add('suite1'); // Make TestList visible
    const wrapper = mount(TestSuite, {
      props: {
        suite: mockSuite,
        isTestRunning: mockIsTestRunning,
        isTestStopPending: mockIsTestStopPending,
      },
    });
    await wrapper.vm.$nextTick();

    const list = wrapper.findComponent({ name: 'TestList' });
    await list.vm.$emit('runSingleTest', 'testId456');

    expect(wrapper.emitted().runSingleTest).toBeTruthy();
    expect(wrapper.emitted().runSingleTest[0][0]).toBe('testId456');
  });
});
