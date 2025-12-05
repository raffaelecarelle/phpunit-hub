import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TestDetails from '../TestDetails.vue';
import { useStore } from '../../store.js'; // Adjust path as necessary

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('TestDetails', () => {
  let mockStore;

  beforeEach(() => {
    mockStore = {
      state: {
        options: {
          displayWarnings: true,
          displayDeprecations: true,
          displayNotices: true,
        },
      },
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly with a basic testcase', () => {
    const testcase = {
      id: '1',
      status: 'passed',
      message: null,
      trace: null,
      warnings: [],
      deprecations: [],
      notices: [],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).not.toContain('Warnings');
    expect(wrapper.text()).not.toContain('Deprecations');
    expect(wrapper.text()).not.toContain('Notices');
  });

  it('displays message and trace for failed testcase', () => {
    const testcase = {
      id: '1',
      status: 'failed',
      message: 'Assertion failed.',
      trace: 'Stack trace details...',
      warnings: [],
      deprecations: [],
      notices: [],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.find('h4').text()).toBe('Assertion failed.');
    expect(wrapper.find('h4').classes()).toContain('text-red-400');
    expect(wrapper.find('pre').text()).toBe('Stack trace details...');
  });

  it('displays message and trace for errored testcase', () => {
    const testcase = {
      id: '1',
      status: 'errored',
      message: 'An error occurred.',
      trace: 'Error stack trace...',
      warnings: [],
      deprecations: [],
      notices: [],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.find('h4').text()).toBe('An error occurred.');
    expect(wrapper.find('h4').classes()).toContain('text-red-400');
    expect(wrapper.find('pre').text()).toBe('Error stack trace...');
  });

  it('does not display message or trace if not present', () => {
    const testcase = {
      id: '1',
      status: 'passed',
      message: null,
      trace: null,
      warnings: [],
      deprecations: [],
      notices: [],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.find('h4').exists()).toBe(false);
    expect(wrapper.find('pre').exists()).toBe(false);
  });

  it('displays warnings when present and displayWarnings is true', () => {
    const testcase = {
      id: '1',
      status: 'passed',
      message: null,
      trace: null,
      warnings: ['Warning 1', 'Warning 2'],
      deprecations: [],
      notices: [],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.text()).toContain('Warnings (2)');
    expect(wrapper.findAll('pre')[0].text()).toBe('Warning 1');
    expect(wrapper.findAll('pre')[1].text()).toBe('Warning 2');
  });

  it('does not display warnings when displayWarnings is false', () => {
    mockStore.state.options.displayWarnings = false;
    const testcase = {
      id: '1',
      status: 'passed',
      message: null,
      trace: null,
      warnings: ['Warning 1'],
      deprecations: [],
      notices: [],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.text()).not.toContain('Warnings');
  });

  it('displays deprecations when present and displayDeprecations is true', () => {
    const testcase = {
      id: '1',
      status: 'passed',
      message: null,
      trace: null,
      warnings: [],
      deprecations: ['Deprecation 1'],
      notices: [],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.text()).toContain('Deprecations (1)');
    expect(wrapper.find('pre').text()).toBe('Deprecation 1');
  });

  it('does not display deprecations when displayDeprecations is false', () => {
    mockStore.state.options.displayDeprecations = false;
    const testcase = {
      id: '1',
      status: 'passed',
      message: null,
      trace: null,
      warnings: [],
      deprecations: ['Deprecation 1'],
      notices: [],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.text()).not.toContain('Deprecations');
  });

  it('displays notices when present and displayNotices is true', () => {
    const testcase = {
      id: '1',
      status: 'passed',
      message: null,
      trace: null,
      warnings: [],
      deprecations: [],
      notices: ['Notice 1', 'Notice 2'],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.text()).toContain('Notices (2)');
    expect(wrapper.findAll('pre')[0].text()).toBe('Notice 1');
    expect(wrapper.findAll('pre')[1].text()).toBe('Notice 2');
  });

  it('does not display notices when displayNotices is false', () => {
    mockStore.state.options.displayNotices = false;
    const testcase = {
      id: '1',
      status: 'passed',
      message: null,
      trace: null,
      warnings: [],
      deprecations: [],
      notices: ['Notice 1'],
    };
    const wrapper = mount(TestDetails, {
      props: { testcase },
    });

    expect(wrapper.text()).not.toContain('Notices');
  });
});
