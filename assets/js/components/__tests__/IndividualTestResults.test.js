import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import IndividualTestResults from '../IndividualTestResults.vue';
import { useStore } from '../../store.js'; // Corrected path

// Mock child components
vi.mock('../TestDetails.vue', () => ({
  default: {
    name: 'TestDetails',
    template: '<div class="mock-test-details"></div>',
  },
}));

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('IndividualTestResults', () => {
  let mockStore;
  const mockFormatNanoseconds = vi.fn((duration) => {
    if (duration === undefined || duration === null) return '0.00s';
    return `${(duration / 1000000000).toFixed(2)}s`;
  });

  beforeEach(() => {
    mockStore = {
      state: {
        expandedTestId: null,
        sortBy: 'duration',
        sortDirection: 'desc',
        options: {
          displayWarnings: true,
          displayDeprecations: true,
          displayNotices: true,
        },
      },
      setSortBy: vi.fn(),
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders nothing when individualResults is empty', () => {
    const wrapper = mount(IndividualTestResults, {
      props: {
        individualResults: [],
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    // Only the header should be present, no test cases
    expect(wrapper.findAll('.divide-y > div').length).toBe(0);
  });

  it('renders individual test cases correctly', () => {
    const individualResults = [
      { id: '1', name: 'testPassed', class: 'App\\Tests\\MyTest', status: 'passed', duration: 100000000, warnings: [], deprecations: [], notices: [] },
      { id: '2', name: 'testFailed', class: 'App\\Tests\\MyTest', status: 'failed', duration: 200000000, warnings: [], deprecations: [], notices: [] },
      { id: '3', name: 'testSkipped', class: 'App\\Tests\\MyTest', status: 'skipped', duration: 50000000, warnings: [], deprecations: [], notices: [] },
    ];
    const wrapper = mount(IndividualTestResults, {
      props: {
        individualResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    const testCases = wrapper.findAll('.divide-y > div');
    expect(testCases.length).toBe(3);

    // Test Passed
    expect(testCases[0].find('.text-sm.text-white').text()).toBe('testPassed');
    expect(testCases[0].find('.text-xs.text-gray-400').text()).toBe('App\\Tests\\MyTest');
    expect(testCases[0].find('.px-2.inline-flex').text()).toBe('passed');
    expect(testCases[0].find('.px-2.inline-flex').classes()).toContain('bg-green-900');
    expect(testCases[0].text()).toContain('0.10s');

    // Test Failed
    expect(testCases[1].find('.text-sm.text-white').text()).toBe('testFailed');
    expect(testCases[1].find('.px-2.inline-flex').text()).toBe('failed');
    expect(testCases[1].find('.px-2.inline-flex').classes()).toContain('bg-red-900');
    expect(testCases[1].text()).toContain('0.20s');

    // Test Skipped
    expect(testCases[2].find('.text-sm.text-white').text()).toBe('testSkipped');
    expect(testCases[2].find('.px-2.inline-flex').text()).toBe('skipped');
    expect(testCases[2].find('.px-2.inline-flex').classes()).toContain('bg-gray-700');
    expect(testCases[2].text()).toContain('0.05s');
  });

  it('displays warnings, deprecations, and notices when present and options enabled', () => {
    const individualResults = [
      {
        id: '1',
        name: 'testWithIssues',
        class: 'App\\Tests\\MyTest',
        status: 'passed',
        duration: 100000000,
        warnings: ['warn1'],
        deprecations: ['deprec1'],
        notices: ['notice1'],
      },
    ];
    const wrapper = mount(IndividualTestResults, {
      props: {
        individualResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    const testCase = wrapper.find('.divide-y > div');
    expect(testCase.text()).toContain('⚠ 1 warning(s)');
    expect(testCase.text()).toContain('⚠ 1 deprecation(s)');
    expect(testCase.text()).toContain('⚠ 1 notice(s)');
  });

  it('does not display warnings if displayWarnings is false', () => {
    mockStore.state.options.displayWarnings = false;
    const individualResults = [
      {
        id: '1',
        name: 'testWithIssues',
        class: 'App\\Tests\\MyTest',
        status: 'passed',
        duration: 100000000,
        warnings: ['warn1'],
        deprecations: [],
        notices: [],
      },
    ];
    const wrapper = mount(IndividualTestResults, {
      props: {
        individualResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    const testCase = wrapper.find('.divide-y > div');
    expect(testCase.text()).not.toContain('warning(s)');
  });

  it('emits toggleTestDetails when a test case is clicked', async () => {
    const testcase = { id: '1', name: 'testPassed', class: 'App\\Tests\\MyTest', status: 'passed', duration: 100000000, warnings: [], deprecations: [], notices: [] };
    const individualResults = [testcase];
    const wrapper = mount(IndividualTestResults, {
      props: {
        individualResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    await wrapper.find('.p-3.cursor-pointer').trigger('click');
    expect(wrapper.emitted().toggleTestDetails).toBeTruthy();
    expect(wrapper.emitted().toggleTestDetails[0][0]).toEqual(testcase);
  });

  it('renders TestDetails component when expandedTestId matches', () => {
    const testcase = { id: '1', name: 'testPassed', class: 'App\\Tests\\MyTest', status: 'passed', duration: 100000000, warnings: [], deprecations: [], notices: [] };
    const individualResults = [testcase];
    mockStore.state.expandedTestId = '1';
    const wrapper = mount(IndividualTestResults, {
      props: {
        individualResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('.mock-test-details').exists()).toBe(true);
  });

  it('does not render TestDetails component when expandedTestId does not match', () => {
    const testcase = { id: '1', name: 'testPassed', class: 'App\\Tests\\MyTest', status: 'passed', duration: 100000000, warnings: [], deprecations: [], notices: [] };
    const individualResults = [testcase];
    mockStore.state.expandedTestId = '2'; // Mismatch
    const wrapper = mount(IndividualTestResults, {
      props: {
        individualResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('.mock-test-details').exists()).toBe(false);
  });

  it('calls store.setSortBy when Duration header is clicked', async () => {
    const wrapper = mount(IndividualTestResults, {
      props: {
        individualResults: [],
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    await wrapper.find('.w-28.text-right.flex-shrink-0.pr-3.cursor-pointer').trigger('click');
    expect(mockStore.setSortBy).toHaveBeenCalledTimes(1);
    expect(mockStore.setSortBy).toHaveBeenCalledWith('duration');
  });

  it('displays sort direction indicator correctly', () => {
    mockStore.state.sortBy = 'duration';
    mockStore.state.sortDirection = 'asc';
    const wrapper = mount(IndividualTestResults, {
      props: {
        individualResults: [],
        formatNanoseconds: mockFormatNanoseconds,
      },
    });
    expect(wrapper.find('.w-28.text-right.flex-shrink-0.pr-3.cursor-pointer').text()).toContain('▲');

    mockStore.state.sortDirection = 'desc';
    const wrapper2 = mount(IndividualTestResults, {
      props: {
        individualResults: [],
        formatNanoseconds: mockFormatNanoseconds,
      },
    });
    expect(wrapper2.find('.w-28.text-right.flex-shrink-0.pr-3.cursor-pointer').text()).toContain('▼');
  });
});
