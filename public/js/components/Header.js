
import { FilterPanel } from './header/FilterPanel.js';
import { ClearResultsButton } from './header/ClearResultsButton.js';
import { RunFailedButton } from './header/RunFailedButton.js';
import { RunStopAllButton } from './header/RunStopAllButton.js';
import { HeaderTitle } from './header/HeaderTitle.js';

export const Header = {
    components: {
        'header-title': HeaderTitle,
        'filter-panel': FilterPanel,
        'clear-results-button': ClearResultsButton,
        'run-failed-button': RunFailedButton,
        'run-stop-all-button': RunStopAllButton
    },
    props: ['store', 'app', 'isAnyTestRunning', 'hasFailedTests', 'isAnyStopPending', 'results'],
    template: `
    <header class="bg-gray-800 shadow-md p-4 flex justify-between items-center z-10 relative">
        <header-title></header-title>
        <div class="flex items-center space-x-4">
            <filter-panel :store="store" :app="app"></filter-panel>
            <clear-results-button :app="app" :is-any-test-running="isAnyTestRunning" :results="results"></clear-results-button>
            <run-failed-button :app="app" :is-any-test-running="isAnyTestRunning" :has-failed-tests="hasFailedTests"></run-failed-button>
            <run-stop-all-button :app="app" :is-any-test-running="isAnyTestRunning" :is-any-stop-pending="isAnyStopPending"></run-stop-all-button>
        </div>
    </header>
    `
};
