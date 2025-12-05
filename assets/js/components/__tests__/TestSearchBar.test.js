import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ref, reactive } from 'vue'; // Import reactive
import TestSearchBar from '../sidebar/TestSearchBar.vue';
import { useStore } from '../../store.js'; // Corrected path

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('TestSearchBar', () => {
  let mockStore;
  let mockTestSuitesRef; // Declare a ref to hold the test suites

  beforeEach(() => {
    mockTestSuitesRef = ref([
      { id: 'suite1', name: 'App\\Tests\\Feature\\UserTest', methods: [{ id: 'm1', name: 'testCreateUser' }, { id: 'm2', name: 'testDeleteUser' }] },
      { id: 'suite2', name: 'App\\Tests\\Unit\\ProductTest', methods: [{ id: 'm3', name: 'testAddProduct' }, { id: 'm4', name: 'testRemoveProduct' }] },
      { id: 'suite3', name: 'App\\Tests\\Feature\\OrderTest', methods: [{ id: 'm5', name: 'testPlaceOrder' }] },
    ]);

    // Make mockStore.state reactive
    mockStore = {
      state: reactive({ // Use reactive here
        testSuites: mockTestSuitesRef.value,
      }),
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly with an input field', () => {
    const wrapper = mount(TestSearchBar);
    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('input[type="text"]').exists()).toBe(true);
    expect(wrapper.find('input[type="text"]').attributes('placeholder')).toBe('Search tests...');
  });

  it('emits all test suites initially when searchQuery is empty', async () => {
    const wrapper = mount(TestSearchBar);
    // Wait for the watch effect to run after initial mount
    await wrapper.vm.$nextTick();
    expect(wrapper.emitted()['update:filtered-suites']).toBeTruthy();
    expect(wrapper.emitted()['update:filtered-suites'][0][0]).toEqual(mockTestSuitesRef.value);
  });

  it('filters suites by suite name', async () => {
    const wrapper = mount(TestSearchBar);
    const input = wrapper.find('input[type="text"]');

    await input.setValue('UserTest');
    await wrapper.vm.$nextTick(); // Ensure reactivity updates are processed

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
    await wrapper.vm.$nextTick(); // Ensure reactivity updates are processed

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
    await wrapper.vm.$nextTick(); // Ensure reactivity updates are processed

    const emitted = wrapper.emitted()['update:filtered-suites'];
    expect(emitted).toBeTruthy();
    const lastEmitted = emitted[emitted.length - 1][0];
    expect(lastEmitted.length).toBe(1);
    expect(lastEmitted[0].name).toBe('App\\Tests\\Feature\\OrderTest');
  });

  it('returns all suites when search query is cleared', async () => {
    const wrapper = mount(TestSearchBar);
    const input = wrapper.find('input[type="text"]');

    // Explicitly trigger input to ensure watcher runs, even if value is already empty
    await input.trigger('input');
    await wrapper.vm.$nextTick(); // Ensure reactivity updates are processed

    let emittedEvents = wrapper.emitted()['update:filtered-suites'];
    expect(emittedEvents).toBeTruthy();
    expect(emittedEvents.length).toBe(1);
    expect(emittedEvents[0][0]).toEqual(mockTestSuitesRef.value);

    // Set search query to 'nonexistent'
    await input.setValue('nonexistent');
    let attempts = 0;
    let foundEmptyFilter = false;
    let lastEmittedPayload = null;

    while (!foundEmptyFilter && attempts < 20) { // Increased attempts for robustness
      await wrapper.vm.$nextTick();
      emittedEvents = wrapper.emitted()['update:filtered-suites'];
      if (emittedEvents && emittedEvents.length > 0) {
        const lastEmitted = emittedEvents[emittedEvents.length - 1];
        if (lastEmitted && lastEmitted.length === 1 && Array.isArray(lastEmitted[0])) {
          lastEmittedPayload = lastEmitted[0];
          if (lastEmittedPayload.length === 0) {
            foundEmptyFilter = true;
          }
        }
      }
      attempts++;
    }
    expect(foundEmptyFilter).toBe(true); // Ensure we found the empty filter emission
    expect(lastEmittedPayload).toEqual([]); // Final check of the content

    // Clear search query
    await input.setValue('');
    attempts = 0;
    let foundAllSuites = false;
    lastEmittedPayload = null;

    while (!foundAllSuites && attempts < 20) {
      await wrapper.vm.$nextTick();
      emittedEvents = wrapper.emitted()['update:filtered-suites'];
      if (emittedEvents && emittedEvents.length > 0) {
        const lastEmitted = emittedEvents[emittedEvents.length - 1];
        if (lastEmitted && lastEmitted.length === 1 && Array.isArray(lastEmitted[0])) {
          lastEmittedPayload = lastEmitted[0];
          if (lastEmittedPayload.length === mockTestSuitesRef.value.length) {
            foundAllSuites = true;
          }
        }
      }
      attempts++;
    }
    expect(foundAllSuites).toBe(true); // Ensure we found the all suites emission
    expect(lastEmittedPayload).toEqual(mockTestSuitesRef.value);
  });

  it('resets search and emits all suites when store.state.testSuites changes', async () => {
    const wrapper = mount(TestSearchBar);
    const input = wrapper.find('input[type="text"]');

    await input.setValue('UserTest');
    await wrapper.vm.$nextTick(); // Ensure all reactivity updates are processed
    const emittedAfterSearch = wrapper.emitted()['update:filtered-suites'];
    expect(emittedAfterSearch).toBeTruthy();
    // Check the last emitted event after the search
    expect(emittedAfterSearch[emittedAfterSearch.length - 1][0].length).toBe(1);

    // Simulate store.state.testSuites changing
    mockTestSuitesRef.value = [{ id: 'suite4', name: 'NewSuite', methods: [] }]; // Update the ref's value
    mockStore.state.testSuites = mockTestSuitesRef.value; // This will now correctly trigger the watch

    await wrapper.vm.$nextTick(); // Wait for watch to trigger

    const emitted = wrapper.emitted()['update:filtered-suites'];
    expect(emitted).toBeTruthy();
    const lastEmitted = emitted[emitted.length - 1][0];
    expect(lastEmitted).toEqual([{ id: 'suite4', name: 'NewSuite', methods: [] }]);
    expect(input.element.value).toBe(''); // Search query should be cleared
  });
});
