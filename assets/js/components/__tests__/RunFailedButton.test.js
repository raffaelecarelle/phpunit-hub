import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import RunFailedButton from '../header/RunFailedButton.vue';

describe('RunFailedButton', () => {
  it('renders correctly and is enabled when tests are not running and there are failed tests', () => {
    const wrapper = mount(RunFailedButton, {
      props: {
        isAnyTestRunning: false,
        hasFailedTests: true,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('button').text()).toBe('Run Failed');
    expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
  });

  it('is disabled when tests are running', () => {
    const wrapper = mount(RunFailedButton, {
      props: {
        isAnyTestRunning: true,
        hasFailedTests: true,
      },
    });

    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
  });

  it('is disabled when there are no failed tests', () => {
    const wrapper = mount(RunFailedButton, {
      props: {
        isAnyTestRunning: false,
        hasFailedTests: false,
      },
    });

    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
  });

  it('emits "runFailedTests" when clicked and enabled', async () => {
    const wrapper = mount(RunFailedButton, {
      props: {
        isAnyTestRunning: false,
        hasFailedTests: true,
      },
    });

    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted().runFailedTests).toBeTruthy();
    expect(wrapper.emitted().runFailedTests.length).toBe(1);
  });

  it('does not emit "runFailedTests" when clicked and disabled due to tests running', async () => {
    const wrapper = mount(RunFailedButton, {
      props: {
        isAnyTestRunning: true,
        hasFailedTests: true,
      },
    });

    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted().runFailedTests).toBeUndefined();
  });

  it('does not emit "runFailedTests" when clicked and disabled due to no failed tests', async () => {
    const wrapper = mount(RunFailedButton, {
      props: {
        isAnyTestRunning: false,
        hasFailedTests: false,
      },
    });

    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted().runFailedTests).toBeUndefined();
  });
});
