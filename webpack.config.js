const path = require('path');
// CHANGE 1: Import Webpack itself
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
    mode: 'production',
    devtool: 'source-map',
    entry: {
        'sparxstar-3iatlas-dictionary-app': './src/js/app.jsx',
        // OPTIONAL: Only keep these if you actually use them features
        // 'sparxstar-3iatlas-dictionary-admin': './src/js/admin.js',
        'sparxstar-3iatlas-dictionary-form': './src/js/form-entry.jsx',
        'sparxstar-3iatlas-dictionary-style': './src/js/style-entry.jsx',
    },
    output: {
        path: path.resolve(__dirname, 'assets'),
        filename: 'js/[name].min.js',
        assetModuleFilename: 'images/[hash][ext][query]',
        clean: true,
    },
    resolve: {
        // Ensure modern builds are picked first
        mainFields: ['browser', 'module', 'main'],
        extensions: ['.mjs', '.js', '.jsx', '.json', '.wasm'],
    },
    module: {
        rules: [
            {
                test: /\.mjs$/,
                include: /node_modules/,
                type: 'javascript/auto',
                resolve: {
                    fullySpecified: false,
                },
            },
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: [
                            ['@babel/preset-env', { modules: false }],
                            '@babel/preset-react',
                        ],
                        sourceType: 'unambiguous',
                        cacheDirectory: false,
                    },
                },
            },
            {
                test: /\.css$/i,
                use: [MiniCssExtractPlugin.loader, 'css-loader', 'postcss-loader'],
            },
            {
                test: /\.(png|svg|jpg|jpeg|gif)$/i,
                type: 'asset/resource',
            },
        ],
    },
    optimization: {
        minimize: true,
        minimizer: [new TerserPlugin({ extractComments: false }), new CssMinimizerPlugin()],
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: 'css/[name].min.css',
        }),
        // CHANGE 2: Define 'process.env.NODE_ENV' for the browser
        new webpack.DefinePlugin({
            'process.env.NODE_ENV': JSON.stringify('production'),
        }),
    ],
};