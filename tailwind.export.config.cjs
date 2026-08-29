/**
 * Cosmic static/export Tailwind contract.
 *
 * Runtime Luna classes are covered by the Builder/Preview/Live browser runtime.
 * This bundle is the deterministic fast baseline shared by Preview and connector
 * exports, and scans every renderer/compiler/theme source plus the explicit
 * dynamic theme safelist view.
 */
module.exports = {
  content: [
    './resources/js/**/*.{js,jsx,ts,tsx}',
    './resources/views/**/*.blade.php',
    './app/Helpers/CmsHtmlCompiler.php',
    './app/Services/**/*.{php}',
    './config/**/*.php',
  ],
  theme: { extend: {} },
  plugins: [],
};
