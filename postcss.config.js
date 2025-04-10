module.exports = {
    plugins: [
        require('autoprefixer')({
            overrideBrowserslist: [
                '>0.2%',
                'not dead',
                'not op_mini all'
            ]
        }),
        require('cssnano')({
            preset: ['default', {
                discardComments: {
                    removeAll: true,
                    removeAllButFirst: true
                },
                normalizeWhitespace: true
            }]
        })
    ]
}; 