import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import RunStopAllButton from '../header/RunStopAllButton.vue';

describe('RunStopAllButton', () => {
  it('renders "Run All" button when no tests are running and no stop is pending', () => {
    const wrapper = mount(RunStopAllButton, {
      props: {
        isAnyTestRunning: false,
        isAnyStopPending: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('button').text()).toContain('Run All');
    expect(wrapper.find('button').attributes('title')).toBe('Run all tests');
    expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
    expect(wrapper.find('svg').exists()).toBe(true); // Play icon
  });

  it('renders "Stop All" button when tests are running and no stop is pending', () => {
    const wrapper = mount(RunStopAllButton, {
      props: {
        isAnyTestRunning: true,
        isAnyStopPending: false,
      },
    });

    expect(wrapper.find('button').text()).toContain('Stop All');
    expect(wrapper.find('button').attributes('title')).toBe('Stop all test runs');
    expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
    expect(wrapper.find('svg').exists()).toBe(true); // Stop icon
  });

  it('renders "Stopping..." when a stop is pending', () => {
    const wrapper = mount(RunStopAllButton, {
      props: {
        isAnyTestRunning: false, // or true, doesn't matter when stop is pending
        isAnyStopPending: true,
      },
    });

    expect(wrapper.find('button').text()).toBe('Stopping...');
    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    expect(wrapper.find('svg').exists()).toBe(false);
  });

  it('is disabled when a stop is pending', () => {
    const wrapper = mount(RunStopAllButton, {
      props: {
        isAnyTestRunning: false,
        isAnyStopPending: true,
      },
    });

    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
  });

  it('emits "togglePlayStop" when clicked and enabled', async () => {
    const wrapper = mount(RunStopAllButton, {
      props: {
        isAnyTestRunning: false,
        isAnyStopPending: false,
      },
    });

    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted().togglePlayStop).toBeTruthy();
    expect(wrapper.emitted().togglePlayStop.length).toBe(1);
  });

  it('does not emit "togglePlayStop" when clicked and disabled due to stop pending', async () => {
    const wrapper = mount(RunStopAllButton, {
      props: {
        isAnyTestRunning: false,
        isAnyStopPending: true,
      },
    });

    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted().togglePlayStop).toBeUndefined();
  });
});
