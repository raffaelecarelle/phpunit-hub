<template>
  <div class="bg-gray-800 rounded-lg shadow-lg p-4 mb-4">
    <button
      class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition duration-150 ease-in-out mb-4"
      @click="goBackToCoverageReport"
    >
      &larr; Back to Coverage Report
    </button>
    <h3 class="text-lg font-semibold text-white mb-2">
      {{ store.state.fileCoverage.path }}
    </h3>
    <pre class="bg-gray-900 p-4 rounded-lg overflow-x-auto text-sm font-mono">
            <div
v-for="line in store.state.fileCoverage.lines"
                 :class="[line.coverage === 'covered' ? 'line-covered' : '', line.coverage === 'uncovered' ? 'line-uncovered' : '']"
                 style="display: flex; align-items: center; line-height: 1.4;max-height: 20px"
>
                <span
class="text-gray-500 flex-shrink-0 text-right pr-3"
style="user-select: none;"
>{{ line.number }}</span>
                <span style="flex: 1; white-space: pre;">
                    <span
v-for="item in line.tokens"
:key="item.value"
:class="getTokenClass(item.type)"
>{{ item.value }}</span>
                </span>
            </div>
        </pre>
  </div>
</template>

<script setup>
import { useStore } from '../store.js';

const store = useStore();

function getTokenClass(tokenType) {
    const TOKEN_TYPE_PREFIX = 'token-';

    if (!tokenType.startsWith('T_')) {
        return TOKEN_TYPE_PREFIX + 'default';
    }

    const t = tokenType.substring(2).toLowerCase();

    // T_STRING is a generic token, not always a string literal.
    // It should be classified as 'token-default' unless it's specifically
    // an encapsed string or constant encapsed string.
    if (['encapsed_and_whitespace', 'constant_encapsed_string'].includes(t)) {
        return TOKEN_TYPE_PREFIX + 'string';
    }
    if (['comment', 'doc_comment'].includes(t)) {
        return TOKEN_TYPE_PREFIX + 'comment';
    }
    if (t.includes('variable')) {
        return TOKEN_TYPE_PREFIX + 'variable';
    }

    const keywords = ['class', 'function', 'public', 'private', 'protected', 'readonly', 'new', 'echo', 'return', 'if', 'else', 'elseif', 'while', 'do', 'for', 'foreach', 'switch', 'case', 'break', 'continue', 'declare', 'const', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'use', 'namespace', 'try', 'catch', 'finally', 'throw', 'extends', 'implements', 'interface', 'trait', 'abstract', 'final', 'static', 'instanceof', 'insteadof', 'global', 'goto', 'include', 'include_once', 'require', 'require_once', 'unset', 'isset', 'empty'];
    if (keywords.includes(t)) {
        return TOKEN_TYPE_PREFIX + 'keyword';
    }

    return TOKEN_TYPE_PREFIX + 'default';
}

function goBackToCoverageReport() {
    store.setFileCoverage(null);
}
</script>
