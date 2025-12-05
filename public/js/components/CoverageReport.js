import { FileCoverageDetail } from './FileCoverageDetail.js';

export const CoverageReport = {
    components: {
        FileCoverageDetail
    },
    props: [
        'store',
        'app'
    ],
    template: `
        <div v-if="store.state.isCoverageLoading" class="flex flex-col justify-center items-center pt-10">
            <div class="spinner-big"></div>
            <div class="mt-4 text-gray-400">Generating coverage report...</div>
        </div>
        <div v-else-if="!store.state.coverageReport || store.state.coverageReport.files.length === 0" class="text-gray-500 text-center pt-10">
            <div v-if="store.state.coverageDriverMissing">
                No coverage driver (Xdebug, pcov) is enabled.
            </div>
            <div v-else>
                Run tests with coverage enabled to see the report.
            </div>
        </div>
        <div v-else-if="store.state.fileCoverage">
            <file-coverage-detail :store="store"></file-coverage-detail>
        </div>
        <div v-else>
            <div class="bg-gray-800 rounded-lg shadow-lg p-4 mb-4">
                <div class="text-center">
                    <div class="text-sm text-gray-400">Total Coverage</div>
                    <div class="text-2xl font-bold">{{ store.state.coverageReport.total_coverage_percent.toFixed(2) }}%</div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="flex items-center p-3 bg-gray-700/50 text-xs text-gray-400 uppercase font-semibold">
                    <div class="flex-grow">File</div>
                    <div class="w-28 text-right flex-shrink-0 pr-3">Coverage</div>
                </div>
                <div class="divide-y divide-gray-700">
                    <div v-for="file in store.state.coverageReport.files"
                         :key="file.path"
                         class="hover:bg-gray-700/50 cursor-pointer"
                         @click="app.showFileCoverage(file.path)">
                        <div class="flex items-center p-3">
                            <div class="flex-grow text-sm text-white">{{ file.path }}</div>
                            <div class="w-28 text-right flex-shrink-0 pr-3">
                                <span class="text-sm" :class="{'text-green-400': file.coverage_percent > 80, 'text-yellow-400': file.coverage_percent > 50 && file.coverage_percent <= 80, 'text-red-400': file.coverage_percent <= 50}">{{ file.coverage_percent.toFixed(2) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `
};
