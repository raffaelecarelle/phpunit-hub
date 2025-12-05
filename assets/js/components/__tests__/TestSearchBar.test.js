import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ref } from 'vue';
import TestSearchBar from '../sidebar/TestSearchBar.vue';
import { useStore } from '../../store.js'; // Adjust path as necessary

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('TestSearchBar', () => {
  let mockStore;
  const mockTestSuites = ref([
    { id: 'suite1', name: 'App\\Tests\\Feature\\UserTest', methods: [{ id: 'm1', name: 'testCreateUser' }, { id: 'm2', name: 'testDeleteUser' }] },
    { id: 'suite2', name: 'App\\Tests\\Unit\\ProductTest', methods: [{ id: 'm3', name: 'testAddProduct' }, { id: 'm4', name: 'testRemoveProduct' }] },
    { id: 'suite3', name: 'App\\Tests\\Feature\\OrderTest', methods: [{ id: 'm5', name: 'testPlaceOrder' }] },
  ]);

  beforeEach(() => {
    mockStore = {
      state: {
        testSuites: mockTestSuites.value,
      },
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly with an input field', () => {
    const wrapper = mount(TestSearchBar);
    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('input[type="text"]').exists()).toBe(true);
    expect(wrapper.find('input[type="text"]').attributes('placeholder')).toBe('Search tests...');
  });

  it('emits all test suites initially when searchQuery is empty', () => {
    const wrapper = mount(TestSearchBar);
    expect(wrapper.emitted()['update:filtered-suites']).toBeTruthy();
    expect(wrapper.emitted()['update:filtered-suites'][0][0]).toEqual(mockTestSuites.value);
  });

  it('filters suites by suite name', async () => {
    const wrapper = mount(TestSearchBar);
    const input = wrapper.find('input[type="text"]');

    await input.setValue('UserTest');

    const emitted = wrapper.emitted()['update:filtered-suites'];
    expect(emitted).toBeTruthy();
    const lastEmitted = emitted[emitted.length - 1][0];
    expect(lastEmitted.length).toBe(1);
    expect(lastEmitted[0].name).toBe('App\\Tests\\Feature\\UserTest');
  });

  it('filters suites by method name', async () => {
    const wrapper = mount(TestSearchBar);
    const input = wrapper.find('input[type="text"]');

    await input.setValue('testAddProduct');

    const emitted = wrapper.emitted()['update:filtered-suites'];
    expect(emitted).toBeTruthy();
    const lastEmitted = emitted[emitted.length - 1][0];
    expect(lastEmitted.length).toBe(1);
    expect(lastEmitted[0].name).toBe('App\\Tests\\Unit\\ProductTest');
    expect(lastEmitted[0].methods.length).toBe(1);
    expect(lastEmitted[0].methods[0].name).toBe('testAddProduct');
  });

  it('filters suites by partial query (case-insensitive)', async () => {
    const wrapper = mount(TestSearchBar);
    const input = wrapper.find('input[type="text"]');

    await input.setValue('order'); // Partial, case-insensitive

    const emitted = wrapper.emitted()['update:filtered-suites'];
    expect(emitted).toBeTruthy();
    const lastEmitted = emitted[emitted.length - 1][0];
    expect(lastEmitted.length).toBe(1);
    expect(lastEmitted[0].name).toBe('App\\Tests\\Feature\\OrderTest');
  });

  it('returns all suites when search query is cleared', async () => {
    const wrapper = mount(TestSearchBar);
    const input = wrapper.find('input[type="text"]');

    await input.setValue('nonexistent');
    expect(wrapper.emitted()['update:filtered-suites'][1][0].length).toBe(0); // Filtered to empty

    await input.setValue(''); // Clear search
    const emitted = wrapper.emitted()['update:filtered-suites'];
    expect(emitted).toBeTruthy();
    const lastEmitted = emitted[emitted.length - 1][0];
    expect(lastEmitted).toEqual(mockTestSuites.value);
  });

  it('resets search and emits all suites when store.state.testSuites changes', async () => {
    const wrapper = mount(TestSearchBar);
    const input = wrapper.find('input[type="text"]');

    await input.setValue('UserTest');
    expect(wrapper.emitted()['update:filtered-suites'][1][0].length).toBe(1);

    // Simulate store.state.testSuites changing
    mockStore.state.testSuites = [{ id: 'suite4', name: 'NewSuite', methods: [] }];
    await wrapper.vm.$nextTick(); // Wait for watch to trigger

    const emitted = wrapper.emitted()['update:filtered-suites'];
    expect(emitted).toBeTruthy();
    const lastEmitted = emitted[emitted.length - 1][0];
    expect(lastEmitted).toEqual([{ id: 'suite4', name: 'NewSuite', methods: [] }]);
    expect(input.element.value).toBe(''); // Search query should be cleared
  });
});
