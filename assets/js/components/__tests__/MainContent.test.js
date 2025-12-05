import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import MainContent from '../MainContent.vue';
import { useStore } from '../../store.js'; // Corrected path

// Mock child components
vi.mock('../TabNavigation.vue', () => ({
  default: {
    name: 'TabNavigation',
    template: '<div class="mock-tab-navigation"></div>',
  },
}));
vi.mock('../ResultsSummary.vue', () => ({
  default: {
    name: 'ResultsSummary',
    template: '<div class="mock-results-summary"></div>',
  },
}));
vi.mock('../IndividualTestResults.vue', () => ({
  default: {
    name: 'IndividualTestResults',
    template: '<div class="mock-individual-test-results"></div>',
  },
}));
vi.mock('../GroupedTestResults.vue', () => ({
  default: {
    name: 'GroupedTestResults',
    template: '<div class="mock-grouped-test-results"></div>',
  },
}));
vi.mock('../CoverageReport.vue', () => ({
  default: {
    name: 'CoverageReport',
    template: '<div class="mock-coverage-report"></div>',
  },
}));

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('MainContent', () => {
  let mockStore;

  beforeEach(() => {
    mockStore = {
      state: {
        activeTab: 'results',
        options: {
          displayMode: 'default',
        },
        expandedTestId: null,
      },
      toggleTestcaseGroupExpansion: vi.fn(),
      setExpandedTest: vi.fn(),
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly with default props', () => {
    const wrapper = mount(MainContent, {
      props: {
        results: [],
        statusCounts: {},
        isAnyTestRunning: false,
        formatNanoseconds: vi.fn(),
        individualResults: [],
        groupedResults: [],
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.mock-tab-navigation').exists()).toBe(true);
    expect(wrapper.find('.mock-results-summary').exists()).toBe(true);
    expect(wrapper.find('.mock-grouped-test-results').exists()).toBe(true);
    expect(wrapper.find('.mock-individual-test-results').exists()).toBe(false);
    expect(wrapper.find('.mock-coverage-report').exists()).toBe(false);
  });

  it('shows "Run tests to see the results" when no results are present', () => {
    const wrapper = mount(MainContent, {
      props: {
        results: null, // No results
        statusCounts: {},
        isAnyTestRunning: false,
        formatNanoseconds: vi.fn(),
        individualResults: [],
        groupedResults: [],
      },
    });

    expect(wrapper.text()).toContain('Run tests to see the results.');
    expect(wrapper.find('.mock-results-summary').exists()).toBe(true); // Summary should still be there
  });

  it('shows spinner when tests are running', () => {
    const wrapper = mount(MainContent, {
      props: {
        results: [],
        statusCounts: {},
        isAnyTestRunning: true,
        formatNanoseconds: vi.fn(),
        individualResults: [],
        groupedResults: [],
      },
    });

    expect(wrapper.find('.spinner-big').exists()).toBe(true);
    expect(wrapper.find('.mock-grouped-test-results').exists()).toBe(false);
    expect(wrapper.find('.mock-individual-test-results').exists()).toBe(false);
  });

  it('renders IndividualTestResults when displayMode is "individual" and not running', async () => {
    mockStore.state.options.displayMode = 'individual';
    const wrapper = mount(MainContent, {
      props: {
        results: [{ id: 1 }],
        statusCounts: {},
        isAnyTestRunning: false,
        formatNanoseconds: vi.fn(),
        individualResults: [{ id: 1 }],
        groupedResults: [],
      },
    });

    await wrapper.vm.$nextTick(); // Wait for reactivity

    expect(wrapper.find('.mock-individual-test-results').exists()).toBe(true);
    expect(wrapper.find('.mock-grouped-test-results').exists()).toBe(false);
  });

  it('renders GroupedTestResults when displayMode is "default" and not running', async () => {
    mockStore.state.options.displayMode = 'default';
    const wrapper = mount(MainContent, {
      props: {
        results: [{ id: 1 }],
        statusCounts: {},
        isAnyTestRunning: false,
        formatNanoseconds: vi.fn(),
        individualResults: [],
        groupedResults: [{ id: 1 }],
      },
    });

    await wrapper.vm.$nextTick(); // Wait for reactivity

    expect(wrapper.find('.mock-grouped-test-results').exists()).toBe(true);
    expect(wrapper.find('.mock-individual-test-results').exists()).toBe(false);
  });

  it('switches to coverage tab', async () => {
    mockStore.state.activeTab = 'coverage';
    const wrapper = mount(MainContent, {
      props: {
        results: [],
        statusCounts: {},
        isAnyTestRunning: false,
        formatNanoseconds: vi.fn(),
        individualResults: [],
        groupedResults: [],
      },
    });

    await wrapper.vm.$nextTick(); // Wait for reactivity

    expect(wrapper.find('.mock-coverage-report').exists()).toBe(true);
    expect(wrapper.find('.mock-results-summary').exists()).toBe(false);
  });

  it('emits showFileCoverage when CoverageReport emits it', async () => {
    mockStore.state.activeTab = 'coverage';
    const wrapper = mount(MainContent, {
      props: {
        results: [],
        statusCounts: {},
        isAnyTestRunning: false,
        formatNanoseconds: vi.fn(),
        individualResults: [],
        groupedResults: [],
      },
    });

    await wrapper.vm.$nextTick();
    const coverageReport = wrapper.findComponent({ name: 'CoverageReport' });
    // Simulate the child component emitting the event
    await coverageReport.vm.$emit('showFileCoverage', '/path/to/file.php');

    expect(wrapper.emitted().showFileCoverage).toBeTruthy();
    expect(wrapper.emitted().showFileCoverage[0][0]).toBe('/path/to/file.php');
  });

  it('calls store.toggleTestcaseGroupExpansion when toggleTestcaseGroup is called', () => {
    const wrapper = mount(MainContent, {
      props: {
        results: [],
        statusCounts: {},
        isAnyTestRunning: false,
        formatNanoseconds: vi.fn(),
        individualResults: [],
        groupedResults: [],
      },
    });

    wrapper.vm.toggleTestcaseGroup('MyClass');
    expect(mockStore.toggleTestcaseGroupExpansion).toHaveBeenCalledWith('MyClass');
  });

  it('calls store.setExpandedTest with null if testcase is already expanded', () => {
    mockStore.state.expandedTestId = 123;
    const wrapper = mount(MainContent, {
      props: {
        results: [],
        statusCounts: {},
        isAnyTestRunning: false,
        formatNanoseconds: vi.fn(),
        individualResults: [],
        groupedResults: [],
      },
    });

    wrapper.vm.handleToggleTestDetails({ id: 123 });
    expect(mockStore.setExpandedTest).toHaveBeenCalledWith(null);
  });

  it('calls store.setExpandedTest with testcase id if testcase is not expanded', () => {
    mockStore.state.expandedTestId = null;
    const wrapper = mount(MainContent, {
      props: {
        results: [],
        statusCounts: {},
        isAnyTestRunning: false,
        formatNanoseconds: vi.fn(),
        individualResults: [],
        groupedResults: [],
      },
    });

    wrapper.vm.handleToggleTestDetails({ id: 456 });
    expect(mockStore.setExpandedTest).toHaveBeenCalledWith(456);
  });
});
