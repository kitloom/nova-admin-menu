let mix = require('laravel-mix');
let path = require('path');

mix
    .setPublicPath('dist')
    .js('resources/js/entry.js', 'js')
    .vue({ version: 3 })
    .webpackConfig({
        externals: {
            vue: 'Vue',
        },
        output: {
            uniqueName: 'kitloom/nova-menu-weights',
        },
        resolve: {
            modules: [
                path.join(__dirname, 'node_modules'),
                'node_modules',
            ],
        },
    })
    .alias({
        'laravel-nova': path.join(
            __dirname,
            '../../vendor/laravel/nova/resources/js/mixins/packages.js'
        ),
    });
