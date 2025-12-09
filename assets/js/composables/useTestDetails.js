import { useStore } from '../store.js';

export function useTestDetails() {
    const store = useStore();

    function toggleTestDetails(testcase) {
        if (store.state.expandedTestId === testcase.id) {
            store.setExpandedTest(null);
        } else {
            store.setExpandedTest(testcase.id);
        }
    }

    return {
        toggleTestDetails,
    };
}
