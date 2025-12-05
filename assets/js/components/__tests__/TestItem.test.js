import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import TestItem from '../sidebar/TestItem.vue';

describe('TestItem', () => {
  const mockIsTestRunning = vi.fn((runId) => runId === 'running-id');

  it('renders correctly with a neutral status', () => {
    const method = { id: '1', name: 'testExample', runId: null, status: null };
    const wrapper = mount(TestItem, {
      props: {
        method,
        isTestRunning: mockIsTestRunning,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.test-name').text()).toBe('testExample');
    expect(wrapper.find('.status-icon').classes()).toContain('status-neutral');
    expect(wrapper.find('.spinner').exists()).toBe(false);
  });

  it('renders correctly with a "passed" status', () => {
    const method = { id: '1', name: 'testPassed', runId: null, status: 'passed' };
    const wrapper = mount(TestItem, {
      props: {
        method,
        isTestRunning: mockIsTestRunning,
      },
    });

    expect(wrapper.find('.status-icon').classes()).toContain('status-passed');
    expect(wrapper.find('.spinner').exists()).toBe(false);
  });

  it('renders correctly with a "failed" status', () => {
    const method = { id: '1', name: 'testFailed', runId: null, status: 'failed' };
    const wrapper = mount(TestItem, {
      props: {
        method,
        isTestRunning: mockIsTestRunning,
      },
    });

    expect(wrapper.find('.status-icon').classes()).toContain('status-failed');
    expect(wrapper.find('.spinner').exists()).toBe(false);
  });

  it('renders spinner when test is running', () => {
    const method = { id: '1', name: 'testRunning', runId: 'running-id', status: null };
    const wrapper = mount(TestItem, {
      props: {
        method,
        isTestRunning: mockIsTestRunning,
      },
    });

    expect(wrapper.find('.spinner').exists()).toBe(true);
    expect(wrapper.find('.status-icon').classes()).not.toContain('status-neutral'); // Spinner replaces status icon
  });

  it('emits "runSingleTest" when clicked', async () => {
    const method = { id: '123', name: 'testClick', runId: null, status: null };
    const wrapper = mount(TestItem, {
      props: {
        method,
        isTestRunning: mockIsTestRunning,
      },
    });

    await wrapper.find('.test-item').trigger('click');
    expect(wrapper.emitted().runSingleTest).toBeTruthy();
    expect(wrapper.emitted().runSingleTest[0][0]).toBe('123');
  });
});
