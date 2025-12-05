import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import TestList from '../sidebar/TestList.vue';

// Mock the TestItem component
vi.mock('../sidebar/TestItem.vue', () => ({
  default: {
    name: 'TestItem',
    props: ['method', 'isTestRunning'],
    template: '<li class="mock-test-item" @click="$emit(\'runSingleTest\', method.id)">{{ method.name }}</li>',
  },
}));

describe('TestList', () => {
  const mockIsTestRunning = vi.fn();

  it('renders a list of TestItem components', () => {
    const suite = {
      methods: [
        { id: '1', name: 'testMethod1' },
        { id: '2', name: 'testMethod2' },
      ],
    };

    const wrapper = mount(TestList, {
      props: {
        suite,
        isTestRunning: mockIsTestRunning,
      },
    });

    const testItems = wrapper.findAll('.mock-test-item');
    expect(testItems.length).toBe(suite.methods.length);
    expect(testItems[0].text()).toBe('testMethod1');
    expect(testItems[1].text()).toBe('testMethod2');
  });

  it('passes the method prop and isTestRunning prop to TestItem', () => {
    const method1 = { id: '1', name: 'testMethod1' };
    const suite = {
      methods: [method1],
    };

    const wrapper = mount(TestList, {
      props: {
        suite,
        isTestRunning: mockIsTestRunning,
      },
    });

    const testItem = wrapper.findComponent({ name: 'TestItem' });
    expect(testItem.props('method')).toEqual(method1);
    expect(testItem.props('isTestRunning')).toBe(mockIsTestRunning);
  });

  it('re-emits "runSingleTest" event from TestItem', async () => {
    const method1 = { id: '1', name: 'testMethod1' };
    const suite = {
      methods: [method1],
    };

    const wrapper = mount(TestList, {
      props: {
        suite,
        isTestRunning: mockIsTestRunning,
      },
    });

    const testItem = wrapper.findComponent({ name: 'TestItem' });
    await testItem.vm.$emit('runSingleTest', '123');

    expect(wrapper.emitted().runSingleTest).toBeTruthy();
    expect(wrapper.emitted().runSingleTest[0][0]).toBe('123');
  });

  it('renders nothing if suite.methods is empty', () => {
    const suite = {
      methods: [],
    };

    const wrapper = mount(TestList, {
      props: {
        suite,
        isTestRunning: mockIsTestRunning,
      },
    });

    expect(wrapper.findAll('.mock-test-item').length).toBe(0);
  });
});
