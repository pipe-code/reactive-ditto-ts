const path = require('path');
const webpack = require("webpack");
require('dotenv').config();

module.exports = {
    entry: ['./src/app.tsx'],
    mode: 'development',
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: 'app.bundle.js',
        // Content-hash the lazy-loaded component chunks so the browser (and any
        // CDN) never serves a stale chunk after a rebuild — a stale chunk looks
        // exactly like "my change didn't work". The main bundle is cache-busted
        // separately via filemtime in ditto_scripts() (inc/wordpress_settings.php).
        chunkFilename: '[name].[contenthash:8].app.bundle.js',
        // Wipe dist/ on every build so old hashed chunks don't accumulate.
        clean: true,
        publicPath: 'auto'
    },
    resolve: {
        extensions: ['.tsx', '.ts', '.js', '.jsx'],
        alias: {
            '@assets': path.resolve(__dirname, 'src/assets/'),
            '@components': path.resolve(__dirname, 'src/components/'),
            '@containers': path.resolve(__dirname, 'src/containers/'),
            '@fonts': path.resolve(__dirname, 'src/fonts/'),
            '@layout': path.resolve(__dirname, 'src/Layout/'),
            '@ui': path.resolve(__dirname, 'src/UI/'),
            '@hooks': path.resolve(__dirname, 'src/hooks/'),
            '@utils': path.resolve(__dirname, 'src/utils/'),
            '@interface': path.resolve(__dirname, 'src/interface/'),
            '@styles': path.resolve(__dirname, 'src/styles/'),
        }
    },
    plugins: [
        // Expose only the specific env vars the bundle needs.
        // Never use JSON.stringify(process.env) — that leaks every env variable
        // (including secrets) into the compiled bundle.
        new webpack.DefinePlugin({
            "process.env.NODE_ENV": JSON.stringify(process.env.NODE_ENV || 'development'),
        })
    ],
    ignoreWarnings: [
        (warning) => warning.message.includes('Sass @import rules are deprecated'),
        (warning) => warning.message.includes('Deprecation The legacy JS API is deprecated')
    ],
    module: {
        rules: [
            {
                test: /\.(ts|tsx)$/,
                exclude: /node_modules/,
                use: 'ts-loader'
            },
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: 'babel-loader'
            },
            {
                test: /\.module\.s(a|c)ss$/,
                exclude: /node_modules/,
                use: [
                    "style-loader", 
                    {
                        loader: 'css-loader',
                        options: {
                            modules: {
                                // No hash — deterministic names so PHP SSR templates can hardcode them
                                localIdentName: "[name]__[local]",
                            }
                        }
                    }, 
                    "sass-loader"
                ]
            },
            {
                test: /\.(css|scss|sass)$/,
                exclude: /\.module.(s(a|c)ss)$/,
                use: ["style-loader", 'css-loader', 'sass-loader']
            },
            {
                test: /\.(jpe?g|png|gif|woff|woff2|eot|ttf|svg)$/i,
                type: "asset"
            }
        ]
    }
}