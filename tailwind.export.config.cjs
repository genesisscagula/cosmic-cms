const forms = require('@tailwindcss/forms');

/**
 * Dedicated Tailwind build for Cosmic static previews/exports.
 * Keep this intentionally broad: Sparks and generated page markup live in
 * Laravel services, Blade files and React/JSX source, not only app.css.
 */
module.exports = {
  content: [
    './app/**/*.php',
    './resources/**/*.blade.php',
    './resources/**/*.{js,jsx,ts,tsx,vue}',
    './routes/**/*.php',
    './config/**/*.php',
    './database/**/*.{php,json}',
  ],
  safelist: [
    { pattern: /^(block|inline-block|inline|flex|inline-flex|grid|hidden)$/ },
    { pattern: /^(sm|md|lg|xl|2xl):(block|flex|grid|hidden)$/ },
    { pattern: /^(grid-cols|col-span|row-span)-(1|2|3|4|5|6|7|8|9|10|11|12)$/ },
    { pattern: /^(sm|md|lg|xl|2xl):(grid-cols|col-span)-(1|2|3|4|5|6|7|8|9|10|11|12)$/ },
    { pattern: /^(text|bg|border|ring)-(white|black|transparent|current)$/ },
    { pattern: /^(text|bg|border|ring)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(50|100|200|300|400|500|600|700|800|900|950)$/ },
  ],
  theme: { extend: {} },
  plugins: [forms],
};
