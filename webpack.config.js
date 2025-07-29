const path = require('path');

module.exports = {
    entry: './assets/js/cdek-delivery.js',
    output: {
        path: path.resolve(__dirname, 'assets/dist'),
        filename: 'cdek-delivery.min.js',
        clean: true,
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env']
                    }
                }
            }
        ]
    },
    resolve: {
        extensions: ['.js']
    },
    externals: {
        jquery: 'jQuery',
        ymaps: 'ymaps'
    },
    optimization: {
        minimize: true
    }
};