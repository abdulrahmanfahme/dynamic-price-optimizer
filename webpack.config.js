const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
    entry: {
        admin: './src/js/admin.js',
        adminStyles: './src/scss/admin.scss'
    },
    output: {
        path: path.resolve(__dirname, 'assets'),
        filename: 'js/[name].js',
        clean: true
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
            },
            {
                test: /\.scss$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    'postcss-loader',
                    'sass-loader'
                ]
            },
            {
                test: /\.(png|svg|jpg|jpeg|gif)$/i,
                type: 'asset/resource'
            },
            {
                test: /\.(woff|woff2|eot|ttf|otf)$/i,
                type: 'asset/resource'
            }
        ]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: 'css/[name].css'
        })
    ],
    externals: {
        jquery: 'jQuery'
    },
    resolve: {
        extensions: ['.js', '.scss']
    },
    optimization: {
        splitChunks: {
            chunks: 'all',
            name: 'vendor'
        }
    }
}; 