/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./modules/**/*.php",
    "./templates/**/*.php",
    "./public/**/*.{js,php}",
    "./api/**/*.php"
  ],
  theme: {
    extend: {
      colors: {
        // Match existing CSS variables
        'primary': 'var(--primary)',
        'primary-dark': 'var(--primary-dark)',
        'accent-blue': 'var(--accent-blue)',
        'accent-green': 'var(--accent-green)',
        'accent-red': 'var(--accent-red)',
        'accent-amber': 'var(--accent-amber)',
        'accent-violet': 'var(--accent-violet)',
      }
    }
  },
  plugins: []
}
