/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./public/**/*.html",
    "./public/**/*.js",
  ],
  safelist: [
    {
      pattern: /^status-/,
    },
    {
      pattern: /^bg-(red|green|amber|purple|yellow|gray)-(500|600|700|800|900)/,
    },
    {
      pattern: /^text-(red|green|amber|purple|yellow|gray)-(200|300|400)/,
    },
    'rotated',
    'rotate-90'
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
