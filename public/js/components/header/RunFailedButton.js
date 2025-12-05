
export const RunFailedButton = {
    props: ['app', 'isAnyTestRunning', 'hasFailedTests'],
    template: `
        <button @click="app.runFailedTests()"
                :disabled="isAnyTestRunning || !hasFailedTests"
                class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out disabled:bg-gray-500">
            Run Failed
        </button>
    `
};
