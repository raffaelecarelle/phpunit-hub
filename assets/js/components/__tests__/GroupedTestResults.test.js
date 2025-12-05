import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import GroupedTestResults from '../GroupedTestResults.vue';
import { useStore } from '../../store.js'; // Adjust path as necessary

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

describe('GroupedTestResults', () => {
  let mockStore;
  const mockFormatNanoseconds = vi.fn((duration) => {
    if (duration === undefined || duration === null) return '0.00s';
    return `${(duration / 1000000000).toFixed(2)}s`;
  });

  beforeEach(() => {
    mockStore = {
      state: {
        expandedTestcaseGroups: new Set(),
        expandedTestId: null,
        options: {
          displayWarnings: true,
          displayDeprecations: true,
          displayNotices: true,
          displaySkipped: true,
          displayIncomplete: true,
          displayRisky: true,
        },
      },
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders nothing when groupedResults is empty', () => {
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults: [],
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.html()).toBe('');
  });

  it('renders a group header correctly', () => {
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 1,
        failed: 0,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [],
      },
    ];
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('.font-bold.text-white').text()).toBe('App\\Tests\\MyTest');
    expect(wrapper.text()).toContain('1 Passed');
    expect(wrapper.find('svg').classes()).not.toContain('rotate-90'); // Not expanded by default
  });

  it('emits toggleTestcaseGroup when group header is clicked', async () => {
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 1,
        failed: 0,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [],
      },
    ];
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    await wrapper.find('.bg-gray-700').trigger('click');
    expect(wrapper.emitted().toggleTestcaseGroup).toBeTruthy();
    expect(wrapper.emitted().toggleTestcaseGroup[0][0]).toBe('App\\Tests\\MyTest');
  });

  it('applies rotate-90 class to SVG when group is expanded', async () => {
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 1,
        failed: 0,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('svg').classes()).toContain('rotate-90');
  });

  it('renders failed testcases when group is expanded', () => {
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 0,
        failed: 1,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [
          { id: 'test1', name: 'testFailure', status: 'failed', duration: 100000000, warnings: [], deprecations: [], notices: [] },
        ],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('.text-sm.text-white').text()).toBe('testFailure');
    expect(wrapper.find('.px-2.inline-flex').text()).toBe('failed');
    expect(wrapper.find('.px-2.inline-flex').classes()).toContain('bg-red-900');
    expect(wrapper.text()).toContain('0.10s');
  });

  it('renders errored testcases when group is expanded', () => {
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 0,
        failed: 0,
        errored: 1,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [
          { id: 'test1', name: 'testError', status: 'errored', duration: 200000000, warnings: [], deprecations: [], notices: [] },
        ],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('.text-sm.text-white').text()).toBe('testError');
    expect(wrapper.find('.px-2.inline-flex').text()).toBe('errored');
    expect(wrapper.find('.px-2.inline-flex').classes()).toContain('bg-red-900');
    expect(wrapper.text()).toContain('0.20s');
  });

  it('renders skipped testcases when group is expanded and displaySkipped is true', () => {
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 0,
        failed: 0,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 1,
        incomplete: 0,
        risky: 0,
        testcases: [
          { id: 'test1', name: 'testSkipped', status: 'skipped', duration: 0, warnings: [], deprecations: [], notices: [] },
        ],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('.text-sm.text-white').text()).toBe('testSkipped');
    expect(wrapper.find('.px-2.inline-flex').text()).toBe('skipped');
    expect(wrapper.find('.px-2.inline-flex').classes()).toContain('bg-gray-700');
  });

  it('does not render skipped testcases when group is expanded and displaySkipped is false', () => {
    mockStore.state.options.displaySkipped = false;
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 0,
        failed: 0,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 1,
        incomplete: 0,
        risky: 0,
        testcases: [
          { id: 'test1', name: 'testSkipped', status: 'skipped', duration: 0, warnings: [], deprecations: [], notices: [] },
        ],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('.text-sm.text-white').exists()).toBe(false);
  });

  it('renders passed tests summary when group is expanded and there are passed tests', () => {
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 5,
        failed: 0,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [
          { id: 'test1', name: 'testPassed1', status: 'passed', duration: 10000000, warnings: [], deprecations: [], notices: [] },
          { id: 'test2', name: 'testPassed2', status: 'passed', duration: 10000000, warnings: [], deprecations: [], notices: [] },
          { id: 'test3', name: 'testPassed3', status: 'passed', duration: 10000000, warnings: [], deprecations: [], notices: [] },
          { id: 'test4', name: 'testPassed4', status: 'passed', duration: 10000000, warnings: [], deprecations: [], notices: [] },
          { id: 'test5', name: 'testPassed5', status: 'passed', duration: 10000000, warnings: [], deprecations: [], notices: [] },
        ],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).toContain('5 test(s) passed');
  });

  it('renders passed testcases with warnings/deprecations/notices if options are enabled', () => {
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 1,
        failed: 0,
        errored: 0,
        warning: 1,
        deprecation: 1,
        notice: 1,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [
          { id: 'test1', name: 'testPassedWithIssues', status: 'passed', duration: 100000000, warnings: ['warn'], deprecations: ['deprec'], notices: ['notice'] },
        ],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    const passedTestcase = wrapper.findAll('.divide-y > div')[1]; // The second div is for passed tests with issues
    expect(passedTestcase.text()).toContain('testPassedWithIssues');
    expect(passedTestcase.text()).toContain('⚠ 1 warning(s)');
    expect(passedTestcase.text()).toContain('⚠ 1 deprecation(s)');
    expect(passedTestcase.text()).toContain('⚠ 1 notice(s)');
  });

  it('emits toggleTestDetails when a testcase is clicked', async () => {
    const testcase = { id: 'test1', name: 'testFailure', status: 'failed', duration: 100000000, warnings: [], deprecations: [], notices: [] };
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 0,
        failed: 1,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [testcase],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    await wrapper.find('.p-3.cursor-pointer').trigger('click');
    expect(wrapper.emitted().toggleTestDetails).toBeTruthy();
    expect(wrapper.emitted().toggleTestDetails[0][0]).toEqual(testcase);
  });

  it('renders TestDetails component when a testcase is expanded', () => {
    const testcase = { id: 'test1', name: 'testFailure', status: 'failed', duration: 100000000, warnings: [], deprecations: [], notices: [] };
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 0,
        failed: 1,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [testcase],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    mockStore.state.expandedTestId = 'test1';
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('.mock-test-details').exists()).toBe(true);
  });

  it('does not render TestDetails component when a testcase is not expanded', () => {
    const testcase = { id: 'test1', name: 'testFailure', status: 'failed', duration: 100000000, warnings: [], deprecations: [], notices: [] };
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 0,
        failed: 1,
        errored: 0,
        warning: 0,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [testcase],
      },
    ];
    mockStore.state.expandedTestcaseGroups.add('App\\Tests\\MyTest');
    mockStore.state.expandedTestId = 'anotherTest'; // Not matching
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.find('.mock-test-details').exists()).toBe(false);
  });

  it('displays correct status counts in group header', () => {
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 1,
        failed: 1,
        errored: 1,
        warning: 1,
        deprecation: 1,
        notice: 1,
        skipped: 1,
        incomplete: 1,
        risky: 1,
        testcases: [],
      },
    ];
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).toContain('1 Failed');
    expect(wrapper.text()).toContain('1 Error');
    expect(wrapper.text()).toContain('1 Warnings');
    expect(wrapper.text()).toContain('1 Deprecations');
    expect(wrapper.text()).toContain('1 Notices');
    expect(wrapper.text()).toContain('1 Skipped');
    expect(wrapper.text()).toContain('1 Incomplete');
    expect(wrapper.text()).toContain('1 Risky');
    expect(wrapper.text()).toContain('1 Passed');
  });

  it('hides warning count in group header if displayWarnings is false', () => {
    mockStore.state.options.displayWarnings = false;
    const groupedResults = [
      {
        className: 'App\\Tests\\MyTest',
        passed: 0,
        failed: 0,
        errored: 0,
        warning: 1,
        deprecation: 0,
        notice: 0,
        skipped: 0,
        incomplete: 0,
        risky: 0,
        testcases: [],
      },
    ];
    const wrapper = mount(GroupedTestResults, {
      props: {
        groupedResults,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).not.toContain('Warnings');
  });
});
