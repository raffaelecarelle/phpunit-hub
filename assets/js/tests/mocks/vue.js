// public/js/tests/mocks/vue.js
export const reactive = (obj) => obj;
export const computed = (fn) => ({ value: fn() }); // Semplice mock per computed
