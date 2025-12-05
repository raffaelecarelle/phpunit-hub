export const TabNavigation = {
    props: ['store'],
    template: `
        <div class="mb-4">
            <nav class="flex space-x-2">
                <a href="#"
                   @click.prevent="store.setActiveTab('results')"
                   :class="['px-4 py-2 rounded-md text-sm font-medium', store.state.activeTab === 'results' ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']">
                    Results
                </a>
                <a href="#"
                   @click.prevent="store.setActiveTab('coverage')"
                   :class="['px-4 py-2 rounded-md text-sm font-medium', store.state.activeTab === 'coverage' ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']">
                    Coverage
                </a>
            </nav>
        </div>
    `
};
