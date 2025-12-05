
import { FilterPanelButton } from './filter/FilterPanelButton.js';
import { FilterPanelContent } from './filter/FilterPanelContent.js';

export const FilterPanel = {
    components: {
        'filter-panel-button': FilterPanelButton,
        'filter-panel-content': FilterPanelContent
    },
    props: ['store', 'app'],
    template: `
        <div class="relative">
            <filter-panel-button @toggle="toggleFilterPanel"></filter-panel-button>
            <filter-panel-content v-show="store.state.showFilterPanel" :store="store" :app="app"></filter-panel-content>
        </div>
    `,
    methods: {
        toggleFilterPanel() {
            this.store.toggleFilterPanel();
        }
    }
};
