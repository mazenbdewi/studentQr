export default {
    content: [
        './app/Filament/**/*.php',
        './app/Livewire/**/*.php',
        './app/View/**/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './vendor/filament/**/*.blade.php',
        './vendor/filament/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Tajawal', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
