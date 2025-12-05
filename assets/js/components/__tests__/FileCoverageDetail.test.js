import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import FileCoverageDetail from '../FileCoverageDetail.vue';
import { useStore } from '../../store.js'; // Adjust path as necessary

// Mock the store
vi.mock('../../store.js', () => ({
  useStore: vi.fn(),
}));

describe('FileCoverageDetail', () => {
  let mockStore;

  beforeEach(() => {
    mockStore = {
      state: {
        fileCoverage: {
          path: '/path/to/src/MyFile.php',
          lines: [
            { number: 1, coverage: 'covered', tokens: [{ type: 'T_OPEN_TAG', value: '<?php' }] },
            { number: 2, coverage: 'uncovered', tokens: [{ type: 'T_NAMESPACE', value: 'namespace' }, { type: 'T_WHITESPACE', value: ' ' }, { type: 'T_STRING', value: 'App' }, { type: 'T_NS_SEPARATOR', value: '\\' }, { type: 'T_STRING', value: 'Http' }, { type: 'T_SEMICOLON', value: ';' }] },
            { number: 3, coverage: 'covered', tokens: [{ type: 'T_CLASS', value: 'class' }, { type: 'T_WHITESPACE', value: ' ' }, { type: 'T_STRING', value: 'MyClass' }, { type: 'T_WHITESPACE', value: ' ' }, { type: 'T_LBRACE', value: '{' }] },
            { number: 4, coverage: 'covered', tokens: [{ type: 'T_WHITESPACE', value: '    ' }, { type: 'T_PUBLIC', value: 'public' }, { type: 'T_WHITESPACE', value: ' ' }, { type: 'T_FUNCTION', value: 'function' }, { type: 'T_WHITESPACE', value: ' ' }, { type: 'T_STRING', value: 'myMethod' }, { type: 'T_LPAREN', value: '(' }, { type: 'T_VARIABLE', value: '$param' }, { type: 'T_RPAREN', value: ')' }, { type: 'T_WHITESPACE', value: ' ' }, { type: 'T_LBRACE', value: '{' }] },
            { number: 5, coverage: 'covered', tokens: [{ type: 'T_WHITESPACE', value: '        ' }, { type: 'T_RETURN', value: 'return' }, { type: 'T_WHITESPACE', value: ' ' }, { type: 'T_STRING', value: 'true' }, { type: 'T_SEMICOLON', value: ';' }] },
            { number: 6, coverage: 'uncovered', tokens: [{ type: 'T_WHITESPACE', value: '    ' }, { type: 'T_RBRACE', value: '}' }] },
            { number: 7, coverage: 'covered', tokens: [{ type: 'T_RBRACE', value: '}' }] },
            { number: 8, coverage: 'none', tokens: [{ type: 'T_COMMENT', value: '// A comment' }] },
            { number: 9, coverage: 'none', tokens: [{ type: 'T_ENCAPSED_AND_WHITESPACE', value: ' ' }] }, // Empty line or whitespace
            { number: 10, coverage: 'covered', tokens: [{ type: 'T_STRING', value: '"hello"' }] }, // String token
          ],
        },
      },
      setFileCoverage: vi.fn(),
    };
    useStore.mockReturnValue(mockStore);
  });

  it('renders correctly with file coverage details', () => {
    const wrapper = mount(FileCoverageDetail);

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('h3').text()).toBe('/path/to/src/MyFile.php');

    const lines = wrapper.findAll('.bg-gray-900 > div');
    expect(lines.length).toBe(10);

    // Check line 1 (covered)
    expect(lines[0].text()).toContain('1');
    expect(lines[0].classes()).toContain('line-covered');
    expect(lines[0].find('span:nth-child(2) > span').classes()).toContain('token-default'); // T_OPEN_TAG

    // Check line 2 (uncovered)
    expect(lines[1].text()).toContain('2');
    expect(lines[1].classes()).toContain('line-uncovered');
    expect(lines[1].find('span:nth-child(2) > span:nth-child(1)').classes()).toContain('token-keyword'); // T_NAMESPACE
    expect(lines[1].find('span:nth-child(2) > span:nth-child(3)').classes()).toContain('token-default'); // T_STRING

    // Check line 4 (covered with variable and keyword)
    expect(lines[3].text()).toContain('4');
    expect(lines[3].classes()).toContain('line-covered');
    expect(lines[3].find('span:nth-child(2) > span:nth-child(2)').classes()).toContain('token-keyword'); // T_PUBLIC
    expect(lines[3].find('span:nth-child(2) > span:nth-child(4)').classes()).toContain('token-keyword'); // T_FUNCTION
    expect(lines[3].find('span:nth-child(2) > span:nth-child(7)').classes()).toContain('token-variable'); // T_VARIABLE

    // Check line 8 (comment)
    expect(lines[7].text()).toContain('8');
    expect(lines[7].classes()).not.toContain('line-covered');
    expect(lines[7].classes()).not.toContain('line-uncovered');
    expect(lines[7].find('span:nth-child(2) > span').classes()).toContain('token-comment'); // T_COMMENT

    // Check line 10 (string)
    expect(lines[9].text()).toContain('10');
    expect(lines[9].classes()).toContain('line-covered');
    expect(lines[9].find('span:nth-child(2) > span').classes()).toContain('token-string'); // T_STRING (for string literal)
  });

  it('calls store.setFileCoverage(null) when "Back to Coverage Report" button is clicked', async () => {
    const wrapper = mount(FileCoverageDetail);
    const backButton = wrapper.find('button');

    await backButton.trigger('click');

    expect(mockStore.setFileCoverage).toHaveBeenCalledTimes(1);
    expect(mockStore.setFileCoverage).toHaveBeenCalledWith(null);
  });

  describe('getTokenClass', () => {
    it('returns token-default for unknown tokens or non-T_ prefixed tokens', () => {
      const wrapper = mount(FileCoverageDetail);
      const vm = wrapper.vm;
      expect(vm.getTokenClass('UNKNOWN_TOKEN')).toBe('token-default');
      expect(vm.getTokenClass('T_UNKNOWN')).toBe('token-default');
      expect(vm.getTokenClass('T_OPEN_TAG')).toBe('token-default');
    });

    it('returns token-string for string related tokens', () => {
      const wrapper = mount(FileCoverageDetail);
      const vm = wrapper.vm;
      expect(vm.getTokenClass('T_STRING')).toBe('token-default'); // T_STRING is not always a string literal
      expect(vm.getTokenClass('T_ENCAPSED_AND_WHITESPACE')).toBe('token-string');
    });

    it('returns token-comment for comment related tokens', () => {
      const wrapper = mount(FileCoverageDetail);
      const vm = wrapper.vm;
      expect(vm.getTokenClass('T_COMMENT')).toBe('token-comment');
      expect(vm.getTokenClass('T_DOC_COMMENT')).toBe('token-comment');
    });

    it('returns token-variable for variable related tokens', () => {
      const wrapper = mount(FileCoverageDetail);
      const vm = wrapper.vm;
      expect(vm.getTokenClass('T_VARIABLE')).toBe('token-variable');
    });

    it('returns token-keyword for PHP keywords', () => {
      const wrapper = mount(FileCoverageDetail);
      const vm = wrapper.vm;
      expect(vm.getTokenClass('T_CLASS')).toBe('token-keyword');
      expect(vm.getTokenClass('T_FUNCTION')).toBe('token-keyword');
      expect(vm.getTokenClass('T_PUBLIC')).toBe('token-keyword');
      expect(vm.getTokenClass('T_RETURN')).toBe('token-keyword');
      expect(vm.getTokenClass('T_NAMESPACE')).toBe('token-keyword');
      expect(vm.getTokenClass('T_IF')).toBe('token-keyword');
      expect(vm.getTokenClass('T_ELSE')).toBe('token-keyword');
    });
  });
});
