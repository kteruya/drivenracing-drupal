const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

module.exports = {
  entry: [
    './js/index.js',
  ],
  mode: 'development',
  optimization: {
    minimize: true,
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          format: {
            comments: false,
          },
        },
        test: /\.js(\?.*)?$/i,
        extractComments: false,
      }),
    ],
    moduleIds: 'named',
  },
  output: {
    path: path.resolve(__dirname, './assets'),
    filename: 'swiper.bundle.min.js',
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: 'swiper.bundle.min.css',
    }),
  ],
  module: {
    rules: [
      { test: /\.svg$/, use: 'raw-loader' },
      {
        test: /\.css$/i,
        use: ["style-loader", "css-loader"],
      },
      {
        test: /\.css$/i,
        use: [
          MiniCssExtractPlugin.loader,
          "css-loader",
          "postcss-loader",
        ],
      },
    ]
  },
};
