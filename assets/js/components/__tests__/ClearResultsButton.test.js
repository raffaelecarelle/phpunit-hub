import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import ClearResultsButton from '../header/ClearResultsButton.vue';

describe('ClearResultsButton', () => {
  it('renders correctly and is enabled when no tests are running and results exist', () => {
    const wrapper = mount(ClearResultsButton, {
      props: {
        isAnyTestRunning: false,
        results: {}, // results object exists
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('button').text()).toBe('Clear Results');
    expect(wrapper.find('button').attributes('title')).toBe('Clear all test results');
    expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
  });

  it('is disabled when tests are running', () => {
    const wrapper = mount(ClearResultsButton, {
      props: {
        isAnyTestRunning: true,
        results: {},
      },
    });

    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
  });

  it('is disabled when no results exist', () => {
    const wrapper = mount(ClearResultsButton, {
      props: {
        isAnyTestRunning: false,
        results: null, // no results
      },
    });

    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
  });

  it('emits "clearAllResults" when clicked and enabled', async () => {
    const wrapper = mount(ClearResultsButton, {
      props: {
        isAnyTestRunning: false,
        results: {},
      },
    });

    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted().clearAllResults).toBeTruthy();
    expect(wrapper.emitted().clearAllResults.length).toBe(1);
  });

  it('does not emit "clearAllResults" when clicked and disabled due to tests running', async () => {
    const wrapper = mount(ClearResultsButton, {
      props: {
        isAnyTestRunning: true,
        results: {},
      },
    });

    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted().clearAllResults).toBeUndefined();
  });

  it('does not emit "clearAllResults" when clicked and disabled due to no results', async () => {
    const wrapper = mount(ClearResultsButton, {
      props: {
        isAnyTestRunning: false,
        results: null,
      },
    });

    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted().clearAllResults).toBeUndefined();
  });
});
