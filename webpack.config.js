const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
    mode: 'production',
    entry: {
        'sparxstar-3iatlas-dictionary-app': './src/js/app.jsx',
        'sparxstar-3iatlas-dictionary-admin': './src/js/admin.js',
        'sparxstar-3iatlas-dictionary-form': './src/js/sparxstar-iatlas-dictionary-form.js',
        'sparxstar-3iatlas-dictionary-admin-style': './src/css/admin.css',
        'sparxstar-3iatlas-dictionary-form-style': './src/css/sparxstar-3iatlas-dictionary-form.css',
    },
    output: {
        path: path.resolve(__dirname, 'assets'),
        filename: 'js/[name].min.js',
        assetModuleFilename: 'images/[hash][ext][query]',
        clean: false,
    },
    resolve: {
        // FIX 1: Added '.mjs' to extensions list
        extensions: ['.js', '.jsx', '.mjs', '.json', '.wasm'],
    },
    module: {
        rules: [
            // FIX 2: Handle .mjs files explicitly for Apollo Client
            {
                test: /\.mjs$/,
                include: /node_modules/,
                type: 'javascript/auto',
                resolve: {
                    fullySpecified: false // Disable strict ESM imports
                }
            },
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env', '@babel/preset-react'],
                    },
                },
            },
            {
                test: /\.css$/i,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    'postcss-loader',
                ],
            },
            {
                test: /\.(png|svg|jpg|jpeg|gif)$/i,
                type: 'asset/resource',
            },
        ],
    },
    optimization: {
        minimize: true,
        minimizer: [
            new TerserPlugin({
                extractComments: false,
            }),
            new CssMinimizerPlugin(),
        ],
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: 'css/[name].min.css',
        }),
    ],
};
