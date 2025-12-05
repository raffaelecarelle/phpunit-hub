export const TestSearchBar = {
    props: ['store'],
    template: `
        <input type="text"
               v-model="store.state.searchQuery"
               placeholder="Search tests..."
               class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 mb-4 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
    `
};
