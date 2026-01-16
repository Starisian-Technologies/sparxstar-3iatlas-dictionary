const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
    mode: 'production',
    entry: {
        // Main App
        'sparxstar-3iatlas-dictionary-app': './src/js/app.jsx',
        // Independent Assets
        'sparxstar-3iatlas-dictionary-admin': './src/js/admin.js',
        'sparxstar-3iatlas-dictionary-form': './src/js/sparxstar-iatlas-dictionary-form.js',
        'sparxstar-3iatlas-dictionary-admin-style': './src/css/admin.css', // Will be extracted
        'sparxstar-3iatlas-dictionary-form-style': './src/css/sparxstar-3iatlas-dictionary-form.css', // Will be extracted
    },
    output: {
        path: path.resolve(__dirname, 'assets'),
        filename: 'js/[name].min.js',
        assetModuleFilename: 'images/[hash][ext][query]',
        clean: false, // Don't clean whole assets folder, just overwrite
    },
    resolve: {
        extensions: ['.js', '.jsx'],
    },
    module: {
        rules: [
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
                    'postcss-loader', // checks for postcss.config.js
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
