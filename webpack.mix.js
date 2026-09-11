let mix = require('laravel-mix');
let webpack = require('webpack');
let path = require('path');

mix
    .setPublicPath('dist')
    .js('resources/js/entry.js', 'js')
    .vue({ version: 3 })
    .webpackConfig({
        plugins: [
            // Stamps each build so a stale tab can be spotted from the console.
            new webpack.DefinePlugin({
                __NMW_BUILD__: JSON.stringify(new Date().toISOString().slice(0, 16).replace('T', ' ')),
            }),
        ],
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
