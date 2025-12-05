import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ResultsSummary from '../ResultsSummary.vue';
import { useStore } from '../../store.js'; // Adjust path as necessary

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('ResultsSummary', () => {
  let mockStore;
  const mockFormatNanoseconds = vi.fn((time) => `${time / 1000000} ms`);

  beforeEach(() => {
    mockStore = {
      state: {
        options: {
          displayWarnings: true,
          displaySkipped: true,
          displayDeprecations: true,
          displayNotices: true,
          displayIncomplete: true,
          displayRisky: true,
        },
      },
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly with no results', () => {
    const wrapper = mount(ResultsSummary, {
      props: {
        results: null,
        statusCounts: { passed: 0 },
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('Total Tests0');
    expect(wrapper.text()).toContain('Total Assertions0');
    expect(wrapper.text()).toContain('Duration0 ms'); // Assuming formatNanoseconds(undefined) returns '0 ms' or similar
    expect(wrapper.text()).toContain('0 Passed');
    expect(wrapper.text()).not.toContain('Failed');
    expect(wrapper.text()).not.toContain('Errors');
    expect(wrapper.text()).not.toContain('Warnings');
    expect(wrapper.text()).not.toContain('Skipped');
    expect(wrapper.text()).not.toContain('Deprecations');
    expect(wrapper.text()).not.toContain('Notices');
    expect(wrapper.text()).not.toContain('Incomplete');
    expect(wrapper.text()).not.toContain('Risky');
  });

  it('renders correctly with full results and all options enabled', () => {
    const results = {
      summary: {
        tests: 10,
        assertions: 20,
        time: 123456789,
        failures: 1,
        errors: 0,
        warnings: 2,
        skipped: 3,
        deprecations: 4,
        notices: 5,
        incomplete: 6,
        risky: 7,
      },
    };
    const statusCounts = { passed: 9 };

    const wrapper = mount(ResultsSummary, {
      props: {
        results,
        statusCounts,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).toContain('Total Tests10');
    expect(wrapper.text()).toContain('Total Assertions20');
    expect(wrapper.text()).toContain('Duration123.456789 ms');
    expect(wrapper.text()).toContain('9 Passed');
    expect(wrapper.text()).toContain('1 Failed');
    expect(wrapper.text()).not.toContain('Errors'); // errors is 0
    expect(wrapper.text()).toContain('2 Warnings');
    expect(wrapper.text()).toContain('3 Skipped');
    expect(wrapper.text()).toContain('4 Deprecations');
    expect(wrapper.text()).toContain('5 Notices');
    expect(wrapper.text()).toContain('6 Incomplete');
    expect(wrapper.text()).toContain('7 Risky');
  });

  it('does not display warnings when displayWarnings is false', () => {
    mockStore.state.options.displayWarnings = false;
    const results = {
      summary: {
        tests: 1,
        assertions: 1,
        time: 1,
        failures: 0,
        errors: 0,
        warnings: 2,
        skipped: 0,
        deprecations: 0,
        notices: 0,
        incomplete: 0,
        risky: 0,
      },
    };
    const statusCounts = { passed: 1 };

    const wrapper = mount(ResultsSummary, {
      props: {
        results,
        statusCounts,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).not.toContain('Warnings');
  });

  it('does not display skipped when displaySkipped is false', () => {
    mockStore.state.options.displaySkipped = false;
    const results = {
      summary: {
        tests: 1,
        assertions: 1,
        time: 1,
        failures: 0,
        errors: 0,
        warnings: 0,
        skipped: 3,
        deprecations: 0,
        notices: 0,
        incomplete: 0,
        risky: 0,
      },
    };
    const statusCounts = { passed: 1 };

    const wrapper = mount(ResultsSummary, {
      props: {
        results,
        statusCounts,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).not.toContain('Skipped');
  });

  it('does not display deprecations when displayDeprecations is false', () => {
    mockStore.state.options.displayDeprecations = false;
    const results = {
      summary: {
        tests: 1,
        assertions: 1,
        time: 1,
        failures: 0,
        errors: 0,
        warnings: 0,
        skipped: 0,
        deprecations: 4,
        notices: 0,
        incomplete: 0,
        risky: 0,
      },
    };
    const statusCounts = { passed: 1 };

    const wrapper = mount(ResultsSummary, {
      props: {
        results,
        statusCounts,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).not.toContain('Deprecations');
  });

  it('does not display notices when displayNotices is false', () => {
    mockStore.state.options.displayNotices = false;
    const results = {
      summary: {
        tests: 1,
        assertions: 1,
        time: 1,
        failures: 0,
        errors: 0,
        warnings: 0,
        skipped: 0,
        deprecations: 0,
        notices: 5,
        incomplete: 0,
        risky: 0,
      },
    };
    const statusCounts = { passed: 1 };

    const wrapper = mount(ResultsSummary, {
      props: {
        results,
        statusCounts,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).not.toContain('Notices');
  });

  it('does not display incomplete when displayIncomplete is false', () => {
    mockStore.state.options.displayIncomplete = false;
    const results = {
      summary: {
        tests: 1,
        assertions: 1,
        time: 1,
        failures: 0,
        errors: 0,
        warnings: 0,
        skipped: 0,
        deprecations: 0,
        notices: 0,
        incomplete: 6,
        risky: 0,
      },
    };
    const statusCounts = { passed: 1 };

    const wrapper = mount(ResultsSummary, {
      props: {
        results,
        statusCounts,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).not.toContain('Incomplete');
  });

  it('does not display risky when displayRisky is false', () => {
    mockStore.state.options.displayRisky = false;
    const results = {
      summary: {
        tests: 1,
        assertions: 1,
        time: 1,
        failures: 0,
        errors: 0,
        warnings: 0,
        skipped: 0,
        deprecations: 0,
        notices: 0,
        incomplete: 0,
        risky: 7,
      },
    };
    const statusCounts = { passed: 1 };

    const wrapper = mount(ResultsSummary, {
      props: {
        results,
        statusCounts,
        formatNanoseconds: mockFormatNanoseconds,
      },
    });

    expect(wrapper.text()).not.toContain('Risky');
  });
});
