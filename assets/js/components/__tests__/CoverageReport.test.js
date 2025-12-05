import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import CoverageReport from '../CoverageReport.vue';
import { useStore } from '../../store.js'; // Corrected path

// Mock child components
vi.mock('../FileCoverageDetail.vue', () => ({
  default: {
    name: 'FileCoverageDetail',
    template: '<div class="mock-file-coverage-detail"></div>',
  },
}));

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('CoverageReport', () => {
  let mockStore;

  beforeEach(() => {
    mockStore = {
      state: {
        isCoverageLoading: false,
        coverageReport: null,
        coverageDriverMissing: false,
        fileCoverage: null,
      },
    };
    useStore.mockReturnValue(mockStore);
  });

  it('shows loading spinner when isCoverageLoading is true', () => {
    mockStore.state.isCoverageLoading = true;
    const wrapper = mount(CoverageReport);

    expect(wrapper.find('.spinner-big').exists()).toBe(true);
    expect(wrapper.text()).toContain('Generating coverage report...');
    expect(wrapper.find('.mock-file-coverage-detail').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('No coverage driver');
    expect(wrapper.text()).not.toContain('Run tests with coverage enabled');
  });

  it('shows "No coverage driver" message when driver is missing', () => {
    mockStore.state.isCoverageLoading = false;
    mockStore.state.coverageReport = null;
    mockStore.state.coverageDriverMissing = true;
    const wrapper = mount(CoverageReport);

    expect(wrapper.text()).toContain('No coverage driver (Xdebug, pcov) is enabled.');
    expect(wrapper.find('.spinner-big').exists()).toBe(false);
    expect(wrapper.find('.mock-file-coverage-detail').exists()).toBe(false);
  });

  it('shows "Run tests with coverage" message when no report and driver not missing', () => {
    mockStore.state.isCoverageLoading = false;
    mockStore.state.coverageReport = null;
    mockStore.state.coverageDriverMissing = false;
    const wrapper = mount(CoverageReport);

    expect(wrapper.text()).toContain('Run tests with coverage enabled to see the report.');
    expect(wrapper.find('.spinner-big').exists()).toBe(false);
    expect(wrapper.find('.mock-file-coverage-detail').exists()).toBe(false);
  });

  it('shows "Run tests with coverage" message when report is empty', () => {
    mockStore.state.isCoverageLoading = false;
    mockStore.state.coverageReport = { files: [] };
    mockStore.state.coverageDriverMissing = false;
    const wrapper = mount(CoverageReport);

    expect(wrapper.text()).toContain('Run tests with coverage enabled to see the report.');
    expect(wrapper.find('.spinner-big').exists()).toBe(false);
    expect(wrapper.find('.mock-file-coverage-detail').exists()).toBe(false);
  });

  it('renders FileCoverageDetail when fileCoverage is active', () => {
    mockStore.state.isCoverageLoading = false;
    // Ensure coverageReport is not empty so the v-else-if condition for empty report is false
    mockStore.state.coverageReport = { files: [{ path: 'src/FileA.php', coverage_percent: 90.0 }] };
    mockStore.state.fileCoverage = { path: '/path/to/file.php' };
    const wrapper = mount(CoverageReport);

    // The v-if="store.state.fileCoverage" condition means the mock-file-coverage-detail should exist
    expect(wrapper.find('.mock-file-coverage-detail').exists()).toBe(true);
    expect(wrapper.find('.spinner-big').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('No coverage driver');
    expect(wrapper.text()).not.toContain('Run tests with coverage enabled');
  });

  it('displays coverage report summary and file list', () => {
    mockStore.state.isCoverageLoading = false;
    mockStore.state.coverageReport = {
      total_coverage_percent: 75.5,
      files: [
        { path: 'src/FileA.php', coverage_percent: 90.0 },
        { path: 'src/FileB.php', coverage_percent: 60.0 },
        { path: 'src/FileC.php', coverage_percent: 40.0 },
      ],
    };
    const wrapper = mount(CoverageReport);

    expect(wrapper.find('.spinner-big').exists()).toBe(false);
    expect(wrapper.find('.mock-file-coverage-detail').exists()).toBe(false);
    expect(wrapper.text()).toContain('Total Coverage');
    expect(wrapper.text()).toContain('75.50%');
    expect(wrapper.text()).toContain('File');
    expect(wrapper.text()).toContain('Coverage');

    const files = wrapper.findAll('.divide-y > div');
    expect(files.length).toBe(3);

    expect(files[0].text()).toContain('src/FileA.php');
    expect(files[0].text()).toContain('90.00%');
    expect(files[0].find('span').classes()).toContain('text-green-400');

    expect(files[1].text()).toContain('src/FileB.php');
    expect(files[1].text()).toContain('60.00%');
    expect(files[1].find('span').classes()).toContain('text-yellow-400');

    expect(files[2].text()).toContain('src/FileC.php');
    expect(files[2].text()).toContain('40.00%');
    expect(files[2].find('span').classes()).toContain('text-red-400');
  });

  it('emits showFileCoverage when a file is clicked', async () => {
    mockStore.state.isCoverageLoading = false;
    mockStore.state.coverageReport = {
      total_coverage_percent: 75.5,
      files: [
        { path: 'src/FileA.php', coverage_percent: 90.0 },
      ],
    };
    const wrapper = mount(CoverageReport);

    const fileEntry = wrapper.find('.divide-y > div');
    await fileEntry.trigger('click');

    expect(wrapper.emitted().showFileCoverage).toBeTruthy();
    expect(wrapper.emitted().showFileCoverage[0][0]).toBe('src/FileA.php');
  });
});
