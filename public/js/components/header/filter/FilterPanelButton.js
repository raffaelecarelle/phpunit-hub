
export const FilterPanelButton = {
    template: `
        <button
            @click.stop="$emit('toggle')"
            class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out">
            Filters / Settings
        </button>
    `
};
